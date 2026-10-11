<?php

declare(strict_types=1);

use App\Database\Seeds\PrivilegedAuthorizationGroupSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Lock\MariaDbDomainLockService;
use App\Infrastructure\Settings\BusinessTimezoneResolver;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Import;

/** @internal */
final class P1Sys007ProductionDecisionTest extends CIUnitTestCase
{
    public function testProposedImportBoundariesAcceptExactLimitsAndRejectOverflow(): void
    {
        $limits = new Import();
        self::assertSame(10 * 1024 * 1024, $limits->maxFileBytes);
        self::assertSame(5000, $limits->maxRows);

        $limits->assertFileSize($limits->maxFileBytes);
        $limits->assertRowCount($limits->maxRows);

        try {
            $limits->assertFileSize($limits->maxFileBytes + 1);
            self::fail('Expected file-size boundary rejection.');
        } catch (DomainException $exception) {
            self::assertSame('IMPORT_FILE_TOO_LARGE', $exception->getErrorCode());
        }

        try {
            $limits->assertRowCount($limits->maxRows + 1);
            self::fail('Expected row-count boundary rejection.');
        } catch (DomainException $exception) {
            self::assertSame('IMPORT_ROW_LIMIT_EXCEEDED', $exception->getErrorCode());
        }
    }

    public function testGroupDefaultTimezoneIsAsiaHoChiMinhWhenNoCanonicalSettingExists(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->method('get')->willReturn(null);

        $timezone = (new BusinessTimezoneResolver($settings))->resolve(null, 42);

        self::assertNotNull($timezone);
        self::assertSame('Asia/Ho_Chi_Minh', $timezone->getName());
    }

    public function testPrivilegedMatrixRemainsExactApprovedNinetyFiveActions(): void
    {
        self::assertSame(95, PrivilegedAuthorizationGroupSeeder::approvedActionCount());
        self::assertContains(['resource_type' => 'policy', 'action' => 'setstate'], PrivilegedAuthorizationGroupSeeder::approvedActions());
        self::assertContains(['resource_type' => 'resource', 'action' => 'setstate'], PrivilegedAuthorizationGroupSeeder::approvedActions());
        self::assertContains(['resource_type' => 'user', 'action' => 'reset_password'], PrivilegedAuthorizationGroupSeeder::approvedActions());
        self::assertNotContains(['resource_type' => 'display_setting', 'action' => 'view'], PrivilegedAuthorizationGroupSeeder::approvedActions());
        self::assertNotContains(['resource_type' => 'display_setting', 'action' => 'edit'], PrivilegedAuthorizationGroupSeeder::approvedActions());
    }

    public function testApprovedDomainLockRetryDefaultsToZero(): void
    {
        $lock = new MariaDbDomainLockService(db_connect('tests'));

        self::assertSame(0, $lock->retryCount());
    }
}
