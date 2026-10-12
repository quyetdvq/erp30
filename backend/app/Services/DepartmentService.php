<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Lock\MariaDbDomainLockService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Repositories\DepartmentRepository;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

final class DepartmentService
{
    public const ERROR_CODE_DUPLICATE = 'DEPARTMENT_CODE_DUPLICATE';
    public const ERROR_REFERENCE_INVALID = 'DEPARTMENT_REFERENCE_INVALID';
    public const ERROR_HIERARCHY_CYCLE = 'DEPARTMENT_HIERARCHY_CYCLE';
    public const ERROR_STATUS_INVALID = 'DEPARTMENT_STATUS_INVALID';
    public const ERROR_MUTATION_FAILED = 'DEPARTMENT_MUTATION_FAILED';

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?DepartmentRepository $repository = null,
        ?ConnectionInterface $db = null,
        private readonly ?DomainLockService $domainLock = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $operation = $this->operation();
        $data = BusinessWorkflowWriteBoundary::create('department', $data, $this->db);
        $payload = $this->prepare($data, true, $operation);

        return $this->withTreeLock(function () use ($payload): int {
            $this->assertCodeAvailable((string) $payload['code']);
            $this->assertParentAndCycle(null, $payload['parent_id']);

            return $this->repo()->insert($payload);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $operation = $this->operation();
        $current = $this->repo()->find($id);
        if ($current === null) {
            throw new DomainException(DepartmentService::ERROR_REFERENCE_INVALID, ['department_id' => $id]);
        }
        BusinessWorkflowWriteBoundary::assertNoDirectStateMutation('department', $current, $data, $this->db);
        $payload = $this->prepare($data, false, $operation);
        $candidate = array_merge($current, $payload);

        return $this->withTreeLock(function () use ($id, $payload, $candidate): bool {
            if (array_key_exists('code', $payload)) {
                $this->assertCodeAvailable((string) $payload['code'], $id);
            }
            if (array_key_exists('parent_id', $payload)) {
                $this->assertParentAndCycle($id, $candidate['parent_id']);
            }

            return $this->repo()->update($id, $payload);
        });
    }

    /** @param array<string, mixed> $data */
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prepare(array $data, bool $creating, OperationContext $operation): array
    {
        $payload = $this->withoutAuditFields($data);
        foreach ($payload as $field => $_value) {
            if (! in_array($field, ['parent_id', 'code', 'name', 'description', 'status'], true)) {
                throw new DomainException(DepartmentRepository::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
        if ($creating && (! array_key_exists('code', $payload) || ! array_key_exists('name', $payload))) {
            throw new DomainException(DepartmentRepository::ERROR_FIELD_REQUIRED, ['field' => 'code']);
        }
        if ($creating && ! array_key_exists('parent_id', $payload)) {
            $payload['parent_id'] = null;
        }
        if (array_key_exists('parent_id', $payload)) {
            $payload['parent_id'] = $payload['parent_id'] === null
                ? null
                : PositiveId::normalize($payload['parent_id'], 'parent_id', self::ERROR_REFERENCE_INVALID);
        }
        if (array_key_exists('code', $payload)) {
            if (! is_string($payload['code']) || trim($payload['code']) === '') {
                throw new DomainException(self::ERROR_REFERENCE_INVALID, ['field' => 'code']);
            }
            $payload['code'] = trim($payload['code']);
        }
        if (array_key_exists('name', $payload) && (! is_string($payload['name']) || trim($payload['name']) === '')) {
            throw new DomainException(self::ERROR_REFERENCE_INVALID, ['field' => 'name']);
        }
        if (array_key_exists('status', $payload)) {
            if (! is_string($payload['status'])) {
                throw new DomainException(self::ERROR_STATUS_INVALID, ['status' => '']);
            }
            $payload['status'] = strtoupper(trim($payload['status']));
            $payload['status'] = WorkflowBusinessStatus::normalize(
                'department', $payload['status'], ['ACTIVE', 'INACTIVE', 'CLOSED'], self::ERROR_STATUS_INVALID, $this->db,
            );
        } elseif ($creating) {
            $payload['status'] = 'ACTIVE';
        }
        $payload = array_merge($payload, $creating ? $operation->createdAudit() : $operation->updatedAudit());

        return $payload;
    }

    private function assertCodeAvailable(string $code, ?int $excludeId = null): void
    {
        $row = $this->repo()->findByCode($code);
        if ($row !== null && ($excludeId === null || (int) $row['id'] !== $excludeId)) {
            throw new DomainException(self::ERROR_CODE_DUPLICATE, ['field' => 'code']);
        }
    }

    private function assertParentAndCycle(?int $departmentId, mixed $parentId): void
    {
        if ($parentId === null) {
            return;
        }
        $parent = $this->repo()->find((int) $parentId);
        if ($parent === null) {
            throw new DomainException(self::ERROR_REFERENCE_INVALID, ['field' => 'parent_id']);
        }
        if ($departmentId !== null && $departmentId === (int) $parentId) {
            throw new DomainException(self::ERROR_HIERARCHY_CYCLE, ['department_id' => $departmentId]);
        }
        $visited = [];
        $cursor = $parent;
        while (true) {
            $cursorId = (int) ($cursor['id'] ?? 0);
            if ($cursorId < 1 || isset($visited[$cursorId])) {
                throw new DomainException(self::ERROR_HIERARCHY_CYCLE, ['department_id' => $departmentId]);
            }
            $visited[$cursorId] = true;
            if ($departmentId !== null && $cursorId === $departmentId) {
                throw new DomainException(self::ERROR_HIERARCHY_CYCLE, ['department_id' => $departmentId]);
            }
            if (($cursor['parent_id'] ?? null) === null) {
                return;
            }
            $cursor = $this->repo()->find((int) $cursor['parent_id']);
            if ($cursor === null) {
                throw new DomainException(self::ERROR_REFERENCE_INVALID, ['field' => 'parent_id']);
            }
        }
    }

    private function operation(): OperationContext
    {
        return ($this->mutationContext ?? MutationContext::system($this->clock ?? service('clock')))->begin();
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withoutAuditFields(array $data): array
    {
        unset($data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);

        return $data;
    }

    private function repo(): DepartmentRepository
    {
        return $this->repository ?? new DepartmentRepository($this->db);
    }

    /** @param callable(): mixed $callback */
    private function withTreeLock(callable $callback): mixed
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => 'department_mutation']);
        }
        $db = $this->db;
        $lock = $this->domainLock ?? new MariaDbDomainLockService($db);

        try {
            return $lock->synchronized('department-tree', function () use ($callback, $db): mixed {
                $db->transBegin();
                try {
                    $result = $callback();
                    if (! $db->transStatus()) {
                        throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => 'department_mutation']);
                    }
                    $db->transCommit();

                    return $result;
                } catch (\Throwable $exception) {
                    $db->transRollback();
                    throw $exception;
                }
            });
        } catch (DomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => 'department_mutation'], $exception);
        }
    }
}
