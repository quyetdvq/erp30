<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\PriceListAuthorizedBucketCompiler;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Pricing\PriceList\Application\Ports\PriceListListAuthorizationPort;
use CodeIgniter\Database\ConnectionInterface;

/**
 * API-050 transitional composition adapter; no new policy implementation.
 * Pricing is not allowed to depend on Authorization's legacy implementation.
 * This adapter preserves the facade's lazy actor/context and PBAC service.
 */
final class PriceListListAuthorizationAdapter implements PriceListListAuthorizationPort
{
    /** @var \Closure():OperationContext */
    private readonly \Closure $operation;

    /** @var \Closure():AuthorizationService */
    private readonly \Closure $authorization;

    /**
     * @param \Closure():OperationContext $operation
     * @param \Closure():AuthorizationService $authorization
     */
    public function __construct(\Closure $operation, \Closure $authorization)
    {
        $this->operation = $operation;
        $this->authorization = $authorization;
    }

    public function operation(): OperationContext
    {
        return ($this->operation)();
    }

    /** @template T @param callable():T $read @return T */
    public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed
    {
        return ($this->authorization)()->withReadOnlyBatch($operation, $read);
    }

    /** @return list<array{organization_id:int,status:string}>|null */
    public function authorizedBuckets(OperationContext $operation, ConnectionInterface $db): ?array
    {
        return (new PriceListAuthorizedBucketCompiler($db, ($this->authorization)()))->compile($operation);
    }

    /** @param array<string,mixed> $row */
    public function rowAllowed(array $row, ?OperationContext $operation = null): bool
    {
        // Legacy decision() resolves Authorization before creating a new
        // OperationContext. Keep that order and never trust request owner hints.
        $authorization = ($this->authorization)();
        $operation ??= ($this->operation)();
        return $authorization->authorize(
            $operation,
            'price_list',
            'view',
            new EvaluationOrganizationRequest(persistedTarget: $row),
        )->isAllowed();
    }
}
