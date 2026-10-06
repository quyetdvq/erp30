<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Resolution;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Time\Clock;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Pricing\PriceList\PriceListRepository;
use App\Modules\Product\Product\ProductRoleService;
use App\Modules\Product\Uom\ProductUomService;
use App\Services\OrganizationService;

/** Canonical read-only Product/UOM/Company selling-price resolution. */
final class PricingService
{
    public const ERROR_NO_VALID_PRICE = 'PRICING_NO_VALID_PRICE';

    private const SOURCE_COMPANY = 'PRICE_LIST_COMPANY';
    private const SOURCE_GROUP = 'PRICE_LIST_GROUP';
    private const SOURCE_DEFAULT = 'PRODUCT_DEFAULT';

    public function __construct(
        private readonly ?PriceListRepository $priceLists = null,
        private readonly ?ProductRoleService $roles = null,
        private readonly ?ProductUomService $productUoms = null,
        private readonly ?OrganizationService $organizations = null,
        private readonly ?Clock $clock = null,
    ) {
    }

    /**
     * Resolve one selling price without authorization, mutation, or caching.
     *
     * @return array{product_id:int,uom_id:int,company_organization_id:int,unit_price:string,price_source:string,price_list_id:int|null}
     */
    public function resolve(
        mixed $productId,
        mixed $uomId,
        mixed $companyOrganizationId,
        ?EvaluationTime $evaluationTime = null,
    ): array {
        $productId = PositiveId::normalize($productId, 'product_id', 'INVALID_REFERENCE');
        $uomId = PositiveId::normalize($uomId, 'uom_id', 'INVALID_REFERENCE');
        $companyOrganizationId = PositiveId::normalize(
            $companyOrganizationId,
            'company_organization_id',
            'INVALID_REFERENCE',
        );
        $evaluationTime ??= EvaluationTime::capture($this->clock ?? service('clock'));

        $groupId = $this->validateCompanyAndResolveGroup($companyOrganizationId);
        $product = $this->validateProduct($productId);
        $sellingUom = $this->validateSellingUom($product, $uomId);
        $evaluationInstant = $evaluationTime->utc()->format('Y-m-d H:i:s');

        $companyCandidate = $this->priceLists()->findBestMatchingPrice(
            $companyOrganizationId,
            $productId,
            $uomId,
            $evaluationInstant,
        );
        if ($companyCandidate !== null) {
            return $this->result(
                $productId,
                $uomId,
                $companyOrganizationId,
                $companyCandidate['price'] ?? null,
                self::SOURCE_COMPANY,
                $companyCandidate['price_list_id'] ?? null,
            );
        }

        $groupCandidate = $this->priceLists()->findBestMatchingPrice(
            $groupId,
            $productId,
            $uomId,
            $evaluationInstant,
        );
        if ($groupCandidate !== null) {
            return $this->result(
                $productId,
                $uomId,
                $companyOrganizationId,
                $groupCandidate['price'] ?? null,
                self::SOURCE_GROUP,
                $groupCandidate['price_list_id'] ?? null,
            );
        }

        $defaultPrice = $sellingUom['is_base']
            ? ($product['price'] ?? null)
            : (($sellingUom['conversion']['price'] ?? null));

        return $this->result(
            $productId,
            $uomId,
            $companyOrganizationId,
            $defaultPrice,
            self::SOURCE_DEFAULT,
            null,
        );
    }

    /** @return array<string, mixed> */
    private function validateProduct(int $productId): array
    {
        $product = $this->roles()->findById($productId);
        if ($product === null) {
            throw new DomainException(ProductRoleService::ERROR_NOT_FOUND, ['product_id' => $productId]);
        }
        if (strtoupper((string) ($product['status'] ?? '')) !== 'ACTIVE') {
            throw new DomainException('INACTIVE_REFERENCE', ['product_id' => $productId]);
        }
        if (! $this->roles()->isSelectable($product)) {
            throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, ['product_id' => $productId]);
        }

