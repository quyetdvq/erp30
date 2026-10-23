<?php

declare(strict_types=1);

/** Vendor-free source gate for the KIR-001 transaction-ownership remediation lane. */
try {
    if ($argc !== 1) {
        throw new InvalidArgumentException('Usage: php tools/test-kir001-source-gate.php');
    }
    $backend = dirname(__DIR__);
    $facade = file_get_contents($backend . '/app/Services/Api050ProductCatalogService.php');
    $allocator = file_get_contents($backend . '/app/Modules/Numbering/Allocation/Application/AutoNumberingCounterAllocator.php');
    $unit = file_get_contents($backend . '/tests/unit/P1Num004CounterAllocatorTest.php');
    $test = file_get_contents($backend . '/tests/integration/Kir001ProductComboAutoTransactionRemediationTest.php');
    $worker = file_get_contents($backend . '/tests/fixtures/kir001_auto_combo_concurrency_worker.php');
    $xml = file_get_contents($backend . '/phpunit.kir001.xml');
    $bootstrap = file_get_contents($backend . '/tests/_support/Kir001/guarded_bootstrap.php');
    $cleaner = file_get_contents($backend . '/tests/_support/Kir001/Kir001FixtureCleaner.php');
    $cleanupTool = file_get_contents($backend . '/tools/test-kir001-cleanup.php');
    $runner = file_get_contents($backend . '/tools/test-kir001.ps1');
    foreach ([$facade, $allocator, $unit, $test, $worker, $xml, $bootstrap, $cleaner, $cleanupTool, $runner] as $source) {
        if (! is_string($source)) {
            throw new RuntimeException('KIR-001 source asset missing.');
        }
    }

    // The Product command remains the outer atomic boundary.
    foreach ([
        "array_key_exists('combo_items', \$payload)",
        '! $db->transBegin()',
        '$this->products()->create($payload, $operation)',
        '$this->combos()->replaceItems($id, $items, $operation)',
        '! $db->transStatus() || ! $db->transCommit()',
        '$db->transRollback()',
    ] as $marker) {
        if (! str_contains($facade, $marker)) {
            throw new RuntimeException('KIR-001 facade command boundary changed: ' . $marker);
        }
    }

    // Numbering owns a transaction only for standalone issuance and otherwise
    // leaves physical commit/rollback responsibility with the caller.
    foreach ([
        '$ownsTransaction = (int) $this->db->transDepth === 0',
        '$ownsTransaction && ! $this->db->transBegin()',
        '! $this->db->transStatus()',
        '$ownsTransaction && ! $this->db->transCommit()',
        'if ($ownsTransaction) {',
        '$this->db->transRollback()',
        '$this->db->resetTransStatus()',
    ] as $marker) {
        if (! str_contains($allocator, $marker)) {
            throw new RuntimeException('KIR-001 allocator participation marker missing: ' . $marker);
        }
    }
    if (str_contains($allocator, 'if ((int) $this->db->transDepth > 0)')
        || str_contains($allocator, "ERROR_TRANSACTION_REQUIRED, ['operation' => 'allocate']")) {
        throw new RuntimeException('KIR-001 allocator still rejects a caller-owned transaction.');
    }

    if (! str_contains($unit, "fixture('ambient-owner'")
        || substr_count($unit, '$this->assertSame(1, (int) $this->db->transDepth)') < 2
        || ! str_contains($unit, '$this->assertTrue($this->db->transRollback())')) {
        throw new RuntimeException('KIR-001 UNIT-STANDARD ambient-owner regression is missing.');
    }

    if (preg_match_all('/\bpublic\s+function\s+test[A-Za-z0-9_]+\s*\(/', $test) !== 9) {
        throw new RuntimeException('KIR-001 remediation matrix must contain exactly 9 runtime tests.');
    }
    if (str_contains($test, "\$created['code']") || ! str_contains($test, "\$created['product_code']")) {
        throw new RuntimeException('KIR-001 success-path assertions must use public product_code, not storage code.');
    }
    foreach ([
        'testAutoIfEmptyManualIdentifierComboWithItemsStillSucceeds',
        'testAutoRequiredComboWithItemsSucceedsInsideCommandTransaction',
        'testAutoRequiredComboWithExplicitEmptyItemsSucceedsInsideCommandTransaction',
        'testAutoRequiredComboWithoutCompositionKeyKeepsStandaloneAllocatorOwnership',
        'testAutoRequiredInvalidCompositionRollsBackParentAndFirstCounterThenReusesIdentifier',
        'testAutoRequiredExistingCounterRollbackRestoresValueAndReusesSequence',
        'testAutoRequiredCreateAllowedButComboEditDeniedRollsBackParentAndCounter',
        'testOccupiedGeneratedIdentifierAdvancesInsideComboTransaction',
        'testConcurrentAutoRequiredComboCreatesUseDistinctSequencesAndSingleCounter',
    ] as $method) {
        if (! str_contains($test, 'function ' . $method . '(')) {
            throw new RuntimeException('KIR-001 runtime case missing: ' . $method);
        }
    }
    foreach (['proc_open(', 'kir001_auto_combo_concurrency_worker.php', 'counterLastValue(', '$series[\'first_identifier\']', '$series[\'second_identifier\']'] as $marker) {
        if (! str_contains($test, $marker)) {
            throw new RuntimeException('KIR-001 concurrency/counter evidence missing: ' . $marker);
        }
    }

    // The original C2.1 cleanup missed component products: their SKU is
    // SKU-KIR001-LEAF-* rather than KIR001-*. A shared fixture selector must
    // cover both cleanup and post-commit residue attestation.
    foreach ([
        "'KIR001-LEAF-'",
        "'SKU-' . \$componentCode",
    ] as $marker) {
        if (! str_contains($test, $marker)) {
            throw new RuntimeException('KIR-001 product fixture format drifted: ' . $marker);
        }
    }
    foreach ([
        "->orLike('sku', 'SKU-KIR001-LEAF-', 'after')",
        "self::productFixtures(\$db)->select('id')",
        'self::productFixtures($db)->countAllResults()',
        'private static function deleteRows(',
        'product(s) referencing its fixture UOMs',
    ] as $marker) {
        if (! str_contains($cleaner, $marker)) {
            throw new RuntimeException('KIR-001 C2.2 cleanup safety marker missing: ' . $marker);
        }
    }

    foreach ([
        'Kir001FixtureCleaner::cleanup()',
        'protected function tearDown(): void',
    ] as $marker) {
        if (! str_contains($test, $marker)) {
            throw new RuntimeException('KIR-001 per-test fixture cleanup marker missing: ' . $marker);
        }
    }
    foreach ([
        "like('sku', 'KIR001-', 'after')",
        "table('product_combo_items')",
        "table('product_uom_conversions')",
        "table('auto_numbering_counters')",
        "table('employee_assignments')",
        "table('auth_groups_users')",
        "table('authorization_logs')",
        'assertNoResidueOn(',
        "'erp_test'",
        'ERP_KIR001_DB_GUARDED',
    ] as $marker) {
        if (! str_contains($cleaner, $marker)) {
            throw new RuntimeException('KIR-001 cleanup/residue marker missing: ' . $marker);
        }
    }
    foreach ([
        'guarded_bootstrap.php',
        'Kir001FixtureCleaner::cleanup()',
        'Kir001FixtureCleaner::assertNoResidue()',
    ] as $marker) {
        if (! str_contains($cleanupTool, $marker)) {
            throw new RuntimeException('KIR-001 cleanup tool marker missing: ' . $marker);
        }
    }
    foreach ([
        'test-kir001-cleanup.php',
        '$runtimeExitCode = $LASTEXITCODE',
        '$cleanupExitCode = $LASTEXITCODE',
        'fixture cleanup/residue attestation PASS',
    ] as $marker) {
        if (! str_contains($runner, $marker)) {
            throw new RuntimeException('KIR-001 runner cleanup fail-safe marker missing: ' . $marker);
        }
    }
    foreach (['Api050ProductCatalogService', "'combo_items'", "'trans_depth'", "db_connect('tests', false)"] as $marker) {
        if (! str_contains($worker, $marker)) {
            throw new RuntimeException('KIR-001 concurrency worker marker missing: ' . $marker);
        }
    }

    if (! str_contains($xml, 'tests/_support/Kir001/guarded_bootstrap.php')
        || ! str_contains($xml, 'Kir001ProductComboAutoTransactionRemediationTest.php')
        || str_contains($xml, '<directory>')) {
        throw new RuntimeException('KIR-001 PHPUnit selection is not pinned to the dedicated remediation test.');
    }
    foreach (['127.0.0.1', 'erp_test', 'dinktech_', 'ERP_KIR001_DB_GUARDED'] as $marker) {
        if (! str_contains($bootstrap, $marker)) {
            throw new RuntimeException('KIR-001 guarded bootstrap marker missing: ' . $marker);
        }
    }

    echo 'KIR-001 C2.2 SOURCE GATE PASS: allocator own-or-participate; 9 runtime cases; per-test + runner fail-safe cleanup; UNIT-STANDARD ambient-owner regression; 0 DB connections, 0 SQL.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'KIR-001 C2.2 SOURCE GATE STOP: ' . $exception->getMessage() . PHP_EOL);
    exit(2);
}
