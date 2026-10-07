<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Enforces the canonical Policy scope identity after proving legacy rows can
 * be reconciled without changing authorization semantics.
 */
final class EnforcePolicyScopeUniqueness extends Migration
{
    private const UNIQUE_KEY = 'uq_policies_scope';

    /** @var array<string, string> */
    private const PARTICIPANT_TABLES = [
        'USER' => 'users',
        'GROUP' => 'groups',
        'POSITION' => 'positions',
        'DEPARTMENT' => 'departments',
        'ORGANIZATION' => 'organizations',
    ];

    /** @var array<string, int> */
    private const EFFECT_PRECEDENCE = [
        'DENY' => 1,
        'ALLOW' => 2,
        'ABSOLUTE_DENY' => 3,
    ];

    public function up(): void
    {
        $table = $this->table('policies');
        if (! $this->tableExists($table)) {
            throw new RuntimeException('POLICY_SCOPE_MIGRATION_BLOCKED: policies table is missing.');
        }

        $groups = $this->auditDuplicateScopes();
        if ($groups !== []) {
            $this->assertReconciliationProof($groups);
        }

        if (! $this->columnExists($table, 'normalized_state_id')) {
            $this->connection()->query(sprintf(
                'ALTER TABLE %s ADD COLUMN normalized_state_id BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(state_id, 0)) PERSISTENT',
                $table,
            ));
        }

        if ($groups !== []) {
            $this->reconcile($groups);
        }

        if (! $this->indexExists($table, self::UNIQUE_KEY)) {
            $this->connection()->query(sprintf(
                'ALTER TABLE %s ADD UNIQUE KEY %s (organization_id, resource_id, normalized_state_id, participant_type, participant_ref_id)',
                $table,
                self::UNIQUE_KEY,
            ));
        }
    }

    /**
     * @return list<array{organization_id:int,resource_id:int,normalized_state_id:int,participant_type:string,participant_ref_id:int,policy_ids:list<int>,actions:array<int,list<array<string,mixed>>>,logs:array<int,int>}>
     */
    public function auditDuplicateScopes(): array
    {
        $table = $this->table('policies');
        $result = $this->connection()->query(sprintf(
            'SELECT organization_id, resource_id, COALESCE(state_id, 0) AS normalized_state_id, participant_type, participant_ref_id, COUNT(*) AS duplicate_count
             FROM %s
             GROUP BY organization_id, resource_id, COALESCE(state_id, 0), participant_type, participant_ref_id
             HAVING COUNT(*) > 1
             ORDER BY organization_id, resource_id, normalized_state_id, participant_type, participant_ref_id',
            $table,
        ));
        if (! $result instanceof BaseResult) {
            throw new RuntimeException('POLICY_SCOPE_MIGRATION_BLOCKED: duplicate audit query failed.');
        }

        $groups = [];
        foreach ($result->getResultArray() as $scope) {
            $policyBuilder = $this->connection()->table('policies')
                ->where('organization_id', (int) $scope['organization_id'])
                ->where('resource_id', (int) $scope['resource_id'])
                ->where('participant_type', (string) $scope['participant_type'])
                ->where('participant_ref_id', (int) $scope['participant_ref_id']);
            $policyBuilder->groupStart()
                ->where('state_id IS NULL', null, false)
                ->orWhere('state_id', (int) $scope['normalized_state_id'])
                ->groupEnd();
            $policies = $policyBuilder->orderBy('id', 'ASC')->get();
            if (! $policies instanceof BaseResult) {
                throw new RuntimeException('POLICY_SCOPE_MIGRATION_BLOCKED: policy scope audit failed.');
            }

            $policyRows = $policies->getResultArray();
            /** @var list<int> $policyIds */
            $policyIds = array_values(array_map(static fn (array $row): int => (int) $row['id'], $policyRows));
            /** @var array<int, list<array<string, mixed>>> $actions */
            $actions = [];
            foreach ($policyIds as $policyId) {
                $actionResult = $this->connection()->query(
                    sprintf(
                        'SELECT pa.id, pa.policy_id, pa.resource_action_id, pa.effect, ra.resource_id AS action_resource_id
                         FROM %s pa LEFT JOIN %s ra ON ra.id = pa.resource_action_id
                         WHERE pa.policy_id = ? ORDER BY pa.id ASC',
                        $this->table('policy_actions'),
                        $this->table('resource_actions'),
                    ),
                    [$policyId],
                );
                if (! $actionResult instanceof BaseResult) {
                    throw new RuntimeException('POLICY_SCOPE_MIGRATION_BLOCKED: action audit failed.');
                }
                $actions[$policyId] = array_values($actionResult->getResultArray());
            }

            $logs = [];
            if ($policyIds !== []) {
                $logResult = $this->connection()->table('authorization_logs')
                    ->select('matched_policy_id, COUNT(*) AS total', false)
                    ->whereIn('matched_policy_id', $policyIds)
                    ->groupBy('matched_policy_id')
                    ->get();
                    if ($logResult instanceof BaseResult) {
                    foreach ($logResult->getResultArray() as $log) {
                        $logs[(int) $log['matched_policy_id']] = (int) $log['total'];
                    }
                }
            }

            $groups[] = [
                'organization_id' => (int) $scope['organization_id'],
                'resource_id' => (int) $scope['resource_id'],
                'normalized_state_id' => (int) $scope['normalized_state_id'],
                'participant_type' => (string) $scope['participant_type'],
                'participant_ref_id' => (int) $scope['participant_ref_id'],
                'policy_ids' => $policyIds,
                'actions' => $actions,
                'logs' => $logs,
            ];
        }

        return $groups;
    }

