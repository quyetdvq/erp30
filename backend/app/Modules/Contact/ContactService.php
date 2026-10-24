<?php

declare(strict_types=1);

namespace App\Modules\Contact;

use App\Exceptions\DomainException;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\UnifiedAccessService;
use App\Shared\Http\AuthorizedListPage;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
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

    public function __construct(User $actor, ?BaseConnection $connection = null)
    {
        $this->db = $connection ?? db_connect();
        $this->access = new UnifiedAccessService($actor, $this->db);
        $this->states = new ResourceStateRepository($this->db);
        $this->scope = new ContactScope($this->db);
        $this->actorId = ContactRules::id((string) $actor->id, 'actor_user_id');
    }

    /** @param array<string,mixed>|null $query @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int} */
    public function list(?array $query = null): array
    {
        $rows = $this->db->table('contacts')->orderBy('id', 'ASC')->get()->getResultArray();
        $authorized = [];
        foreach ($rows as $row) {
            try {
                $scope = $this->scope->resolve($row);
            } catch (DomainException $exception) {
                // Malformed/missing owner and ambiguous primary assignment never
                // grant a contact visible outside its canonical owner scope.
                if (! in_array($exception->getErrorCode(), [
                    'CONTACT_OWNER_NOT_FOUND', 'CONTACT_PRIMARY_SCOPE_UNAVAILABLE',
                    'CONTACT_TYPE_UNSUPPORTED', 'CONTACT_TYPE_REQUIRED',
                    'CONTACT_OWNER_REFERENCE_REQUIRED',
                ], true)) {
                    throw $exception;
                }
                continue;
            }
            if ($this->access->canView($scope['resource'], $scope['target'])) {
                $authorized[] = $row; // never expose the synthetic organization_id
            }
        }
        return AuthorizedListPage::paginate('contact', $authorized, $query ?? []);
    }

    /** @return array<string,mixed> */
    public function show(string $id): array
    {
        return $this->visible($this->target(ContactRules::id($id)));
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
            $this->permit($scope['resource'], 'create', null, $scope['target']);
            $this->assertAddress($payload);
            $this->persistInsert([...$payload, ...$this->createdAudit()]);
            return $this->visible($this->target((string) $this->db->insertID()));
        });
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
        if (! $this->access->canView($scope['resource'], $scope['target'])) {
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
