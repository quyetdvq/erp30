<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Infrastructure\Lock\MariaDbDomainLockService;
use CodeIgniter\Database\Seeder;
use RuntimeException;

final class GroupRootSeeder extends Seeder
{
    public const ROOT_CODE = 'GROUP_DINKTECH';
    private const LOCK_KEY = 'organization-tree';

    public function run(): void
    {
        // Keep the advisory lock and transaction on this Seeder's connection;
        // the root uniqueness check must serialize with organization creates.
        (new MariaDbDomainLockService($this->db))->synchronized(self::LOCK_KEY, function (): void {
            $this->db->transBegin();
            try {
                $this->ensureRoot();
                if (! $this->db->transStatus()) {
                    throw new RuntimeException('GROUP root bootstrap failed.');
                }
                $this->db->transCommit();
            } catch (\Throwable $exception) {
                $this->db->transRollback();
                throw $exception;
            }
        });
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
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
        ]);
    }
}
