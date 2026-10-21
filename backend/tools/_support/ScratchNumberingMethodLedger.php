<?php

declare(strict_types=1);

/**
 * 03C-04B: reconstruct a five-table, run-specific ownership ledger using
 * explicit fixture markers and FK proofs. It cannot write SQL. Unknown rows
 * or an unexpected counter/identifier fail closed before any deletion.
 */
final class ScratchNumberingMethodLedger
{
    /** @var array<string,array{resources:int,organizations:int,auto_numbering_series:int,products:int,auto_numbering_counters:int,codes:list<string>,counter:int|null}> */
    private const EXPECTED = [
        'P1AutoNumberingMandatoryRegressionTest::testProductContractGlobalNamespaceGenerationAndAtomicRollback' => [
            'resources' => 1, 'organizations' => 1, 'auto_numbering_series' => 2,
            'products' => 3, 'auto_numbering_counters' => 1,
            'codes' => ['100', '105', 'MANUAL-001'], 'counter' => 105,
        ],
        'P1Num005ResourceIdentifierAdapterTest::testFacadeRejectsOversizedGeneratedCandidateBeforeMutation' => [
            'resources' => 1, 'organizations' => 1, 'auto_numbering_series' => 1,
            'products' => 0, 'auto_numbering_counters' => 0, 'codes' => [], 'counter' => null,
        ],
        'P1Num005ResourceIdentifierAdapterTest::testFacadeDelegatesGeneratedCandidateAndAuthoritativeScopeToAdapter' => [
            'resources' => 1, 'organizations' => 1, 'auto_numbering_series' => 1,
            'products' => 0, 'auto_numbering_counters' => 1, 'codes' => [], 'counter' => 7,
        ],
        'P1Num006ProductIdentifierAdapterTest::testProductAdapterUsesGlobalOccupancyInsideAutoNumberTransaction' => [
            'resources' => 1, 'organizations' => 1, 'auto_numbering_series' => 1,
            'products' => 2, 'auto_numbering_counters' => 1, 'codes' => ['001', '002'], 'counter' => 2,
        ],
        'P1Num006ProductIdentifierAdapterTest::testRollbackDoesNotPersistProductOrCounter' => [
            'resources' => 1, 'organizations' => 1, 'auto_numbering_series' => 1,
            'products' => 0, 'auto_numbering_counters' => 0, 'codes' => [], 'counter' => null,
        ],
    ];

    /** @return array<string,list<array{id:int,marker:int|string}>> */
    public static function discover(ScratchNumberingPilotAudit $audit, string $selected, string $token, bool $phpunitPassed): array
    {
        if (preg_match('/\A[A-F0-9]{16}\z/D', $token) !== 1 || ! isset(self::EXPECTED[$selected])) {
            throw new RuntimeException('03C-04B QUARANTINE: invalid ledger selector/token.');
        }
        $suffix = '-S04B-' . $token;
        $skuPrefix = 'S04B-' . $token . '-';
        $resources = $audit->rows("SELECT id, type, name FROM `dinktech_resources` WHERE RIGHT(name, 22) = '" . $suffix . "'");
        $orgs = $audit->rows("SELECT id, type, parent_id, code FROM `dinktech_organizations` WHERE RIGHT(code, 22) = '" . $suffix . "'");
        $series = $audit->rows("SELECT id, resource_id, series_code FROM `dinktech_auto_numbering_series` WHERE RIGHT(series_code, 22) = '" . $suffix . "'");
        $products = $audit->rows("SELECT id, code, sku FROM `dinktech_products` WHERE LEFT(sku, 22) = '" . $skuPrefix . "'");
        $counters = $audit->rows("SELECT c.id, c.numbering_series_id, c.last_value FROM `dinktech_auto_numbering_counters` c INNER JOIN `dinktech_auto_numbering_series` s ON s.id = c.numbering_series_id WHERE RIGHT(s.series_code, 22) = '" . $suffix . "'");
        return self::verifyRows($selected, $token, $phpunitPassed, $resources, $orgs, $series, $products, $counters);
    }

