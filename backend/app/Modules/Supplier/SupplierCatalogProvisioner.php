<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use App\Modules\Access\ResourceStateRepository;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * Explicit, transactional SUP-T01B catalog only.
 * Never grants permissions/policies, creates bank endpoints or business rows.
 */
final class SupplierCatalogProvisioner
{
    public function __construct(private readonly BaseConnection $db) {}

    public function provision(): void
    {
        if (!$this->db->tableExists('suppliers') || !$this->db->tableExists('supplier_company_usages')) {
            throw new RuntimeException('SUP_CATALOG_MIGRATION_000028_REQUIRED');
        }
        if (!$this->db->transBegin()) throw new RuntimeException('SUP_CATALOG_TRANSACTION_START_FAILED');
        try {
            $contact = $this->contactLifecycle();
            $supplier = $this->resource('supplier');
            if ($supplier === null) {
                $lifecycle = $this->defaultLifecycle();
            } else {
                if (($supplier['status'] ?? null) !== 'ACTIVE'
                    || ($supplier['domain'] ?? null) !== 'MASTER'
                    || !ctype_digit((string)($supplier['lifecycle_id'] ?? ''))
                    || (int)$supplier['lifecycle_id'] <= 0) {
                    throw new RuntimeException('SUP_CATALOG_RESOURCE_CONFLICT: supplier');
                }
                $lifecycle = (string)$supplier['lifecycle_id'];
            }
            $this->assertBoundLifecycle($lifecycle);
            $this->provisionResource('supplier', $lifecycle);
            $this->provisionResource('supplier_contact', $contact);
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new RuntimeException('SUP_CATALOG_COMMIT_FAILED');
            }
        } catch (Throwable $error) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $error;
        }
    }

    private function contactLifecycle(): string
    {
        $rows = $this->db->table('resources')->select('type,domain,status,lifecycle_id')
            ->where('type', 'customer_contact')->get()->getResultArray();
        $id = SupplierCatalogPolicy::contactLifecycleId($rows);
        $this->assertBoundLifecycle($id);
        return $id;
    }

    private function resource(string $type): ?array
    {
        return $this->db->table('resources')->where('type', $type)->get()->getRowArray();
    }

    private function defaultLifecycle(): string
    {
        $record = $this->db->table('lifecycles')->where('code', SupplierCatalogPolicy::DEFAULT_LIFECYCLE)
            ->get()->getRowArray();
        if ($record !== null) return (string)$record['id'];

        $now = gmdate('Y-m-d H:i:s');
        $states = [];
        foreach (['ACTIVE', 'INACTIVE'] as $code) {
            $state = $this->db->table('workflow_states')->where('code', $code)->where('status', 'ACTIVE')
                ->get()->getRowArray();
            if ($state === null) throw new RuntimeException('SUP_CATALOG_WORKFLOW_STATE_MISSING: ' . $code);
            $states[$code] = (string)$state['id'];
        }
        if (!$this->db->table('lifecycles')->insert([
            'code' => SupplierCatalogPolicy::DEFAULT_LIFECYCLE,
            'name' => 'Supplier Active/Inactive', 'status' => 'ACTIVE', 'created_at' => $now,
        ])) throw new RuntimeException('SUP_CATALOG_LIFECYCLE_INSERT_FAILED');
        $id = (string)$this->db->insertID();
        foreach (['ACTIVE', 'INACTIVE'] as $sequence => $code) {
            if (!$this->db->table('lifecycle_states')->insert([
                'lifecycle_id' => $id, 'state_id' => $states[$code], 'sequence' => $sequence + 1,
                'is_initial' => $code === 'ACTIVE' ? 1 : 0, 'created_at' => $now,
            ])) throw new RuntimeException('SUP_CATALOG_STATE_INSERT_FAILED');
        }
        foreach ([['ACTIVE', 'INACTIVE'], ['INACTIVE', 'ACTIVE']] as [$from, $to]) {
            if (!$this->db->table('lifecycle_transitions')->insert([
                'lifecycle_id' => $id, 'from_state_id' => $states[$from],
                'to_state_id' => $states[$to], 'action_code' => 'setstate',
                'status' => 'ACTIVE', 'created_at' => $now,
            ])) throw new RuntimeException('SUP_CATALOG_TRANSITION_INSERT_FAILED');
        }
        return $id;
    }

    private function assertBoundLifecycle(string $id): void
    {
        $record = $this->db->table('lifecycles')->select('id,status')->where('id', $id)
            ->get()->getRowArray();
        $members = $this->db->table('lifecycle_states ls')
            ->select('ws.id, ws.code, ws.status, ls.is_initial')
            ->join('workflow_states ws', 'ws.id = ls.state_id')
            ->where('ls.lifecycle_id', $id)->orderBy('ls.sequence', 'ASC')->get()->getResultArray();
        SupplierCatalogPolicy::assertLifecycle($record ?? [], $members);
    }

    private function provisionResource(string $type, string $lifecycle): void
    {
        $row = $this->resource($type);
        if ($row === null) {
            if (!$this->db->table('resources')->insert([
                'type' => $type, 'name' => $type, 'domain' => 'MASTER',
                'lifecycle_id' => $lifecycle, 'status' => 'ACTIVE',
                'created_at' => gmdate('Y-m-d H:i:s'),
            ])) throw new RuntimeException('SUP_CATALOG_RESOURCE_INSERT_FAILED: ' . $type);
            $row = $this->resource($type);
        }
        SupplierCatalogPolicy::assertResource($row ?? [], $type, $lifecycle);
        $id = (string)$row['id'];
        $rows = $this->db->table('resource_actions')->select('code')->where('resource_id', $id)
            ->get()->getResultArray();
        SupplierCatalogPolicy::assertActionRows($type, $rows);
        $existing = array_column($rows, 'code');
        foreach (SupplierCatalogPolicy::ACTIONS[$type] as $code) {
            if (in_array($code, $existing, true)) continue;
            $permission = $type . '.' . $code;
            // A pre-existing Shield direct grant could become effective merely
            // by creating this Action. Detect it and require manual review.
            $table = config('Auth')->tables['permissions_users'];
            if ($this->db->table($table)->where('permission', $permission)->countAllResults() !== 0) {
                throw new RuntimeException('SUP_CATALOG_PREEXISTING_DIRECT_GRANT: ' . $permission);
            }
            if (!$this->db->table('resource_actions')->insert([
                'resource_id' => $id, 'code' => $code, 'created_at' => gmdate('Y-m-d H:i:s'),
            ])) throw new RuntimeException('SUP_CATALOG_ACTION_INSERT_FAILED: ' . $permission);
        }
        // Canonical P1 lifecycle validation after resource binding.
        (new ResourceStateRepository($this->db))->definitionFor($type);
    }
}
