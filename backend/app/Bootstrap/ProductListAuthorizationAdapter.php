<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Authorization\AuthorizationService;
use App\Authorization\EffectiveListBucketCompiler;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Product\Product\Application\Ports\ProductListAuthorizationPort;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Temporary API-050 composition adapter. Authorization continues to own PBAC,
 * Workflow/Policy determine whether State-specific checks are needed, and the
 * original facade supplies the same lazy actor/time and cached dependencies.
 * Do not make this adapter a general-purpose PBAC bypass/read API.
 */
final class ProductListAuthorizationAdapter implements ProductListAuthorizationPort
{
    /** @var \Closure():OperationContext */
    private readonly \Closure $operation;

    /** @var \Closure():AuthorizationService */
    private readonly \Closure $authorization;

    /** @var \Closure():bool */
    private readonly \Closure $requiresState;

    /**
     * @param ConnectionInterface<mixed,mixed> $db
     * @param \Closure():OperationContext $operation
     * @param \Closure():AuthorizationService $authorization
     * @param \Closure():bool $requiresState
     */
    public function __construct(
        private readonly ConnectionInterface $db,
        \Closure $operation,
        \Closure $authorization,
        \Closure $requiresState,
    ) {
        $this->operation = $operation;
        $this->authorization = $authorization;
        $this->requiresState = $requiresState;
    }

    public function requiresRowStateAuthorization(): bool
    {
        return ($this->requiresState)();
    }

    public function operation(): OperationContext
    {
        return ($this->operation)();
    }

    public function authorizeBaseView(): void
    {
        if (! $this->decision(null)) {
            throw new DomainException('PRODUCT_AUTHORIZATION_DENIED', ['action' => 'view']);
        }
    }

    /** @param array<string,mixed> $row */
    public function rowVisible(array $row): bool
    {
        // Match the legacy per-row fallback: obtain an operation for each row.
        return $this->decision($row);
    }

    /** @param array<string,mixed> $row */
    public function recheckRowVisible(OperationContext $operation, array $row): bool
    {
        return $this->decision($row, $operation);
    }

    /** @template T @param callable():T $read @return T */
    public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed
    {
        return ($this->authorization)()->withReadOnlyBatch($operation, $read);
    }

    /** @return list<string>|null */
    public function authorizedStatuses(OperationContext $operation): ?array
    {
        $buckets = (new EffectiveListBucketCompiler($this->db, ($this->authorization)()))
            ->compile('product', $operation, static fn (DomainException $_): bool => false);

        return $buckets === null ? null : array_column($buckets, 'status');
    }

    /** @param array<string,mixed>|null $row */
    private function decision(?array $row, ?OperationContext $operation = null): bool
    {
        // Same evaluation order as API-050's decision(): first resolve the
        // cached Authorization service, then capture the request operation.
        $authorization = ($this->authorization)();
        $operation ??= $this->operation();
        return $authorization->authorize(
            $operation,
            'product',
            'view',
            new EvaluationOrganizationRequest(persistedTarget: $row),
        )->isAllowed();
    }
}
