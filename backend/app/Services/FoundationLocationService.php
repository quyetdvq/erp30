<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Repositories\FoundationLocationRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Domain service for the existing Province/Ward foundation tables. */
final class FoundationLocationService
{
    public const ERROR_PROVINCE_NOT_FOUND = 'PROVINCE_NOT_FOUND';
    public const ERROR_WARD_NOT_FOUND = 'WARD_NOT_FOUND';
    public const ERROR_PROVINCE_REFERENCE_INVALID = 'WARD_PROVINCE_NOT_FOUND';
    public const ERROR_PROVINCE_INACTIVE = 'WARD_PROVINCE_INACTIVE';
    public const ERROR_WARD_PROVINCE_MISMATCH = 'WARD_PROVINCE_MISMATCH';
    public const ERROR_WARD_REFERENCED = 'WARD_PROVINCE_REASSIGN_REFERENCED';
    public const ERROR_STATUS_INVALID = 'LOCATION_STATUS_INVALID';
    public const ERROR_CODE_DUPLICATE = 'LOCATION_CODE_DUPLICATE';

    /** @var list<string> */
    private const STATUSES = ['ACTIVE', 'INACTIVE', 'CLOSED'];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?FoundationLocationRepository $repository = null,
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
        private ?ResourceWorkflowStateResolver $workflowStates = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function createProvince(array $data): int
    {
        $payload = $this->locationPayload($data, ['code', 'name', 'status']);
        $this->assertInitialState('province', (string) $payload['status']);
        $this->assertCodeAvailable('provinces', (string) $payload['code']);

        return $this->repo()->insert('provinces', $payload, ['code', 'name', 'status'], $this->operation());
    }

    /** @param array<string, mixed> $data */
    public function updateProvince(int $id, array $data): bool
    {
        $current = $this->province($id);
        $payload = $this->locationPayload($data, []);
        $candidate = array_merge($current, $payload);
        $this->assertStateMutationAllowed('province', $current, $payload);
        $this->assertCodeAvailable('provinces', (string) $candidate['code'], $id);

        return $this->repo()->update('provinces', $id, $payload, $this->operation());
    }

    /** @param array<string, mixed> $data */
    public function createWard(array $data): int
    {
        $payload = $this->locationPayload($data, ['province_id', 'code', 'name', 'status']);
        $this->assertInitialState('ward', (string) $payload['status']);
        $payload['province_id'] = PositiveId::normalize($payload['province_id'], 'province_id', self::ERROR_PROVINCE_REFERENCE_INVALID);
        $this->assertActiveProvince($payload['province_id']);
        $this->assertCodeAvailable('wards', (string) $payload['code']);

        return $this->repo()->insert('wards', $payload, ['province_id', 'code', 'name', 'status'], $this->operation());
    }

    /** @param array<string, mixed> $data */
    public function updateWard(int $id, array $data): bool
    {
        $current = $this->ward($id);
        $payload = $this->locationPayload($data, []);
        $candidate = array_merge($current, $payload);
        $this->assertStateMutationAllowed('ward', $current, $payload);
        $candidate['province_id'] = PositiveId::normalize($candidate['province_id'], 'province_id', self::ERROR_PROVINCE_REFERENCE_INVALID);
        // Historical Ward references remain editable when their Province has
        // since been inactivated. Only a *new* Province selection must be ACTIVE.
        if ((int) $current['province_id'] !== $candidate['province_id']) {
            return $this->reparentWard($id, (int) $current['province_id'], $candidate['province_id'], $payload);
        }
        $this->assertCodeAvailable('wards', (string) $candidate['code'], $id);

        return $this->repo()->update('wards', $id, $payload, $this->operation());
    }

    public function setProvinceState(int $id, string $status): bool
    {
        return $this->updateProvince($id, ['status' => $status]);
    }

    public function setWardState(int $id, string $status): bool
    {
        return $this->updateWard($id, ['status' => $status]);
    }

    /** @return array<string, mixed> */
    public function province(int $id): array
    {
        $row = $this->repo()->find('provinces', PositiveId::normalize($id, 'province_id', self::ERROR_PROVINCE_NOT_FOUND));
        if ($row === null) {
            throw new DomainException(self::ERROR_PROVINCE_NOT_FOUND, ['province_id' => $id]);
        }

        return $row;
    }

