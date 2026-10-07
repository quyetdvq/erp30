<?php

declare(strict_types=1);

namespace App\Modules\Pricing\PriceList;

use App\Authorization\AuthorizationAuditService;
use App\Authorization\AuthorizationDecision;
use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\ConcurrencyException;
use App\Exceptions\DomainException;
use App\Infrastructure\Decimal\Decimal;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Product\Product\ProductRoleService;
use App\Modules\Product\Uom\ProductUomService;
use App\Services\OrganizationService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use DateTimeImmutable;

/** Authorized Price List configuration and atomic item-set mutations. */
final class PriceListService
{
    public const ERROR_NOT_FOUND = 'PRICE_LIST_NOT_FOUND';
    public const ERROR_AUTHORIZATION_DENIED = 'PRICE_LIST_AUTHORIZATION_DENIED';
    public const ERROR_REQUIRED_FIELD = 'PRICE_LIST_REQUIRED_FIELD';
    public const ERROR_INVALID_FIELD = 'PRICE_LIST_INVALID_FIELD';
    public const ERROR_INVALID_STATUS = 'PRICE_LIST_INVALID_STATUS';
    public const ERROR_STATUS_MUTATION_FORBIDDEN = 'PRICE_LIST_STATUS_REQUIRES_SETSTATE';
    public const ERROR_INVALID_INTERVAL = 'PRICE_LIST_INVALID_EFFECTIVE_INTERVAL';
    public const ERROR_INVALID_SCOPE = 'PRICE_LIST_INVALID_ORGANIZATION_SCOPE';
    public const ERROR_INVALID_REFERENCE = 'INVALID_REFERENCE';
    public const ERROR_DUPLICATE = 'DUPLICATE_KEY';
    public const ERROR_INVALID_PRICE = 'PRICE_LIST_INVALID_PRICE';
    public const ERROR_ITEM_DUPLICATE = 'DUPLICATE_KEY';
    public const ERROR_ITEM_INVALID = 'PRICE_LIST_INVALID_ITEM';
    public const ERROR_PRODUCT_INACTIVE = 'INACTIVE_REFERENCE';
    public const ERROR_CONCURRENT_MODIFICATION = 'CONCURRENT_MODIFICATION';
    public const ERROR_CONNECTION_INVALID = 'PRICE_LIST_CONNECTION_INVALID';
    public const ERROR_MUTATION_FAILED = 'PRICE_LIST_MUTATION_FAILED';

    /** @var list<string> */
    private const STATUSES = ['ACTIVE', 'INACTIVE'];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?PriceListRepository $repository = null,
        private readonly ?ProductPriceListItemRepository $items = null,
        private readonly ?ProductRoleService $roles = null,
        private readonly ?ProductUomService $productUoms = null,
        private readonly ?OrganizationService $organizations = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?AuthorizationAuditService $audit = null,
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        $operation ??= $this->operation();
        $clientHint = $data['evaluation_organization_id'] ?? null;
        $payload = $this->normalizeCreate($data);
        $this->validateOrganization((int) $payload['organization_id']);
        $this->authorize($operation, 'create', null, $payload, $clientHint);

        return $this->repo()->create($payload, $operation);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        $targetId = PositiveId::normalize($id, 'price_list_id', self::ERROR_NOT_FOUND);
        $current = $this->existing($targetId);
        $clientHint = $data['evaluation_organization_id'] ?? null;
        if (array_key_exists('status', $data)) {
            throw new DomainException(self::ERROR_STATUS_MUTATION_FORBIDDEN, ['field' => 'status']);
        }
        $payload = $this->normalizeUpdate($data);
        $candidate = array_merge($current, $payload);
        $this->validateOrganization((int) $candidate['organization_id']);
        $this->validateInterval($candidate);
        $this->authorize($operation, 'edit', $current, $payload, $clientHint);

