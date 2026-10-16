<?php

declare(strict_types=1);

/** BE-REORG-005 reuses the guarded one-suite path; full PHPUnit runs exactly once. */
$arguments = array_slice($argv, 1);
if ($arguments === [] || $arguments === ['--help']) {
    fwrite(STDOUT, "Usage: php tools/test-settings.php --confirm-test-db\n"
        . "Requires the accepted BE-REORG-004_FIX01 checkpoint and a disposable loopback erp_test database.\n"
        . "Runs full PHPUnit once, checks Settings + Employee Structure + Contact + Organization + Foundation gates, then PHPStan.\n"
        . "Writes ../test-result.log. No migration command, config override or business DB reset.\n");
    exit($arguments === [] ? 2 : 0);
}
if ($arguments !== ['--confirm-test-db']) {
    fwrite(STDERR, "Unknown arguments. Use --help.\n");
    exit(2);
}

define('BE_REORG_ORGANIZATION_CASES', [
    'BeReorg002OrganizationBoundaryTest' => 4,
    'BeReorg002OrganizationCompatibilityTest' => 9,
    'BeReorg002OrganizationCharacterizationTest' => 16,
    'BeReorg002OrganizationHttpTest' => 5,
    'BeReorg002OrganizationLockTest' => 3,
]);

define('BE_REORG_CONTACT_CASES', [
    'BeReorg003ContactBoundaryTest' => 4,
    'BeReorg003ContactCompatibilityTest' => 10,
    'BeReorg003ContactCharacterizationTest' => 9,
    'BeReorg003ContactHttpTest' => 5,
    'BeReorg003ContactLifecycleTest' => 4,
]);

define('BE_REORG_EMPLOYEE_STRUCTURE_CASES', [
    'BeReorg004EmployeeStructureBoundaryTest' => 6,
    'BeReorg004EmployeeStructureCompatibilityTest' => 18,
    'BeReorg004DepartmentPositionCharacterizationTest' => 10,
    'BeReorg004EmployeeCharacterizationTest' => 10,
    'BeReorg004AssignmentCharacterizationTest' => 12,
    'BeReorg004EmployeeStructureHttpTest' => 5,
    'BeReorg004EmployeeStructureLockTest' => 3,
]);

define('BE_REORG_SETTINGS_CASES', [
    'BeReorg005SettingsBoundaryTest' => 5,
    'BeReorg005SettingsCompatibilityTest' => 11,
    'BeReorg005SettingsStorageResolverCharacterizationTest' => 8,
    'BeReorg005SettingsMutationAuditCharacterizationTest' => 10,
    'BeReorg005SettingsHttpTest' => 5,
]);

define('BE_REORG_SETTINGS_EXISTING_CASES', [
    'P1Dsp001DisplaySettingsTest' => 11,
    'P1Dsp002DisplaySettingsResolverTest' => 7,
    'P1Dsp003DisplaySettingsMutationServiceTest' => 5,
    'P1Dsp004DisplaySettingsApiTest' => 5,
    'P1Db003ConfigurationAuditEventsTest' => 1,
]);

require __DIR__ . '/test-foundation.php';
