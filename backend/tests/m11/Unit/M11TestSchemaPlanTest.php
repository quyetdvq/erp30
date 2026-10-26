<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\M11\Support\M11TestSchemaPlan;

require_once __DIR__ . '/../Support/M11TestSchemaPlan.php';

final class M11TestSchemaPlanTest extends TestCase
{
    /** @return array<string, string> */
    private function existingTables(bool $with019, bool $with020, bool $with021 = false, bool $with022 = false): array
    {
        $tables = [];
        foreach (M11TestSchemaPlan::TABLES_BY_MIGRATION as $version => $logicalNames) {
            if (($version === '2026-10-26-000019' && ! $with019)
                || ($version === '2026-10-26-000020' && ! $with020)
                || ($version === '2026-10-26-000021' && ! $with021)
                || ($version === '2026-10-26-000022' && ! $with022)) {
                continue;
            }
            foreach ($logicalNames as $logical) {
                $tables['dinktech_' . $logical] = 'InnoDB';
            }
        }
        return $tables;
    }

    public function testAllMigrationsMissingCanBePreparedInOrder(): void
    {
        self::assertSame(['2026-10-26-000019', '2026-10-26-000020', '2026-10-26-000021', '2026-10-26-000022'],
            M11TestSchemaPlan::pending([], [], 'dinktech_'));
    }

    public function testOnlyMigration020MissingCanBePrepared(): void
    {
        self::assertSame(['2026-10-26-000020', '2026-10-26-000021', '2026-10-26-000022'], M11TestSchemaPlan::pending(
            ['2026-10-26-000019' => true], $this->existingTables(true, false), 'dinktech_'));
    }

    public function testFullyPreparedDatabaseRequiresNoDdl(): void
    {
        self::assertSame([], M11TestSchemaPlan::pending(
            ['2026-10-26-000019' => true, '2026-10-26-000020' => true, '2026-10-26-000021' => true, '2026-10-26-000022' => true],
            $this->existingTables(true, true, true, true), 'dinktech_'));
    }

    public function testOnlyMigration021MissingCanBePrepared(): void
    {
        self::assertSame(['2026-10-26-000021', '2026-10-26-000022'], M11TestSchemaPlan::pending(
            ['2026-10-26-000019' => true, '2026-10-26-000020' => true],
            $this->existingTables(true, true), 'dinktech_'));
    }

    public function testOnlyMigration022MissingCanBePrepared(): void
    {
        self::assertSame(['2026-10-26-000022'], M11TestSchemaPlan::pending(
            ['2026-10-26-000019' => true, '2026-10-26-000020' => true,
                '2026-10-26-000021' => true],
            $this->existingTables(true, true, true), 'dinktech_'));
    }

    public function testMigration022Without021IsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        M11TestSchemaPlan::pending(
            ['2026-10-26-000019' => true, '2026-10-26-000020' => true,
                '2026-10-26-000022' => true],
            $this->existingTables(true, true, false, true), 'dinktech_');
    }

    public function testMigration021Without020IsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        M11TestSchemaPlan::pending(
            ['2026-10-26-000019' => true, '2026-10-26-000021' => true],
            $this->existingTables(true, false, true), 'dinktech_');
    }

    public function testPartialTablesWithoutLedgerAreRejected(): void
    {
        $this->expectException(RuntimeException::class);
        M11TestSchemaPlan::pending([], ['dinktech_notification_settings' => 'InnoDB'], 'dinktech_');
    }

    public function testLedgerWithoutTableIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        M11TestSchemaPlan::pending(['2026-10-26-000019' => true], [], 'dinktech_');
    }

    public function testOutOfOrderLedgerIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        M11TestSchemaPlan::pending(['2026-10-26-000020' => true],
            $this->existingTables(false, true), 'dinktech_');
    }

    public function testNonTransactionalTableIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $engines = $this->existingTables(true, false);
        $engines['dinktech_notification_outbox'] = 'MyISAM';
        M11TestSchemaPlan::pending(['2026-10-26-000019' => true], $engines, 'dinktech_');
    }

    public function testWrongPrefixIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        M11TestSchemaPlan::pending([], [], '');
    }
}
