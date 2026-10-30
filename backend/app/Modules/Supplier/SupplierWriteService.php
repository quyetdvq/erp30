<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use App\Exceptions\DomainException;
use App\Modules\Access\Query\EffectiveRowActionHints;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\UnifiedAccessService;
use App\Modules\History\HistoryPolicy;
use App\Modules\History\HistoryRecorder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

/**
 * SUP-T04 transaction owner. HTTP routes, bank access and Shared Contacts
 * are intentionally outside this service; no generic bank columns are read.
 * All writes use the same Supplier advisory lock, InnoDB transaction, and
 * optimistic row_version CAS. Every mutation is re-authorized on DB facts.
 */
final class SupplierWriteService
{
    private readonly BaseConnection $db;
    private readonly ResourceStateRepository $states;
    private readonly DateTimeImmutable $instant;
    private readonly string $actorId;

    private const PROFILE_COLUMNS = [
        'id','group_organization_id','supplier_code','name','supplier_type',
        'supplier_group_code','phone','email','tax_code','address','province_id',
        'ward_id','payment_term_code','all_companies','status','row_version',
        'created_by','updated_by','created_at','updated_at',
    ];

    public function __construct(private readonly User $actor, ?BaseConnection $connection = null)
    {
        $this->db = $connection ?? db_connect();
        $this->states = new ResourceStateRepository($this->db);
        $this->instant = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->actorId = SupplierSql::positiveId((string)$actor->id);
    }

    /** @param array<string,mixed> $body @return array<string,mixed> */
    public function create(array $body): array
    {
        $data = SupplierWritePayload::create($body);
        return $this->transaction(function (UnifiedAccessService $access) use ($data): array {
            $group = $access->trustedGroupRootId();
            $this->permit($access, 'create');
            $this->assertAddress($data['profile']);
            $this->assertCompanies($group, $data['company_ids']);
            $state = $this->states->initialState('supplier');
            if (!is_string($state) || preg_match('/\A[A-Z][A-Z0-9_:-]{0,19}\z/D', $state) !== 1) {
                throw new DomainException('SUPPLIER_INITIAL_STATE_INVALID');
            }
            $at = gmdate('Y-m-d H:i:s');
            $supplierId = (new SupplierNumberIssuer($this->db))->issue(
                $data['series'], $data['manual'],
                function (string $code, string $issuedGroup) use ($group, $data, $state, $at): string {
                    if ($issuedGroup !== $group) {
                        throw new DomainException('SUPPLIER_GROUP_ROOT_MISMATCH');
                    }
                    try {
                        $saved = $this->db->table('suppliers')->insert([
                            ...$data['profile'],
                            'group_organization_id'=>$group,
                            'supplier_code'=>SupplierNumberingPolicy::manualCode($code),
                            'all_companies'=>$data['all_companies'],
                            'status'=>$state,
                            'row_version'=>1,
                            'created_by'=>$this->actorId,
                            'created_at'=>$at,
                        ]);
                    } catch (Throwable $e) { $this->persistenceFailure($e); }
                    if (!$saved) $this->persistenceFailure();
                    $id = SupplierSql::positiveId((string)$this->db->insertID());
                    $this->replaceUsageRows($id, $data['all_companies'], $data['company_ids']);
                    return $id;
                }
            );
            $row = $this->safeRow($supplierId, $group);
            $row['company_ids'] = $data['company_ids'];
            (new HistoryRecorder($this->db))->created('supplier', $supplierId, $this->actorId, $state);
            return $this->visible($access, $row);
        });
    }

    /** @param array<string,mixed> $body @return array<string,mixed> */
    public function update(string $id, array $body): array
    {
        $id = SupplierSql::positiveId($id);
        $data = SupplierWritePayload::patch($body);
        return $this->transaction(function (UnifiedAccessService $access) use ($id, $data): array {
            $group = $access->trustedGroupRootId();
            $old = $this->safeRow($id, $group, true);
            $this->assertVisible($access, $old);
            $this->permit($access, 'edit', $old);
            $nextVersion = $this->nextVersion($old, $data['expected_version']);
            $merged = array_replace($old, $data['profile']);
            try { SupplierRules::assertRequiredProfile($merged); }
            catch (InvalidArgumentException $e) { throw new DomainException($e->getMessage(), [], $e); }
            $this->assertAddress($merged);
            $oldIds = $this->companyIds($id, $old);
            $this->cas($id, $group, $data['expected_version'], [
                ...$data['profile'], 'row_version'=>$nextVersion,
                'updated_by'=>$this->actorId, 'updated_at'=>gmdate('Y-m-d H:i:s'),
            ]);
            $after = $this->safeRow($id, $group);
            $after['company_ids'] = $oldIds;
            (new HistoryRecorder($this->db))->updated('supplier', $id, $this->actorId,
                [...$old, 'company_ids'=>$oldIds], $after);
            return $this->visible($access, $after);
        });
    }

