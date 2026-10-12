<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Repositories\PositionRepository;
use CodeIgniter\Database\ConnectionInterface;

final class PositionService
{
    public const ERROR_CODE_DUPLICATE = 'POSITION_CODE_DUPLICATE';
    public const ERROR_REFERENCE_INVALID = 'POSITION_REFERENCE_INVALID';
    public const ERROR_STATUS_INVALID = 'POSITION_STATUS_INVALID';

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?PositionRepository $repository = null,
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $operation = $this->operation();
        $data = BusinessWorkflowWriteBoundary::create('position', $data, $this->db);
        $payload = $this->prepare($data, true, $operation);
        $this->assertCodeAvailable((string) $payload['code']);

        return $this->repo()->insert($payload);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $current = $this->repo()->find($id);
        if ($current === null) {
            throw new DomainException(self::ERROR_REFERENCE_INVALID, ['position_id' => $id]);
        }
        BusinessWorkflowWriteBoundary::assertNoDirectStateMutation('position', $current, $data, $this->db);
        $payload = $this->prepare($data, false, $this->operation());
        if (array_key_exists('code', $payload)) {
            $this->assertCodeAvailable((string) $payload['code'], $id);
        }

        return $this->repo()->update($id, $payload);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prepare(array $data, bool $creating, OperationContext $operation): array
    {
        unset($data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);
        foreach ($data as $field => $_value) {
            if (! in_array($field, ['code', 'name', 'level', 'description', 'status'], true)) {
                throw new DomainException(PositionRepository::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
        if ($creating && (! array_key_exists('code', $data) || ! array_key_exists('name', $data))) {
            throw new DomainException(PositionRepository::ERROR_FIELD_REQUIRED, ['field' => 'code']);
        }
        if (array_key_exists('code', $data)) {
            if (! is_string($data['code']) || trim($data['code']) === '') {
                throw new DomainException(self::ERROR_REFERENCE_INVALID, ['field' => 'code']);
            }
            $data['code'] = trim($data['code']);
        }
        if (array_key_exists('name', $data) && (! is_string($data['name']) || trim($data['name']) === '')) {
            throw new DomainException(self::ERROR_REFERENCE_INVALID, ['field' => 'name']);
        }
        if (array_key_exists('level', $data) && $data['level'] !== null) {
            if (is_int($data['level'])) {
                // Preserve the nullable INT domain field without inventing a
                // positive-only business rule.
            } elseif (is_string($data['level']) && preg_match('/\A-?[0-9]+\z/', $data['level']) === 1) {
                $data['level'] = (int) $data['level'];
            } else {
                throw new DomainException(self::ERROR_REFERENCE_INVALID, ['field' => 'level']);
            }
        }
        if (array_key_exists('status', $data)) {
            if (! is_string($data['status'])) {
                throw new DomainException(self::ERROR_STATUS_INVALID, ['status' => '']);
            }
            $data['status'] = strtoupper(trim($data['status']));
            $data['status'] = WorkflowBusinessStatus::normalize(
                'position', $data['status'], ['ACTIVE', 'INACTIVE', 'CLOSED'], self::ERROR_STATUS_INVALID, $this->db,
            );
        } elseif ($creating) {
            $data['status'] = 'ACTIVE';
        }

        return array_merge($data, $creating ? $operation->createdAudit() : $operation->updatedAudit());
    }

    private function assertCodeAvailable(string $code, ?int $excludeId = null): void
    {
        $row = $this->repo()->findByCode($code);
        if ($row !== null && ($excludeId === null || (int) $row['id'] !== $excludeId)) {
            throw new DomainException(self::ERROR_CODE_DUPLICATE, ['field' => 'code']);
        }
    }

    private function repo(): PositionRepository
    {
        return $this->repository ?? new PositionRepository($this->db);
    }

    private function operation(): OperationContext
    {
        return ($this->mutationContext ?? MutationContext::system($this->clock ?? service('clock')))->begin();
    }
}
