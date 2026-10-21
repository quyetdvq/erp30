<?php

declare(strict_types=1);

namespace App\Modules\Contact\Integration\Workflow;

use App\Services\LifecycleService;
use App\Services\LifecycleTargetAdapter;
use App\Services\LifecycleTargetSnapshot;
use App\Services\ResourceWorkflowStateResolver;

use App\Authorization\ContactAuthorizationRegistry;
use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\EvaluationOrganizationResolver;
use App\Exceptions\ConcurrencyException;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\OperationContext;
use App\Repositories\ContactRepository;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/** Canonical Lifecycle adapter for owner-kind Contact authorization Resources. */
final class ContactLifecycleAdapter implements LifecycleTargetAdapter
{
    /** @var ConnectionInterface<mixed,mixed> */
    private ConnectionInterface $db;
    private ContactRepository $contacts;
    private ResourceWorkflowStateResolver $states;

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public function __construct(
        private readonly string $type,
        ?ConnectionInterface $db = null,
        ?ContactRepository $contacts = null,
        ?ResourceWorkflowStateResolver $states = null,
    ) {
        if (! in_array($type, ['organization_contact', 'employee_contact'], true)) {
            throw new DomainException(LifecycleService::ERROR_TARGET_ADAPTER_UNAVAILABLE, ['resource' => $type]);
        }
        $this->db = $db ?? db_connect();
        $this->contacts = $contacts ?? new ContactRepository($this->db);
        $this->states = $states ?? new ResourceWorkflowStateResolver($this->db);
    }

    public function resourceType(): string
    {
        return $this->type;
    }

    public function load(mixed $targetId, OperationContext $operation): LifecycleTargetSnapshot
    {
        $id = PositiveId::normalize($targetId, 'target_id', LifecycleService::ERROR_TARGET_NOT_FOUND);
        $row = $this->contacts->find($id);
        if ($row === null || ContactAuthorizationRegistry::resourceFor($row) !== $this->type) {
            throw new DomainException(LifecycleService::ERROR_TARGET_NOT_FOUND, ['target_id' => $id]);
        }

        return $this->snapshot($id, $row, $operation);
    }

    public function transactional(callable $callback): mixed
    {
        if (! $this->db instanceof BaseConnection || ! $this->db->transBegin()) {
            throw new PersistenceException(ContactRepository::ERROR_PERSISTENCE_FAILED, 'workflowTransactionBegin');
        }
        try {
            $value = $callback();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new PersistenceException(ContactRepository::ERROR_PERSISTENCE_FAILED, 'workflowTransactionCommit');
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
            throw new PersistenceException(ContactRepository::ERROR_PERSISTENCE_FAILED, 'workflowLockConnection');
        }
        $table = $this->db->prefixTable('contacts');
        $result = $this->db->query("SELECT * FROM `{$table}` WHERE id = ? FOR UPDATE", [$authorizedTarget->targetId]);
        if (! $result instanceof \CodeIgniter\Database\ResultInterface) {
            throw new PersistenceException(ContactRepository::ERROR_PERSISTENCE_FAILED, 'workflowLock');
        }
        $row = $result->getRowArray();
        if ($row === null || ContactAuthorizationRegistry::resourceFor($row) !== $this->type) {
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
        $from = $this->states->codeForStateId($this->type, $expectedCurrentStateId);
        $to = $this->states->codeForStateId($this->type, $destinationStateId);
        if (($lockedTarget->authorizationTarget['status'] ?? null) !== $from) {
            throw new ConcurrencyException('lifecycle.state_change');
        }
        try {
            $updated = $this->db->table('contacts')->where('id', $lockedTarget->targetId)
                ->where('status', $from)
                ->update(['status' => $to, ...$operation->updatedAudit()]);
        } catch (\Throwable $exception) {
            throw new PersistenceException(ContactRepository::ERROR_PERSISTENCE_FAILED, 'workflowMutate', $exception);
        }
        if ($updated === false || ! $this->db instanceof BaseConnection || $this->db->affectedRows() !== 1) {
            throw new ConcurrencyException('lifecycle.state_change');
        }
    }

    /** @param array<string,mixed> $row */
    private function snapshot(int $id, array $row, OperationContext $operation): LifecycleTargetSnapshot
    {
        $lifecycleId = $this->states->lifecycleId($this->type);
        $stateId = $this->states->persistedStateId($this->type, $row);
        if ($lifecycleId === null || $stateId === null
            || ($row['status'] ?? null) !== $this->states->codeForStateId($this->type, $stateId)) {
            throw new DomainException(LifecycleService::ERROR_INVALID_CONFIGURATION, ['resource' => $this->type]);
        }
        $organization = (new EvaluationOrganizationResolver(db: $this->db))->resolveResource(
            $this->type,
            'setstate',
            $this->states->resource($this->type),
            new EvaluationOrganizationRequest(persistedTarget: $row, evaluationTime: $operation->evaluationTime),
        );

        return new LifecycleTargetSnapshot($this->type, $id, $stateId, $lifecycleId, $organization->organizationId, $row);
    }
}
