<?php

declare(strict_types=1);

namespace App\Modules\Locations\Mutation;

use App\Exceptions\DomainException;
use App\Modules\Access\ReadAccessBridge;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use Throwable;

/**
 * M5.1: guarded Province/Ward commands over the existing v1.2.4 schema.
 * The same connection owns the outer transaction, PBAC facts and row locks.
 */
final class LocationMutationService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly ResourceStateRepository $states;
    private readonly string $actorId;

    public function __construct(private readonly User $actor, ?BaseConnection $connection = null)
    {
        $this->db = $connection ?? db_connect();
        $this->access = new UnifiedAccessService($actor, $this->db);
        $this->states = new ResourceStateRepository($this->db);
        $this->actorId = LocationMutationRules::id((string) $actor->id, 'province');
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(string $type, array $input): array
    {
        $data = LocationMutationRules::payload($type, $input, true);
        $initial = $this->states->initialState($type);
        if ($initial === null) {
            // Locations are Lifecycle-managed in M3. No silent native fallback.
            throw new DomainException('WF_INITIAL_STATE_REQUIRED', ['resource' => $type]);
        }
        if (isset($data['status']) && $data['status'] !== $initial) {
            throw new DomainException('WF_INITIAL_STATE_INVALID', ['resource' => $type]);
        }
        $data['status'] = $initial;
        return $this->transaction(function () use ($type, $data): array {
            $this->permit($type, 'create', null, $data);
            if ($type === 'ward') {
                $this->assertActiveProvince((string) $data['province_id']);
            }
            $this->assertUniqueCode($type, (string) $data['code']);
            $audit = ['created_by' => $this->actorId, 'created_at' => gmdate('Y-m-d H:i:s')];
            $ok = false;
            try {
                $ok = $this->db->table($this->table($type))->insert([...$data, ...$audit]);
            } catch (Throwable $exception) {
                $this->writeFailure($exception);
            }
            if (! $ok) {
                $this->writeFailure();
            }
            // Read access is part of the same unit of work: never commit a
            // successful mutation that will immediately fail its response gate.
            return $this->visible($type, (string) $this->db->insertID());
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function update(string $type, string $id, array $input): array
    {
        $id = LocationMutationRules::id($id, $type);
        return $this->transaction(function () use ($type, $id, $input): array {
            $old = $this->target($type, $id, true);
            $this->assertVisible($type, $old);
            // As in v1.2.4, forbid even no-op status on PATCH; check the
            // actor's edit permission before exposing validation details.
            $this->permit($type, 'edit', $old);
            if (array_key_exists('status', $input)) {
                throw new DomainException('WF_TRANSITION_REQUIRED', ['resource' => $type]);
            }
            $patch = LocationMutationRules::payload($type, $input, false);
            if ($type === 'ward' && isset($patch['province_id'])
                && (string) $old['province_id'] !== (string) $patch['province_id']) {
                $this->assertActiveProvince((string) $patch['province_id']);
                $this->assertWardReparentCompatible($id, (string) $patch['province_id']);
            }
            $this->assertUniqueCode($type, (string) ($patch['code'] ?? $old['code']), $id);
            $this->updatePersisted($type, $id, $patch);
            return $this->visible($type, $id);
        });
    }

    /** @return array<string,mixed> */
    public function setState(string $type, string $id, mixed $status): array
    {
        $id = LocationMutationRules::id($id, $type);
        return $this->transaction(function () use ($type, $id, $status): array {
            $row = $this->target($type, $id, true);
            $this->assertVisible($type, $row);
            $this->permit($type, 'setstate', $row);
            $requested = LocationMutationRules::status($status);
            $definition = $this->states->definitionFor($type);
            $transitions = $this->db->table('lifecycle_transitions')
                ->where('lifecycle_id', $definition['lifecycle_id'])
                ->where('status', 'ACTIVE')->where('action_code', 'setstate')
                ->get()->getResultArray();
            $next = LocationTransitionRules::destination($definition, $row, $transitions, $requested);
            // The resolved action needs PBAC, not just permission to view.
            $this->permit($type, 'setstate', $row, true);
            $oldStatus = (string) $row['status'];
            $ok = false;
            try {
                $ok = $this->db->table($this->table($type))
                    ->where('id', $id)->where('status', $oldStatus)
                    ->update(['status' => $next, ...$this->updatedAudit()]);
            } catch (Throwable $exception) {
                $this->writeFailure($exception);
            }
            if (! $ok) {
                $this->writeFailure();
            }
            if ($this->db->affectedRows() !== 1) {
                throw new DomainException('WF_CONCURRENT_STATE_CHANGE');
            }
            return $this->visible($type, $id);
        });
    }

    private function table(string $type): string
    {
        return match ($type) {
            'province' => 'provinces',
            'ward' => 'wards',
            default => throw new \InvalidArgumentException('Unsupported Location resource'),
        };
    }

    /** @return array<string,mixed> */
    private function target(string $type, string $id, bool $lock = false): array
    {
        $builder = $this->db->table($this->table($type))->where('id', $id);
        $result = $lock
            ? $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE')
            : $builder->get();
        if ($result === false) {
            throw new DomainException('LOCATION_PERSISTENCE_FAILED');
        }
        $row = $result->getRowArray();
        if ($row === null) {
            throw new DomainException(strtoupper($type) . '_NOT_FOUND');
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function visible(string $type, string $id): array
    {
        $row = $this->target($type, $id);
        $this->assertVisible($type, $row);
        return $row;
    }

    /** @param array<string,mixed> $row */
    private function assertVisible(string $type, array $row): void
    {
        if (! (new ReadAccessBridge($this->actor, $this->db))->canView($type, $row)) {
            throw new DomainException(strtoupper($type) . '_NOT_FOUND');
        }
    }

    /** @param array<string,mixed>|null $persisted @param array<string,mixed> $candidate */
    private function permit(string $type, string $action, ?array $persisted, array $candidate = [], bool $transition = false): void
    {
        $decision = $transition
            ? $this->access->decideTransitionAction($type, $action, $persisted ?? [])
            : $this->access->decideMutation($type, $action, $persisted, $candidate);
        if (! $decision['allowed']) {
            throw new DomainException('AUTH_POLICY_DENIED', ['action' => $action]);
        }
    }

    private function assertActiveProvince(string $id): void
    {
        $id = LocationMutationRules::provinceId($id);
        $builder = $this->db->table('provinces')->where('id', $id);
        $result = $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE');
        if ($result === false) {
            throw new DomainException('LOCATION_PERSISTENCE_FAILED');
        }
        $row = $result->getRowArray();
        if ($row === null) {
            throw new DomainException('WARD_PROVINCE_NOT_FOUND', ['field' => 'province_id']);
        }
        if ($row['status'] !== 'ACTIVE') {
            throw new DomainException('WARD_PROVINCE_INACTIVE', ['field' => 'province_id']);
        }
    }

    private function assertWardReparentCompatible(string $wardId, string $newProvinceId): void
    {
        foreach (['organizations', 'contacts'] as $table) {
            // Existing (province, ward) address pairs must remain consistent.
            $referenced = $this->db->table($table)->select('id')
                ->where('ward_id', $wardId)->where('province_id IS NOT NULL', null, false)
                ->where('province_id !=', $newProvinceId)->limit(1)->get()->getRowArray();
            if ($referenced !== null) {
                throw new DomainException('WARD_PROVINCE_REASSIGN_REFERENCED', ['field' => 'province_id']);
            }
        }
    }

    private function assertUniqueCode(string $type, string $code, ?string $except = null): void
    {
        $builder = $this->db->table($this->table($type))->select('id')->where('code', $code);
        if ($except !== null) {
            $builder->where('id !=', $except);
        }
        if ($builder->limit(1)->get()->getRowArray() !== null) {
            throw new DomainException('LOCATION_CODE_DUPLICATE', ['field' => 'code']);
        }
    }

    /** @param array<string,mixed> $patch */
    private function updatePersisted(string $type, string $id, array $patch): void
    {
        $ok = false;
        try {
            $ok = $this->db->table($this->table($type))->where('id', $id)
                ->update([...$patch, ...$this->updatedAudit()]);
        } catch (Throwable $exception) {
            $this->writeFailure($exception);
        }
        if (! $ok) {
            $this->writeFailure();
        }
    }

    /** @return array<string,string> */
    private function updatedAudit(): array
    {
        return ['updated_by' => $this->actorId, 'updated_at' => gmdate('Y-m-d H:i:s')];
    }

    private function writeFailure(?Throwable $exception = null): never
    {
        $error = $this->db->error();
        if ((int) ($error['code'] ?? 0) === 1062 || (int) ($exception?->getCode() ?? 0) === 1062) {
            throw new DomainException('LOCATION_CODE_DUPLICATE', ['field' => 'code'], $exception);
        }
        throw new DomainException('LOCATION_PERSISTENCE_FAILED', [], $exception);
    }

    /** @template T @param callable():T $body @return T */
    private function transaction(callable $body): mixed
    {
        if (! $this->db->transBegin()) {
            throw new DomainException('LOCATION_PERSISTENCE_FAILED');
        }
        try {
            $result = $body();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new DomainException('LOCATION_PERSISTENCE_FAILED');
            }
            return $result;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $exception;
        }
    }
}
