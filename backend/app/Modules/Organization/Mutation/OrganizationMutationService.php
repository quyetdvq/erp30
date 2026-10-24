<?php

declare(strict_types=1);

namespace App\Modules\Organization\Mutation;

use App\Exceptions\DomainException;
use App\Modules\Access\ReadAccessBridge;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use Throwable;

/** M5.2: Organization mutations with serialized hierarchy validation and PBAC. */
final class OrganizationMutationService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly ResourceStateRepository $states;
    private readonly string $actorId;

    public function __construct(private readonly User $actor, ?BaseConnection $connection=null)
    {
        $this->db = $connection ?? db_connect();
        $this->access = new UnifiedAccessService($actor, $this->db);
        $this->states = new ResourceStateRepository($this->db);
        $this->actorId = OrganizationMutationRules::id((string)$actor->id, 'actor_user_id');
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(array $input): array
    {
        $payload = OrganizationMutationRules::payload($input, true);
        $initial = $this->states->initialState('organization');
        if ($initial === null) throw new DomainException('WF_INITIAL_STATE_REQUIRED', ['resource'=>'organization']);
        if (isset($payload['status']) && $payload['status'] !== $initial) {
            throw new DomainException('WF_INITIAL_STATE_INVALID', ['resource'=>'organization']);
        }
        $payload['status'] = $initial;
        $payload['parent_id'] ??= null;
        OrganizationMutationRules::assertDates($payload);
        return $this->treeTransaction(function () use ($payload): array {
            $rows = $this->snapshot();
            OrganizationTreeRules::assertCandidate($rows, null, $payload['type'], $payload['parent_id']);
            // GROUP bootstrap requires pre-provisioned PBAC root. No automatic
            // global authorization for a brand-new GROUP root is granted.
            if ($payload['parent_id'] === null) {
                throw new DomainException('AUTH_POLICY_DENIED', ['action'=>'create']);
            }
            $this->assertLocation($payload);
            $this->permitCreate($payload['parent_id'], $payload['status']);
            $this->assertUniqueCode($payload['code']);
            $this->writeInsert([...$payload, ...$this->createAudit()]);
            return $this->visible((string)$this->db->insertID());
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function update(string $id, array $input): array
    {
        $id = OrganizationMutationRules::id($id);
        return $this->treeTransaction(function () use ($id, $input): array {
            $old = $this->target($id, true);
            $this->assertVisible($old);
            $this->permit('edit', $old);
            if (array_key_exists('status', $input)) {
                throw new DomainException('WF_TRANSITION_REQUIRED', ['resource'=>'organization']);
            }
            $patch = OrganizationMutationRules::payload($input, false);
            $candidate = array_replace($old, $patch);
            OrganizationMutationRules::assertDates($candidate);
            $parentId = $candidate['parent_id'] === null ? null : (string)$candidate['parent_id'];
            $rows = $this->snapshot();
            OrganizationTreeRules::assertCandidate($rows, $id, (string)$candidate['type'], $parentId);
            if ($parentId !== null && (string)($old['parent_id'] ?? '') !== $parentId) {
                // In addition to edit rights on the old record, moving to a new
                // scope requires CREATE rights at the trusted destination parent.
                $this->permitCreate($parentId, $this->states->initialState('organization') ?? '');
            }
            $this->assertLocation($candidate);
            $this->assertUniqueCode((string)$candidate['code'], $id);
            $this->writeUpdate($id, [...$patch, ...$this->updateAudit()]);
            return $this->visible($id);
        });
    }

    /** @return array<string,mixed> */
    public function setState(string $id, mixed $status): array
    {
        $id = OrganizationMutationRules::id($id);
        return $this->treeTransaction(function () use ($id, $status): array {
            $row = $this->target($id, true);
            $this->assertVisible($row);
            $this->permit('setstate', $row);
            $requested = OrganizationMutationRules::status($status);
            $definition = $this->states->definitionFor('organization');
            $transitions = $this->db->table('lifecycle_transitions')
                ->where('lifecycle_id', $definition['lifecycle_id'])
                ->where('status', 'ACTIVE')->where('action_code', 'setstate')
                ->get()->getResultArray();
            $next = OrganizationTransitionRules::destination($definition, $row, $transitions, $requested);
            $decision = $this->access->decideTransitionAction('organization', 'setstate', $row);
            if (!$decision['allowed']) throw new DomainException('AUTH_POLICY_DENIED', ['action'=>'setstate']);
            $oldStatus = (string)$row['status'];
            try {
                $ok = $this->db->table('organizations')->where('id', $id)->where('status', $oldStatus)
                    ->update(['status'=>$next, ...$this->updateAudit()]);
            } catch (Throwable $exception) {
                $this->writeFailure($exception);
            }
            if (!$ok) $this->writeFailure();
            if ($this->db->affectedRows() !== 1) throw new DomainException('WF_CONCURRENT_STATE_CHANGE');
            return $this->visible($id);
        });
    }

    /** @return list<array<string,mixed>> */
    private function snapshot(): array
    {
        $query = $this->db->query('SELECT id,parent_id,type FROM '.$this->db->prefixTable('organizations').' ORDER BY id FOR UPDATE');
        if ($query === false) throw new DomainException('ORG_MUTATION_FAILED');
        return $query->getResultArray();
    }

    /** @return array<string,mixed> */
    private function target(string $id, bool $lock=false): array
    {
        $builder = $this->db->table('organizations')->where('id', $id);
        $result = $lock ? $this->db->query($builder->getCompiledSelect().' FOR UPDATE') : $builder->get();
        if ($result === false) throw new DomainException('ORG_PERSISTENCE_FAILED');
        $row = $result->getRowArray();
        if ($row === null) throw new DomainException('ORG_NOT_FOUND');
        return $row;
    }

    /** @return array<string,mixed> */
    private function visible(string $id): array
    {
        $row = $this->target($id);
        $this->assertVisible($row);
        return $row;
    }

    /** @param array<string,mixed> $row */
    private function assertVisible(array $row): void
    {
        if (!(new ReadAccessBridge($this->actor, $this->db))->canView('organization', $row)) {
            throw new DomainException('ORG_NOT_FOUND');
        }
    }

    /** @param array<string,mixed> $row */
    private function permit(string $action, array $row): void
    {
        $decision = $this->access->decideMutation('organization', $action, $row);
        if (!$decision['allowed']) throw new DomainException('AUTH_POLICY_DENIED', ['action'=>$action]);
    }

    private function permitCreate(string $parentId, string $initial): void
    {
        // parentId was resolved from a locked organization snapshot; never a
        // free-form client-supplied scope or policy override.
        $decision = $this->access->decideMutation('organization', 'create', null,
            ['organization_id'=>$parentId, 'status'=>$initial]);
        if (!$decision['allowed']) throw new DomainException('AUTH_POLICY_DENIED', ['action'=>'create']);
    }

    /** @param array<string,mixed> $data */
    private function assertLocation(array $data): void
    {
        $province = $data['province_id'] ?? null;
        $ward = $data['ward_id'] ?? null;
        if ($province !== null && $this->db->table('provinces')->select('id')->where('id', $province)->get()->getRowArray() === null) {
            throw new DomainException('ORG_REFERENCE_INVALID', ['field'=>'province_id']);
        }
        if ($ward !== null) {
            $selected = $this->db->table('wards')->select('id,province_id')->where('id', $ward)->get()->getRowArray();
            if ($selected === null) throw new DomainException('ORG_REFERENCE_INVALID', ['field'=>'ward_id']);
            if ($province !== null && (string)$selected['province_id'] !== (string)$province) {
                throw new DomainException('ORG_WARD_PROVINCE_MISMATCH', ['field'=>'ward_id']);
            }
        }
    }

    private function assertUniqueCode(string $code, ?string $except=null): void
    {
        $builder = $this->db->table('organizations')->select('id')->where('code', $code);
        if ($except !== null) $builder->where('id !=', $except);
        if ($builder->limit(1)->get()->getRowArray() !== null) {
            throw new DomainException('ORG_CODE_DUPLICATE', ['field'=>'code']);
        }
    }

    /** @param array<string,mixed> $data */
    private function writeInsert(array $data): void
    {
        try { $ok = $this->db->table('organizations')->insert($data); }
        catch (Throwable $exception) { $this->writeFailure($exception); }
        if (!$ok) $this->writeFailure();
    }

    /** @param array<string,mixed> $data */
    private function writeUpdate(string $id, array $data): void
    {
        try { $ok = $this->db->table('organizations')->where('id',$id)->update($data); }
        catch (Throwable $exception) { $this->writeFailure($exception); }
        if (!$ok) $this->writeFailure();
    }

    /** @return array<string,string> */
    private function createAudit(): array
    {
        return ['created_by'=>$this->actorId, 'created_at'=>gmdate('Y-m-d H:i:s')];
    }

    /** @return array<string,string> */
    private function updateAudit(): array
    {
        return ['updated_by'=>$this->actorId, 'updated_at'=>gmdate('Y-m-d H:i:s')];
    }

    private function writeFailure(?Throwable $previous=null): never
    {
        $code = (int)($this->db->error()['code'] ?? 0);
        if ($code === 1062 || (int)($previous?->getCode() ?? 0) === 1062) {
            throw new DomainException('ORG_CODE_DUPLICATE', ['field'=>'code'], $previous);
        }
        throw new DomainException('ORG_PERSISTENCE_FAILED', [], $previous);
    }

    /** @template T @param callable():T $callback @return T */
    private function treeTransaction(callable $callback): mixed
    {
        // MariaDB named lock serializes competing root/reparent operations.
        // It is connection-owned, independent of a transaction rollback.
        $result = $this->db->query("SELECT GET_LOCK('dinkerp.m52.organization.tree', 10) AS acquired");
        if ($result === false || (int)($result->getRowArray()['acquired'] ?? 0) !== 1) {
            throw new DomainException('ORG_MUTATION_FAILED');
        }
        try {
            if (!$this->db->transBegin()) throw new DomainException('ORG_MUTATION_FAILED');
            try {
                $value = $callback();
                if (!$this->db->transStatus() || !$this->db->transCommit()) {
                    throw new DomainException('ORG_MUTATION_FAILED');
                }
                return $value;
            } catch (Throwable $exception) {
                $this->db->transRollback();
                $this->db->resetTransStatus();
                throw $exception;
            }
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('dinkerp.m52.organization.tree')");
        }
    }
}
