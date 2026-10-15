<?php

declare(strict_types=1);

use App\Database\UserDisplayMetadataResetPolicy;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal Guard against wiping account metadata outside isolated PHPUnit. */
final class UserDisplayMetadataResetPolicyTest extends CIUnitTestCase
{
    public function testOnlyExplicitlyOptedInPhpUnitOnErpTestCanResetPopulatedMetadata(): void
    {
        self::assertTrue(UserDisplayMetadataResetPolicy::allows('testing', 'erp_test', '1'));
    }

    public function testOtherContextsAlwaysRefusePopulatedMetadataDowngrade(): void
    {
        foreach ([
            ['production', 'erp', '1'],
            ['production', 'erp_test', '1'],
            ['development', 'erp_test', '1'],
            ['testing', 'erp', '1'],
            ['testing', 'ERP_TEST', '1'],
            ['testing', 'erp_test_archive', '1'],
            ['testing', 'erp_test', null],
            ['testing', 'erp_test', ''],
            ['testing', 'erp_test', '0'],
            ['testing', 'erp_test', 'true'],
        ] as [$environment, $database, $optIn]) {
            self::assertFalse(
                UserDisplayMetadataResetPolicy::allows($environment, $database, $optIn),
                sprintf('Unsafe reset allowed in environment=%s database=%s', $environment, $database),
            );
        }
    }

    public function testPhpUnitDatabaseIsTheDisposableErpTestDatabase(): void
    {
        self::assertSame('testing', ENVIRONMENT);
        self::assertSame('1', $_ENV['ERP_PHPUNIT_ALLOW_DISPLAY_FIELD_RESET'] ?? null);
        self::assertSame('tests', config('Database')->defaultGroup);

        // Read-only preflight. Unlike DatabaseTestTrait this does not migrate.
        $db = db_connect('tests');
        self::assertSame('erp_test', $db->database);
        $actual = $db->query('SELECT DATABASE() AS db_name')->getRowArray();
        self::assertSame('erp_test', (string) ($actual['db_name'] ?? ''));
    }
}
