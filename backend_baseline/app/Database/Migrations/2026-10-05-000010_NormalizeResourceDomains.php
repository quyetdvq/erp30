<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class NormalizeResourceDomains extends Migration
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
        'import_job' => 'ORGANIZATION',
    ];

    public function up(): void
    {
        foreach (self::DOMAINS as $type => $domain) {
            $this->db->table('resources')->where('type', $type)->update(['domain' => $domain]);
        }

        $result = $this->db->table('resources')->select('type, domain')->get();
        if ($result === false) {
            throw new \RuntimeException('Unable to read resources.domain during normalization.');
        }
        $rows = $result->getResultArray();
        $allowed = array_values(self::DOMAINS);
        foreach ($rows as $row) {
            $domain = $row['domain'] ?? null;
            if (! is_string($domain) || trim($domain) === '' || ! in_array($domain, $allowed, true)) {
                throw new \RuntimeException('Unsafe resources.domain value for resource type ' . (string) ($row['type'] ?? 'unknown'));
            }
        }

        $this->forge->modifyColumn('resources', [
            'domain' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->modifyColumn('resources', [
            'domain' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
        ]);
    }
}
