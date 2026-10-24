<?php

declare(strict_types=1);

/**
 * BE-REORG-014K: exercise the REAL Pricing-owned SQL list source with a
 * recording SQL-builder double. No vendor, connection, SQL, Shield or HTTP.
 * Database/PBAC parity remains the responsibility of P1Perf008 on erp_test.
 * Run: php backend/tools/test-be-reorg-014k-price-list-sql-owner.php
 */
namespace CodeIgniter\Database {
    interface ConnectionInterface {}

    final class Be014kResult
    {
        /** @param list<array<string,mixed>> $rows */
        public function __construct(private array $rows) {}
        public function getRowArray(): ?array { return $this->rows[0] ?? null; }
        /** @return list<array<string,mixed>> */
        public function getResultArray(): array { return $this->rows; }
    }

    /** Minimal evaluator for the builder chain used by SqlPriceListListSource. */
    final class BaseBuilder
    {
        /** @var array{children:list<array{op:string,test:mixed}>} */
        private array $root = ['children' => []];
        /** @var list<int> */
        private array $path = [];
        /** @var list<array{field:string,direction:string}> */
        private array $sort = [];
        private ?int $limit = null;
        private int $offset = 0;
        private ?string $selected = null;
        /** @var list<string> */
        public array $events = [];

        public function __construct(private readonly Be014kDb $db) {}

        public function select(string $column): self
        {
            $this->selected = $column;
            $this->events[] = 'select:' . $column;
            return $this;
        }

        public function where(string $key, mixed $value = null, bool $escape = true): self
        {
            $this->events[] = 'where:' . $key;
            if ($key === '1 = 0') {
                return $this->add(static fn (array $row): bool => false);
            }
            if ($key === 'priority <') {
                return $this->add(static fn (array $row): bool => (int) $row['priority'] < (int) $value);
            }
            if ($key === 'status') {
                // Approximate MariaDB case-insensitive collation. HEX below
                // must still enforce byte-exact status equivalence.
                return $this->add(static fn (array $row): bool => strcasecmp((string) $row['status'], (string) $value) === 0);
            }
            if (preg_match("/\AHEX\(status\) = '([0-9A-F]+)'\z/D", $key, $match) === 1) {
                return $this->add(static fn (array $row): bool => strtoupper(bin2hex((string) $row['status'])) === $match[1]);
            }
            if (preg_match('/\AHEX\(status\) IN \((.+)\)\z/D', $key, $match) === 1) {
                preg_match_all("/'([0-9A-F]+)'/", $match[1], $statuses);
                $allowed = $statuses[1];
                return $this->add(static fn (array $row): bool => in_array(strtoupper(bin2hex((string) $row['status'])), $allowed, true));
            }
            throw new \RuntimeException('Unexpected SQL WHERE shape: ' . $key);
        }

        public function whereIn(string $column, array $values): self
        {
            $this->events[] = 'where-in:' . $column;
            if ($column !== 'organization_id') {
                throw new \RuntimeException('Unexpected SQL WHERE IN column');
            }
            return $this->add(static fn (array $row): bool => in_array((int) $row[$column], $values, true));
        }

        public function groupStart(): self { return $this->startGroup('AND'); }
        public function orGroupStart(): self { return $this->startGroup('OR'); }

        public function groupEnd(): self
        {
            if ($this->path === []) {
                throw new \RuntimeException('Unbalanced SQL builder grouping');
            }
            $this->events[] = 'group-end';
            array_pop($this->path);
            return $this;
        }

        public function orderBy(string $column, string $direction): self
        {
            $this->events[] = 'order:' . $column;
            $this->sort[] = ['field' => $column, 'direction' => $direction];
            return $this;
        }

        public function limit(int $limit, int $offset = 0): self
        {
            $this->events[] = 'limit';
            $this->limit = $limit;
            $this->offset = $offset;
            return $this;
        }

        public function countAllResults(): int
        {
            $this->events[] = 'count';
            $this->db->executions[] = 'count';
            return count($this->matchingRows());
        }

