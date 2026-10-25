<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

final class ResourceActionSeeder extends Seeder
{
    /** @var array<string, string> */
    private const DOMAINS = [
        'province' => 'MASTER', 'ward' => 'MASTER', 'department' => 'MASTER',
        'position' => 'MASTER', 'employee' => 'MASTER', 'product_group' => 'MASTER',
        'product_uom' => 'MASTER', 'product' => 'MASTER',
        'user' => 'SYSTEM', 'authorization_group' => 'SYSTEM', 'resource' => 'SYSTEM',
        'workflow_state' => 'SYSTEM', 'lifecycle' => 'SYSTEM',
        'organization' => 'ORGANIZATION', 'contact' => 'ORGANIZATION',
        'organization_contact' => 'ORGANIZATION', 'employee_contact' => 'ORGANIZATION',
        'employee_assignment' => 'ORGANIZATION', 'policy' => 'ORGANIZATION',
        'authorization_log' => 'ORGANIZATION', 'price_list' => 'ORGANIZATION',
        'import_job' => 'ORGANIZATION', 'display_setting' => 'ORGANIZATION',
    ];

    /** @var array<string, list<string>> */
    private const MATRIX = [
        'province' => ['view', 'create', 'edit', 'setstate', 'export'],
        'ward' => ['view', 'create', 'edit', 'setstate', 'export'],
        'contact' => ['view', 'create', 'edit', 'setstate', 'export'], // retired; retained for audit references
        'organization_contact' => ['view', 'create', 'edit', 'setstate', 'export'],
        'employee_contact' => ['view', 'create', 'edit', 'setstate', 'export'],
        'organization' => ['view', 'create', 'edit', 'setstate', 'export'],
        'department' => ['view', 'create', 'edit', 'setstate', 'export'],
        'position' => ['view', 'create', 'edit', 'setstate', 'export'],
        'employee' => ['view', 'create', 'edit', 'setstate', 'export'],
        'employee_assignment' => ['view', 'create', 'edit', 'setstate'],
        'user' => ['view', 'create', 'edit', 'setstate', 'reset_password', 'assign_permission'],
        'authorization_group' => ['view', 'create', 'edit', 'setstate', 'assign_member', 'assign_permission'],
        'resource' => ['view', 'create', 'edit', 'delete', 'configure_numbering', 'setstate'],
        'policy' => ['view', 'create', 'edit', 'delete', 'setstate'],
        'authorization_log' => ['view', 'export'],
        'workflow_state' => ['view', 'create', 'edit', 'setstate'],
        'lifecycle' => ['view', 'create', 'edit', 'setstate'],
        'product_group' => ['view', 'create', 'edit', 'setstate', 'export'],
        'product_uom' => ['view', 'create', 'edit', 'setstate', 'export'],
        'product' => ['view', 'create', 'edit', 'setstate', 'import', 'export'],
        'price_list' => ['view', 'create', 'edit', 'setstate', 'export'],
        'import_job' => ['view', 'export'],
        'display_setting' => ['view', 'edit'],
    ];

    public function run(): void
    {
        if (! $this->db->transBegin()) {
            throw new RuntimeException('RESOURCE_ACTION_SEED_TRANSACTION_START_FAILED');
        }
        try {
            foreach (self::MATRIX as $type => $actions) {
                $result = $this->db->table('resources')->where('type', $type)->get();
                if ($result === false) {
                    throw new RuntimeException('RESOURCE_ACTION_SEED_READ_FAILED: ' . $type);
                }
                $resource = $result->getRowArray();

                if ($resource === null) {
                    $created = $this->db->table('resources')->insert([
                        'type'       => $type,
                        'name'       => $type,
                        'domain'     => self::DOMAINS[$type],
                        'created_at' => gmdate('Y-m-d H:i:s'),
                        'status'     => $type === 'contact' ? 'INACTIVE' : 'ACTIVE',
                    ]);
                    if (! $created) {
                        throw new RuntimeException('RESOURCE_ACTION_SEED_INSERT_FAILED: ' . $type);
                    }
                    $resourceId = (int) $this->db->insertID();
                } else {
                    // A repeat bootstrap is NOT permission to rewrite an
                    // existing resource's authorization domain or state.
                    if ((string) ($resource['domain'] ?? '') !== self::DOMAINS[$type]) {
                        throw new RuntimeException('RESOURCE_ACTION_SEED_DOMAIN_CONFLICT: ' . $type);
                    }
                    if ($type === 'contact' && (string) ($resource['status'] ?? '') !== 'INACTIVE') {
                        throw new RuntimeException('RESOURCE_ACTION_SEED_RETIRED_CONTACT_ACTIVE');
                    }
                    $resourceId = (int) $resource['id'];
                }

                foreach ($actions as $code) {
                    $exists = $this->db->table('resource_actions')
                        ->where('resource_id', $resourceId)
                        ->where('code', $code)
                        ->countAllResults() > 0;
                    if (! $exists && ! $this->db->table('resource_actions')->insert([
                        'resource_id' => $resourceId,
                        'code'        => $code,
                        'created_at'  => gmdate('Y-m-d H:i:s'),
                    ])) {
                        throw new RuntimeException('RESOURCE_ACTION_SEED_ACTION_FAILED: ' . $type . '.' . $code);
                    }
                }
            }

            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('RESOURCE_ACTION_SEED_COMMIT_FAILED');
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $exception;
        }
    }
}
