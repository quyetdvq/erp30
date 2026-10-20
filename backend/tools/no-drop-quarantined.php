<?php

declare(strict_types=1);

/**
 * Known residual test conversions that are NOT approved for NO-DROP PHPUnit.
 *
 * P1Prd002 was changed by TST-PERF-002B Batch 01. The user intentionally
 * never applied its FIX02 restoration. This test has SQL duplicate-key
 * expectations incompatible with an outer test transaction.
 *
 * Quarantine is NOT approval: none of these classes may enter the cohort or
 * the NO-DROP module runner. The LF-normalized source hash makes any other
 * source state an explicit audit error, not a silent exemption.
 *
 * @return array<string,array{path:string,sha256_lf:string,reason:string}>
 */
return [
    'P1Prd002ProductUomMasterServiceTest' => [
        'path' => 'backend/tests/Modules/Product/Uom/P1Prd002ProductUomMasterServiceTest.php',
        'sha256_lf' => '802959a1d94cb1c00eafde482af649f218505bc4d37fe3050b17505863d24a0f',
        'reason' => 'TST-PERF-002B Batch01 outer transaction changes duplicate-key error handling; excluded pending a separate no-drop design.',
    ],
];