    /** @return array<string, mixed> */
    public function ward(int $id): array
    {
        $row = $this->repo()->find('wards', PositiveId::normalize($id, 'ward_id', self::ERROR_WARD_NOT_FOUND));
        if ($row === null) {
            throw new DomainException(self::ERROR_WARD_NOT_FOUND, ['ward_id' => $id]);
        }

        return $row;
    }

    private function assertActiveProvince(int $id): void
    {
        if ((string) $this->province($id)['status'] !== 'ACTIVE') {
            throw new DomainException(self::ERROR_PROVINCE_INACTIVE, ['province_id' => $id]);
        }
    }

    /**
     * Reparent is rare. Serialize the Ward row and check referenced address
     * pairs in the same transaction, rather than adding cost to ordinary edit.
     * FK locks also serialize actual child inserts against the Ward row write.
     *
     * @param array<string,mixed> $payload
     */
    private function reparentWard(int $id, int $expectedProvinceId, int $newProvinceId, array $payload): bool
    {
        if (! $this->db instanceof \CodeIgniter\Database\BaseConnection || ! $this->db->transBegin()) {
            throw new PersistenceException(FoundationLocationRepository::ERROR_PERSISTENCE_FAILED, 'wardReparentBegin');
        }
        try {
            $locked = $this->repo()->lockWardForUpdate($id);
            if ($locked === null || (int) $locked['province_id'] !== $expectedProvinceId) {
                throw new DomainException('CONCURRENT_MODIFICATION', ['ward_id' => $id]);
            }
            $this->assertActiveProvince($newProvinceId);
            // Keep the canonical duplicate-code domain error when editing
            // code and parent in the same request, not only on ordinary edits.
            $this->assertCodeAvailable('wards', (string) ($payload['code'] ?? $locked['code']), $id);
            if ($this->repo()->wardHasConflictingAddress($id, $newProvinceId)) {
                throw new DomainException(self::ERROR_WARD_REFERENCED, [
                    'ward_id' => $id, 'province_id' => $newProvinceId,
                ]);
            }
            $updated = $this->repo()->update('wards', $id, $payload, $this->operation());
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new PersistenceException(FoundationLocationRepository::ERROR_PERSISTENCE_FAILED, 'wardReparentCommit');
            }
            return $updated;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $required
     * @return array<string, mixed>
     */
    private function locationPayload(array $data, array $required): array
    {
        unset($data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);
        if (array_key_exists('status', $data)) {
            if (! is_string($data['status'])) {
                throw new DomainException(self::ERROR_STATUS_INVALID, ['field' => 'status']);
            }
            $data['status'] = strtoupper(trim($data['status']));
            if (! in_array($data['status'], self::STATUSES, true)) {
                throw new DomainException(self::ERROR_STATUS_INVALID, ['field' => 'status']);
            }
        }
        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                throw new DomainException(FoundationLocationRepository::ERROR_FIELD_REQUIRED, ['field' => $field]);
            }
        }

        return $data;
    }

    private function assertInitialState(string $resource, string $status): void
    {
        $initial = $this->states()->initialCode($resource);
        if ($initial !== null && $initial !== $status) {
            throw new DomainException(ResourceWorkflowStateResolver::ERROR_INITIAL_STATE_INVALID, [
                'resource' => $resource,
                'initial_status' => $initial,
            ]);
        }
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $changes */
    private function assertStateMutationAllowed(string $resource, array $current, array $changes): void
    {
        if (array_key_exists('status', $changes)
            && $changes['status'] !== $current['status']
            && $this->states()->lifecycleId($resource) !== null) {
            throw new DomainException(Api010FoundationOrganizationContactService::ERROR_TRANSITION_REQUIRED, ['resource' => $resource]);
        }
    }

    private function states(): ResourceWorkflowStateResolver
    {
        return $this->workflowStates ??= new ResourceWorkflowStateResolver($this->db);
    }

    private function assertCodeAvailable(string $table, string $code, ?int $ignoreId = null): void
    {
        $existing = $this->repo()->findByCode($table, $code);
        if ($existing !== null && ($ignoreId === null || (int) $existing['id'] !== $ignoreId)) {
            throw new DomainException(self::ERROR_CODE_DUPLICATE, ['field' => 'code']);
        }
    }

    private function operation(): \App\Infrastructure\Mutation\OperationContext
    {
        $context = $this->mutationContext ?? MutationContext::system($this->clock ?? service('clock'));

        return $context->begin();
    }

    private function repo(): FoundationLocationRepository
    {
        return $this->repository ?? new FoundationLocationRepository($this->db);
    }
}
