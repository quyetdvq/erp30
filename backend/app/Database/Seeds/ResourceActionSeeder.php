<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

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
        'employee_assignment' => 'ORGANIZATION', 'policy' => 'ORGANIZATION',
        'authorization_log' => 'ORGANIZATION', 'price_list' => 'ORGANIZATION',
        'import_job' => 'ORGANIZATION', 'display_setting' => 'ORGANIZATION',
    ];

    /** @var array<string, list<string>> */
    private const MATRIX = [
        'province' => ['view', 'create', 'edit', 'setstate', 'export'],
        'ward' => ['view', 'create', 'edit', 'setstate', 'export'],
        'contact' => ['view', 'create', 'edit', 'setstate', 'export'],
        'organization' => ['view', 'create', 'edit', 'setstate', 'export'],
        'department' => ['view', 'create', 'edit', 'setstate', 'export'],
        'position' => ['view', 'create', 'edit', 'setstate', 'export'],
        'employee' => ['view', 'create', 'edit', 'setstate', 'export'],
        'employee_assignment' => ['view', 'create', 'edit', 'setstate'],
        'user' => ['view', 'create', 'edit', 'setstate', 'assign_permission'],
        'authorization_group' => ['view', 'create', 'edit', 'setstate', 'assign_member', 'assign_permission'],
        'resource' => ['view', 'create', 'edit', 'delete', 'configure_numbering'],
        'policy' => ['view', 'create', 'edit', 'delete'],
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
        $this->db->transStart();

        foreach (self::MATRIX as $type => $actions) {
            $result = $this->db->table('resources')
                ->where('type', $type)
                ->get();
            $resource = $result === false ? null : $result->getRowArray();

            if ($resource === null) {
                $this->db->table('resources')->insert([
                    'type'       => $type,
                    'name'       => $type,
                    'domain'     => self::DOMAINS[$type],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $resourceId = (int) $this->db->insertID();
            } else {
                $resourceId = (int) $resource['id'];
                $this->db->table('resources')->where('id', $resourceId)->update(['domain' => self::DOMAINS[$type]]);
            }

            foreach ($actions as $code) {
                $exists = $this->db->table('resource_actions')
                    ->where('resource_id', $resourceId)
                    ->where('code', $code)
                    ->countAllResults() > 0;

                if (! $exists) {
                    $this->db->table('resource_actions')->insert([
                        'resource_id' => $resourceId,
                        'code'        => $code,
                        'created_at'  => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        $this->db->transComplete();
    }
}
