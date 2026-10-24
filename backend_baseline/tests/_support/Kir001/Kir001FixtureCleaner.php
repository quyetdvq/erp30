<?php

declare(strict_types=1);

namespace Tests\Support\Kir001;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ConnectionInterface;
use RuntimeException;
use Throwable;

/**
 * Removes only KIR-001 runtime fixtures that can outlive the PHPUnit process.
 *
 * The concurrency workers use independent MariaDB connections and commit on
 * purpose, so CI4's per-test lifecycle cannot reliably roll those rows back.
 */
final class Kir001FixtureCleaner
{
    public static function cleanup(): void
    {
        self::assertGuardedLane();
        $db = \Config\Database::connect('tests', false);
        try {
            self::assertTarget($db);
            if ((int) $db->transDepth !== 0 || ! $db->transBegin()) {
                throw new RuntimeException('KIR-001 cleanup cannot begin an isolated cleanup transaction.');
            }

            try {
                $productIds = self::ids(self::productFixtures($db)->select('id')->get()->getResultArray());
                $uomIds = self::ids($db->table('product_uoms')->select('id')
                    ->like('code', 'KIR001-U-', 'after')->get()->getResultArray());
                $seriesIds = self::ids($db->table('auto_numbering_series')->select('id')
                    ->like('series_code', 'KIR001-', 'after')->get()->getResultArray());
                $userIds = self::ids($db->table('users')->select('id')
                    ->like('username', 'kir001-', 'after')->get()->getResultArray());
                $groupIds = self::ids($db->table('groups')->select('id')
                    ->like('code', 'KIR001-AUTH-', 'after')->get()->getResultArray());
                $employeeIds = $userIds === [] ? [] : self::ids($db->table('employees')->select('id')
                    ->whereIn('user_id', $userIds)->get()->getResultArray());

                if ($userIds !== []) {
                    self::deleteRows($db, $db->table('authorization_logs')->whereIn('user_id', $userIds), 'authorization_logs');
                }
                self::deleteRows($db, $db->table('authorization_logs')->like('request_id', 'kir001-', 'after'), 'authorization_logs');

                if ($productIds !== []) {
                    self::deleteRows($db, $db->table('product_combo_items')->whereIn('combo_product_id', $productIds), 'product_combo_items');
                    self::deleteRows($db, $db->table('product_combo_items')->whereIn('component_product_id', $productIds), 'product_combo_items');
                    self::deleteRows($db, $db->table('product_uom_conversions')->whereIn('product_id', $productIds), 'product_uom_conversions');
                }
                if ($uomIds !== []) {
                    self::deleteRows($db, $db->table('product_uom_conversions')->whereIn('uom_id', $uomIds), 'product_uom_conversions');
                }
                if ($productIds !== []) {
                    self::deleteRows($db, $db->table('products')->whereIn('id', $productIds), 'products');
                }

                if ($seriesIds !== []) {
                    self::deleteRows($db, $db->table('auto_numbering_counters')->whereIn('numbering_series_id', $seriesIds), 'auto_numbering_counters');
                    self::deleteRows($db, $db->table('auto_numbering_series')->whereIn('id', $seriesIds), 'auto_numbering_series');
                }

                if ($employeeIds !== []) {
                    self::deleteRows($db, $db->table('employee_assignments')->whereIn('employee_id', $employeeIds), 'employee_assignments');
                    self::deleteRows($db, $db->table('employees')->whereIn('id', $employeeIds), 'employees');
                }
                if ($userIds !== []) {
                    self::deleteRows($db, $db->table('auth_groups_users')->whereIn('user_id', $userIds), 'auth_groups_users');
                }
                self::deleteRows($db, $db->table('auth_groups_users')->like('group', 'kir001-auth-', 'after'), 'auth_groups_users');

                if ($groupIds !== []) {
                    self::deleteRows($db, $db->table('group_permissions')->whereIn('group_id', $groupIds), 'group_permissions');
                    self::deleteRows($db, $db->table('groups')->whereIn('id', $groupIds), 'groups');
                }
                if ($userIds !== []) {
                    self::deleteRows($db, $db->table('users')->whereIn('id', $userIds), 'users');
                }

                self::deleteRows($db, $db->table('organizations')->like('code', 'KIR001-C-', 'after'), 'organizations');
                self::deleteRows($db, $db->table('organizations')->like('code', 'KIR001-G-', 'after'), 'organizations');
                if ($uomIds !== []) {
                    // A missed component SKU must never leave a FK violation hidden
                    // behind a generic CI4 transaction-status error.
                    $referencingProducts = (int) $db->table('products')
                        ->whereIn('base_uom_id', $uomIds)->countAllResults();
                    if ($referencingProducts !== 0) {
                        throw new RuntimeException('KIR-001 cleanup still has ' . $referencingProducts
                            . ' product(s) referencing its fixture UOMs.');
                    }
                    self::deleteRows($db, $db->table('product_uoms')->whereIn('id', $uomIds), 'product_uoms');
                }

                if (! $db->transStatus() || ! $db->transCommit()) {
                    throw new RuntimeException('KIR-001 cleanup transaction did not commit cleanly.');
                }
                $db->resetTransStatus();
            } catch (Throwable $exception) {
                while ((int) $db->transDepth > 0) {
                    $db->transRollback();
                }
                $db->resetTransStatus();
                throw $exception;
            }

            self::assertNoResidueOn($db);
        } finally {
            while ((int) $db->transDepth > 0) {
                $db->transRollback();
            }
            $db->resetTransStatus();
            $db->close();
        }
    }

