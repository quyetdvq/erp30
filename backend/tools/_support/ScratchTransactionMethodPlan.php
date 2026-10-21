<?php

declare(strict_types=1);

namespace DinkERP\TestTools;

use RuntimeException;

/**
 * 03C-04A: immutable, vendor-free and SQL-free contract for the seven methods.
 * This is intentionally NOT a test runner and grants NO write/cleanup access.
 * Never use the previously validated canary as evidence for a PHPUnit method.
 */
final class ScratchTransactionMethodPlan
{
    public const TARGET = '127.0.0.1:3306/erp_test_scratch';
    public const PREFIX = 'dinktech_';
    public const NUMBERING_METHODS = [
        'P1AutoNumberingMandatoryRegressionTest::testProductContractGlobalNamespaceGenerationAndAtomicRollback',
        'P1Num005ResourceIdentifierAdapterTest::testFacadeRejectsOversizedGeneratedCandidateBeforeMutation',
        'P1Num005ResourceIdentifierAdapterTest::testFacadeDelegatesGeneratedCandidateAndAuthoritativeScopeToAdapter',
        'P1Num006ProductIdentifierAdapterTest::testProductAdapterUsesGlobalOccupancyInsideAutoNumberTransaction',
        'P1Num006ProductIdentifierAdapterTest::testRollbackDoesNotPersistProductOrCounter',
    ];
    public const WORKFLOW_METHODS = [
        'P1Wf006StateAwarePbacAndLocationTransitionsTest::testProductSqlPaginationFallsBackToAuthorizationBeforeCounting',
        'P1Wf007NativeStateContractTest::testNativeProductStateDenyFiltersBeforeTotalAndPagination',
    ];

    /**
     * @param list<string> $pinnedCandidates
     * @param list<string> $reviewedClasses
     * @return array<string,mixed>
     */
    public static function build(string $backend, array $pinnedCandidates, array $reviewedClasses): array
    {
        $sourceGate = UnitNoDropSelection::resolve($backend, $reviewedClasses);
        $actualCandidates = $sourceGate['candidates'];
        sort($actualCandidates, SORT_STRING);
        sort($pinnedCandidates, SORT_STRING);
        if ($actualCandidates !== $pinnedCandidates || count($pinnedCandidates) !== 5
            || count($sourceGate['ready']) !== 91 || count($sourceGate['blocked']) !== 8) {
            throw new RuntimeException('03C-04 STOP: unreviewed classes, source drift or unsafe class inventory.');
        }
        $split = UnitNoDropRollbackSubset::plan($backend, $pinnedCandidates);
        $declared = array_merge(self::NUMBERING_METHODS, self::WORKFLOW_METHODS);
        if (count($declared) !== 7 || count(array_unique($declared)) !== 7 || count($split['selected']) !== 47
            || count($split['scratch_only']) !== 7) {
            throw new RuntimeException('03C-04 STOP: scratch-only method split drift.');
        }
        $sort = static function (array $names): array {
            sort($names, SORT_STRING);
            return $names;
        };
        if ($sort($split['scratch_only']) !== $sort($declared)) {
            throw new RuntimeException('03C-04 STOP: method list differs from source-pinned 47/7 split.');
        }
        foreach ($declared as $name) {
            [$class, $method] = explode('::', $name, 2);
            if (! in_array($class, $pinnedCandidates, true)) {
                throw new RuntimeException('03C-04 STOP: unapproved class in scratch plan.');
            }
            $code = @file_get_contents($backend . '/tests/unit/' . $class . '.php');
            if (! is_string($code) || preg_match('/\bpublic\s+function\s+' . preg_quote($method, '/') . '\s*\(/', $code) !== 1
                || preg_match('/\buse\s+TransactionalDatabaseTestTrait\s*;/', $code) !== 1) {
                throw new RuntimeException('03C-04 STOP: original method or rollback trait is missing.');
            }
            // Confirm we plan the EXACT existing PHPUnit methods, not rewritten approximations.
            if (in_array($name, self::NUMBERING_METHODS, true) && ! str_contains($code, '->issue(')) {
                throw new RuntimeException('03C-04 STOP: reviewed Numbering issuance call changed.');
            }
            if (in_array($name, self::WORKFLOW_METHODS, true)
                && (! str_contains($code, '->listProducts(')
                    || ! str_contains($code, 'ResourceActionSeeder'))) {
                throw new RuntimeException('03C-04 STOP: reviewed Product/Workflow fixture path changed.');
            }
        }
        $trait = @file_get_contents($backend . '/tests/_support/Performance/TransactionalDatabaseTestTrait.php');
        $wf007 = @file_get_contents($backend . '/tests/unit/P1Wf007NativeStateContractTest.php');
        if (! is_string($trait) || ! str_contains($trait, '$this->db->transBegin()')
            || ! is_string($wf007) || ! str_contains($wf007, "->where('status !=', 'ACTIVE')")
            || ! str_contains($wf007, "->update(['status' => 'ACTIVE'])")) {
            throw new RuntimeException('03C-04 STOP: transaction isolation or broad teardown risk contract changed.');
        }
        $numberingTables = \ScratchNumberingPilotPolicy::TABLES;
        $expectedTables = [
            'dinktech_auto_numbering_counters', 'dinktech_auto_numbering_series',
            'dinktech_organizations', 'dinktech_products', 'dinktech_resources',
        ];
        if ($numberingTables !== $expectedTables || \ScratchNumberingPilotPolicy::TARGET !== self::TARGET
            || \ScratchNumberingPilotPolicy::PREFIX !== self::PREFIX) {
            throw new RuntimeException('03C-04 STOP: known canary ownership scope changed.');
        }
        \ScratchNumberingPilotPolicy::assertSources($backend);

        return [
            'status' => '03C04A_SOURCE_PINNED_PLAN_PASS',
            'target' => self::TARGET,
            'prefix' => self::PREFIX,
            'reviewed_candidate_classes' => count($pinnedCandidates),
            'candidate_total_methods' => 54,
            'rollback_verified_separately' => 47,
            'scratch_required' => 7,
            'numbering_methods' => self::NUMBERING_METHODS,
            'product_workflow_methods' => self::WORKFLOW_METHODS,
            'numbering_canary_owned_tables' => $numberingTables,
            'requirements_before_numbering_phpunit' => [
                'scratch-only PHPUnit bootstrap with authenticated DML-only identity',
                'method-scoped transaction-free isolation for exactly five Numbering tests',
                'run-specific owned-row marker for every persisted root fixture',
                'exact-PK and FK-dependent counter/Product recovery and cleanup',
                'failure quarantine and clean full-schema postflight',
            ],
            'requirements_before_workflow_phpunit' => [
                'dedicated ledger for ResourceActionSeeder and authorization/organization fixtures',
                'replace WF007 teardown full-table status update with owned-fixture cleanup',
                'allow independent Product snapshot transaction without outer PHPUnit rollback',
                'source-pinned exact-method filter, full before/after attestation',
            ],
            'database_connections' => 0,
            'sql_statements' => 0,
            'writes_executed' => 0,
            'real_phpunit_tests_executed' => 0,
            'pilot_authorized' => false,
            'cleanup_authorized' => false,
            'method_promotion_authorized' => false,
        ];
    }
}
