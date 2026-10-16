<?php

declare(strict_types=1);

/** BE-REORG-003 reuses the existing guarded bootstrap/lock/subprocess path; full PHPUnit runs once. */
$arguments = array_slice($argv, 1);
if ($arguments === [] || $arguments === ['--help']) {
    fwrite(STDOUT, "Usage: php tools/test-contact.php --confirm-test-db\n"
        . "Requires the accepted BE-REORG-002_FIX01 checkpoint and a confirmed disposable loopback erp_test database.\n"
        . "Runs full PHPUnit once, checks Contact + Organization + Foundation coverage, then PHPStan.\n"
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

require __DIR__ . '/test-foundation.php';