    public static function assertNoResidue(): void
    {
        self::assertGuardedLane();
        $db = \Config\Database::connect('tests', false);
        try {
            self::assertTarget($db);
            self::assertNoResidueOn($db);
        } finally {
            $db->close();
        }
    }

    private static function assertGuardedLane(): void
    {
        if (! defined('ERP_KIR001_DB_GUARDED') || ERP_KIR001_DB_GUARDED !== true
            || ! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing') {
            throw new RuntimeException('KIR-001 cleanup requires the guarded testing lane.');
        }
    }

    /** @param ConnectionInterface<mixed, mixed> $db */
    private static function assertTarget(ConnectionInterface $db): void
    {
        $row = $db->query('SELECT DATABASE() AS db, @@port AS port')->getRowArray();
        if (! is_array($row) || ($row['db'] ?? null) !== 'erp_test' || (int) ($row['port'] ?? 0) !== 3306) {
            throw new RuntimeException('KIR-001 cleanup refuses a target other than erp_test:3306.');
        }
    }

    /** @param ConnectionInterface<mixed, mixed> $db */
    private static function productFixtures(ConnectionInterface $db): BaseBuilder
    {
        // COMBO/occupied SKUs start KIR001-, but fixture components are written
        // as SKU-KIR001-LEAF-*. Both must be deleted before the fixture UOMs.
        return $db->table('products')->groupStart()
            ->like('sku', 'KIR001-', 'after')
            ->orLike('sku', 'SKU-KIR001-LEAF-', 'after')
            ->groupEnd();
    }

    /** @param ConnectionInterface<mixed, mixed> $db */
    private static function deleteRows(ConnectionInterface $db, BaseBuilder $builder, string $table): void
    {
        if ($builder->delete() === false || ! $db->transStatus()) {
            $error = $db->error();
            throw new RuntimeException('KIR-001 cleanup DELETE failed for ' . $table
                . ' (DB error ' . (string) ($error['code'] ?? '?') . '): '
                . (string) ($error['message'] ?? 'transaction status failed'));
        }
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function ids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $rows));
    }

    /** @param ConnectionInterface<mixed, mixed> $db */
    private static function assertNoResidueOn(ConnectionInterface $db): void
    {
        $counts = [
            'products' => (int) self::productFixtures($db)->countAllResults(),
            'product_uoms' => (int) $db->table('product_uoms')->like('code', 'KIR001-U-', 'after')->countAllResults(),
            'series' => (int) $db->table('auto_numbering_series')->like('series_code', 'KIR001-', 'after')->countAllResults(),
            'users' => (int) $db->table('users')->like('username', 'kir001-', 'after')->countAllResults(),
            'groups' => (int) $db->table('groups')->like('code', 'KIR001-AUTH-', 'after')->countAllResults(),
            'group_membership' => (int) $db->table('auth_groups_users')->like('group', 'kir001-auth-', 'after')->countAllResults(),
            'companies' => (int) $db->table('organizations')->like('code', 'KIR001-C-', 'after')->countAllResults(),
            'roots' => (int) $db->table('organizations')->like('code', 'KIR001-G-', 'after')->countAllResults(),
            'authorization_logs' => (int) $db->table('authorization_logs')->like('request_id', 'kir001-', 'after')->countAllResults(),
        ];
        $residue = array_filter($counts, static fn (int $count): bool => $count !== 0);
        if ($residue !== []) {
            throw new RuntimeException('KIR-001 fixture residue remains: ' . json_encode($residue, JSON_THROW_ON_ERROR));
        }
    }
}
