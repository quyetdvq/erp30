<?php

declare(strict_types=1);

/**
 * HF05 SQL/PBAC stress + failure-injection test. Entirely offline:
 * the fake connection has SELECT-only builders and cannot access MariaDB.
 */
namespace CodeIgniter\Database {
    class BaseConnection {}
}

namespace App\Modules\Access {
    class UnifiedAccessService
    {
        public function canView(string $type, array $row): bool
        {
            if ($type !== 'resource') throw new \RuntimeException('Wrong PBAC type');
            if (($row['fault'] ?? null) === 'view') throw new \RuntimeException('PBAC VIEW failure');
            return ($row['view'] ?? false) === true;
        }

        public function decideMutation(string $type, string $action, array $row): array
        {
            if ($type !== 'resource' || $action !== 'configure_numbering') {
                throw new \RuntimeException('Wrong PBAC mutation');
            }
            if (($row['fault'] ?? null) === 'configure') throw new \RuntimeException('PBAC mutation failure');
            return ['allowed' => ($row['configure'] ?? false) === true];
        }
    }
}

namespace {
    require_once __DIR__ . '/../app/Exceptions/DomainException.php';
    require_once __DIR__ . '/../app/Modules/Numbering/Configuration/NumberingRules.php';
    require_once __DIR__ . '/../app/Modules/Numbering/Configuration/NumberingOverview.php';
    require_once __DIR__ . '/../app/Modules/Numbering/Configuration/NumberingService.php';

    use App\Modules\Access\UnifiedAccessService;
    use App\Modules\Numbering\Configuration\NumberingService;
    use CodeIgniter\Database\BaseConnection;

    final class NumHF05Result
    {
        public function __construct(private array $rows) {}
        public function getResultArray(): array { return $this->rows; }
    }

    final class NumHF05Builder
    {
        private array $ids = [];
        private array $orders = [];

        public function __construct(private NumHF05Connection $db, private string $table) {}

        public function orderBy(string $field, string $direction): self
        {
            if (!in_array($field, ['id', 'type'], true) || $direction !== 'ASC') {
                throw new \RuntimeException('Unexpected query ordering');
            }
            $this->orders[] = $field;
            return $this;
        }

        public function whereIn(string $field, array $ids): self
        {
            if ($this->table !== 'auto_numbering_series' || $field !== 'resource_id'
                || $ids === [] || count($ids) > 200) {
                throw new \RuntimeException('Series must use a bounded resource_id whereIn');
            }
            foreach ($ids as $id) {
                if (!is_string($id) || preg_match('/^[1-9][0-9]*$/D', $id) !== 1) {
                    throw new \RuntimeException('Invalid authorized Resource ID in SQL');
                }
            }
            $this->ids = $ids;
            return $this;
        }

        public function get(): NumHF05Result|false
        {
            $this->db->calls[] = ['table' => $this->table, 'ids' => $this->ids, 'order' => $this->orders];
            $ordinal = count(array_filter($this->db->calls, fn (array $row): bool => $row['table'] === $this->table));
            if (($this->db->failOn[$this->table] ?? 0) === $ordinal) return false;

            $rows = $this->db->tables[$this->table];
            if ($this->table === 'resources') {
                if ($this->orders !== ['type', 'id']) throw new \RuntimeException('Unordered Resource catalog');
            } else {
                if ($this->orders !== ['id']) throw new \RuntimeException('Unordered Numbering Series');
                $rows = array_values(array_filter($rows, fn (array $row): bool =>
                    in_array((string) ($row['resource_id'] ?? ''), $this->ids, true)));
            }
            usort($rows, function (array $a, array $b): int {
                foreach ($this->orders as $field) {
                    $cmp = $field === 'id'
                        ? ((int) $a[$field] <=> (int) $b[$field])
                        : strcmp((string) $a[$field], (string) $b[$field]);
                    if ($cmp !== 0) return $cmp;
                }
                return 0;
            });
            return new NumHF05Result($rows);
        }
    }

    final class NumHF05Connection extends BaseConnection
    {
        public array $calls = [];
        /** @var array<string,int> Fail on ordinal SELECT per table. */
        public array $failOn = [];

        public function __construct(public array $tables) {}
        public function table(string $table): NumHF05Builder
        {
            if (!in_array($table, ['resources', 'auto_numbering_series'], true)) {
                throw new \RuntimeException('Unexpected table/possible write path');
            }
            return new NumHF05Builder($this, $table);
        }
    }