    /** Full replacement, never PATCH merge. @param array<string,mixed> $body @return array<string,mixed> */
    public function replaceCompanyUsage(string $id, array $body): array
    {
        $id = SupplierSql::positiveId($id);
        $data = SupplierWritePayload::replaceUsage($body);
        return $this->transaction(function (UnifiedAccessService $access) use ($id, $data): array {
            $group = $access->trustedGroupRootId();
            $old = $this->safeRow($id, $group, true);
            $this->assertVisible($access, $old);
            $this->permit($access, 'edit', $old);
            $nextVersion = $this->nextVersion($old, $data['expected_version']);
            $oldIds = $this->companyIds($id, $old);
            $this->assertCompanies($group, $data['company_ids']);
            $this->cas($id, $group, $data['expected_version'], [
                'all_companies'=>$data['all_companies'], 'row_version'=>$nextVersion,
                'updated_by'=>$this->actorId, 'updated_at'=>gmdate('Y-m-d H:i:s'),
            ]);
            $this->replaceUsageRows($id, $data['all_companies'], $data['company_ids']);
            $after = $this->safeRow($id, $group);
            $after['company_ids'] = $data['company_ids'];
            (new HistoryRecorder($this->db))->updated('supplier', $id, $this->actorId,
                [...$old, 'company_ids'=>$oldIds], $after);
            return $this->visible($access, $after);
        });
    }

    /** Canonical non-branching Workflow setstate only; no direct status PATCH. */
    /** @param array<string,mixed> $body @return array<string,mixed> */
    public function setState(string $id, array $body): array
    {
        $id = SupplierSql::positiveId($id);
        $data = SupplierWritePayload::setState($body);
        return $this->transaction(function (UnifiedAccessService $access) use ($id, $data): array {
            $group = $access->trustedGroupRootId();
            $old = $this->safeRow($id, $group, true);
            $this->assertVisible($access, $old);
            $this->permit($access, 'setstate', $old);
            $nextVersion = $this->nextVersion($old, $data['expected_version']);
            if ($data['status'] === (string)$old['status']) {
                throw new DomainException('SUPPLIER_STATE_TRANSITION_INVALID');
            }
            try { $target = $this->states->transitionTarget('supplier', $old, 'setstate'); }
            catch (UnexpectedValueException $e) {
                if ($e->getMessage() !== 'Transition not allowed or ambiguous for current State.') throw $e;
                throw new DomainException('SUPPLIER_STATE_TRANSITION_INVALID', [], $e);
            }
            if ($target !== $data['status']) {
                throw new DomainException('SUPPLIER_STATE_TRANSITION_INVALID');
            }
            // Recheck Company facts at activation, including changed Organization status.
            if (HistoryPolicy::isOperational('supplier', $target)) {
                $this->assertCompanies($group, $this->companyIds($id, $old));
            }
            $this->permit($access, 'setstate', $old, true);
            $this->cas($id, $group, $data['expected_version'], [
                'status'=>$target, 'row_version'=>$nextVersion,
                'updated_by'=>$this->actorId, 'updated_at'=>gmdate('Y-m-d H:i:s'),
            ], (string)$old['status']);
            $after = $this->safeRow($id, $group);
            $after['company_ids'] = $this->companyIds($id, $after);
            (new HistoryRecorder($this->db))->transitioned('supplier', $id, $this->actorId,
                (string)$old['status'], $target, 'setstate', $after);
            // Snapshot assigned recipients transactionally, after successful
            // CAS+History and before COMMIT. A producer failure rolls back all.
            // This is NOT generic Workflow execution; Supplier retains CAS.
            \App\Modules\Notifications\NotificationOutboxProducer::enqueueWorkflowTransition(
                $this->db, 'supplier', $id, (string)$old['status'], $target, 'setstate',
            );
            return $this->visible($access, $after);
        });
    }

