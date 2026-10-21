<?php

declare(strict_types=1);

namespace DinkERP\TestTools;

use RuntimeException;

/**
 * 03C-03: Exact, source-pinned rollback-compatible METHODS from the five
 * candidate classes. This is a diagnostic cohort, NOT class promotion.
 *
 * The seven omitted methods require allocator-owned transactions or a
 * transaction-free Product SQL snapshot. They belong to a separate scratch
 * runner and MUST NOT execute inside the erp_test rollback transaction.
 *
 * This helper never accesses a database. UnitNoDropSelection::resolve()
 * must first pin all five candidate source hashes before this plan is used.
 */
final class UnitNoDropRollbackSubset
{
    /** @var array<string,list<string>> */
    private const SCRATCH_ONLY = [
        'P1AutoNumberingMandatoryRegressionTest' => [
            'testProductContractGlobalNamespaceGenerationAndAtomicRollback',
        ],
        'P1Num005ResourceIdentifierAdapterTest' => [
            'testFacadeRejectsOversizedGeneratedCandidateBeforeMutation',
            'testFacadeDelegatesGeneratedCandidateAndAuthoritativeScopeToAdapter',
        ],
        'P1Num006ProductIdentifierAdapterTest' => [
            'testProductAdapterUsesGlobalOccupancyInsideAutoNumberTransaction',
            'testRollbackDoesNotPersistProductOrCounter',
        ],
        'P1Wf006StateAwarePbacAndLocationTransitionsTest' => [
            'testProductSqlPaginationFallsBackToAuthorizationBeforeCounting',
        ],
        'P1Wf007NativeStateContractTest' => [
            'testNativeProductStateDenyFiltersBeforeTotalAndPagination',
        ],
    ];

    /**
     * @param list<string> $sourcePinnedCandidates
     * @return array{filter:string,selected:list<string>,scratch_only:list<string>}
     */
    public static function plan(string $backend, array $sourcePinnedCandidates): array
    {
        $allowed = array_keys(self::SCRATCH_ONLY);
        $provided = $sourcePinnedCandidates;
        sort($allowed, SORT_STRING);
        sort($provided, SORT_STRING);
        if ($provided !== $allowed) {
            throw new RuntimeException('03C-03 STOP: candidate class inventory not exactly source-pinned five.');
        }

        $selected = [];
        $scratch = [];
        $total = 0;
        foreach (self::SCRATCH_ONLY as $class => $excluded) {
            $source = file_get_contents($backend . '/tests/unit/' . $class . '.php');
            if (! is_string($source)
                || preg_match_all('/\bpublic\s+function\s+(test[A-Z][A-Za-z0-9_]*)\s*\(/', $source, $matches) === false) {
                throw new RuntimeException('03C-03 STOP: candidate method source cannot be read.');
            }
            $names = $matches[1];
            if (count($names) !== count(array_unique($names)) || $names === []) {
                throw new RuntimeException('03C-03 STOP: duplicate or absent public test methods.');
            }
            foreach ($excluded as $method) {
                if (! in_array($method, $names, true)) {
                    throw new RuntimeException('03C-03 STOP: scratch-only method missing from source.');
                }
            }
            foreach ($names as $method) {
                $qualified = $class . '::' . $method;
                if (in_array($method, $excluded, true)) {
                    $scratch[] = $qualified;
                } else {
                    $selected[] = $qualified;
                }
                $total++;
            }
        }
        if ($total !== 54 || count($selected) !== 47 || count($scratch) !== 7
            || count(array_unique($selected)) !== 47) {
            throw new RuntimeException('03C-03 STOP: 47/7 method cohort inventory drift.');
        }
        // Positive exact-method filter: no generic exclusion regex that could
        // unintentionally run new/unknown methods. PHPUnit may append a data
        // provider label starting with # or space, but no providers are present.
        $filter = '/(?:^|\\\\)(?:' . implode('|', array_map(
            static fn (string $name): string => preg_quote($name, '/'),
            $selected,
        )) . ')(?:$|[ #])/';
        return ['filter' => $filter, 'selected' => $selected, 'scratch_only' => $scratch];
    }
}
