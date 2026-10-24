<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

/** Verify that M2.1 HTTP read gates can resolve their persisted IAM catalogs. */
final class M21Verify extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'm21:verify';
    protected $description = 'Check read-route PBAC catalog readiness without writing or exposing record contents.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        $db = db_connect();
        $authTables = config('Auth')->tables;
        $needed = [
            'provinces','wards','organizations','departments','positions','employees','employee_assignments',
            'resources','resource_actions','groups','group_permissions','policies','policy_actions',
            'workflow_states','lifecycles','lifecycle_states',
            $authTables['users'],$authTables['groups_users'],$authTables['permissions_users'],
        ];
        foreach (array_unique($needed) as $table) {
            if (! $db->tableExists($table)) {
                throw new RuntimeException('Required baseline table missing: ' . $table);
            }
        }
        CLI::write('PASS: M2.1 data, PBAC, Lifecycle and Shield tables exist', 'green');

        $rootCount = $db->table('organizations')->where('type', 'GROUP')
            ->where('parent_id', null)->countAllResults();
        if ($rootCount !== 1) {
            throw new RuntimeException('Exactly one root GROUP organization is required for PBAC.');
        }
        CLI::write('PASS: exactly one GROUP root (context resolution)', 'green');

        // This release expects the baseline resource and policy status migration.
        // If the user's database is older, fail with the missing column name;
        // do not issue a migration or write to their production data.
        foreach ([
            'resources' => ['id', 'type', 'domain', 'status', 'lifecycle_id'],
            'resource_actions' => ['id', 'resource_id', 'code'],
            'policies' => ['id', 'resource_id', 'organization_id', 'state_id', 'status', 'participant_type', 'participant_ref_id'],
            'policy_actions' => ['policy_id', 'resource_action_id', 'effect'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                if (! $db->fieldExists($column, $table)) {
                    throw new RuntimeException("M2.1 requires {$table}.{$column}, but it is missing in the connected database. Review baseline migration state; no automatic changes were made.");
                }
            }
        }
        CLI::write('PASS: M2.1 resource/action/policy columns present', 'green');

        $resources = [
            'province' => 'MASTER', 'ward' => 'MASTER', 'department' => 'MASTER',
            'position' => 'MASTER', 'employee' => 'MASTER', 'organization' => 'ORGANIZATION',
            'employee_assignment' => 'ORGANIZATION',
        ];
        foreach ($resources as $type => $domain) {
            // Separate the two indexed lookups rather than relying on a JOIN
            // with a raw escape=false condition. Both must be checked for errors.
            $resourceQuery = $db->table('resources')
                ->select('id, status, domain')->where('type', $type)->get();
            if ($resourceQuery === false) {
                $error = $db->error();
                throw new RuntimeException('Resource lookup SQL failed for ' . $type . ': '
                    . ($error['message'] ?? 'unknown database error'));
            }
            $resource = $resourceQuery->getRowArray();
            if ($resource === null) {
                throw new RuntimeException('Missing PBAC resource for M2.1 read: ' . $type);
            }
            if ($resource['domain'] !== $domain || $resource['status'] !== 'ACTIVE') {
                throw new RuntimeException('M2.1 read resource has wrong domain or inactive status: ' . $type);
            }
            $actionQuery = $db->table('resource_actions')->select('id')
                ->where('resource_id', $resource['id'])->where('code', 'view')->get();
            if ($actionQuery === false) {
                $error = $db->error();
                throw new RuntimeException('VIEW action lookup SQL failed for ' . $type . ': '
                    . ($error['message'] ?? 'unknown database error'));
            }
            if ($actionQuery->getRowArray() === null) {
                throw new RuntimeException('Missing VIEW action for M2.1 resource: ' . $type);
            }
        }
        CLI::write('PASS: all seven M2.1 resources have active VIEW actions and expected domain', 'green');
        CLI::write('NOTE: This is a schema/catalog check, NOT an actor PBAC equivalence test.', 'yellow');
        CLI::write('M2.1 verification completed without database writes.', 'green');
    }
}
