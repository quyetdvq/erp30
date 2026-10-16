<?php

declare(strict_types=1);

/** BE-REORG-006 reuses the guarded one-suite path; full PHPUnit runs exactly once. */
$arguments = array_slice($argv, 1);
if ($arguments === [] || $arguments === ['--help']) {
    fwrite(STDOUT, "Usage: php tools/test-numbering.php --confirm-test-db\n"
        . "Requires the accepted BE-REORG-005_FIX01 checkpoint and a disposable loopback erp_test database.\n"
        . "Runs full PHPUnit once, checks Numbering + Settings + Employee Structure + Contact + Organization + Foundation gates, then PHPStan.\n"
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

define('BE_REORG_NUMBERING_CASES', [
    'BeReorg006NumberingBoundaryTest' => 4,
    'BeReorg006NumberingCompatibilityTest' => 6,
    'BeReorg006NumberingFactoryTest' => 4,
    'BeReorg006NumberingHttpTest' => 6,
]);

define('BE_REORG_NUMBERING_EXISTING_CASES', [
    'P1Num001AutoNumberingTest' => 9,
    'P1Num002NumberingPatternValidatorTest' => 9,
    'P1Num003ScopePeriodResolverTest' => 12,
    'P1Num004CounterAllocatorTest' => 10,
    'P1Num005ResourceIdentifierAdapterTest' => 6,
    'P1Num006ProductIdentifierAdapterTest' => 7,
    'P1Api040AutoNumberingTest' => 6,
    'P1AutoNumberingMandatoryRegressionTest' => 5,
    'P1Db050ResourceAuthorizationNumberingTest' => 4,
]);

require __DIR__ . '/test-foundation.php';
