<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

final class GroupRootSeeder extends Seeder
{
    public const ROOT_CODE = 'GROUP_DINKTECH';
    // Must match OrganizationMutationService::treeTransaction() in the live backend.
    private const LOCK_NAME = 'dinkerp.m52.organization.tree';

    public function run(): void
    {
        // The original backend_baseline Seeder used MariaDbDomainLockService.
        // That service does not exist in the active backend. Use the same
        // connection-scoped MariaDB named lock as OrganizationMutationService
        // so bootstrap cannot race against root creation/reparenting.
        $lockResult = $this->db->query(
            'SELECT GET_LOCK(?, 10) AS acquired',
            [self::LOCK_NAME],
        );
        if ($lockResult === false || (int) ($lockResult->getRowArray()['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('GROUP_ROOT_LOCK_NOT_ACQUIRED');
        }

        try {
            if (! $this->db->transBegin()) {
                throw new RuntimeException('GROUP_ROOT_TRANSACTION_START_FAILED');
            }
            try {
                $this->ensureRoot();
                if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                    throw new RuntimeException('GROUP_ROOT_BOOTSTRAP_FAILED');
                }
            } catch (\Throwable $exception) {
                $this->db->transRollback();
                $this->db->resetTransStatus();
                throw $exception;
            }
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?) AS released', [self::LOCK_NAME]);
        }
    }

    private function ensureRoot(): void
    {
        $rootsResult = $this->db->table('organizations')
            ->where('type', 'GROUP')
            ->where('parent_id IS NULL', null, false)
            ->orderBy('id', 'ASC')
            ->get();
        $roots = $rootsResult === false ? [] : $rootsResult->getResultArray();

        if (count($roots) > 1) {
            throw new RuntimeException('MULTIPLE_GROUP_ROOTS_EXIST');
        }

        $codeResult = $this->db->table('organizations')->where('code', self::ROOT_CODE)->get();
        $codedRow = $codeResult === false ? null : $codeResult->getRowArray();

        if (count($roots) === 1) {
            $root = $roots[0];
            if ((string) $root['code'] !== self::ROOT_CODE) {
                throw new RuntimeException('GROUP_ROOT_ALREADY_EXISTS_WITH_DIFFERENT_CODE');
            }
            if ($codedRow === null || (int) $codedRow['id'] !== (int) $root['id']) {
                throw new RuntimeException('GROUP_ROOT_CODE_CONFLICT');
            }

            return;
        }

        if ($codedRow !== null) {
            if ((string) $codedRow['type'] !== 'GROUP') {
                throw new RuntimeException('GROUP_ROOT_CODE_INCOMPATIBLE_TYPE');
            }

            throw new RuntimeException('GROUP_ROOT_CODE_NOT_ROOT');
        }

        $this->db->table('organizations')->insert([
            'parent_id' => null,
            'type' => 'GROUP',
            'code' => self::ROOT_CODE,
            'name' => 'Dinktech Group',
            'description' => 'Tập đoàn Dinktech',
            'status' => 'ACTIVE',
            'legal_name' => null,
            'tax_code' => null,
            'address' => null,
            'province_id' => null,
            'ward_id' => null,
            'active_from' => null,
            'active_to' => null,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => null,
        ]);
    }
}
