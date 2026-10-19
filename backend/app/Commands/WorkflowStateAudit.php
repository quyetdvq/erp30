<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\ResourceNativeStateCatalog;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ResultInterface;

/** Read-only inventory under the unified ten-exception Lifecycle rule. */
final class WorkflowStateAudit extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'erp:workflow-state-audit';
    protected $description = 'Read-only audit of Lifecycle membership and business record state consistency.';

    /** @return ResultInterface<mixed,mixed> */
    private function result(BaseBuilder $builder): ResultInterface
    {
        $result = $builder->get();
        if ($result === false) {
            throw new \RuntimeException('Workflow State audit database read failed.');
        }
        return $result;
    }

    /** @var array<string,string> */
    private const TABLES = [
        'province' => 'provinces', 'ward' => 'wards', 'organization' => 'organizations',
        'department' => 'departments', 'position' => 'positions', 'employee' => 'employees',
        'product_group' => 'product_groups', 'product_uom' => 'product_uoms',
        'product' => 'products', 'price_list' => 'price_lists',
        'organization_contact' => 'contacts', 'employee_contact' => 'contacts',
        'contact' => 'contacts',
    ];

    public function run(array $params)
    {
        $db = db_connect();
        // Audit ALL registered Resources, not just those already bound to a
        // Lifecycle. Otherwise missing bindings are invisible to operators.
        $resources = $this->result($db->table('resources')
            ->select('id, type, lifecycle_id')
            ->orderBy('type', 'ASC'))->getResultArray();
        CLI::write('Lifecycle authority audit (READ ONLY)');
        $failures = 0;
        $bound = 0;
        $native = 0;
        foreach ($resources as $resource) {
            $type = strtolower(trim((string) $resource['type']));
            $independent = ResourceNativeStateCatalog::isIndependent($type);
            $lifecycleId = $resource['lifecycle_id'] === null ? null : (int) $resource['lifecycle_id'];
            if ($lifecycleId === null) {
                if ($independent) {
                    $native++;
                } else {
                    $failures++;
                }
                CLI::write(sprintf(
                    '[%s] %s (resource_id=%d) -> %s; issues=%s',
                    $independent ? 'OK' : 'BLOCKED', $type, (int) $resource['id'],
                    $independent ? 'native / All States' : 'Lifecycle not configured',
                    $independent ? '(none)' : 'LIFECYCLE_NOT_CONFIGURED',
                ), $independent ? 'green' : 'yellow');
                continue;
            }
            $bound++;
            $lifecycle = $this->result($db->table('lifecycles')->where('id', $lifecycleId))->getRowArray();
            $members = $this->result($db->table('lifecycle_states ls')
                ->select('ls.is_initial, ws.code, ws.status AS state_status')
                ->join('workflow_states ws', 'ws.id = ls.state_id', 'left')
                ->where('ls.lifecycle_id', $lifecycleId)
                ->orderBy('ls.sequence', 'ASC'))->getResultArray();
            $initials = [];
            $codes = [];
            $issues = [];
            if ($independent) {
                $issues[] = 'INDEPENDENT_RESOURCE_HAS_LIFECYCLE';
            }
            if ($lifecycle === null || ($lifecycle['status'] ?? null) !== 'ACTIVE') {
                $issues[] = 'LIFECYCLE_MISSING_OR_INACTIVE';
            }
            foreach ($members as $member) {
                $code = strtoupper((string) ($member['code'] ?? ''));
                $codes[] = $code;
                if (in_array($member['is_initial'] ?? null, [true, 1, '1'], true)) {
                    $initials[] = $code;
                }
                if ($code === '' || ($member['state_status'] ?? null) !== 'ACTIVE') {
                    $issues[] = 'MISSING_OR_INACTIVE_WORKFLOW_STATE';
                }
                if (strlen($code) > 50) {
                    $issues[] = 'STATE_CODE_EXCEEDS_SCHEMA_LIMIT';
                }
            }
            if (count($initials) !== 1) {
                $issues[] = 'INITIAL_COUNT_NOT_ONE';
            }
            // Persisted data in a State absent from the newly bound Lifecycle
            // becomes invisible to state-scoped authorization. Report it before
            // anyone changes membership or migrations, never rewrite rows.
            $outside = [];
            $table = self::TABLES[$type] ?? null;
            if ($table !== null && $codes !== []) {
                $query = $db->table($table)->select('status, COUNT(*) AS row_count')->groupBy('status');
                if ($type === 'organization_contact') {
                    $query->where('contact_type', 'ORGANIZATION');
                } elseif ($type === 'employee_contact') {
                    $query->where('contact_type', 'EMPLOYEE');
                } elseif ($type === 'contact') {
                    // Retired authorization key; only old metadata refers to it.
                    $query = null;
                }
                if ($query !== null) {
                    foreach ($this->result($query)->getResultArray() as $row) {
                        if (! in_array(strtoupper((string) $row['status']), $codes, true)) {
                            $outside[] = (string) $row['status'] . ':' . (int) $row['row_count'];
                        }
                    }
                }
            }
            if ($outside !== []) {
                $issues[] = 'EXISTING_RECORD_STATE_NOT_IN_LIFECYCLE';
            }
            $policies = $db->table('policies')->where('resource_id', $resource['id'])->countAllResults();
            $status = $issues === [] ? 'OK' : 'BLOCKED';
            if ($issues !== []) {
                $failures++;
            }
            CLI::write(sprintf(
                '[%s] %s (resource_id=%d) -> %s (#%d); initial=%s; states=%s; data_outside=%s; policies=%d; issues=%s',
                $status, $type, (int) $resource['id'],
                (string) ($lifecycle['code'] ?? '(missing)'), $lifecycleId,
                implode(',', $initials) ?: '(none)',
                implode(',', $codes) ?: '(none)',
                implode(',', $outside) ?: '(none)',
                $policies, implode(',', array_unique($issues)) ?: '(none)',
            ), $issues === [] ? 'green' : 'yellow');
        }
        CLI::write(sprintf(
            'Audited: %d resources (%d Lifecycle-bound, %d native/unbound); blocked: %d. No database changes.',
            count($resources), $bound, $native, $failures,
        ));
    }
}