        public function get(): Be014kResult|false
        {
            $this->events[] = 'get';
            $this->db->executions[] = $this->selected === 'id' ? 'priority-probe' : 'page';
            if ($this->db->failGet) {
                return false;
            }
            $rows = $this->matchingRows();
            if ($this->sort !== []) {
                usort($rows, function (array $a, array $b): int {
                    foreach ($this->sort as $item) {
                        $cmp = $a[$item['field']] <=> $b[$item['field']];
                        if ($cmp !== 0) {
                            return $item['direction'] === 'DESC' ? -$cmp : $cmp;
                        }
                    }
                    return 0;
                });
            }
            if ($this->limit !== null) {
                $rows = array_slice($rows, $this->offset, $this->limit);
            }
            if ($this->selected === 'id') {
                $rows = array_map(static fn (array $row): array => ['id' => $row['id']], $rows);
            }
            return new Be014kResult($rows);
        }

        /** @param callable(array<string,mixed>):bool $predicate */
        private function add(callable $predicate): self
        {
            $node =& $this->current();
            $node['children'][] = ['op' => 'AND', 'test' => $predicate];
            return $this;
        }

        private function startGroup(string $operator): self
        {
            $this->events[] = $operator === 'OR' ? 'or-group' : 'group';
            $node =& $this->current();
            $node['children'][] = ['op' => $operator, 'test' => ['children' => []]];
            $this->path[] = count($node['children']) - 1;
            return $this;
        }

        /** @return array{children:list<array{op:string,test:mixed}>} */
        private function &current(): array
        {
            $node =& $this->root;
            foreach ($this->path as $index) {
                $node =& $node['children'][$index]['test'];
            }
            return $node;
        }

        /** @param array{children:array} $group */
        private function matches(array $group, array $row): bool
        {
            $result = null;
            foreach ($group['children'] as $element) {
                $matches = is_array($element['test'])
                    ? $this->matches($element['test'], $row)
                    : ($element['test'])($row);
                $result = $result === null ? $matches
                    : ($element['op'] === 'OR' ? $result || $matches : $result && $matches);
            }
            return $result ?? true;
        }

        /** @return list<array<string,mixed>> */
        private function matchingRows(): array
        {
            if ($this->path !== []) {
                throw new \RuntimeException('Unbalanced SQL builder grouping before execution');
            }
            return array_values(array_filter($this->db->rows,
                fn (array $row): bool => $this->matches($this->root, $row)));
        }
    }

    final class Be014kDb implements ConnectionInterface
    {
        /** @var list<BaseBuilder> */
        public array $builders = [];
        /** @var list<string> */
        public array $executions = [];
        public bool $failGet = false;
        /** @param list<array<string,mixed>> $rows */
        public function __construct(public array $rows) {}
        public function table(string $table): BaseBuilder
        {
            if ($table !== 'price_lists') {
                throw new \RuntimeException('Price List SQL touched a foreign table: ' . $table);
            }
            return $this->builders[] = new BaseBuilder($this);
        }
    }
}

namespace {
    use App\Infrastructure\Api\CommonListQueryAdapter;
    use App\Infrastructure\Api\CommonListQueryAllowlist;
    use App\Modules\Pricing\PriceList\Application\SqlPriceListListSource;
    use CodeIgniter\Database\Be014kDb;

