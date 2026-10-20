<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/tests/_support/NoDrop/NoDropSchemaGuard.php';

use Tests\Support\NoDrop\NoDropSchemaGuard;

$allow = [
    'GRANT USAGE ON *.* TO `erp_test_runner`@`localhost`',
    'GRANT SELECT, INSERT, UPDATE, DELETE ON `erp_test`.* TO `erp_test_runner`@`localhost`',
];
$deny = [
    ['GRANT ALL PRIVILEGES ON *.* TO `erp_test_runner`@`localhost`'],
    ['GRANT DROP ON `erp_test`.* TO `erp_test_runner`@`localhost`'],
    ['GRANT SELECT, INSERT, UPDATE, DELETE ON *.* TO `erp_test_runner`@`localhost`'],
    ['GRANT SELECT, INSERT, UPDATE, DELETE, CREATE ON `erp_test`.* TO `erp_test_runner`@`localhost`'],
    ['GRANT SELECT, INSERT, UPDATE, DELETE ON `erp_test`.* TO `erp_test_runner`@`localhost` WITH GRANT OPTION'],
    ['GRANT `other_role`@`localhost` TO `erp_test_runner`@`localhost`'],
    ['GRANT SELECT, INSERT, UPDATE, DELETE ON `another_db`.* TO `erp_test_runner`@`localhost`'],
];
NoDropSchemaGuard::assertLeastPrivilege($allow, 'erp_test_runner@localhost');
foreach ($deny as $grants) {
    try {
        NoDropSchemaGuard::assertLeastPrivilege($grants, 'erp_test_runner@localhost');
        throw new RuntimeException('Dangerous privilege was accepted: ' . implode(', ', $grants));
    } catch (RuntimeException $exception) {
        if (str_starts_with($exception->getMessage(), 'Dangerous privilege was accepted')) {
            throw $exception;
        }
    }
}
try {
    NoDropSchemaGuard::assertLeastPrivilege($allow, 'root@localhost');
    throw new RuntimeException('Root account unexpectedly passed privilege guard.');
} catch (RuntimeException $exception) {
    if (str_starts_with($exception->getMessage(), 'Root account unexpectedly')) {
        throw $exception;
    }
}
echo "NO-DROP privilege parser: PASS (safe grant + 8 negative cases).\n";