    /**
     * Pure fail-closed ledger validation, allowing targeted negative tests
     * without connecting to MySQL or executing any DELETE.
     *
     * @param list<array<string,mixed>> $resources
     * @param list<array<string,mixed>> $orgs
     * @param list<array<string,mixed>> $series
     * @param list<array<string,mixed>> $products
     * @param list<array<string,mixed>> $counters
     * @return array<string,list<array{id:int,marker:int|string}>>
     */
    public static function verifyRows(string $selected, string $token, bool $phpunitPassed,
        array $resources, array $orgs, array $series, array $products, array $counters): array
    {
        if (preg_match('/\A[A-F0-9]{16}\z/D', $token) !== 1 || ! isset(self::EXPECTED[$selected])) {
            throw new RuntimeException('03C-04B QUARANTINE: invalid ledger selector/token.');
        }
        $expected = self::EXPECTED[$selected];
        $suffix = '-S04B-' . $token;
        $skuPrefix = 'S04B-' . $token . '-';
        $ledger = [];

        $counts = [
            'resources' => count($resources), 'organizations' => count($orgs),
            'auto_numbering_series' => count($series), 'products' => count($products),
            'auto_numbering_counters' => count($counters),
        ];
        foreach ($counts as $table => $n) {
            if ($n > $expected[$table] || ($phpunitPassed && $n !== $expected[$table])) {
                throw new RuntimeException('03C-04B QUARANTINE: wrong number of method-owned rows.');
            }
        }
        $isNum005 = str_starts_with($selected, 'P1Num005');
        $expectedResourceType = str_contains($selected, 'testFacadeRejectsOversized')
            ? 'num005_length_' : 'num005_generated_';
        foreach ($resources as $resource) {
            $name = (string) ($resource['name'] ?? '');
            $type = (string) ($resource['type'] ?? '');
            if (! str_ends_with($name, $suffix)
                || ($isNum005
                    ? (! str_starts_with($type, $expectedResourceType) || ! str_ends_with($type, strtolower($suffix)))
                    : ($type !== 'product' || $name !== 'Product' . $suffix))) {
                throw new RuntimeException('03C-04B QUARANTINE: resource marker/type mismatch.');
            }
            self::add($ledger, 'dinktech_resources', $resource, $name);
        }
        foreach ($orgs as $org) {
            if (($org['type'] ?? null) !== 'GROUP' || (! array_key_exists('parent_id', $org) || $org['parent_id'] !== null)
                || ! str_ends_with((string) ($org['code'] ?? ''), $suffix)) {
                throw new RuntimeException('03C-04B QUARANTINE: organization proof mismatch.');
            }
            self::add($ledger, 'dinktech_organizations', $org, (string) $org['code']);
        }
        $ownedResourceIds = array_column($resources, 'id');
        $ownedSeriesIds = [];
        foreach ($series as $record) {
            if (! in_array($record['resource_id'], $ownedResourceIds, false)
                || ! str_ends_with((string) ($record['series_code'] ?? ''), $suffix)) {
                throw new RuntimeException('03C-04B QUARANTINE: series foreign key or marker mismatch.');
            }
            self::add($ledger, 'dinktech_auto_numbering_series', $record, (string) $record['series_code']);
            $ownedSeriesIds[] = (int) $record['id'];
        }
        $actualCodes = [];
        foreach ($products as $record) {
            $code = (string) ($record['code'] ?? '');
            $sku = (string) ($record['sku'] ?? '');
            if ($sku !== $skuPrefix . $code || ! in_array($code, $expected['codes'], true)) {
                throw new RuntimeException('03C-04B QUARANTINE: Product SKU/code marker mismatch.');
            }
            $actualCodes[] = $code;
            self::add($ledger, 'dinktech_products', $record, $sku);
        }
        if (count($actualCodes) !== count(array_unique($actualCodes))) {
            throw new RuntimeException('03C-04B QUARANTINE: Product code repeated in owned fixture.');
        }
        if ($phpunitPassed) {
            sort($actualCodes, SORT_STRING);
            $wanted = $expected['codes'];
            sort($wanted, SORT_STRING);
            if ($actualCodes !== $wanted) {
                throw new RuntimeException('03C-04B QUARANTINE: unexpected committed Product code set.');
            }
        }
        foreach ($counters as $counter) {
            if (! in_array((int) ($counter['numbering_series_id'] ?? 0), $ownedSeriesIds, true)
                || $expected['counter'] === null || (int) ($counter['last_value'] ?? -1) !== $expected['counter']) {
                throw new RuntimeException('03C-04B QUARANTINE: unowned or unexpected counter state.');
            }
            self::add($ledger, 'dinktech_auto_numbering_counters', $counter, (int) $counter['numbering_series_id']);
        }
        return $ledger;
    }

    /** @param array<string,list<array{id:int,marker:int|string}>> $ledger */
    private static function add(array &$ledger, string $table, array $record, string|int $marker): void
    {
        $id = (int) ($record['id'] ?? 0);
        if ($id < 1 || $marker === '') {
            throw new RuntimeException('03C-04B QUARANTINE: invalid owned row identity.');
        }
        $ledger[$table][] = ['id' => $id, 'marker' => $marker];
    }
}