        $role = $this->roles()->getRole($product);
        if (! in_array($role, [
            ProductRoleService::ROLE_SIMPLE,
            ProductRoleService::ROLE_CHILD,
            ProductRoleService::ROLE_COMBO,
        ], true)) {
            throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, [
                'product_id' => $productId,
                'role' => $role,
            ]);
        }

        return $product;
    }

    /** @param array<string, mixed> $product
     *  @return array{uom:array<string,mixed>,conversion:array<string,mixed>|null,is_base:bool}
     */
    private function validateSellingUom(array $product, int $uomId): array
    {
        $role = $this->roles()->getRole($product);
        if ($role === ProductRoleService::ROLE_COMBO) {
            $base = $this->productUoms()->requireActiveBaseUom($product['base_uom_id'] ?? null);
            if ((int) $base['id'] !== $uomId) {
                throw new DomainException(ProductUomService::ERROR_INVALID_CONVERSION, [
                    'reason' => 'combo_requires_base_uom',
                    'uom_id' => $uomId,
                ]);
            }

            return ['uom' => $base, 'conversion' => null, 'is_base' => true];
        }

        return $this->productUoms()->validateSellingUom($product, $uomId);
    }

    private function validateCompanyAndResolveGroup(int $companyId): int
    {
        try {
            $path = $this->organizations()->resolveAncestorChain($companyId);
        } catch (DomainException $exception) {
            throw new DomainException('INVALID_REFERENCE', ['organization_id' => $companyId], $exception);
        }

        $company = $path[count($path) - 1] ?? null;
        $group = $path[0] ?? null;
        if (! is_array($company) || strtoupper((string) ($company['type'] ?? '')) !== 'COMPANY') {
            throw new DomainException('INVALID_REFERENCE', ['organization_id' => $companyId]);
        }
        if (! is_array($group) || strtoupper((string) ($group['type'] ?? '')) !== 'GROUP') {
            throw new DomainException('INVALID_REFERENCE', ['organization_id' => $companyId]);
        }

        return PositiveId::normalize($group['id'] ?? null, 'group_organization_id', 'INVALID_REFERENCE');
    }

    /** @return array{product_id:int,uom_id:int,company_organization_id:int,unit_price:string,price_source:string,price_list_id:int|null} */
    private function result(
        int $productId,
        int $uomId,
        int $companyOrganizationId,
        mixed $price,
        string $source,
        mixed $priceListId,
    ): array {
        if ($price === null || $price === '' || ! $this->isValidPrice($price)) {
            throw new DomainException(self::ERROR_NO_VALID_PRICE, [
                'product_id' => $productId,
                'uom_id' => $uomId,
                'price_source' => $source,
            ]);
        }

        $priceListId = $priceListId === null
            ? null
            : PositiveId::normalize($priceListId, 'price_list_id', 'INVALID_REFERENCE');

        return [
            'product_id' => $productId,
            'uom_id' => $uomId,
            'company_organization_id' => $companyOrganizationId,
            'unit_price' => (string) $price,
            'price_source' => $source,
            'price_list_id' => $priceListId,
        ];
    }

    private function isValidPrice(mixed $price): bool
    {
        if (is_int($price)) {
            $price = (string) $price;
        }
        if (! is_string($price) || preg_match('/\A(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?\z/', $price) !== 1) {
            return false;
        }

        return true;
    }

    private function priceLists(): PriceListRepository
    {
        return $this->priceLists ?? new PriceListRepository();
    }

    private function roles(): ProductRoleService
    {
        return $this->roles ?? new ProductRoleService();
    }

    private function productUoms(): ProductUomService
    {
        return $this->productUoms ?? new ProductUomService();
    }

    private function organizations(): OrganizationService
    {
        return $this->organizations ?? new OrganizationService();
    }
}