        return $this->transaction(function () use ($targetId, $current, $payload, $operation): bool {
            $locked = $this->repo()->findForUpdate($targetId);
            if ($locked === null) {
                throw new DomainException(self::ERROR_NOT_FOUND, ['price_list_id' => $targetId]);
            }
            $this->assertUnchangedOwner($current, $locked, $targetId);
            $merged = array_merge($locked, $payload);
            $this->validateOrganization((int) $merged['organization_id']);
            $this->validateInterval($merged);

            return $this->repo()->updateMutable($targetId, $payload, $operation);
        }, 'price_list_update');
    }

    public function setState(int $id, string $status, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        $targetId = PositiveId::normalize($id, 'price_list_id', self::ERROR_NOT_FOUND);
        $current = $this->existing($targetId);
        $normalizedStatus = $this->normalizeStatus($status);
        $decision = $this->authorize($operation, 'setstate', $current, []);

        return $this->transaction(function () use ($targetId, $current, $normalizedStatus, $operation, $decision): bool {
            $locked = $this->repo()->findForUpdate($targetId);
            if ($locked === null) {
                throw new DomainException(self::ERROR_NOT_FOUND, ['price_list_id' => $targetId]);
            }
            $this->assertUnchangedOwner($current, $locked, $targetId);
            $updated = $this->repo()->updateStatus($targetId, $normalizedStatus, $operation);
            $this->audit()->recordIfRequired($decision);

            return $updated;
        }, 'price_list_setstate');
    }

    public function activate(int $id, ?OperationContext $operation = null): bool
    {
        return $this->setState($id, 'ACTIVE', $operation);
    }

    public function inactivate(int $id, ?OperationContext $operation = null): bool
    {
        return $this->setState($id, 'INACTIVE', $operation);
    }

    /** @return array<string, mixed>|null */
    public function findById(mixed $id): ?array
    {
        return $this->repo()->findById($id);
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        return $this->repo()->listAll();
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->repo()->findByCode(strtoupper(trim($code)));
    }

    /** @return list<array<string, mixed>> */
    public function listByOrganization(mixed $organizationId): array
    {
        return $this->repo()->listByOrganization($organizationId);
    }

    /** @return list<array<string, mixed>> */
    public function listItems(int $priceListId, ?OperationContext $operation = null, mixed $clientHint = null): array
    {
        $operation ??= $this->operation();
        $targetId = PositiveId::normalize($priceListId, 'price_list_id', self::ERROR_NOT_FOUND);
        $target = $this->existing($targetId);
        $this->authorize($operation, 'view', $target, [], $clientHint);

        return $this->items()->listByPriceListId($targetId);
    }

    /**
     * Replace the complete Product/UOM item set atomically.
     *
     * @param list<array<string, mixed>> $submittedItems
     * @return list<array<string, mixed>>
     */
    public function replaceItems(int $priceListId, array $submittedItems, ?OperationContext $operation = null, mixed $clientHint = null): array
    {
        $operation ??= $this->operation();
        $targetId = PositiveId::normalize($priceListId, 'price_list_id', self::ERROR_NOT_FOUND);
        $current = $this->existing($targetId);
        $items = $this->normalizeItems($submittedItems);
        $this->authorize($operation, 'edit', $current, [], $clientHint);

        return $this->transaction(function () use ($targetId, $current, $items, $operation): array {
            $locked = $this->repo()->findForUpdate($targetId);
            if ($locked === null) {
                throw new DomainException(self::ERROR_NOT_FOUND, ['price_list_id' => $targetId]);
            }
            $this->assertUnchangedOwner($current, $locked, $targetId);
            $existingRows = $this->items()->listByPriceListId($targetId);
            $existingByKey = [];
            foreach ($existingRows as $row) {
                $existingByKey[$this->itemKey((int) $row['product_id'], (int) $row['uom_id'])] = $row;
            }
            $submittedKeys = [];
            foreach ($items as $item) {
                $key = $this->itemKey($item['product_id'], $item['uom_id']);
                if (isset($submittedKeys[$key])) {
                    throw new DomainException(self::ERROR_ITEM_DUPLICATE, ['product_id' => $item['product_id'], 'uom_id' => $item['uom_id']]);
                }
                $submittedKeys[$key] = true;
                $existing = $existingByKey[$key] ?? null;
                if ($existing !== null && $this->samePrice((string) $existing['price'], $item['price'])) {
                    continue;
                }
                $this->validateItem($item);
            }

            foreach ($existingRows as $row) {
                $key = $this->itemKey((int) $row['product_id'], (int) $row['uom_id']);
                if (! isset($submittedKeys[$key])) {
                    $this->items()->delete((int) $row['id']);
                }
            }
            foreach ($items as $item) {
                $key = $this->itemKey($item['product_id'], $item['uom_id']);
                $existing = $existingByKey[$key] ?? null;
                if ($existing === null) {
                    $this->items()->insert([
                        'price_list_id' => $targetId,
                        'product_id' => $item['product_id'],
                        'uom_id' => $item['uom_id'],
                        'price' => $item['price'],
                    ], $operation);
                } elseif (! $this->samePrice((string) $existing['price'], $item['price'])) {
                    $this->items()->updatePrice((int) $existing['id'], $item['price'], $operation);
                }
            }

            return $this->items()->listByPriceListId($targetId);
        }, 'price_list_items_replace');
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalizeCreate(array $data): array
    {
        $this->assertSupportedFields($data);
        $payload = $this->withoutInfrastructureFields($data);
        unset($payload['evaluation_organization_id']);
        $payload['code'] = $this->requiredText($payload['code'] ?? null, 'code', 50, true);
        $payload['name'] = $this->requiredText($payload['name'] ?? null, 'name', 255);
        $payload['organization_id'] = $this->requiredId($payload['organization_id'] ?? null, 'organization_id');
        $payload['effective_from'] = $this->timestamp($payload['effective_from'] ?? null, 'effective_from', true);
        $payload['effective_to'] = array_key_exists('effective_to', $payload)
            ? $this->timestamp($payload['effective_to'], 'effective_to', false)
            : null;
        $payload['priority'] = array_key_exists('priority', $payload) ? $this->priority($payload['priority']) : 0;
        $payload['status'] = array_key_exists('status', $payload) ? $this->normalizeStatus($payload['status']) : 'ACTIVE';
        $payload['description'] = $this->description($payload['description'] ?? null);
        $this->validateInterval($payload);

        return $payload;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalizeUpdate(array $data): array
    {
        $this->assertSupportedFields($data);
        $payload = $this->withoutInfrastructureFields($data);
        unset($payload['evaluation_organization_id']);
        if (array_key_exists('code', $payload)) {
            $payload['code'] = $this->requiredText($payload['code'], 'code', 50, true);
        }
        if (array_key_exists('name', $payload)) {
            $payload['name'] = $this->requiredText($payload['name'], 'name', 255);
        }
        if (array_key_exists('organization_id', $payload)) {
            $payload['organization_id'] = $this->requiredId($payload['organization_id'], 'organization_id');
        }
        if (array_key_exists('effective_from', $payload)) {
            $payload['effective_from'] = $this->timestamp($payload['effective_from'], 'effective_from', true);
        }
        if (array_key_exists('effective_to', $payload)) {
            $payload['effective_to'] = $this->timestamp($payload['effective_to'], 'effective_to', false);
        }
        if (array_key_exists('priority', $payload)) {
            $payload['priority'] = $this->priority($payload['priority']);
        }
        if (array_key_exists('description', $payload)) {
            $payload['description'] = $this->description($payload['description']);
        }

        return $payload;
    }

    /** @param array<string, mixed> $data */
    private function assertSupportedFields(array $data): void
    {
        $supported = [
            'code', 'name', 'organization_id', 'effective_from', 'effective_to',
            'priority', 'status', 'description', 'evaluation_organization_id',
            'id', 'created_by', 'updated_by', 'created_at', 'updated_at',
        ];
        foreach ($data as $field => $_value) {
            if (! in_array($field, $supported, true)) {
                throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => $field]);
            }
        }
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function withoutInfrastructureFields(array $data): array
    {
        unset($data['id'], $data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);

        return $data;
    }

    private function requiredText(mixed $value, string $field, int $maxLength, bool $uppercase = false): string
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $maxLength) {
            throw new DomainException(self::ERROR_REQUIRED_FIELD, ['field' => $field]);
        }
        $value = trim($value);

        return $uppercase ? strtoupper($value) : $value;
    }

    private function requiredId(mixed $value, string $field): int
    {
        return PositiveId::normalize($value, $field, self::ERROR_INVALID_REFERENCE);
    }

    private function priority(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/\A-?\d+\z/', $value) === 1) {
            return (int) $value;
        }

        throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => 'priority']);
    }

    private function description(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => 'description']);
        }

        return $value;
    }

    private function timestamp(mixed $value, string $field, bool $required): ?string
    {
        if ($value === null || ($value === '' && ! $required)) {
            if (! $required) {
                return null;
            }
            throw new DomainException(self::ERROR_REQUIRED_FIELD, ['field' => $field]);
        }
        if (! is_string($value)) {
            throw new DomainException(self::ERROR_INVALID_INTERVAL, ['field' => $field]);
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || $parsed->format('Y-m-d H:i:s') !== $value
            || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))) {
            throw new DomainException(self::ERROR_INVALID_INTERVAL, ['field' => $field]);
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function validateInterval(array $data): void
    {
        $from = $data['effective_from'] ?? null;
        $to = $data['effective_to'] ?? null;
        if (! is_string($from)) {
            throw new DomainException(self::ERROR_REQUIRED_FIELD, ['field' => 'effective_from']);
        }
        if ($to !== null && (! is_string($to) || $to <= $from)) {
            throw new DomainException(self::ERROR_INVALID_INTERVAL, ['field' => 'effective_to']);
        }
    }

    private function normalizeStatus(mixed $value): string
    {
        if (! is_string($value)) {
            throw new DomainException(self::ERROR_INVALID_STATUS, ['status' => $value]);
        }
        $status = strtoupper(trim($value));
        if (! in_array($status, self::STATUSES, true)) {
            throw new DomainException(self::ERROR_INVALID_STATUS, ['status' => $status]);
        }

        return $status;
    }

    /** @param array<mixed> $submittedItems
     *  @return list<array{product_id:int,uom_id:int,price:string}>
     */
    private function normalizeItems(array $submittedItems): array
    {
        if (! array_is_list($submittedItems)) {
            throw new DomainException(self::ERROR_ITEM_INVALID, ['field' => 'items']);
        }
        $normalized = [];
        foreach ($submittedItems as $item) {
            if (! is_array($item)) {
                throw new DomainException(self::ERROR_ITEM_INVALID, ['field' => 'items']);
            }
            foreach ($item as $field => $_value) {
                if (! in_array($field, ['product_id', 'uom_id', 'price'], true)) {
                    throw new DomainException(self::ERROR_ITEM_INVALID, ['field' => $field]);
                }
            }
            $normalized[] = [
                'product_id' => $this->requiredId($item['product_id'] ?? null, 'product_id'),
                'uom_id' => $this->requiredId($item['uom_id'] ?? null, 'uom_id'),
                'price' => $this->price($item['price'] ?? null),
            ];
        }

        return $normalized;
    }

    private function price(mixed $value): string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (! is_string($value) || preg_match('/\A(?:0|[1-9]\d*)(?:\.\d+)?\z/', $value) !== 1) {
            throw new DomainException(self::ERROR_INVALID_PRICE, ['field' => 'price']);
        }
        $parts = explode('.', $value, 2);
        if (strlen($parts[0]) > 16 || (isset($parts[1]) && strlen($parts[1]) > 2)) {
            throw new DomainException(self::ERROR_INVALID_PRICE, ['field' => 'price']);
        }
        if ((new Decimal($value))->compare('0', 2) < 0) {
            throw new DomainException(self::ERROR_INVALID_PRICE, ['field' => 'price']);
        }

        return $value;
    }

    /** @param array{product_id:int,uom_id:int,price:string} $item */
    private function validateItem(array $item): void
    {
        $product = $this->roles()->findById($item['product_id']);
        if ($product === null) {
            throw new DomainException(ProductRoleService::ERROR_NOT_FOUND, ['product_id' => $item['product_id']]);
        }
        $role = $this->roles()->getRole($product);
        if ($role === ProductRoleService::ROLE_PARENT) {
            throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, ['product_id' => $item['product_id'], 'role' => $role]);
        }
        if (($product['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException(self::ERROR_PRODUCT_INACTIVE, ['product_id' => $item['product_id']]);
        }
        if ($role === ProductRoleService::ROLE_COMBO) {
            $base = $this->productUoms()->requireActiveBaseUom($product['base_uom_id'] ?? null);
            if ((int) $base['id'] !== $item['uom_id']) {
                throw new DomainException(ProductUomService::ERROR_INVALID_CONVERSION, ['reason' => 'combo_requires_base_uom']);
            }

            return;
        }
        if (! in_array($role, [ProductRoleService::ROLE_SIMPLE, ProductRoleService::ROLE_CHILD], true)) {
            throw new DomainException(ProductRoleService::ERROR_INVALID_ROLE, ['product_id' => $item['product_id'], 'role' => $role]);
        }
        $this->productUoms()->validateSellingUom($product, $item['uom_id']);
    }

    private function itemKey(int $productId, int $uomId): string
    {
        return $productId . ':' . $uomId;
    }

    private function samePrice(string $left, string $right): bool
    {
        return (new Decimal($left))->compare($right, 2) === 0;
    }

    /** @return array<string, mixed> */
    private function existing(int $id): array
    {
        $row = $this->repo()->findById($id);
        if ($row === null) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['price_list_id' => $id]);
        }

        return $row;
    }

    private function validateOrganization(int $organizationId): void
    {
        try {
            $path = $this->organizations()->resolveAncestorChain($organizationId);
        } catch (DomainException $exception) {
            throw new DomainException(self::ERROR_INVALID_REFERENCE, ['organization_id' => $organizationId], $exception);
        }
        $organization = $path[count($path) - 1] ?? null;
        if (! is_array($organization) || ! in_array(strtoupper((string) ($organization['type'] ?? '')), ['GROUP', 'COMPANY'], true)) {
            throw new DomainException(self::ERROR_INVALID_SCOPE, ['organization_id' => $organizationId]);
        }
    }

    /** @param array<string, mixed>|null $target
     *  @param array<string, mixed> $input
     */
    private function authorize(OperationContext $operation, string $action, ?array $target, array $input, mixed $clientHint = null): AuthorizationDecision
    {
        $decision = $this->auth()->authorize($operation, 'price_list', $action, new EvaluationOrganizationRequest(
            persistedTarget: $target,
            input: $input,
            clientEvaluationOrganizationId: $clientHint,
        ));
        if (! $decision->isAllowed()) {
            if ($action === 'setstate') {
                $this->audit()->recordIfRequired($decision);
            }
            throw new DomainException(self::ERROR_AUTHORIZATION_DENIED, ['action' => $action]);
        }

        return $decision;
    }

    /** @param array<string, mixed> $before
     *  @param array<string, mixed> $after
     */
    private function assertUnchangedOwner(array $before, array $after, int $id): void
    {
        if ((int) $before['organization_id'] !== (int) $after['organization_id']) {
            throw new DomainException(self::ERROR_CONCURRENT_MODIFICATION, ['price_list_id' => $id]);
        }
    }

    private function repo(): PriceListRepository
    {
        return $this->repository ?? new PriceListRepository($this->db);
    }

    private function items(): ProductPriceListItemRepository
    {
        return $this->items ?? new ProductPriceListItemRepository($this->db);
    }

    private function roles(): ProductRoleService
    {
        return $this->roles ?? new ProductRoleService(db: $this->db);
    }

    private function productUoms(): ProductUomService
    {
        return $this->productUoms ?? new ProductUomService(db: $this->db);
    }

    private function organizations(): OrganizationService
    {
        return $this->organizations ?? new OrganizationService(db: $this->db);
    }

    private function auth(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService(db: $this->db);
    }

    private function audit(): AuthorizationAuditService
    {
        return $this->audit ?? new AuthorizationAuditService(db: $this->db);
    }

    private function operation(): OperationContext
    {
        return ($this->mutationContext ?? MutationContext::system($this->clock ?? service('clock')))->begin();
    }

    /** @param callable(): mixed $callback */
    private function transaction(callable $callback, string $operation): mixed
    {
        if (! $this->db instanceof BaseConnection || ! $this->db->transBegin()) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => $operation]);
        }
        try {
            $result = $callback();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => $operation]);
            }

            return $result;
        } catch (ConcurrencyException $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw new DomainException(self::ERROR_CONCURRENT_MODIFICATION, ['operation' => $operation], $exception);
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => $operation], $exception);
        }
    }
}