    /** @template T @param callable(UnifiedAccessService):T $task @return T */
    private function transaction(callable $task): mixed
    {
        if ($this->db->transDepth !== 0) throw new DomainException('SUPPLIER_NESTED_TRANSACTION_UNSUPPORTED');
        $name = 'dinkerp:' . (defined('ENVIRONMENT') ? ENVIRONMENT : 'development')
            . ':' . $this->db->getDatabase() . ':supplier-master';
        if (strlen($name) > 64) $name = substr($name, 0, 30) . ':' . substr(hash('sha256', $name), 0, 32);
        $lock = $this->db->query('SELECT GET_LOCK(' . $this->db->escape($name) . ',10) AS acquired');
        if ($lock === false || (int)($lock->getRowArray()['acquired'] ?? 0) !== 1) {
            throw new DomainException('SUPPLIER_MUTATION_LOCK_FAILED');
        }
        try {
            if (!$this->db->transBegin()) throw new DomainException('SUPPLIER_PERSISTENCE_FAILED');
            try {
                // Snapshot PBAC inside the write transaction, never from client hints.
                $access = new UnifiedAccessService($this->actor, $this->db, $this->instant);
                $value = $task($access);
                if (!$this->db->transStatus() || !$this->db->transCommit()) {
                    throw new DomainException('SUPPLIER_PERSISTENCE_FAILED');
                }
                return $value;
            } catch (Throwable $e) {
                $this->db->transRollback();
                $this->db->resetTransStatus();
                throw $e;
            }
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(' . $this->db->escape($name) . ')');
        }
    }

    /** @return array<string,mixed> */
    private function safeRow(string $id, string $group, bool $lock = false): array
    {
        $builder = $this->db->table('suppliers')->select(implode(', ', self::PROFILE_COLUMNS))
            ->where('id', $id)->where('group_organization_id', $group);
        $query = $lock ? $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE') : $builder->get();
        if ($query === false) throw new DomainException('SUPPLIER_PERSISTENCE_FAILED');
        $row = $query->getRowArray();
        if ($row === null) throw new PageNotFoundException('Supplier not available.');
        return $row;
    }

