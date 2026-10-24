<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Access\ResourceStateCatalog;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\ResourceStateValidator;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;
use Throwable;

/**
 * M3-STATE-001 read-only readiness audit. Intentionally performs no repair:
 * Lifecycle binding/policy changes require separate reviewed migrations.
 */
final class M3Audit extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'm3:audit';
    protected $description = 'Read-only audit of 10 State exceptions, Lifecycle bindings, transitions and ALL policies.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        $db = db_connect();
        $auth = config('Auth')->tables;
        $required = [
            'resources' => ['id','type','status','lifecycle_id'],
            'workflow_states' => ['id','code','status'],
            'lifecycles' => ['id','status'],
            'lifecycle_states' => ['lifecycle_id','state_id','is_initial'],
            'lifecycle_transitions' => ['id','lifecycle_id','from_state_id','to_state_id','action_code','status'],
            'policies' => ['id','resource_id','state_id','status','participant_type'],
            'resource_actions' => ['id','resource_id','code'],
            'policy_actions' => ['policy_id','resource_action_id','effect'],
            'group_permissions' => ['group_id','resource_action_id'],
            $auth['users'] => ['id','status','active'],
        ];
        foreach ($required as $table => $columns) {
            if (! $db->tableExists($table)) {
                throw new RuntimeException('M3 cannot audit missing table: ' . $table);
            }
            foreach ($columns as $column) {
                if (! $db->fieldExists($column, $table)) {
                    throw new RuntimeException('M3 cannot audit missing column: ' . $table . '.' . $column);
                }
            }
        }
        CLI::write('PASS: required M3 State/Lifecycle/Shield catalog schema exists', 'green');
        $issues = [];
        $counts = ['native' => 0, 'all_states' => 0, 'lifecycle' => 0, 'policies' => 0, 'transitions' => 0, 'policy_actions' => 0];
        $resourcesQuery = $db->table('resources')
            ->select('id, type, status, lifecycle_id')->orderBy('id', 'ASC')->get();
        if ($resourcesQuery === false) {
            throw new RuntimeException('M3 resources SQL failed: ' . ($db->error()['message'] ?? 'unknown'));
        }
        $resources = $resourcesQuery->getResultArray();
        $resolver = new ResourceStateRepository($db);
        foreach ($resources as $resource) {
            $type = (string) $resource['type'];
            $resourceId = (string) $resource['id'];
            try {
                $mode = ResourceStateCatalog::mode($type);
                $counter = match ($mode) {
                    ResourceStateCatalog::NATIVE => 'native',
                    ResourceStateCatalog::ALL_STATES => 'all_states',
                    default => 'lifecycle',
                };
                $counts[$counter]++;
                $definition = $resolver->definitionFor($type);
            } catch (Throwable $e) {
                $issues[] = "RESOURCE {$type} (#{$resourceId}): " . $e->getMessage();
                $definition = null;
            }
            // Check every policy, including INACTIVE: one can be reactivated.
            $policiesQuery = $db->table('policies')
                ->select('id, state_id, status')->where('resource_id', $resourceId)->get();
            if ($policiesQuery === false) {
                throw new RuntimeException('M3 policies SQL failed: ' . ($db->error()['message'] ?? 'unknown'));
            }
            foreach ($policiesQuery->getResultArray() as $policy) {
                $counts['policies']++;
                if ($definition === null) {
                    // The resource-level defect above already blocks use.
                    continue;
                }
                try {
                    ResourceStateValidator::assertPolicyState($definition, $policy['state_id']);
                } catch (Throwable $e) {
                    $issues[] = "POLICY #{$policy['id']} ({$policy['status']}) on {$type}: " . $e->getMessage();
                }
            }
            if ($definition === null || $definition['mode'] !== ResourceStateCatalog::LIFECYCLE) {
                continue;
            }
            // Check ALL transition rows (even INACTIVE: they can later be enabled).
            $transitionsQuery = $db->table('lifecycle_transitions')
                ->select('id, from_state_id, to_state_id, status, action_code')
                ->where('lifecycle_id', $definition['lifecycle_id'])->get();
            if ($transitionsQuery === false) {
                throw new RuntimeException('M3 transitions SQL failed: ' . ($db->error()['message'] ?? 'unknown'));
            }
            foreach ($transitionsQuery->getResultArray() as $transition) {
                $counts['transitions']++;
                if (! in_array($transition['status'], ['ACTIVE', 'INACTIVE'], true)
                    || ! isset($definition['by_id'][(string) $transition['from_state_id']])
                    || ! isset($definition['by_id'][(string) $transition['to_state_id']])) {
                    $issues[] = "TRANSITION #{$transition['id']} in {$type}: disabled/unknown status or State not in Lifecycle";
                }
            }
        }
        $this->checkActionCatalog($issues, $counts);
        $this->checkPersistedStateVocabularies($resolver, $resources, $issues);

        CLI::write('Catalog summary: ' . json_encode($counts, JSON_UNESCAPED_SLASHES), 'yellow');
        CLI::write('Canonical independent types: ' . implode(', ', ResourceStateCatalog::independentTypes()));
        if ($issues !== []) {
            foreach ($issues as $issue) {
                CLI::write('BLOCKER: ' . $issue, 'red');
            }
            CLI::write('No data was changed. Resolve/configure Lifecycle and policies by reviewed migration only.', 'yellow');
            throw new RuntimeException('M3 readiness audit found ' . count($issues) . ' blocker(s).');
        }
        CLI::write('PASS: M3 State classification, existing State policies and Lifecycle transitions valid', 'green');
        CLI::write('NOTE: NOT a runtime actor PBAC parity test; no writes or new API routes.', 'yellow');
        CLI::write('M3 readiness audit finished without database writes.', 'green');
    }

    /**
     * The State audit also ensures a Policy cannot silently address an Action
     * belonging to another Resource. This includes inactive policy rows.
     * @param list<string> $issues
     * @param array<string,int> $counts
     */
    private function checkActionCatalog(array &$issues, array &$counts): void
    {
        $db = db_connect();
        $actionResult = $db->table('resource_actions')
            ->select('id, resource_id, code')->get();
        $policyResult = $db->table('policies')
            ->select('id, resource_id, status, participant_type')->get();
        $bindingResult = $db->table('policy_actions')
            ->select('policy_id, resource_action_id, effect')->get();
        if ($actionResult === false || $policyResult === false || $bindingResult === false) {
            throw new RuntimeException('PBAC action integrity query failed: ' . ($db->error()['message'] ?? 'unknown'));
        }
        $actions = [];
        foreach ($actionResult->getResultArray() as $action) {
            $actions[(string) $action['id']] = (string) $action['resource_id'];
        }
        $policies = [];
        foreach ($policyResult->getResultArray() as $policy) {
            $policies[(string) $policy['id']] = (string) $policy['resource_id'];
            if (! in_array($policy['status'], ['ACTIVE', 'INACTIVE'], true)) {
                $issues[] = 'POLICY #' . $policy['id'] . ': invalid persisted policy status';
            }
            if (! in_array($policy['participant_type'], ['USER', 'GROUP', 'DEPARTMENT', 'POSITION', 'ORGANIZATION'], true)) {
                $issues[] = 'POLICY #' . $policy['id'] . ': unknown participant type';
            }
        }
        foreach ($bindingResult->getResultArray() as $binding) {
            $counts['policy_actions']++;
            $policyId = (string) $binding['policy_id'];
            $actionId = (string) $binding['resource_action_id'];
            if (! in_array($binding['effect'], ['ALLOW', 'DENY', 'ABSOLUTE_DENY'], true)) {
                $issues[] = 'POLICY_ACTION ' . $policyId . ':' . $actionId . ': invalid effect';
            }
            if (! isset($actions[$actionId], $policies[$policyId]) || $actions[$actionId] !== $policies[$policyId]) {
                $issues[] = 'POLICY_ACTION ' . $policyId . ':' . $actionId . ': action does not belong to policy Resource';
            }
        }
    }

    /**
     * Check distinct trusted status codes without logging business records.
     * Covers persisted status tables already used by M2.1 and independent
     * State resources with unambiguous primary status columns.
     * @param list<array<string,mixed>> $resources
     * @param list<string> $issues
     */
    private function checkPersistedStateVocabularies(ResourceStateRepository $resolver, array $resources, array &$issues): void
    {
        $tables = [
            'province' => 'provinces', 'ward' => 'wards', 'organization' => 'organizations',
            'department' => 'departments', 'position' => 'positions', 'employee' => 'employees',
            'employee_assignment' => 'employee_assignments', 'authorization_group' => 'groups',
            'import_job' => 'import_jobs', 'lifecycle' => 'lifecycles',
            'policy' => 'policies', 'workflow_state' => 'workflow_states', 'resource' => 'resources',
        ];
        $db = db_connect();
        foreach ($resources as $resource) {
            $type = (string) $resource['type'];
            $table = $tables[$type] ?? null;
            if ($table === null || ! $db->tableExists($table) || ! $db->fieldExists('status', $table)) {
                continue;
            }
            try {
                $definition = $resolver->definitionFor($type);
            } catch (Throwable) {
                // Already reported during resource scan.
                continue;
            }
            $result = $db->table($table)->select('status')->distinct()->get();
            if ($result === false) {
                throw new RuntimeException('M3 status audit SQL failed: ' . $table . ': ' . ($db->error()['message'] ?? 'unknown'));
            }
            foreach ($result->getResultArray() as $row) {
                try {
                    ResourceStateValidator::persistedStateId($definition, $row);
                } catch (Throwable $e) {
                    $issues[] = 'DATA ' . $table . '.status=' . var_export($row['status'], true) . ': ' . $e->getMessage();
                }
            }
        }
    }
}
