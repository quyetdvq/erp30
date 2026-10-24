<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Mutation;

use App\Exceptions\DomainException;
use App\Modules\Workflow\Management\WorkflowReadService;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;
use Throwable;

/**
 * M3.3B4 guarded Workflow catalog writes. The Workflow State/Lifecycle enable
 * flag is Native State; other business Resources still use Lifecycle only.
 * Configuration PUT is append-only, matching v1.2.4 LifecycleService::configure.
 */
final class WorkflowMutationService
{
    private readonly BaseConnection $db;
    private readonly WorkflowMutationRepository $repository;
    private readonly UnifiedAccessService $access;
    private readonly WorkflowReadService $read;
    private readonly string $actorId;

    public function __construct(User $actor, ?BaseConnection $connection = null)
    {
        $this->db = $connection ?? db_connect();
        $this->repository = new WorkflowMutationRepository($this->db);
        $this->access = new UnifiedAccessService($actor, $this->db);
        $this->read = new WorkflowReadService($actor, $this->db);
        $this->actorId = WorkflowMutationRules::id((string) $actor->id, 'actor_id');
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function create(string $type, array $payload): array
    {
        $this->type($type);
        $data = WorkflowMutationRules::master($payload, true);
        $id = $this->transaction(function () use ($type, $data): string {
            $this->permit($type, 'create', null, $data);
            if ($this->repository->byCode($type, (string) $data['code']) !== null) {
                throw new DomainException('LIFECYCLE_MUTATION_FAILED', ['field' => 'code']);
            }
            return $this->repository->createMaster($type, [...$data, ...$this->createdAudit()]);
        });
        return $this->visible($type, $id);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function update(string $type, string $id, array $payload): array
    {
        $this->type($type);
        $id = WorkflowMutationRules::id($id, 'id');
        $data = WorkflowMutationRules::master($payload, false);
        $this->transaction(function () use ($type, $id, $data): void {
            $current = $this->locked($type, $id);
            $this->permit($type, 'edit', $current);
            // Baseline accepts inline status for Workflow masters (unlike
            // Policy/Resource). A dedicated set-state endpoint also exists.
            $this->repository->updateMaster($type, $id, [...$data, ...$this->updatedAudit()]);
        });
        return $this->visible($type, $id);
    }

    /** @return array<string,mixed> */
    public function setState(string $type, string $id, mixed $status): array
    {
        $this->type($type);
        $id = WorkflowMutationRules::id($id, 'id');
        $next = WorkflowMutationRules::status($status);
        $this->transaction(function () use ($type, $id, $next): void {
            $current = $this->locked($type, $id);
            $this->permit($type, 'setstate', $current);
            $this->repository->updateMaster($type, $id, ['status' => $next, ...$this->updatedAudit()]);
        });
        // The v1.2.4 API-030 adapter returns get() for Workflow masters,
        // unlike Policy/Resource which return a minimal projection.
        return $this->visible($type, $id);
    }

    /**
     * Both PUT /states and PUT /transitions invoke this SAME append-only
     * operation in the original controller. A missing list means [] and does
     * not remove existing rows or implicitly change the other relation.
     *
     * @param array<string,mixed> $payload
     * @return array{lifecycle:array<string,mixed>,states:list<array<string,mixed>>,transitions:list<array<string,mixed>>}
     */
    public function configure(string $id, array $payload): array
    {
        $id = WorkflowMutationRules::id($id, 'lifecycle_id');
        $batch = WorkflowMutationRules::configuration($id, $payload);
        return $this->transaction(function () use ($id, $batch): array {
            $lifecycle = $this->locked('lifecycle', $id);
            $this->permit('lifecycle', 'edit', $lifecycle);
            $oldStates = $this->repository->states($id);
            $oldTransitions = $this->repository->transitions($id);
            $newStates = array_map(static fn (array $item): array => [
                'lifecycle_id' => $id, ...$item,
            ], $batch['states']);
            $composedStates = [...$oldStates, ...$newStates];
            $composedTransitions = [...$oldTransitions, ...$batch['transitions']];
            WorkflowMutationRules::assertConfiguration(
                $lifecycle, $composedStates, $composedTransitions,
                fn (string $stateId): bool => $this->repository->activeState($stateId),
            );
            foreach ($newStates as $state) {
                $this->repository->insertState([...$state, ...$this->createdAudit()]);
            }
            foreach ($batch['transitions'] as $transition) {
                $this->repository->insertTransition([...$transition, ...$this->createdAudit()]);
            }
            // Baseline validates the saved composition within the transaction.
            $states = $this->repository->states($id);
            $transitions = $this->repository->transitions($id);
            WorkflowMutationRules::assertConfiguration(
                $lifecycle, $states, $transitions,
                fn (string $stateId): bool => $this->repository->activeState($stateId),
            );
            return ['lifecycle' => $lifecycle, 'states' => $states, 'transitions' => $transitions];
        });
    }

    /** @param array<string,mixed>|null $row @param array<string,mixed> $candidate */
    private function permit(string $type, string $action, ?array $row, array $candidate = []): void
    {
        $decision = $this->access->decideMutation($type, $action, $row, $candidate);
        if (! $decision['allowed']) {
            throw new DomainException('AUTH_POLICY_DENIED', ['action' => $action]);
        }
    }

    /** @return array<string,mixed> */
    private function locked(string $type, string $id): array
    {
        $row = $this->repository->master($type, $id, true);
        if ($row === null) {
            throw new DomainException($type === 'lifecycle' ? 'WF_LIFECYCLE_NOT_FOUND' : 'RESOURCE_NOT_FOUND');
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function visible(string $type, string $id): array
    {
        $row = $this->read->detail($type, $id);
        if ($row === null) {
            throw new DomainException('RESOURCE_NOT_FOUND');
        }
        return $row;
    }

    private function type(string $type): void
    {
        if (! in_array($type, ['workflow_state', 'lifecycle'], true)) {
            throw new \InvalidArgumentException('Unsupported Workflow master mutation.');
        }
    }

    /** @return array{created_by:string,created_at:string} */
    private function createdAudit(): array
    {
        return ['created_by' => $this->actorId, 'created_at' => gmdate('Y-m-d H:i:s')];
    }

    /** @return array{updated_by:string,updated_at:string} */
    private function updatedAudit(): array
    {
        return ['updated_by' => $this->actorId, 'updated_at' => gmdate('Y-m-d H:i:s')];
    }

    /** @template T @param callable():T $callback @return T */
    private function transaction(callable $callback): mixed
    {
        if (! $this->db->transBegin()) {
            throw new RuntimeException('Workflow transaction could not start.');
        }
        try {
            $result = $callback();
            if (! $this->db->transStatus()) {
                throw new RuntimeException('Workflow transaction was marked failed.');
            }
            if (! $this->db->transCommit()) {
                throw new RuntimeException('Workflow transaction could not commit.');
            }
            return $result;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $exception;
        }
    }
}