    private function assertVisible(UnifiedAccessService $access, array $row): void
    {
        if (!$access->canView('supplier', $row)) {
            throw new PageNotFoundException('Supplier not available.');
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function visible(UnifiedAccessService $access, array $row): array
    {
        $this->assertVisible($access, $row);
        $row['allowed_actions'] = EffectiveRowActionHints::forViewedRow($access, 'supplier', $row);
        return $row;
    }

    private function permit(UnifiedAccessService $access, string $action, ?array $row = null, bool $transition = false): void
    {
        $decision = $transition
            ? $access->decideTransitionAction('supplier', $action, $row ?? [])
            : $access->decideMutation('supplier', $action, $row);
        if (!($decision['allowed'] ?? false)) {
            throw new DomainException('AUTH_POLICY_DENIED', ['action'=>$action]);
        }
    }

    /** @param array<string,mixed> $row */
    private function nextVersion(array $row, int $expected): int
    {
        $stored = $row['row_version'] ?? null;
        if ((!is_int($stored) && !is_string($stored))
            || preg_match('/\A[1-9][0-9]*\z/D', (string)$stored) !== 1
            || strlen((string)$stored) > 10
            || (strlen((string)$stored) === 10 && strcmp((string)$stored, '4294967295') > 0)) {
            throw new DomainException('SUPPLIER_ROW_VERSION_CORRUPT');
        }
        if ((int)$stored !== $expected) {
            throw new DomainException('SUPPLIER_VERSION_CONFLICT');
        }
        if ((int)$stored === 4294967295) {
            throw new DomainException('SUPPLIER_VERSION_EXHAUSTED');
        }
        return (int)$stored + 1;
    }

    /** @param array<string,mixed> $changes */
    private function cas(string $id, string $group, int $expected, array $changes, ?string $state = null): void
    {
        $builder = $this->db->table('suppliers')->where('id', $id)
            ->where('group_organization_id', $group)->where('row_version', $expected);
        if ($state !== null) $builder->where('status', $state);
        try { $ok = $builder->update($changes); }
        catch (Throwable $e) { $this->persistenceFailure($e); }
        if (!$ok) $this->persistenceFailure();
        if ($this->db->affectedRows() !== 1) {
            throw new DomainException('SUPPLIER_VERSION_CONFLICT');
        }
    }

    /** @param array<string,mixed> $profile */
    private function assertAddress(array $profile): void
    {
        $province = $this->lockedRow('provinces', (string)$profile['province_id'], 'id, status');
        $ward = $this->lockedRow('wards', (string)$profile['ward_id'], 'id, province_id, status');
        if ($province === null || $ward === null || (string)$ward['province_id'] !== (string)$profile['province_id']) {
            throw new DomainException('SUPPLIER_WARD_PROVINCE_MISMATCH');
        }
    }

    /** @param list<string> $ids */
    private function assertCompanies(string $group, array $ids): void
    {
        if ($ids === []) return;
        // One bounded locking read for up to 512 Company IDs, not N+1 queries.
        $sql = $this->db->table('organizations')
            ->select('id, type, status, parent_id')->whereIn('id', $ids)
            ->orderBy('id', 'ASC')->getCompiledSelect();
        $query = $this->db->query($sql . ' FOR UPDATE');
        if ($query === false) throw new DomainException('SUPPLIER_REFERENCE_LOOKUP_FAILED');
        $rows = $query->getResultArray();
        if (count($rows) !== count($ids)) {
            throw new DomainException('SUPPLIER_COMPANY_SCOPE_INVALID');
        }
        $expected = array_fill_keys($ids, true);
        foreach ($rows as $row) {
            $id = (string)($row['id'] ?? '');
            if (!isset($expected[$id]) || ($row['type'] ?? null) !== 'COMPANY'
                || ($row['status'] ?? null) !== 'ACTIVE'
                || (string)($row['parent_id'] ?? '') !== $group) {
                throw new DomainException('SUPPLIER_COMPANY_SCOPE_INVALID');
            }
            unset($expected[$id]);
        }
        if ($expected !== []) throw new DomainException('SUPPLIER_COMPANY_SCOPE_INVALID');
    }

    /** @return array<string,mixed>|null */
    private function lockedRow(string $table, string $id, string $columns): ?array
    {
        $sql = $this->db->table($table)->select($columns)->where('id', $id)->getCompiledSelect();
        $query = $this->db->query($sql . ' FOR UPDATE');
        if ($query === false) throw new DomainException('SUPPLIER_REFERENCE_LOOKUP_FAILED');
        return $query->getRowArray();
    }

    /** @param array<string,mixed> $row @return list<string> */
    private function companyIds(string $id, array $row): array
    {
        $query = $this->db->table('supplier_company_usages')->select('company_organization_id')
            ->where('supplier_id', $id)->orderBy('company_organization_id', 'ASC')
            ->limit(SupplierRules::MAX_COMPANIES + 1)->get();
        if ($query === false) throw new DomainException('SUPPLIER_USAGE_READ_FAILED');
        $rows = $query->getResultArray();
        $all = (string)($row['all_companies'] ?? '');
        if (count($rows) > SupplierRules::MAX_COMPANIES
            || !in_array($all, ['0','1'], true)
            || ($all === '1' && $rows !== []) || ($all === '0' && $rows === [])) {
            throw new DomainException('SUPPLIER_USAGE_MODE_CORRUPT');
        }
        return array_values(array_map('strval', array_column($rows, 'company_organization_id')));
    }

    /** @param list<string> $ids */
    private function replaceUsageRows(string $id, int $all, array $ids): void
    {
        try {
            $deleted = $this->db->table('supplier_company_usages')->where('supplier_id', $id)->delete();
            if (!$deleted) $this->persistenceFailure();
            if ($all === 0) foreach ($ids as $companyId) {
                $ok = $this->db->table('supplier_company_usages')->insert([
                    'supplier_id'=>$id, 'company_organization_id'=>$companyId,
                ]);
                if (!$ok) $this->persistenceFailure();
            }
        } catch (Throwable $e) {
            if ($e instanceof DomainException) throw $e;
            $this->persistenceFailure($e);
        }
    }

    private function persistenceFailure(?Throwable $e = null): never
    {
        $error = $this->db->error();
        if ((int)($error['code'] ?? 0) === 1062 || (int)($e?->getCode() ?? 0) === 1062) {
            $detail = (string)($error['message'] ?? '') . ' ' . ($e?->getMessage() ?? '');
            if (str_contains($detail, 'uq_supplier_group_code')) {
                throw new DomainException('SUPPLIER_CODE_DUPLICATE', ['field'=>'supplier_code'], $e);
            }
            throw new DomainException('SUPPLIER_UNIQUE_CONFLICT', [], $e);
        }
        throw new DomainException('SUPPLIER_PERSISTENCE_FAILED', [], $e);
    }
}
