<?php

declare(strict_types=1);

namespace App\Modules\Access\Mutation;

use App\Exceptions\DomainException;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/** Fixed-table, SQL-backed Policy writes. Caller owns one transaction. */
final class PolicyMutationRepository
{
    private const TARGETS = [
        'USER' => 'users', 'GROUP' => 'groups',
        'POSITION' => 'positions', 'DEPARTMENT' => 'departments',
        'ORGANIZATION' => 'organizations',
    ];

    public function __construct(private readonly BaseConnection $db) {}

    /** @return array<string,mixed>|null */
    public function policy(string $id, bool $lock = false): ?array
    {
        $builder = $this->db->table('policies')->where('id', $id);
        return $this->one($lock ? $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE') : $builder->get(), 'Policy');
    }

    /** @return array<string,mixed>|null */
    public function resource(string $id): ?array
    {
        return $this->one($this->db->table('resources')->where('id', $id)->get(), 'Policy Resource');
    }

    /** @param array<string,mixed> $scope */
    public function assertUniqueScope(array $scope, ?string $except = null): void
    {
        $builder = $this->db->table('policies')->select('id')
            ->where('organization_id', $scope['organization_id'])
            ->where('resource_id', $scope['resource_id'])
            ->where('participant_type', $scope['participant_type'])
            ->where('participant_ref_id', $scope['participant_ref_id']);
        // Exact uniqueness: NULL and specific workflow State are distinct.
        if ($scope['state_id'] === null) {
            $builder->where('state_id', null);
        } else {
            $builder->where('state_id', $scope['state_id']);
        }
        if ($except !== null) {
            $builder->where('id !=', $except);
        }
        if ($this->one($this->db->query($builder->getCompiledSelect() . ' FOR UPDATE'), 'Policy scope') !== null) {
            throw new DomainException('POLICY_SCOPE_ALREADY_EXISTS', ['field' => 'scope']);
        }
    }

    /** @param array<string,mixed> $scope */
    public function validateReferences(array $scope, array $actions, string $resourceType, callable $stateCheck): void
    {
        // Baseline checks organization_id as a positive ID and lets the DB
        // foreign key enforce existence. Do not introduce a different 422
        // on invalid reference by querying this relation ahead of INSERT.
        $table = $scope['participant_type'] === 'USER'
            ? (string) config('Auth')->tables['users'] : self::TARGETS[$scope['participant_type']];
        if ($this->one($this->db->table($table)->select('id')
            ->where('id', $scope['participant_ref_id'])->get(), 'Policy participant') === null) {
            throw new DomainException('POLICY_INVALID_PARTICIPANT', ['field' => 'participant_ref_id']);
        }
        // Lifecycle/Native/All States membership must be checked against the
        // Resource pointed to by this policy, NEVER the Policy object itself.
        $stateCheck($resourceType, $scope['state_id']);
        foreach ($actions as $action) {
            $found = $this->one($this->db->table('resource_actions')
                ->select('id')->where('id', $action['resource_action_id'])
                ->where('resource_id', $scope['resource_id'])->get(), 'Policy Resource Action');
            if ($found === null) {
                throw new DomainException('POLICY_RESOURCE_ACTION_MISMATCH', [
                    'resource_action_id' => $action['resource_action_id'],
                ]);
            }
        }
    }

    /** @return list<array<string,mixed>> */
    public function actions(string $id): array
    {
        return $this->many($this->db->table('policy_actions')
            ->where('policy_id', $id)->orderBy('id', 'ASC')->get(), 'Policy Actions');
    }

    /** @param array<string,mixed> $scope @param array<string,mixed> $audit */
    public function insert(array $scope, array $audit): string
    {
        $this->write(fn (): bool => $this->db->table('policies')->insert([
            ...$scope, 'status' => 'ACTIVE', ...$audit,
        ]), 'Policy insert');
        $id = (string) $this->db->insertID();
        return PolicyMutationRules::id($id, 'policy_id');
    }

    /** @param array<string,mixed> $scope @param array<string,mixed> $audit */
    public function update(string $id, array $scope, array $audit): void
    {
        $this->write(fn (): bool => $this->db->table('policies')
            ->where('id', $id)->update([...$scope, ...$audit]), 'Policy update');
    }

    public function setStatus(string $id, string $state, string $old, array $audit): void
    {
        $this->write(fn (): bool => $this->db->table('policies')
            ->where('id', $id)->where('status', $old)
            ->update(['status' => $state, ...$audit]), 'Policy setstate');
        // Baseline's expected-old-status update fails with a CONCURRENT error
        // if the SQL statement changed no row (including a no-op status POST).
        if ($this->db->affectedRows() === 0) {
            throw new DomainException('POLICY_CONCURRENT_UPDATE');
        }
    }

    /** @param list<array{resource_action_id:string,effect:string}> $actions
     *  @param array<string,mixed> $audit
     */
    public function replaceActions(string $policyId, array $actions, array $audit): void
    {
        $this->write(fn (): bool => $this->db->table('policy_actions')
            ->where('policy_id', $policyId)->delete(), 'Policy Actions delete');
        foreach ($actions as $action) {
            $this->write(fn (): bool => $this->db->table('policy_actions')->insert([
                'policy_id' => $policyId, ...$action, ...$audit,
            ]), 'Policy Action insert');
        }
    }

    public function delete(string $id): void
    {
        $this->write(fn (): bool => $this->db->table('policy_actions')
            ->where('policy_id', $id)->delete(), 'Policy Actions delete');
        $this->write(fn (): bool => $this->db->table('policies')->where('id', $id)->delete(), 'Policy delete');
    }

    /** @param callable():bool $execute */
    private function write(callable $execute, string $context): void
    {
        try {
            if (! $execute()) {
                $this->assertConflict();
                throw new RuntimeException($context . ' failed.');
            }
        } catch (Throwable $error) {
            if ($error instanceof DomainException) {
                throw $error;
            }
            $this->assertConflict($error);
            throw $error;
        }
    }

    private function assertConflict(?Throwable $error = null): void
    {
        $details = $this->db->error();
        $message = strtolower(($error?->getMessage() ?? '') . ' ' . (string) ($details['message'] ?? ''));
        if (str_contains($message, 'uq_policies_scope')
            || (str_contains($message, 'duplicate entry') && str_contains($message, 'normalized_state_id'))) {
            throw new DomainException('POLICY_SCOPE_ALREADY_EXISTS', ['field' => 'scope'], $error);
        }
    }

    /** @return array<string,mixed>|null */
    private function one(mixed $result, string $label): ?array
    {
        if ($result === false) {
            throw new RuntimeException($label . ' SQL failed.');
        }
        return $result->getRowArray();
    }

    /** @return list<array<string,mixed>> */
    private function many(mixed $result, string $label): array
    {
        if ($result === false) {
            throw new RuntimeException($label . ' SQL failed.');
        }
        return $result->getResultArray();
    }
}
