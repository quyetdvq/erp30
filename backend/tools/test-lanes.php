<?php

declare(strict_types=1);

/**
 * Central lane catalog. Each lane is EXPLICIT; the DB/quarantine manifests
 * remain their existing canonical sources so older safe runners keep working.
 * This catalog does NOT approve OTHER_TEST or any scanner output.
 *
 * @return array{unit_no_db:array<string,array{path:string,sha256_lf:string,methods:int}>,
 *    legacy_fast:list<string>,unit_suite_sha256_lf:string,unit_expected:array{classes:int,methods:int},rollback_db:array<string,list<string>>,
 *    quarantine:array<string,array<string,string>>}
 */
return [
    'unit_no_db' => require __DIR__ . '/unit-no-db-approved.php',
    'unit_suite_sha256_lf' => 'f5f1c97bfd8a6c18c8979bed6dc99ab70a2c7f01ac8984251eb8ecabf8115367',
    'unit_expected' => ['classes' => 13, 'methods' => 104],
    'legacy_fast' => [
        'CanonicalRepositoryHelperTest',
        'BeReorg001FoundationBoundaryTest',
        'BeReorg002OrganizationBoundaryTest',
        'BeReorg003ContactBoundaryTest',
        'BeReorg004EmployeeStructureBoundaryTest',
        'BeReorg005SettingsBoundaryTest',
        'BeReorg006NumberingBoundaryTest',
        'BeReorg007AuthorizationBoundaryTest',
        'BeReorg009LifecycleBoundaryTest',
        'BeReorg010StateResolutionBoundaryTest',
        'BeReorg011BusinessActionsBoundaryTest',
        'BeReorg012WriteBoundaryBoundaryTest',
        'ProductMediaPm1BoundaryTest',
        'ProductMediaPm2BoundaryTest',
    ],
    'rollback_db' => require __DIR__ . '/no-drop-approved.php',
    'quarantine' => require __DIR__ . '/no-drop-quarantined.php',
];
