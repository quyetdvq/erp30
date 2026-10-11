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
use App\Repositories\FoundationLocationRepository;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/** Canonical workflow adapter for persisted province/ward status columns. */
final class FoundationLocationLifecycleAdapter implements LifecycleTargetAdapter
{
    /** @var ConnectionInterface<mixed,mixed> */
    private ConnectionInterface $db;
    private FoundationLocationRepository $repository;
    private ResourceWorkflowStateResolver $states;

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public function __construct(
        private readonly string $type,
        ?ConnectionInterface $db = null,
        ?FoundationLocationRepository $repository = null,
        ?ResourceWorkflowStateResolver $states = null,
    ) {
        if (! in_array($type, ['province', 'ward'], true)) {
            throw new DomainException(LifecycleService::ERROR_TARGET_ADAPTER_UNAVAILABLE, ['resource' => $type]);
        }
        $this->db = $db ?? db_connect();
        $this->repository = $repository ?? new FoundationLocationRepository($this->db);
        $this->states = $states ?? new ResourceWorkflowStateResolver($this->db);
    }

    public function resourceType(): string
    {
        return $this->type;
    }

    public function load(mixed $targetId, OperationContext $operation): LifecycleTargetSnapshot
    {
        $id = PositiveId::normalize($targetId, 'target_id', LifecycleService::ERROR_TARGET_NOT_FOUND);
        $row = $this->repository->find($this->table(), $id);
        if ($row === null) {
            throw new DomainException(LifecycleService::ERROR_TARGET_NOT_FOUND, ['target_id' => $id]);
        }

        return $this->snapshot($id, $row);
    }

    public function transactional(callable $callback): mixed
    {
        if (! $this->db instanceof BaseConnection || ! $this->db->transBegin()) {
            throw new PersistenceException(FoundationLocationRepository::ERROR_PERSISTENCE_FAILED, 'workflowTransactionBegin');
        }
        try {
            $value = $callback();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new PersistenceException(FoundationLocationRepository::ERROR_PERSISTENCE_FAILED, 'workflowTransactionCommit');
            }

            return $value;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function lockAndReload(LifecycleTargetSnapshot $authorizedTarget, OperationContext $operation): LifecycleTargetSnapshot
    {
        if (! $this->db instanceof BaseConnection) {
            throw new PersistenceException(FoundationLocationRepository::ERROR_PERSISTENCE_FAILED, 'workflowLockConnection');
        }
        // Table name is taken from a closed enum, never from client input.
        $table = $this->db->prefixTable($this->table());
        $result = $this->db->query("SELECT * FROM `{$table}` WHERE id = ? FOR UPDATE", [$authorizedTarget->targetId]);
        if ($result === false) {
            throw new PersistenceException(FoundationLocationRepository::ERROR_PERSISTENCE_FAILED, 'workflowLock');
        }
        $row = $result->getRowArray();
        if ($row === null) {
            throw new ConcurrencyException('lifecycle.target_missing');
        }

        return $this->snapshot($authorizedTarget->targetId, $row);
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
        $destinationCode = $this->states->codeForStateId($this->type, $destinationStateId);
        // The row is locked. Retain the persisted status predicate as a
        // secondary guard against a stale or mismatched source state.
        try {
            $updated = $this->db->table($this->table())
                ->where('id', $lockedTarget->targetId)
                ->where('status', $fromCode)
                ->update(['status' => $destinationCode, ...$operation->updatedAudit()]);
        } catch (\Throwable $exception) {
            throw new PersistenceException(FoundationLocationRepository::ERROR_PERSISTENCE_FAILED, 'workflowMutate', $exception);
        }
        if ($updated === false || $this->db->affectedRows() !== 1) {
            throw new ConcurrencyException('lifecycle.state_change');
        }
    }

    private function table(): string
    {
        return $this->type === 'province' ? 'provinces' : 'wards';
    }

    /** @param array<string,mixed> $row */
    private function snapshot(int $id, array $row): LifecycleTargetSnapshot
    {
        $lifecycleId = $this->states->lifecycleId($this->type);
        $stateId = $this->states->persistedStateId($this->type, $row);
        if ($lifecycleId === null || $stateId === null) {
            throw new DomainException(LifecycleService::ERROR_INVALID_CONFIGURATION, ['resource' => $this->type]);
        }
        $organization = (new EvaluationOrganizationResolver(db: $this->db))->resolveResource(
            $this->type,
            'setstate',
            $this->states->resource($this->type),
            new EvaluationOrganizationRequest(persistedTarget: $row),
        );

        return new LifecycleTargetSnapshot($this->type, $id, $stateId, $lifecycleId, $organization->organizationId, $row);
    }
}