    $app = dirname(__DIR__) . '/app/';
    spl_autoload_register(static function (string $class) use ($app): void {
        if (! str_starts_with($class, 'App\\')) { return; }
        $file = $app . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) { require $file; }
    });

    function kAssert(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new \RuntimeException('BE-REORG-014K: ' . $message);
        }
    }
    /** @return list<array<string,mixed>> */
    function kRows(): array
    {
        return [
            ['id' => 1, 'organization_id' => 11, 'status' => 'ACTIVE', 'priority' => 0],
            ['id' => 2, 'organization_id' => 11, 'status' => 'active', 'priority' => 1],
            ['id' => 3, 'organization_id' => 11, 'status' => 'INACTIVE', 'priority' => 2],
            ['id' => 4, 'organization_id' => 12, 'status' => 'ACTIVE', 'priority' => 3],
            ['id' => 5, 'organization_id' => 11, 'status' => 'ACTIVE', 'priority' => 2],
            ['id' => 6, 'organization_id' => 11, 'status' => 'ACTIVE', 'priority' => 7],
        ];
    }
    function kQuery(array $args = []): \App\Infrastructure\Api\CommonListQuery
    {
        return (new CommonListQueryAdapter())->parse($args, new CommonListQueryAllowlist(
            search: ['code', 'name'],
            filters: ['code', 'name', 'organization_id', 'status'],
            sort: ['id', 'code', 'effective_from', 'name', 'organization_id', 'priority', 'status'],
        ));
    }
    function kApproved(): array
    {
        return [
            ['organization_id' => 11, 'status' => 'ACTIVE'],
            ['organization_id' => 11, 'status' => 'INACTIVE'],
        ];
    }
    $passed = 0;
    $check = static function (string $name, callable $run) use (&$passed): void {
        $run(); $passed++;
        echo '  ' . $name . ': PASS' . PHP_EOL;
    };

    $check('Pricing owner autoload and no Shared implementation or alias', static function () use ($app): void {
        $owner = $app . 'Modules/Pricing/PriceList/Application/SqlPriceListListSource.php';
        kAssert(! is_file($app . 'Infrastructure/Api/SqlPriceListListSource.php'), 'Shared Pricing source still exists');
        kAssert((new \ReflectionClass(SqlPriceListListSource::class))->getFileName() === realpath($owner), 'Pricing source not resolved');
        kAssert(! class_exists('App\\Infrastructure\\Api\\SqlPriceListListSource'), 'Old internal class alias still exists');
        $service = (string) file_get_contents($app . 'Modules/Pricing/PriceList/Application/PriceListQueryService.php');
        kAssert(! str_contains($service, 'App\\Infrastructure\\Api\\SqlPriceListListSource'), 'Pricing service still imports Shared owner');
        $test = (string) file_get_contents(dirname(__DIR__) . '/tests/unit/P1Perf008PriceListSqlPaginationTest.php');
        kAssert(str_contains($test, 'use App\\Modules\\Pricing\\PriceList\\Application\\SqlPriceListListSource;'), 'Runtime parity test not retargeted');
        $gate = (string) file_get_contents(dirname(__DIR__) . '/tools/test-be-reorg-014d-price-list.php');
        kAssert(str_contains($gate, 'namespace App\\Modules\\Pricing\\PriceList\\Application {'), '014D fake has wrong namespace');
    });
    $check('Owner/byte-exact State PBAC precedes authorized COUNT and page', static function (): void {
        $db = new Be014kDb(kRows());
        $source = new SqlPriceListListSource($db, kApproved());
        $q = kQuery(['sort' => '-id', 'page_size' => '2', 'page' => '2']);
        kAssert($source->countAuthorized($q) === 4, 'COUNT must exclude foreign owner and lower-case state');
        kAssert(array_column(iterator_to_array($source->fetchAuthorized($q)), 'id') === [3, 1], 'Page must use authorized set before LIMIT');
        kAssert($db->executions === ['count', 'page'], 'COUNT/page should not use extra read or priority probe');
        kAssert(in_array('where:HEX(status) = \'414354495645\'', $db->builders[0]->events, true), 'Missing byte-exact State');
    });
    $check('Authorization buckets disjoin Owner/State pairs correctly', static function (): void {
        $db = new Be014kDb(kRows());
        $source = new SqlPriceListListSource($db, [
            ['organization_id' => 11, 'status' => 'INACTIVE'],
            ['organization_id' => 12, 'status' => 'ACTIVE'],
        ]);
        $q = kQuery();
        kAssert($source->countAuthorized($q) === 2, 'Owner/State bucket OR grouping is incorrect');
        kAssert(array_column(iterator_to_array($source->fetchAuthorized($q)), 'id') === [3, 4], 'Bucket grouping leaks disallowed rows');
    });
    $check('Status and canonical Owner filters apply inside COUNT and page', static function (): void {
        $db = new Be014kDb(kRows());
        $source = new SqlPriceListListSource($db, kApproved());
        $q = kQuery(['filter' => ['status' => 'ACTIVE', 'organization_id' => '11']]);
        kAssert($source->countAuthorized($q) === 3, 'status/owner filters must precede count');
        kAssert(array_column(iterator_to_array($source->fetchAuthorized($q)), 'id') === [1, 5, 6], 'status/owner filters must precede page');
        kAssert($source->countAuthorized(kQuery(['filter' => ['status' => 'active']])) === 0, 'case-insensitive SQL collation bypassed exact filter');
        kAssert($source->countAuthorized(kQuery(['filter' => ['organization_id' => '12']])) === 0, 'caller owner filter bypassed PBAC buckets');
    });
    $check('Empty approved buckets return exact zero without scanning rows', static function (): void {
        $db = new Be014kDb(kRows());
        $source = new SqlPriceListListSource($db, []);
        $q = kQuery();
        kAssert($source->countAuthorized($q) === 0, 'empty proven buckets must produce zero');
        kAssert(iterator_to_array($source->fetchAuthorized($q)) === [], 'empty buckets leaked rows');
        kAssert(in_array('where:1 = 0', $db->builders[0]->events, true), 'deny-all SQL guard missing');
    });
    $check('Unsafe sort/search/noncanonical Owner remains in legacy fallback', static function (): void {
        kAssert(! SqlPriceListListSource::supports(kQuery(['q' => 'milk'])), 'text search entered SQL fast path');
        kAssert(! SqlPriceListListSource::supports(kQuery(['sort' => 'name'])), 'Unicode ordering entered SQL fast path');
        kAssert(! SqlPriceListListSource::supports(kQuery(['filter' => ['organization_id' => '011']])), 'noncanonical owner entered SQL fast path');
        kAssert(! SqlPriceListListSource::supports(kQuery(['filter' => ['code' => 'A']])), 'unsupported filter entered SQL fast path');
    });
    $check('Negative priority triggers one fail-safe probe and id sort still works', static function (): void {
        $rows = kRows(); $rows[0]['priority'] = -1;
        $db = new Be014kDb($rows);
        $source = new SqlPriceListListSource($db, kApproved());
        $priority = kQuery(['sort' => 'priority']);
        kAssert(SqlPriceListListSource::supports($priority), 'priority must pass static query guard');
        kAssert(! $source->canServe($priority), 'negative priority must disable SQL comparator');
        kAssert(! $source->canServe($priority), 'cached priority guard drifted');
        kAssert($db->executions === ['priority-probe'], 'priority guard should probe once per source');
        kAssert($source->canServe(kQuery(['sort' => 'id'])), 'id ordering should still be supported');
    });
    $check('Authorized priority ordering retains stable id tie breaker', static function (): void {
        $db = new Be014kDb(kRows());
        $source = new SqlPriceListListSource($db, kApproved());
        $q = kQuery(['sort' => 'priority', 'page_size' => '2', 'page' => '2']);
        kAssert($source->countAuthorized($q) === 4, 'priority COUNT incorrect');
        kAssert(array_column(iterator_to_array($source->fetchAuthorized($q)), 'id') === [5, 6], 'priority,id order changed');
        kAssert($db->executions === ['priority-probe', 'count', 'page'], 'priority probe must remain cached');
    });
    $check('Malformed scope buckets and broken SQL fetch fail closed', static function (): void {
        $db = new Be014kDb(kRows());
        foreach ([[['organization_id' => 0, 'status' => 'ACTIVE']], [['organization_id' => 11, 'status' => '']],
            [['organization_id' => '11', 'status' => 'ACTIVE']], array_fill(0, 257, ['organization_id' => 11, 'status' => 'ACTIVE'])] as $bucket) {
            try { new SqlPriceListListSource($db, $bucket); }
            catch (\InvalidArgumentException) { continue; }
            throw new \RuntimeException('Malformed PBAC scope accepted');
        }
        $db->failGet = true;
        $source = new SqlPriceListListSource($db, kApproved());
        try { $source->fetchAuthorized(kQuery()); }
        catch (\RuntimeException $error) {
            kAssert($error->getMessage() === 'Price List SQL page lookup failed.', 'SQL failure lost original message');
            return;
        }
        throw new \RuntimeException('SQL failed but source returned a partial page');
    });

    echo "BE-REORG-014K PRICE LIST SQL SOURCE OWNERSHIP PASS: {$passed} checks; actual Pricing class with recording SQL builder. DB/Shield/HTTP NOT_RUN.\n";
}
