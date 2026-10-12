<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\EvaluationOrganizationResolver;
use App\Exceptions\ConcurrencyException;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Lifecycle adapter for registered business resources that store a status.
 * Only fixed table names are accepted; no client-controlled SQL identifiers.
 * The canonical LifecycleService owns PBAC, transition, locking and audit.
 */
final class BusinessLifecycleTargetAdapter implements LifecycleTargetAdapter
{
    private const TABLES = [
        'organization' => 'organizations',
        'department' => 'departments',
        'position' => 'positions',
        'employee' => 'employees',
        'product_group' => 'product_groups',
        'product_uom' => 'product_uoms',
        'product' => 'products',
        'price_list' => 'price_lists',
    ];

    /** @var ConnectionInterface<mixed,mixed> */
    private ConnectionInterface $db;
    private ResourceWorkflowStateResolver $states;

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public function __construct(private readonly string $type, ?ConnectionInterface $db = null, ?ResourceWorkflowStateResolver $states = null)
    {
        if (! array_key_exists($type, self::TABLES)) {
            throw new DomainException(LifecycleService::ERROR_TARGET_ADAPTER_UNAVAILABLE, ['resource' => $type]);
        }
        $this->db = $db ?? db_connect();
        $this->states = $states ?? new ResourceWorkflowStateResolver($this->db);
    }

    public static function supports(string $resource): bool
    {
        return isset(self::TABLES[$resource]);
    }

    public function resourceType(): string
    {
        return $this->type;
    }

    public function load(mixed $targetId, OperationContext $operation): LifecycleTargetSnapshot
    {
        $id = PositiveId::normalize($targetId, 'target_id', LifecycleService::ERROR_TARGET_NOT_FOUND);
        $row = $this->db->table($this->table())->where('id', $id)->get()->getRowArray();
        if ($row === null) {
            throw new DomainException(LifecycleService::ERROR_TARGET_NOT_FOUND, ['target_id' => $id]);
        }
        return $this->snapshot($id, $row, $operation);
    }

    public function transactional(callable $callback): mixed
    {
        if (! $this->db instanceof BaseConnection || ! $this->db->transBegin()) {
            throw new PersistenceException('WF_TARGET_PERSISTENCE_FAILED', 'workflowTransactionBegin');
        }
        try {
            $value = $callback();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new PersistenceException('WF_TARGET_PERSISTENCE_FAILED', 'workflowTransactionCommit');
            }
            return $value;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function lockAndReload(LifecycleTargetSnapshot $authorizedTarget, OperationContext $operation): LifecycleTargetSnapshot
    {
        $table = str_replace('`', '``', $this->db->prefixTable($this->table()));
        $result = $this->db->query("SELECT * FROM `{$table}` WHERE id = ? FOR UPDATE", [$authorizedTarget->targetId]);
        if ($result === false) {
            throw new PersistenceException('WF_TARGET_PERSISTENCE_FAILED', 'workflowLock');
        }
        $row = $result->getRowArray();
        if ($row === null) {
            throw new ConcurrencyException('lifecycle.target_missing');
        }
        return $this->snapshot($authorizedTarget->targetId, $row, $operation);
    }

    public function mutate(
        LifecycleTargetSnapshot $lockedTarget,
        int $expectedCurrentStateId,
        int $destinationStateId,
        OperationContext $operation,
    ): void {
        if ($lockedTarget->currentStateId !== $expectedCurrentStateId) {
            throw new ConcurrencyException('lifecycle.state_change');
        }
        $fromCode = $this->states->codeForStateId($this->type, $expectedCurrentStateId);
        $toCode = $this->states->codeForStateId($this->type, $destinationStateId);
        try {
            $result = $this->db->table($this->table())
                ->where('id', $lockedTarget->targetId)
                ->where('status', $fromCode)
                ->update(['status' => $toCode, ...$operation->updatedAudit()]);
        } catch (\Throwable $exception) {
            throw new PersistenceException('WF_TARGET_PERSISTENCE_FAILED', 'workflowMutate', $exception);
        }
        if ($result === false || $this->db->affectedRows() !== 1) {
            throw new ConcurrencyException('lifecycle.state_change');
        }
    }

    /** @param array<string,mixed> $row */
    private function snapshot(int $id, array $row, OperationContext $operation): LifecycleTargetSnapshot
    {
        $lifecycleId = $this->states->lifecycleId($this->type);
        $stateId = $this->states->persistedStateId($this->type, $row);
        if ($lifecycleId === null || $stateId === null) {
            throw new DomainException(LifecycleService::ERROR_INVALID_CONFIGURATION, ['resource' => $this->type]);
        }
        $scope = (new EvaluationOrganizationResolver(db: $this->db))->resolveResource(
            $this->type,
            'setstate',
            $this->states->resource($this->type),
            new EvaluationOrganizationRequest(persistedTarget: $row, evaluationTime: $operation->evaluationTime),
        );
        return new LifecycleTargetSnapshot($this->type, $id, $stateId, $lifecycleId, $scope->organizationId, $row);
    }

    private function table(): string
    {
        return self::TABLES[$this->type];
    }
}
