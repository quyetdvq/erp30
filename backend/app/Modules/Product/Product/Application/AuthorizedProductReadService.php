<?php

declare(strict_types=1);

namespace App\Modules\Product\Product\Application;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Product\Product\ProductMasterService;
use App\Modules\Product\Product\ProductRoleService;

/**
 * Shared, state-aware Product read capability for API-050 and Product Media.
 * All callers supply the same trusted operation authority; this class does not
 * depend on either HTTP API or Product Media and does not cache decisions.
 */
final class AuthorizedProductReadService
{
    /** @var \Closure():OperationContext */
    private readonly \Closure $operation;

    /** @param \Closure():OperationContext $operation */
    public function __construct(
        private readonly ProductMasterService $products,
        private readonly ProductRoleService $roles,
        private readonly AuthorizationService $authorization,
        \Closure $operation,
    ) {
        $this->operation = $operation;
    }

    /** @return array<string,mixed> Unmodified persisted Product row. */
    public function authorizedRow(int $id, string $action): array
    {
        // Preserve the historical error precedence: bad action before row lookup.
        if (! in_array($action, ['view', 'edit'], true)) {
            throw new DomainException('PRODUCT_INVALID_ACTION');
        }
        $row = $this->requireProduct($id);
        $this->authorize($row, $action);
        return $row;
    }

    /** @return array<string,mixed> Authorized and redacted public Product DTO. */
    public function publicProduct(int $id): array
    {
        $row = $this->requireProduct($id);
        // Never reuse an earlier edit authorization for the response read.
        $this->authorize($row, 'view');
        $row['role'] = $this->roles->getRole($row);
        $row['full_name'] = $this->roles->getFullName($row);
        $row['selectable'] = $this->roles->isSelectable($row);
        $row['leaf_transactionable'] = $this->roles->isLeafTransactionable($row);
        return self::projectForApi($row);
    }

    /**
     * Reusable public projection for API-050 Product listing as well as reads.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function projectForApi(array $row): array
    {
        // Preserve driver-dependent TINYINT and legacy Product code handling.
        if (array_key_exists('image_filename', $row)) {
            $row['image_available'] = is_string($row['image_filename']) && $row['image_filename'] !== '';
        }
        unset($row['image_filename'], $row['image_mime'], $row['image_size_bytes']);
        if (array_key_exists('serial_management_enabled', $row)) {
            $row['serial_management_enabled'] = in_array($row['serial_management_enabled'], [true, 1, '1'], true);
        }
        if (array_key_exists('code', $row)) {
            $row['product_code'] = $row['code'];
            unset($row['code']);
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function requireProduct(int $id): array
    {
        $row = $this->products->findById($id);
        if ($row === null) {
            throw new DomainException(ProductMasterService::ERROR_NOT_FOUND);
        }
        return $row;
    }

    /** @param array<string,mixed> $row */
    private function authorize(array $row, string $action): void
    {
        $decision = $this->authorization->authorize(
            ($this->operation)(),
            'product',
            $action,
            new EvaluationOrganizationRequest(persistedTarget: $row),
        );
        if (! $decision->isAllowed()) {
            throw new DomainException('PRODUCT_AUTHORIZATION_DENIED', ['action' => $action]);
        }
    }
}