    public function down(): void
    {
        $table = $this->table('policies');
        if ($this->indexExists($table, self::UNIQUE_KEY)) {
            try {
                $this->connection()->query(sprintf('ALTER TABLE %s DROP INDEX %s', $table, self::UNIQUE_KEY));
            } catch (\Throwable $exception) {
                $message = strtolower($exception->getMessage());
                if (! str_contains($message, "doesn't exist") && ! str_contains($message, 'check that it exists')) {
                    throw $exception;
                }
            }
        }
        if ($this->columnExists($table, 'normalized_state_id')) {
            try {
                $this->connection()->query(sprintf('ALTER TABLE %s DROP COLUMN normalized_state_id', $table));
            } catch (\Throwable $exception) {
                $message = strtolower($exception->getMessage());
                if (! str_contains($message, "doesn't exist") && ! str_contains($message, 'check that it exists')) {
                    throw $exception;
                }
            }
        }
    }

    /** @param list<array{organization_id:int,resource_id:int,normalized_state_id:int,participant_type:string,participant_ref_id:int,policy_ids:list<int>,actions:array<int,list<array<string,mixed>>>,logs:array<int,int>}> $groups */
    private function assertReconciliationProof(array $groups): void
    {
        foreach ($groups as $group) {
            $participantTable = self::PARTICIPANT_TABLES[$group['participant_type']] ?? null;
            if ($participantTable === null) {
                throw $this->blocked($group, 'participant_type is not canonical');
            }
            $participant = $this->connection()->table($participantTable)->where('id', $group['participant_ref_id'])->get();
            if ($participant === false || $participant->getRowArray() === null) {
                throw $this->blocked($group, 'participant mapping cannot be proven');
            }
            if (count($group['policy_ids']) < 2) {
                throw $this->blocked($group, 'duplicate scope membership cannot be proven');
            }
            $policyIds = $group['policy_ids'];
            $policyResult = $this->connection()->table('policies')->whereIn('id', $policyIds)->get();
            if (! $policyResult instanceof BaseResult) {
                throw $this->blocked($group, 'policy scope rows cannot be re-read');
            }
            foreach ($policyResult->getResultArray() as $policy) {
                if (strtoupper(trim((string) $policy['participant_type'])) !== strtoupper($group['participant_type'])
                    || (int) $policy['participant_ref_id'] !== $group['participant_ref_id']
                    || (int) $policy['organization_id'] !== $group['organization_id']
                    || (int) $policy['resource_id'] !== $group['resource_id']) {
                    throw $this->blocked($group, 'participant or scope mapping is inconsistent');
                }
                if ($policy['state_id'] !== null && ((int) $policy['state_id'] < 1 || ! $this->stateExists((int) $policy['state_id']))) {
                    throw $this->blocked($group, 'workflow state mapping cannot be proven');
                }
            }
            foreach ($group['policy_ids'] as $policyId) {
                $actions = $group['actions'][$policyId] ?? [];
                if ($actions === []) {
                    throw $this->blocked($group, 'empty Action set cannot be reconciled');
                }
                foreach ($actions as $action) {
                    $effect = strtoupper(trim((string) ($action['effect'] ?? '')));
                    if (! isset(self::EFFECT_PRECEDENCE[$effect])) {
                        throw $this->blocked($group, 'unknown Action Effect cannot be reconciled');
                    }
                    if ((int) ($action['action_resource_id'] ?? 0) !== $group['resource_id']) {
                        throw $this->blocked($group, 'Action does not belong to Policy Resource');
                    }
                }
                if (($group['logs'][$policyId] ?? 0) > 0 && $policyId !== min($policyIds)) {
                    throw $this->blocked($group, 'authorization log references a duplicate survivor candidate');
                }
            }
        }
    }

