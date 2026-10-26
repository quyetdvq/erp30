<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\M11\Support\M11DatabaseGuard;

require_once __DIR__ . '/../Support/M11DatabaseGuard.php';

final class DatabaseSafetyGuardTest extends TestCase
{
    /** @return array<string, mixed> */
    private function validGroup(): array
    {
        return [
            'DSN' => '', 'hostname' => '127.0.0.1', 'port' => 3306,
            'database' => 'erp_test', 'DBDriver' => 'MySQLi',
            'DBPrefix' => 'dinktech_', 'username' => 'erp_test_runner',
            'password' => 'test-placeholder-only', 'pConnect' => false,
        ];
    }

    public function testTheExistingConfiguredTestDatabaseIsAcceptedWithoutConnecting(): void
    {
        M11DatabaseGuard::assertSafeTestGroup($this->validGroup());
        self::assertTrue(true);
    }

    /** @dataProvider unsafeGroupChanges */
    public function testUnsafeTestDatabaseConfigurationsAreRejectedBeforeConnecting(string $field, mixed $unsafeValue): void
    {
        $group = $this->validGroup();
        $group[$field] = $unsafeValue;
        $this->expectException(RuntimeException::class);
        M11DatabaseGuard::assertSafeTestGroup($group);
    }

    /** @return iterable<string, array{string,mixed}> */
    public static function unsafeGroupChanges(): iterable
    {
        yield 'production database' => ['database', 'erp'];
        yield 'other database' => ['database', 'erp_m11_test'];
        yield 'remote database' => ['hostname', '10.10.10.10'];
        yield 'wrong port' => ['port', 3307];
        yield 'wrong prefix' => ['DBPrefix', ''];
        yield 'root account' => ['username', 'root'];
        yield 'blank password' => ['password', ''];
        yield 'wrong driver' => ['DBDriver', 'SQLite3'];
        yield 'persistent connection' => ['pConnect', true];
        yield 'DSN override' => ['DSN', 'mysql://other-db'];
    }
}
