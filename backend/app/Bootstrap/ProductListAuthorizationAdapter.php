<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Authorization\AuthorizationService;
use App\Authorization\EffectiveListBucketCompiler;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Repositories\ResourceCatalogRepository;
use App\Services\ResourceWorkflowStateResolver;
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

    /**
     * Advisory Product actions for the already authorized page. Only GROUP-scoped
     * MASTER Product rows can share a PBAC result for byte-identical States.
     * An altered Resource domain is never grouped by State; each row is checked
     * independently. The cache lives in this invocation only, not the adapter.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public function withAllowedActions(OperationContext $operation, array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $resource = (new ResourceCatalogRepository($this->db))->findResourceByType('product');
        // No guessed GROUP scope when Resource metadata is absent or modified.
        // The authoritative decision below will reject invalid metadata.
        $sameScopeByState = ($resource['domain'] ?? null) === 'MASTER';
        $authorization = ($this->authorization)();
        $states = new ResourceWorkflowStateResolver($this->db);
        $decisions = [];
        foreach ($rows as $index => $row) {
            if (! array_key_exists('id', $row) || ! array_key_exists('status', $row)) {
                throw new DomainException('PRODUCT_AUTHORIZATION_DENIED', ['action' => 'view']);
            }
            // HEX/typed grouping: MariaDB collations and numeric-looking State
            // strings must never silently merge distinct PBAC input values.
            $key = $sameScopeByState
                ? 'state:' . bin2hex(serialize($row['status']))
                : 'row:' . $index;
            if (! array_key_exists($key, $decisions)) {
                $target = new EvaluationOrganizationRequest(persistedTarget: $row);
                $currentStateId = $states->persistedStateId('product', $row);
                $decisions[$key] = [
                    'edit' => $authorization->authorize($operation, 'product', 'edit', $target)->isAllowed(),
                    // Lifecycle transitions require an explicit FROM-State ID,
                    // derived only from this persisted row (never request data).
                    'setstate' => $authorization->authorize($operation, 'product', 'setstate', $target, $currentStateId)->isAllowed(),
                ];
            }
            $rows[$index]['allowed_actions'] = $decisions[$key];
        }
        return $rows;
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
