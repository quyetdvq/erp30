<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

final class WorkflowStateSeeder extends Seeder
{
    /** @var list<string> */
    private const CODES = [
        'DRAFT',
        'PENDING_APPROVAL',
        'APPROVED',
        'REJECTED',
        'CANCELLED',
    ];

    public function run(): void
    {
        $this->db->transStart();

        foreach (self::CODES as $code) {
            $result = $this->db->table('workflow_states')
                ->where('code', $code)
                ->get();
            $state = $result === false ? null : $result->getRowArray();

            if ($state === null) {
                $this->db->table('workflow_states')->insert([
                    'code'       => $code,
                    'name'       => $code,
                    'status'     => 'ACTIVE',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $this->db->transComplete();
    }
}