    /** @param list<array{organization_id:int,resource_id:int,normalized_state_id:int,participant_type:string,participant_ref_id:int,policy_ids:list<int>,actions:array<int,list<array<string,mixed>>>,logs:array<int,int>}> $groups */
    private function reconcile(array $groups): void
    {
        $this->connection()->transBegin();
        try {
            foreach ($groups as $group) {
                $policyIds = $group['policy_ids'];
                if ($policyIds === []) {
                    throw $this->blocked($group, 'empty duplicate scope membership');
                }
                $survivor = min($policyIds);
                $winning = [];
                foreach ($policyIds as $policyId) {
                    foreach ($group['actions'][$policyId] as $action) {
                        $actionId = (int) $action['resource_action_id'];
                        $effect = strtoupper(trim((string) $action['effect']));
                        if (! isset($winning[$actionId]) || self::EFFECT_PRECEDENCE[$effect] > self::EFFECT_PRECEDENCE[$winning[$actionId]]) {
                            $winning[$actionId] = $effect;
                        }
                    }
                }
                foreach ($winning as $actionId => $effect) {
                    $existingResult = $this->connection()->table('policy_actions')
                        ->where('policy_id', $survivor)
                        ->where('resource_action_id', $actionId)
                        ->get()
                        ;
                    if (! $existingResult instanceof BaseResult) {
                        throw new RuntimeException('POLICY_SCOPE_MIGRATION_BLOCKED: survivor Action lookup failed.');
                    }
                    $existing = $existingResult->getRowArray();
                    if ($existing === null) {
                        $this->connection()->table('policy_actions')->insert([
                            'policy_id' => $survivor,
                            'resource_action_id' => $actionId,
                            'effect' => $effect,
                            'created_by' => null,
                            'updated_by' => null,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => null,
                        ]);
                    } else {
                        $this->connection()->table('policy_actions')->where('id', (int) $existing['id'])->update([
                            'effect' => $effect,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
                $losers = array_values(array_filter($policyIds, static fn (int $id): bool => $id !== $survivor));
                if ($losers !== []) {
                    $this->connection()->table('policy_actions')->whereIn('policy_id', $losers)->delete();
                    $this->connection()->table('policies')->whereIn('id', $losers)->delete();
                }
            }
            if (! $this->connection()->transStatus()) {
                throw new RuntimeException('POLICY_SCOPE_MIGRATION_BLOCKED: reconciliation transaction failed.');
            }
            $this->connection()->transCommit();
        } catch (\Throwable $exception) {
            $this->connection()->transRollback();
            $this->connection()->resetTransStatus();
            throw $exception;
        }
    }

    /** @param array{organization_id:int,resource_id:int,normalized_state_id:int,participant_type:string,participant_ref_id:int,policy_ids:list<int>,actions:array<int,list<array<string,mixed>>>,logs:array<int,int>} $group */
    private function blocked(array $group, string $reason): RuntimeException
    {
        return new RuntimeException(sprintf(
            'POLICY_SCOPE_MIGRATION_BLOCKED: %s; scope=%s/%s/%s/%s/%s; policies=%s',
            $reason,
            $group['organization_id'],
            $group['resource_id'],
            $group['normalized_state_id'],
            $group['participant_type'],
            $group['participant_ref_id'],
            implode(',', $group['policy_ids']),
        ));
    }

    private function table(string $name): string
    {
        return $this->connection()->getPrefix() . $name;
    }

    private function tableExists(string $table): bool
    {
        $result = $this->connection()->query(
            'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        );

        if (! $result instanceof BaseResult) {
            return false;
        }
        $row = $result->getRowArray();

        return $row !== null && (int) ($row['total'] ?? 0) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $result = $this->connection()->query(
            'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );

        if (! $result instanceof BaseResult) {
            return false;
        }
        $row = $result->getRowArray();

        return $row !== null && (int) ($row['total'] ?? 0) > 0;
    }

    private function stateExists(int $stateId): bool
    {
        $result = $this->connection()->table('workflow_states')->where('id', $stateId)->get();

        return $result instanceof BaseResult && $result->getRowArray() !== null;
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = $this->connection()->query(sprintf('SHOW INDEX FROM %s WHERE Key_name = ?', $table), [$index]);

        return $result instanceof BaseResult && $result->getRowArray() !== null;
    }

    /** @return BaseConnection<mixed, mixed> */
    private function connection(): BaseConnection
    {
        if (! $this->db instanceof BaseConnection) {
            throw new RuntimeException('POLICY_SCOPE_MIGRATION_BLOCKED: a transactional database connection is required.');
        }

        return $this->db;
    }
}
