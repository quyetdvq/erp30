<?php

declare(strict_types=1);

/** Reuse the existing safety/bootstrap/lock/subprocess path; run the full suite only once. */
$arguments = array_slice($argv, 1);
if ($arguments === [] || $arguments === ['--help']) {
    fwrite(STDOUT, "Usage: php tools/test-organization.php --confirm-test-db\n"
        . "Requires the BE-REORG-001B checkpoint and a confirmed disposable loopback erp_test database.\n"
        . "Runs full PHPUnit once, checks Organization and Foundation coverage, then PHPStan.\n"
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
require __DIR__ . '/test-foundation.php';
