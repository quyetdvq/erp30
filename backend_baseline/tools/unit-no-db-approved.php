<?php

declare(strict_types=1);

/**
 * Explicitly reviewed Unit/Contract/Read-only Characterization test classes.
 *
 * Only this inventory is allowed in the NO-DB lane. Source is LF-hash pinned:
 * legitimate test changes must be re-reviewed instead of implicitly promoted.
 * I/O used by these tests is read-only file inspection; not a claim of no file I/O.
 *
 * @return array<string,array{path:string,sha256_lf:string,methods:int}>
 */
return [
    'BeReorg004EmployeeStructureCompatibilityTest' => [
        'path' => 'tests/unit/BeReorg004EmployeeStructureCompatibilityTest.php',
        'sha256_lf' => '82048da94757d6188f2220d2837cd57d86579fff9d4dec66ac635a369d53ba1d',
        'methods' => 18,
    ],
    'BeReorg004AssignmentCharacterizationTest' => [
        'path' => 'tests/database/BeReorg004AssignmentCharacterizationTest.php',
        'sha256_lf' => 'bb1636bad832f0f5c945f70533cd665a0634924a404f7dc6b35337acce53f2c5',
        'methods' => 12,
    ],
    'BeReorg004DepartmentPositionCharacterizationTest' => [
        'path' => 'tests/database/BeReorg004DepartmentPositionCharacterizationTest.php',
        'sha256_lf' => '8f698d96d0cf60c4baa0f20dd2071e8ea614ccf1e903139c1f7dbaa2b612baa7',
        'methods' => 10,
    ],
    'BeReorg004EmployeeCharacterizationTest' => [
        'path' => 'tests/database/BeReorg004EmployeeCharacterizationTest.php',
        'sha256_lf' => 'ffa64b664f54eb2a8bd89f26569a8907e53cf3393de06617870fcbf653d45af8',
        'methods' => 10,
    ],
    'BeReorg010StateResolutionDomainTest' => [
        'path' => 'tests/unit/BeReorg010StateResolutionDomainTest.php',
        'sha256_lf' => '93180d6dc9386d521c592d1f1d088abfee3f8fed4998083e87f99941d3db277d',
        'methods' => 8,
    ],
    'CsvImportReaderTest' => [
        'path' => 'tests/Infrastructure/Import/Reader/CsvImportReaderTest.php',
        'sha256_lf' => 'ed9e7415f32870cf5d618b26a72a334dcf4fd67ae684f5f4f0b7e4fbf4a9e052',
        'methods' => 8,
    ],
    'BeReorg002OrganizationCompatibilityTest' => [
        'path' => 'tests/unit/BeReorg002OrganizationCompatibilityTest.php',
        'sha256_lf' => 'af8b222371b95e850e2579647d38468633e58f8bfb2fb4b317a79c5e7bbb0244',
        'methods' => 7,
    ],
    'BeReorg012InitialStateRulesTest' => [
        'path' => 'tests/unit/BeReorg012InitialStateRulesTest.php',
        'sha256_lf' => '6addb609fdb58a0c7961d605c1cb366650e53039d0f83fae883b4f7399e47c1e',
        'methods' => 7,
    ],
    'BeReorg012WriteBoundaryDomainTest' => [
        'path' => 'tests/unit/BeReorg012WriteBoundaryDomainTest.php',
        'sha256_lf' => '15aea83181d200020b88bc0ee2194c13cb352e6cf11bf584e1b665acdc8043ec',
        'methods' => 7,
    ],
    'BeReorg006NumberingCompatibilityTest' => [
        'path' => 'tests/unit/BeReorg006NumberingCompatibilityTest.php',
        'sha256_lf' => '9153ce5ce4420232d48621b7aeb9183cacff81e85466d9a48465556adca4133e',
        'methods' => 6,
    ],
    'BeReorg010StateResolutionCompatibilityTest' => [
        'path' => 'tests/unit/BeReorg010StateResolutionCompatibilityTest.php',
        'sha256_lf' => 'ece85384ca36df7dba342d98351ee760744993f87c73c97f8c8479e4c37e7ed0',
        'methods' => 5,
    ],
    'BeReorg010StateResolutionCacheTest' => [
        'path' => 'tests/unit/BeReorg010StateResolutionCacheTest.php',
        'sha256_lf' => '2ba858940cd60eacd73db24f8044473666e240c407ee930d33e00e01457e3b81',
        'methods' => 4,
    ],
    'BusinessWorkflowTenNativeBoundaryTest' => [
        'path' => 'tests/unit/BusinessWorkflowTenNativeBoundaryTest.php',
        'sha256_lf' => '160859501f76a145240274d5a4af9509ac1851c7016dffb511a5bcb87f95e39b',
        'methods' => 2,
    ],
];
