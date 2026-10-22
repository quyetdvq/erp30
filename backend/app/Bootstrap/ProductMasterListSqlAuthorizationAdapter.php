<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Authorization\AuthorizationService;
use App\Authorization\EffectiveListBucketCompiler;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Product\Lookup\Application\Ports\ProductMasterListSqlAuthorizationPort;
use CodeIgniter\Database\ConnectionInterface;

/** Trusted composition only: narrow MASTER State bucket/recheck adapter. */
final class ProductMasterListSqlAuthorizationAdapter implements ProductMasterListSqlAuthorizationPort
{
    /**
     * @param ConnectionInterface<mixed,mixed> $db
     * @param \Closure():OperationContext $operation
     * @param \Closure():AuthorizationService $authorization
     */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $resource,
        private readonly \Closure $operation,
        private readonly \Closure $authorization,
    ) {
        if (! in_array($resource, ['product_group', 'product_uom'], true)) {
            throw new \InvalidArgumentException('Unsupported Product master list authorization.');
        }
    }

    public function operation(): OperationContext
    {
        return ($this->operation)();
    }

    /** @return list<array{status:string}>|null */
    public function authorizedBuckets(OperationContext $operation): ?array
    {
        // Domain must be authoritative MASTER; the compiler returns null on
        // unapproved metadata, too many States, or an uncertain representative.
        return (new EffectiveListBucketCompiler($this->db, ($this->authorization)()))
            ->compile($this->resource, $operation, static fn (DomainException $_): bool => false);
    }

    /** @template T @param callable():T $read @return T */
    public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed
    {
        return ($this->authorization)()->withReadOnlyBatch($operation, $read);
    }

    /** @param array<string,mixed> $row */
    public function recheckRowVisible(OperationContext $operation, array $row): bool
    {
        return ($this->authorization)()->authorize(
            $operation,
            $this->resource,
            'view',
            new EvaluationOrganizationRequest(persistedTarget: $row),
        )->isAllowed();
    }
}
