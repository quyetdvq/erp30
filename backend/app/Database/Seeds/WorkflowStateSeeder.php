<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

final class WorkflowStateSeeder extends Seeder
{
    /** @var list<string> */
    private const CODES = [
        'DRAFT',
        'PENDING_APPROVAL',
        'APPROVED',
        'REJECTED',
        'CANCELLED',
        'CONFIRMED',
        'PROCESSING',
        'COMPLETED',
        // Canonical State definitions for native master/config resources.
        // These are Workflow State catalog identities, not enable flags.
        'ACTIVE',
        'INACTIVE',
        'CLOSED',
    ];

    public function run(): void
    {
        if (! $this->db->transBegin()) {
            throw new RuntimeException('WORKFLOW_STATE_SEED_TRANSACTION_START_FAILED');
        }
        try {
            foreach (self::CODES as $code) {
                $result = $this->db->table('workflow_states')->where('code', $code)->get();
                if ($result === false) {
                    throw new RuntimeException('WORKFLOW_STATE_SEED_READ_FAILED: ' . $code);
                }
                if ($result->getRowArray() !== null) {
                    continue;
                }
                if (! $this->db->table('workflow_states')->insert([
                    'code'       => $code,
                    'name'       => $code,
                    'status'     => 'ACTIVE',
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ])) {
                    throw new RuntimeException('WORKFLOW_STATE_SEED_INSERT_FAILED: ' . $code);
                }
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('WORKFLOW_STATE_SEED_COMMIT_FAILED');
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $exception;
        }
    }
}