    $count = 0;
    $ok = static function (bool $condition, string $label) use (&$count): void {
        if (!$condition) throw new \RuntimeException('HF05 STRESS FAIL: ' . $label);
        ++$count;
        echo 'PASS: ' . $label . PHP_EOL;
    };
    $reject = static function (callable $action, string $class, string $message, string $label) use ($ok): void {
        try {
            $action();
        } catch (\Throwable $caught) {
            $ok($caught instanceof $class && str_contains($caught->getMessage(), $message), $label);
            return;
        }
        $ok(false, $label . ' did not fail closed');
    };
    $make = static function (array $resources, array $series): array {
        $db = new NumHF05Connection(['resources' => $resources, 'auto_numbering_series' => $series]);
        $service = (new \ReflectionClass(NumberingService::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(NumberingService::class, 'db'))->setValue($service, $db);
        (new \ReflectionProperty(NumberingService::class, 'access'))->setValue($service, new UnifiedAccessService());
        (new \ReflectionProperty(NumberingService::class, 'actorId'))->setValue($service, '1');
        return [$service, $db];
    };
    $resource = static fn (int $id, string $type = 'PRODUCT', bool $view = true, bool $configure = true): array => [
        'id' => (string) $id, 'type' => $type, 'name' => 'Resource ' . $id,
        'domain' => 'SYSTEM', 'status' => 'ACTIVE', 'view' => $view,
        'configure' => $configure, 'private_marker' => 'NEVER_EXPOSE',
    ];
    $series = static fn (int $id, int $owner): array => [
        'id' => (string) $id, 'resource_id' => (string) $owner,
        'series_code' => 'S' . $id, 'name' => 'Series ' . $id,
        'scope_type' => 'GROUP', 'pattern' => 'S-{SEQ:6}', 'reset_policy' => 'NEVER',
        'generation_mode' => 'AUTO_REQUIRED', 'start_number' => 1,
        'increment_by' => 1, 'is_default' => 0, 'status' => 'ACTIVE',
    ];

    // Build a deliberately unsorted catalog with exactly 405 permitted Resources.
    // Unpermitted IDs have legitimate series in the fake DB to prove SQL isolation.
    $allResources = [];
    $allSeries = [];
    for ($id = 410; $id >= 1; --$id) {
        $allowed = $id <= 405;
        $allResources[] = $resource($id, $id <= 300 ? 'PRODUCT' : 'WORKFLOW', $allowed, $allowed);
        // A pair of unsorted series per authorized Resource verifies both SQL
        // ID ordering and projection grouping after three independent chunks.
        $allSeries[] = $series($id * 100 + 2, $id);
        $allSeries[] = $series($id * 100 + 1, $id);
    }
    [$service, $db] = $make($allResources, $allSeries);
    $result = $service->overview();
    $queries = array_values(array_filter($db->calls, fn (array $row): bool => $row['table'] === 'auto_numbering_series'));
    $ok(count($db->calls) === 4, '405 Resources use exactly 1 catalog SELECT + 3 batched series SELECTs');
    $ok(count($queries) === 3, 'No N+1 series query under a large Resource catalog');
    $ok(array_map(fn (array $q): int => count($q['ids']), $queries) === [200, 200, 5],
        'SQL WHERE IN batches enforce a maximum of 200 authorized IDs');
    $ok(count(array_unique(array_merge(...array_column($queries, 'ids')))) === 405,
        'SQL batches cover all authorized Resource IDs exactly once');
    $ok(!in_array('406', array_merge(...array_column($queries, 'ids')), true),
        'Denied Resource ID never enters batched SQL');
    $ok(count($result['resources']) === 405 && count($result['series']) === 810,
        'Complete authorized response without backend pagination/truncation');
    $ok($result['resources'][0]['id'] === '1' && $result['resources'][404]['id'] === '405',
        'Resource catalog uses stable type/id ordering');
    $ok(array_column(array_slice($result['series'], 0, 4), 'id') === ['101', '102', '201', '202'],
        'Series ordered by Resource followed by numeric series ID');
    $ok($result['series'][808]['resource_id'] === '405',
        'Last authorized Resource series survives the 3rd batch');
    $ok(!in_array('406', array_column($result['series'], 'resource_id'), true),
        'Unauthorized series never appear in output');
    $ok(!array_key_exists('private_marker', $result['resources'][0]),
        'Response Resource fields use a strict allowlist');
    $ok($result['series'][0]['start_number'] === '1' && $result['series'][0]['is_default'] === false,
        'Overview serialization equals existing NumberingRules visible contract');
    $ok($db->calls[0]['table'] === 'resources', 'Server obtains authoritative Resource IDs first');
    $ok(array_reduce($db->calls, fn (bool $acc, array $row): bool =>
        $acc && in_array($row['table'], ['resources', 'auto_numbering_series'], true), true),
        'Every backend DB operation is an authorized read');

    [$none, $dbNone] = $make([$resource(2, 'PRODUCT', true, false), $resource(3, 'WORKFLOW', false, true)],
        [$series(21, 2), $series(31, 3)]);
    $ok($none->overview() === ['resources' => [], 'series' => []],
        'Disallowed configure and disallowed VIEW are both excluded');
    $ok(count($dbNone->calls) === 1, 'No series SELECT for a fully denied actor');

    [$one, $dbOne] = $make([$resource(1, 'PRODUCT', true, false), $resource(2)], [$series(11, 1), $series(21, 2)]);
    $ok(array_column($one->overview()['series'], 'resource_id') === ['2'],
        'PBAC decision applies to each persisted Resource before any series read');
    $ok($dbOne->calls[1]['ids'] === ['2'], 'Only permitted IDs reach SQL when actor has mixed grants');

    [$faultView] = $make([[...$resource(1), 'fault' => 'view']], [$series(11, 1)]);
    $reject(static fn () => $faultView->overview(), \RuntimeException::class, 'PBAC VIEW failure',
        'PBAC VIEW evaluation failure rejects the overview');
    [$faultConfigure] = $make([[...$resource(1), 'fault' => 'configure']], [$series(11, 1)]);
    $reject(static fn () => $faultConfigure->overview(), \RuntimeException::class, 'PBAC mutation failure',
        'PBAC configure evaluation failure rejects the overview');

    [$failCatalog, $dbCatalog] = $make([$resource(1)], [$series(11, 1)]);
    $dbCatalog->failOn['resources'] = 1;
    $reject(static fn () => $failCatalog->overview(), \RuntimeException::class, 'catalog read failed',
        'Resource SQL failure fails closed before any serialization');
    $ok(count($dbCatalog->calls) === 1, 'Failed Resource SQL never triggers series query');

    [$failFirst, $dbFirst] = $make([$resource(1)], [$series(11, 1)]);
    $dbFirst->failOn['auto_numbering_series'] = 1;
    $reject(static fn () => $failFirst->overview(), \RuntimeException::class, 'series read failed',
        'Numbering SQL failure rejects the entire overview');
    $ok(count($dbFirst->calls) === 2, 'Failed first series batch does not retry indefinitely');

    [$failThird, $dbThird] = $make($allResources, $allSeries);
    $dbThird->failOn['auto_numbering_series'] = 3;
    $reject(static fn () => $failThird->overview(), \RuntimeException::class, 'series read failed',
        'Last-batch SQL failure cannot return a partial 400-Resource overview');
    $ok(count($dbThird->calls) === 4, 'Last-batch failure stops after bounded three queries');

    // A mis-scoped or inconsistent SELECT may never leak raw Resource data.
    foreach ([
        'cross-resource' => [$resource(1), $resource(2, 'WORKFLOW', false, false)],
        'duplicate-id' => [$resource(1), $resource(2, 'WORKFLOW')],
    ] as $kind => $catalog) {
        $raw = $kind === 'cross-resource'
            ? [$series(11, 1), $series(22, 2)]
            : [$series(11, 1), $series(11, 2)];
        [$bad] = $make($catalog, $raw);
        if ($kind === 'cross-resource') {
            // The strict fake SQL filters out hidden IDs. Force an incorrectly
            // routed raw row at projection level to test the defensive boundary.
            $reject(static fn () => \App\Modules\Numbering\Configuration\NumberingOverview::assemble(
                $catalog, static fn (array $r): bool => ($r['view'] ?? false) && ($r['configure'] ?? false),
                static fn (array $ids): array => $raw), \RuntimeException::class, 'cross-Resource',
                'Projection rejects a series that bypasses scoped SQL');
        } else {
            $reject(static fn () => $bad->overview(), \RuntimeException::class, 'cross-Resource',
                'Duplicate series ID across batches/Resources rejects the entire response');
        }
    }

    echo 'TOTAL: ' . $count . ' HF05 overview SQL/PBAC/failure-injection assertions passed.' . PHP_EOL;
}
