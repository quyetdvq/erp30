<?php

declare(strict_types=1);

namespace App\Modules\Contact;

use App\Exceptions\DomainException;
use App\Modules\Access\Query\AuthorizationSqlScopeCompiler;
use App\Modules\Access\Query\EffectiveRowActionHints;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\UnifiedAccessService;
use App\Shared\Read\UtcBusinessDate;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use UnexpectedValueException;
use Throwable;

/**
 * API-010 Contacts: owner-boundary read+write without a generic `contact` PBAC
 * resource. All command checks and response visibility use one SQL connection.
 */
final class ContactService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly ResourceStateRepository $states;
    private readonly ContactScope $scope;
    private readonly string $actorId;
    private readonly DateTimeImmutable $instant;
    private readonly User $actor;

    public function __construct(User $actor, ?BaseConnection $connection = null)
    {
        $this->actor = $actor;
        $this->db = $connection ?? db_connect();
        $this->instant = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->access = new UnifiedAccessService($actor, $this->db, $this->instant);
        $this->states = new ResourceStateRepository($this->db);
        $this->scope = new ContactScope($this->db);
        $this->actorId = ContactRules::id((string) $actor->id, 'actor_user_id');
    }

    /** @param array<string,mixed>|null $query @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int} */
    public function list(?array $query = null): array
    {
        $params = ContactSql::normalize($query ?? []);
        return $this->readSnapshot(function(UnifiedAccessService $access) use ($params): array {
            [$organization, $employee] = $this->compileContactScopes($access);
            $date = UtcBusinessDate::at($this->instant);
            $readScope = new ContactScope($this->db, $date);
            $plan = ContactSql::collection($params, $organization, $employee,
                $date, (string)$this->db->DBPrefix,
                ...$this->customerScopes($access));
            $countQuery = $this->db->query($plan['count_sql'], $plan['count_binds']);
            if ($countQuery === false) throw new UnexpectedValueException('Contact scoped COUNT failed.');
            $total = self::validCount($countQuery->getRowArray()['total'] ?? null);
            if ($total === 0) return ['data'=>[], 'page'=>$params['page'], 'page_size'=>$params['page_size'], 'total'=>0];
            $pageQuery = $this->db->query($plan['rows_sql'], $plan['rows_binds']);
            if ($pageQuery === false) throw new UnexpectedValueException('Contact scoped page query failed.');
            $rows = array_values($pageQuery->getResultArray());
            if (count($rows) > $params['page_size']) throw new UnexpectedValueException('Contact page exceeded requested limit.');
            foreach ($rows as &$row) {
                // Defense in depth. A mismatch must fail the whole response,
                // NEVER silently post-filter and corrupt an authorized total.
                $scope = $readScope->resolve($row);
                if (!$access->canView($scope['resource'], $scope['target'])
                    || !$this->customerParentView($row,$access)) {
                    throw new UnexpectedValueException('AUTH_SCOPE_PBAC_MISMATCH: Contact list.');
                }
                $row['allowed_actions'] = EffectiveRowActionHints::forViewedRow($access, $scope['resource'], $scope['target']);
            }
            unset($row);
            return ['data'=>$rows, 'page'=>$params['page'], 'page_size'=>$params['page_size'], 'total'=>$total];
        });
    }

    /** @return array<string,mixed> */
    public function show(string $id): array
    {
        $id = ContactRules::id($id);
        return $this->readSnapshot(function(UnifiedAccessService $access) use ($id): array {
            [$organization, $employee] = $this->compileContactScopes($access);
            $date = UtcBusinessDate::at($this->instant);
            $readScope = new ContactScope($this->db, $date);
            $plan = ContactSql::detail($id, $organization, $employee,
                $date, (string)$this->db->DBPrefix,
                ...$this->customerScopes($access));
            $result = $this->db->query($plan['sql'], $plan['binds']);
            if ($result === false) throw new UnexpectedValueException('Contact scoped detail failed.');
            $row = $result->getRowArray();
            if ($row === null) throw new PageNotFoundException('Contact record not available.');
            $scope = $readScope->resolve($row);
            if (!$access->canView($scope['resource'], $scope['target'])
                || !$this->customerParentView($row,$access)) {
                throw new UnexpectedValueException('AUTH_SCOPE_PBAC_MISMATCH: Contact detail.');
            }
            $row['allowed_actions'] = EffectiveRowActionHints::forViewedRow($access, $scope['resource'], $scope['target']);
            return $row;
        });
    }

    /** @return array{0:\App\Modules\Access\Query\AuthorizedSqlScope,1:\App\Modules\Access\Query\AuthorizedSqlScope} */
    private function compileContactScopes(UnifiedAccessService $access): array
    {
        $compiler = new AuthorizationSqlScopeCompiler($access);
        // Two DIFFERENT PBAC resources; one generic `contact` grant is invalid.
        return [
            $compiler->compile(new ContactScopeProvider('organization_contact', $this->db)),
            $compiler->compile(new ContactScopeProvider('employee_contact', $this->db)),
        ];
    }

    /** @return array{0:\App\Modules\Access\Query\AuthorizedSqlScope,1:\App\Modules\Access\Query\AuthorizedSqlScope} */
    private function customerScopes(UnifiedAccessService $access): array
    {
        return [
            \App\Modules\Access\Query\AuthorizedSqlScope::master(
                'customer_contact',$access->viewableMasterLifecycleStatuses('customer_contact')),
            \App\Modules\Access\Query\AuthorizedSqlScope::master(
                'customer',$access->viewableMasterLifecycleStatuses('customer')),
        ];
    }

    /** Parent GROUP PBAC is independent of Contact's own GROUP PBAC. */
    private function customerParentView(array $contact, UnifiedAccessService $access): bool
    {
        if (strtoupper(trim((string)($contact['contact_type']??'')))!=='CUSTOMER') return true;
        $customer=$this->db->table('customers')->where('id',$contact['contact_ref_id'])->get()->getRowArray();
        return $customer!==null && $access->canView('customer',$customer);
    }

    /** Consistent READ snapshot. Never commit or roll back a caller's write transaction. */
    private function readSnapshot(callable $work): mixed
    {
        if ($this->db->transDepth !== 0) {
            throw new UnexpectedValueException('Contact GET cannot borrow another transaction.');
        }
        if ($this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') === false
            || !$this->db->transBegin()) {
            throw new UnexpectedValueException('Cannot start Contact consistent read snapshot.');
        }
        try {
            $access = new UnifiedAccessService($this->actor, $this->db, $this->instant);
            $result = $work($access);
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new UnexpectedValueException('Contact read snapshot failed to complete.');
            }
            return $result;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $exception;
        }
    }

    private static function validCount(mixed $value): int
    {
        $text = is_string($value) || is_int($value) ? (string)$value : '';
        $limit = (string)PHP_INT_MAX;
        if (preg_match('/\A[0-9]+\z/D', $text) !== 1 || strlen($text) > strlen($limit)
            || (strlen($text) === strlen($limit) && strcmp($text,$limit)>0)) {
            throw new UnexpectedValueException('Invalid Contact SQL COUNT result.');
        }
        return (int)$text;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(array $input): array
    {
        $payload = ContactRules::payload($input, true);
        $owner = ContactRules::owner($payload);
        $initial = $this->states->initialState($owner['resource']);
        if ($initial === null) throw new DomainException('WF_INITIAL_STATE_REQUIRED', ['resource' => $owner['resource']]);
        if (isset($payload['status']) && $payload['status'] !== $initial) {
            throw new DomainException('WF_INITIAL_STATE_INVALID', ['field' => 'status']);
        }
        $payload['status'] = $initial;
        return $this->transaction(function () use ($payload): array {
            $scope = $this->scope->resolve($payload);
            if (! $this->customerParentView($payload,$this->access))
                throw new DomainException('AUTH_POLICY_DENIED');
            $this->permit($scope['resource'], 'create', null, $scope['target']);
            $this->assertAddress($payload);
            $this->persistInsert([...$payload, ...$this->createdAudit()]);
            return $this->visible($this->target((string) $this->db->insertID()));
        });
    }


    /**
     * CUS-G1: create a CUSTOMER-owned Contact within the caller's transaction.
     * Reuse canonical Contact validation, Lifecycle, PBAC, address and audit.
     * This does not commit or start another transaction, and deliberately does
     * not require Contact VIEW merely to complete an authorized CREATE.
     * @param array<string,mixed> $input
     */
    public function createCustomerInOwningTransaction(array $input): string
    {
        if ($this->db->transDepth !== 1) {
            throw new DomainException('CONTACT_PARENT_TRANSACTION_REQUIRED');
        }
        $payload = ContactRules::payload($input, true);
        $owner = ContactRules::owner($payload);
        if ($owner['type'] !== 'CUSTOMER') {
            throw new DomainException('CONTACT_TYPE_UNSUPPORTED');
        }
        $initial = $this->states->initialState('customer_contact');
        if ($initial === null) {
            throw new DomainException('WF_INITIAL_STATE_REQUIRED', ['resource' => 'customer_contact']);
        }
        if (isset($payload['status']) && $payload['status'] !== $initial) {
            throw new DomainException('WF_INITIAL_STATE_INVALID', ['field' => 'status']);
        }
        $payload['status'] = $initial;
        $scope = $this->scope->resolve($payload);
        if (! $this->customerParentView($payload, $this->access)) {
            throw new DomainException('AUTH_POLICY_DENIED');
        }
        $this->permit($scope['resource'], 'create', null, $scope['target']);
        $this->assertAddress($payload);
        $this->persistInsert([...$payload, ...$this->createdAudit()]);
        return (string) $this->db->insertID();
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function update(string $id, array $input): array
    {
        $id = ContactRules::id($id);
        return $this->transaction(function () use ($id, $input): array {
            $old = $this->target($id, true);
            $scope = $this->assertVisible($old);
            $this->permit($scope['resource'], 'edit', $scope['target']);
            $patch = ContactRules::payload($input, false);
            $this->assertAddress(array_replace($old, $patch));
            $this->persistUpdate($id, [...$patch, ...$this->updatedAudit()]);
            return $this->visible($this->target($id));
        });
    }

    /** @param array<string,mixed> $body @return array<string,mixed> */
    public function setState(string $id, array $body): array
    {
        $id = ContactRules::id($id);
        return $this->transaction(function () use ($id, $body): array {
            $old = $this->target($id, true);
            $scope = $this->assertVisible($old);
            $this->permit($scope['resource'], 'setstate', $scope['target']);
            $requested = ContactRules::requestedState($body);
            $definition = $this->states->definitionFor($scope['resource']);
            $transitions = $this->db->table('lifecycle_transitions')
                ->where('lifecycle_id', $definition['lifecycle_id'])
                ->where('status', 'ACTIVE')->where('action_code', 'setstate')->get()->getResultArray();
            $next = ContactRules::destination($definition, $old, $transitions, $requested);
            $this->permit($scope['resource'], 'setstate', $scope['target'], [], true);
            try {
                $ok = $this->db->table('contacts')->where('id', $id)
                    ->where('status', (string) $old['status'])
                    ->update(['status' => $next, ...$this->updatedAudit()]);
            } catch (Throwable $cause) {
                $this->writeFailure($cause);
            }
            if (! $ok) $this->writeFailure();
            if ($next !== (string) $old['status'] && $this->db->affectedRows() !== 1) {
                throw new DomainException('WF_CONCURRENT_STATE_CHANGE');
            }
            return $this->visible($this->target($id));
        });
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function visible(array $row): array
    {
        $this->assertVisible($row);
        return $row;
    }

    /** @param array<string,mixed> $row @return array{resource:string,target:array<string,mixed>} */
    private function assertVisible(array $row): array
    {
        try {
            $scope = $this->scope->resolve($row);
        } catch (DomainException $exception) {
            if (in_array($exception->getErrorCode(), [
                'CONTACT_OWNER_NOT_FOUND', 'CONTACT_PRIMARY_SCOPE_UNAVAILABLE',
                'CONTACT_TYPE_UNSUPPORTED', 'CONTACT_TYPE_REQUIRED',
                'CONTACT_OWNER_REFERENCE_REQUIRED',
            ], true)) {
                throw new PageNotFoundException('Contact record not available.');
            }
            throw $exception;
        }
        if (! $this->access->canView($scope['resource'], $scope['target'])
            || ! $this->customerParentView($row, $this->access)) {
            throw new PageNotFoundException('Contact record not available.');
        }
        return $scope;
    }

    /** @param array<string,mixed>|null $persisted @param array<string,mixed> $candidate */
    private function permit(string $resource, string $action, ?array $persisted, array $candidate = [], bool $transition = false): void
    {
        $decision = $transition
            ? $this->access->decideTransitionAction($resource, $action, $persisted ?? [])
            : $this->access->decideMutation($resource, $action, $persisted, $candidate);
        if (! $decision['allowed']) throw new DomainException('AUTH_POLICY_DENIED', ['action' => $action]);
    }

    /** @param array<string,mixed> $data */
    private function assertAddress(array $data): void
    {
        $province = $data['province_id'] ?? null;
        $ward = $data['ward_id'] ?? null;
        if ($province !== null && $this->db->table('provinces')->select('id')
            ->where('id', $province)->get()->getRowArray() === null) {
            throw new DomainException('CONTACT_WARD_PROVINCE_MISMATCH', ['field' => 'province_id']);
        }
        if ($ward !== null) {
            $selected = $this->db->table('wards')->select('id,province_id')->where('id', $ward)->get()->getRowArray();
            if ($selected === null || ($province !== null && (string) $selected['province_id'] !== (string) $province)) {
                throw new DomainException('CONTACT_WARD_PROVINCE_MISMATCH', ['field' => 'ward_id']);
            }
        }
    }

    /** @return array<string,mixed> */
    private function target(string $id, bool $lock = false): array
    {
        $builder = $this->db->table('contacts')->where('id', $id);
        $result = $lock ? $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE') : $builder->get();
        if ($result === false) throw new DomainException('CONTACT_PERSISTENCE_FAILED');
        $row = $result->getRowArray();
        if ($row === null) throw new PageNotFoundException('Contact record not found.');
        return $row;
    }

    /** @param array<string,mixed> $payload */
    private function persistInsert(array $payload): void
    {
        try { $ok = $this->db->table('contacts')->insert($payload); }
        catch (Throwable $cause) { $this->writeFailure($cause); }
        if (! $ok) $this->writeFailure();
    }

    /** @param array<string,mixed> $payload */
    private function persistUpdate(string $id, array $payload): void
    {
        try { $ok = $this->db->table('contacts')->where('id', $id)->update($payload); }
        catch (Throwable $cause) { $this->writeFailure($cause); }
        if (! $ok) $this->writeFailure();
    }

    /** @return array<string,string> */
    private function createdAudit(): array
    { return ['created_by' => $this->actorId, 'created_at' => gmdate('Y-m-d H:i:s')]; }

    /** @return array<string,string> */
    private function updatedAudit(): array
    { return ['updated_by' => $this->actorId, 'updated_at' => gmdate('Y-m-d H:i:s')]; }

    private function writeFailure(?Throwable $cause = null): never
    {
        throw new DomainException('CONTACT_PERSISTENCE_FAILED', [], $cause);
    }

    /** @template T @param callable():T $task @return T */
    private function transaction(callable $task): mixed
    {
        if (! $this->db->transBegin()) throw new DomainException('CONTACT_PERSISTENCE_FAILED');
        try {
            $result = $task();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new DomainException('CONTACT_PERSISTENCE_FAILED');
            }
            return $result;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $exception;
        }
    }
}
