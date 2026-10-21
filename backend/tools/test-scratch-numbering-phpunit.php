<?php

declare(strict_types=1);

/** Pure 03C-04B source/negative gate; NO database connections or SQL. */
require_once __DIR__ . '/../tests/_support/Scratch/NumberingMarker.php';
require_once __DIR__ . '/../tests/_support/Scratch/NumberingMethodGate.php';
require_once __DIR__ . '/_support/ScratchNumberingMethodLedger.php';

try {
    if ($argc !== 1 || getenv('ERP_SCRATCH_NUMBERING_TOKEN') !== false
        || getenv('ERP_SCRATCH_NUMBERING_CLASS') !== false
        || getenv('ERP_SCRATCH_NUMBERING_METHOD') !== false) {
        throw new RuntimeException('03C-04B gate refuses any inherited scratch worker state.');
    }
    $positive = 0;
    $negative = 0;
    $check = static function (bool $ok) use (&$positive): void {
        if (! $ok) { throw new RuntimeException('03C-04B positive gate failed.'); }
        $positive++;
    };
    $deny = static function (string $class, string $method) use (&$negative): void {
        try {
            \Tests\Support\Scratch\NumberingMethodGate::assertPair($class, $method);
        } catch (RuntimeException) {
            $negative++;
            return;
        }
        throw new RuntimeException('03C-04B FAIL OPEN: rejected method was accepted.');
    };
    $pairs = \Tests\Support\Scratch\NumberingMethodGate::METHODS;
    $check(count($pairs) === 5);
    $plan = require __DIR__ . '/unit-no-drop-inventory.php';
    $check(count($plan['candidates_unverified']) === 5);
    foreach ($pairs as $key => $method) {
        $class = explode('#', $key, 2)[0];
        \Tests\Support\Scratch\NumberingMethodGate::assertPair($class, $method);
        $check(isset($plan['candidates_unverified'][$class]));
    }
    $check(\Tests\Support\Scratch\NumberingMarker::tag('unchanged') === 'unchanged');
    $check(\Tests\Support\Scratch\NumberingMarker::sku('001') === null);
    $check(\Tests\Support\Scratch\NumberingMarker::product(['code' => '001'], '001') === ['code' => '001']);
    $check(is_file(__DIR__ . '/../phpunit.scratch-numbering.xml'));
    $xml = file_get_contents(__DIR__ . '/../phpunit.scratch-numbering.xml');
    $check(is_string($xml) && str_contains($xml, 'guarded_numbering_bootstrap.php'));
    $trait = file_get_contents(__DIR__ . '/../tests/_support/Performance/TransactionalDatabaseTestTrait.php');
    $check(is_string($trait) && str_contains($trait, 'NumberingMethodGate::assertWorker')
        && str_contains($trait, '$this->db->transBegin()'));
    $bootstrap = file_get_contents(__DIR__ . '/../tests/_support/Scratch/guarded_numbering_bootstrap.php');
    $check(is_string($bootstrap) && str_contains($bootstrap, "'erp_test_scratch'")
        && str_contains($bootstrap, 'ScratchConnectionGuard::assertEffective')
        && str_contains($bootstrap, 'scratch PHPUnit methods are disabled pending verified incident recovery')
        && str_contains($bootstrap, 'ERP_NO_DROP_DB_GUARDED'));
    $actualConnectionGuard = file_get_contents(__DIR__ . '/../tests/_support/Scratch/ScratchConnectionGuard.php');
    $check(is_string($actualConnectionGuard)
        && str_contains($actualConnectionGuard, 'assertDmlOnlyGrants')
        && str_contains($actualConnectionGuard, 'getConnections()')
        && str_contains($actualConnectionGuard, 'SELECT CURRENT_USER()'));
    $runner = file_get_contents(__DIR__ . '/scratch-numbering-phpunit.php');
    $check(is_string($runner) && str_contains($runner, 'cleanupExactOwnedRows')
        && str_contains($runner, 'ScratchNumberingMethodLedger::discover')
        && str_contains($runner, "'--execute'") && str_contains($runner, "'--plan'"));
    foreach (['P1AutoNumberingMandatoryRegressionTest', 'P1Num005ResourceIdentifierAdapterTest', 'P1Num006ProductIdentifierAdapterTest'] as $class) {
        $test = file_get_contents(__DIR__ . '/../tests/unit/' . $class . '.php');
        $check(is_string($test) && str_contains($test, 'NumberingMarker::tag('));
        $check(is_string($test) && ($class === 'P1Num005ResourceIdentifierAdapterTest'
            || str_contains($test, 'NumberingMarker::product(')));
    }
    foreach ([
        ['P1Wf007NativeStateContractTest', 'testNativeProductStateDenyFiltersBeforeTotalAndPagination'],
        ['P1Wf006StateAwarePbacAndLocationTransitionsTest', 'testProductSqlPaginationFallsBackToAuthorizationBeforeCounting'],
        ['P1Num005ResourceIdentifierAdapterTest', 'testFacadeUsesCanonicalSeriesAndManualAutoIfEmptyWithoutCounter'],
        ['P1Num006ProductIdentifierAdapterTest', 'testOnlyProductsCodeUniqueConflictIsClassified'],
        ['P1AutoNumberingMandatoryRegressionTest', 'testConfigurationDefaultsIssuedIdentityAndNoAutoFallback'],
        ['P1Num005ResourceIdentifierAdapterTest', 'testUnknownMethod'],
        ['P1Num006ProductIdentifierAdapterTest', 'testFoo'],
        ['P1Wf007NativeStateContractTest', 'testFoo'],
        ['', 'testFoo'],
        ['P1Num005ResourceIdentifierAdapterTest', ''],
        ['P1Num005ResourceIdentifierAdapterTest;', 'testFacadeDelegatesGeneratedCandidateAndAuthoritativeScopeToAdapter'],
        ['P1Num005ResourceIdentifierAdapterTest', 'testFacadeDelegatesGeneratedCandidateAndAuthoritativeScopeToAdapter|testFoo'],
    ] as [$class, $method]) {
        $deny($class, $method);
    }
    // Exercise pure ownership proof and failure quarantine logic with fake
    // rows; no mysqli connection or database driver is needed.
    $token = 'ABCDEF0123456789';
    $suffix = '-S04B-' . $token;
    $num005 = 'P1Num005ResourceIdentifierAdapterTest::testFacadeRejectsOversizedGeneratedCandidateBeforeMutation';
    $fakeResources = [['id' => 101, 'type' => 'num005_length_12345678' . strtolower($suffix), 'name' => 'NUM005' . $suffix]];
    $fakeOrgs = [['id' => 201, 'type' => 'GROUP', 'parent_id' => null, 'code' => 'NUM005-GROUP-FAKE' . $suffix]];
    $fakeSeries = [['id' => 301, 'resource_id' => 101, 'series_code' => 'DEFAULT' . $suffix]];
    $ledger = ScratchNumberingMethodLedger::verifyRows($num005, $token, true, $fakeResources, $fakeOrgs, $fakeSeries, [], []);
    $check(count($ledger['dinktech_resources']) === 1 && count($ledger['dinktech_auto_numbering_series']) === 1);
    $num005Success = 'P1Num005ResourceIdentifierAdapterTest::testFacadeDelegatesGeneratedCandidateAndAuthoritativeScopeToAdapter';
    $res2 = $fakeResources;
    $res2[0]['type'] = 'num005_generated_12345678' . strtolower($suffix);
    $counter7 = [['id' => 401, 'numbering_series_id' => 301, 'last_value' => 7]];
    $ledger = ScratchNumberingMethodLedger::verifyRows($num005Success, $token, true, $res2, $fakeOrgs, $fakeSeries, [], $counter7);
    $check(count($ledger['dinktech_auto_numbering_counters']) === 1);
    $prodResource = [['id' => 101, 'type' => 'product', 'name' => 'Product' . $suffix]];
    $num006 = 'P1Num006ProductIdentifierAdapterTest::testProductAdapterUsesGlobalOccupancyInsideAutoNumberTransaction';
    $prod = [
        ['id' => 501, 'code' => '001', 'sku' => 'S04B-' . $token . '-001'],
        ['id' => 502, 'code' => '002', 'sku' => 'S04B-' . $token . '-002'],
    ];
    $counter2 = [['id' => 401, 'numbering_series_id' => 301, 'last_value' => 2]];
    $ledger = ScratchNumberingMethodLedger::verifyRows($num006, $token, true, $prodResource, $fakeOrgs, $fakeSeries, $prod, $counter2);
    $check(count($ledger['dinktech_products']) === 2 && count($ledger['dinktech_auto_numbering_counters']) === 1);
    $rollback = 'P1Num006ProductIdentifierAdapterTest::testRollbackDoesNotPersistProductOrCounter';
    $ledger = ScratchNumberingMethodLedger::verifyRows($rollback, $token, true, $prodResource, $fakeOrgs, $fakeSeries, [], []);
    $check(! isset($ledger['dinktech_products']) && ! isset($ledger['dinktech_auto_numbering_counters']));
    $mandatory = 'P1AutoNumberingMandatoryRegressionTest::testProductContractGlobalNamespaceGenerationAndAtomicRollback';
    $series2 = [$fakeSeries[0], ['id' => 302, 'resource_id' => 101, 'series_code' => 'MANDATORY-MANUAL-ABC' . $suffix]];
    $series2[0]['series_code'] = 'MANDATORY-ABC' . $suffix;
    $prod3 = [
        ['id' => 501, 'code' => '100', 'sku' => 'S04B-' . $token . '-100'],
        ['id' => 502, 'code' => '105', 'sku' => 'S04B-' . $token . '-105'],
        ['id' => 503, 'code' => 'MANUAL-001', 'sku' => 'S04B-' . $token . '-MANUAL-001'],
    ];
    $counter105 = [['id' => 401, 'numbering_series_id' => 301, 'last_value' => 105]];
    $ledger = ScratchNumberingMethodLedger::verifyRows($mandatory, $token, true, $prodResource, $fakeOrgs, $series2, $prod3, $counter105);
    $check(count($ledger['dinktech_products']) === 3 && count($ledger['dinktech_auto_numbering_series']) === 2);

    $expectLedgerDenial = static function (string $name, string $key, mixed $replacement) use (&$negative, $num005, $token, $fakeResources, $fakeOrgs, $fakeSeries, $num006, $prodResource, $prod, $counter2): void {
        $r = $fakeResources; $o = $fakeOrgs; $s = $fakeSeries; $p = []; $c = [];
        $selected = $num005; $passToken = $token;
        switch ($key) {
            case 'resource': $r = $replacement; break;
            case 'org': $o = $replacement; break;
            case 'series': $s = $replacement; break;
            case 'product': $p = $replacement; break;
            case 'counter': $c = $replacement; break;
            case 'token': $passToken = $replacement; break;
            case 'num006prod':
                $selected = $num006; $r = $prodResource; $p = $replacement; $c = $counter2; break;
            case 'num006count':
                $selected = $num006; $r = $prodResource; $p = $prod; $c = $replacement; break;
        }
        try {
            ScratchNumberingMethodLedger::verifyRows($selected, $passToken, true, $r, $o, $s, $p, $c);
        } catch (RuntimeException) {
            $negative++;
            return;
        }
        throw new RuntimeException('03C-04B FAIL OPEN: owned ledger accepted ' . $name);
    };
    $badResource = $fakeResources; $badResource[0]['name'] = 'NUM005-UNOWNED';
    $expectLedgerDenial('resource unmarked', 'resource', $badResource);
    $badResource = $fakeResources; $badResource[0]['type'] = 'num005_length_wrong';
    $expectLedgerDenial('resource type without token', 'resource', $badResource);
    $badOrg = $fakeOrgs; $badOrg[0]['parent_id'] = 42;
    $expectLedgerDenial('not a GROUP root', 'org', $badOrg);
    $badSeries = $fakeSeries; $badSeries[0]['resource_id'] = 999;
    $expectLedgerDenial('wrong series FK', 'series', $badSeries);
    $badSeries = $fakeSeries; $badSeries[0]['series_code'] = 'DEFAULT-OTHER';
    $expectLedgerDenial('series missing marker', 'series', $badSeries);
    $expectLedgerDenial('missing series', 'series', []);
    $expectLedgerDenial('unexpected Product', 'product', [['id' => 551, 'code' => '001', 'sku' => 'S04B-' . $token . '-001']]);
    $expectLedgerDenial('unexpected counter', 'counter', [['id' => 401, 'numbering_series_id' => 301, 'last_value' => 2]]);
    $expectLedgerDenial('token spoof', 'token', 'INVALID-TOKEN');
    $badProd = $prod; $badProd[0]['sku'] = 'S04B-' . $token . '-OTHER';
    $expectLedgerDenial('Product SKU mismatch', 'num006prod', $badProd);
    $expectLedgerDenial('wrong counter value', 'num006count', [['id' => 401, 'numbering_series_id' => 301, 'last_value' => 999]]);
    $expectLedgerDenial('counter FK outside own series', 'num006count', [['id' => 401, 'numbering_series_id' => 999, 'last_value' => 2]]);

    echo '03C-04B PURE GATE PASS: ' . $positive . ' positive, ' . $negative
        . ' negative; 5 original Numbering methods, scratch-only bootstrap and ownership hooks; 0 DB connections, 0 SQL.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, '03C-04B PURE GATE STOP: source/permission/selection changed.' . PHP_EOL);
    exit(2);
}
