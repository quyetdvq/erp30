<?php

declare(strict_types=1);

/**
 * BE-PERF-015A — vendor-free SQL path selection and PBAC/count/page parity.
 * Fake CI query builder emulates SQL predicates (including insensitive status
 * comparisons versus byte-exact HEX). It is NOT real MariaDB/Shield evidence.
 * Run: php backend/tools/test-be-perf-015a-master-lists.php
 */
namespace CodeIgniter\Database {
    interface ConnectionInterface {}
    class BaseBuilder {}
    class BaseConnection implements ConnectionInterface
    {
        public int $transDepth = 0;
        public int $begins = 0;
        public int $commits = 0;
        public int $rollbacks = 0;
        public int $counts = 0;
        public int $pages = 0;
        public bool $failPage = false;
        public bool $failCount = false;
        /** @var list<array<string,mixed>> */
        public array $rows = [];
        /** @var list<string> */
        public array $queries = [];
        public function query(string $sql): bool
        {
            $this->queries[] = $sql;
            if ($sql !== 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') {
                throw new \RuntimeException('Unexpected raw SQL: ' . $sql);
            }
            return true;
        }
        public function transBegin(): bool { $this->begins++; $this->transDepth++; return true; }
        public function transStatus(): bool { return true; }
        public function transCommit(): bool { $this->commits++; $this->transDepth--; return true; }
        public function transRollback(): bool { $this->rollbacks++; $this->transDepth--; return true; }
        public function escape(string $value): string { return "'" . str_replace("'", "''", $value) . "'"; }
        public function table(string $table): \Be015aBuilder { return new \Be015aBuilder($this, $table); }
    }
}
namespace CodeIgniter\HTTP\Exceptions {
    class BadRequestException extends \RuntimeException {}
}
namespace App\Modules\Product\ProductGroup {
    final class ProductGroupService
    {
        public int $loads = 0;
        /** @param list<array<string,mixed>> $rows */
        public function __construct(public array $rows) {}
        /** @return list<array<string,mixed>> */
        public function listAll(): array { $this->loads++; return $this->rows; }
    }
}
namespace App\Modules\Product\Uom {
    final class ProductUomMasterService
    {
        public int $loads = 0;
        /** @param list<array<string,mixed>> $rows */
        public function __construct(public array $rows) {}
        /** @return list<array<string,mixed>> */
        public function listAll(): array { $this->loads++; return $this->rows; }
    }
}
namespace {
    $app = dirname(__DIR__) . '/app/';
    spl_autoload_register(static function (string $class) use ($app): void {
        if (! str_starts_with($class, 'App\\')) { return; }
        $file = $app . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) { require $file; }
    });
    if (! function_exists('mb_strtolower')) {
        function mb_strtolower(string $value): string { return strtolower($value); }
    }

    use App\Exceptions\DomainException;
    use App\Infrastructure\Api\CommonListQueryAdapter;
    use App\Infrastructure\Api\CommonListQueryAllowlist;
    use App\Infrastructure\Api\SqlAuthorizedBucketListSource;
    use App\Infrastructure\Mutation\OperationContext;
    use App\Infrastructure\Time\EvaluationTime;
    use App\Modules\Product\Lookup\Application\Ports\ProductMasterListSqlAuthorizationPort;
    use App\Modules\Product\Lookup\Application\ProductMasterListSqlFastPath;
    use App\Modules\Product\ProductGroup\Application\Ports\ProductGroupListAuthorizationPort;
    use App\Modules\Product\Uom\Application\Ports\ProductUomListAuthorizationPort;
    use App\Modules\Product\ProductGroup\Application\ProductGroupListQueryService;
    use App\Modules\Product\Uom\Application\ProductUomListQueryService;
    use App\Modules\Product\ProductGroup\ProductGroupService;
    use App\Modules\Product\Uom\ProductUomMasterService;
    use CodeIgniter\Database\BaseConnection;

    final class Be015aBuilder extends \CodeIgniter\Database\BaseBuilder
    {
        /** @var list<array{logic:string,predicates:list<array{logic:string,check:\Closure}>}> */
        private array $stack = [['logic' => 'AND', 'predicates' => []]];
        private ?int $limit = null;
        private int $offset = 0;
        private string $orderField = 'id';
        private string $direction = 'ASC';
        public function __construct(private BaseConnection $db, private string $table) {
            if (! in_array($table, ['product_groups', 'product_uoms'], true)) {
                throw new \RuntimeException('Unexpected SQL table: ' . $table);
            }
        }
        private function add(\Closure $check, string $logic = 'AND'): self {
            $last = count($this->stack) - 1;
            $this->stack[$last]['predicates'][] = ['logic' => $logic, 'check' => $check];
            return $this;
        }
        public function groupStart(): self { $this->stack[] = ['logic' => 'AND', 'predicates' => []]; return $this; }
        public function orGroupStart(): self { $this->stack[] = ['logic' => 'OR', 'predicates' => []]; return $this; }
        public function groupEnd(): self {
            if (count($this->stack) < 2) { throw new \LogicException('Orphan SQL groupEnd'); }
            $child = array_pop($this->stack);
            return $this->add(self::predicate($child), $child['logic']);
        }
        private static function predicate(array $group): \Closure {
            return static function (array $row) use ($group): bool {
                $value = false;
                foreach ($group['predicates'] as $i => $p) {
                    $item = ($p['check'])($row);
                    $value = $i === 0 ? $item : ($p['logic'] === 'OR' ? ($value || $item) : ($value && $item));
                }
                return count($group['predicates']) === 0 || $value;
            };
        }
        public function whereIn(string $field, array $values): self {
            return $this->add(static function (array $row) use ($field, $values): bool {
                // MariaDB CI collation is usually case-insensitive; the real
                // HEX(status) check must prevent unauthorized case collisions.
                if ($field === 'status') {
                    return in_array(strtolower((string) ($row[$field] ?? '')), array_map('strtolower', $values), true);
                }
                return in_array((string) ($row[$field] ?? ''), array_map('strval', $values), true);
            });
        }
        public function where(string $expression, mixed $value = null, bool $escape = true): self {
            if ($expression === '1 = 0') { return $this->add(static fn (array $row): bool => false); }
            if (preg_match('/\A([a-z_]+) IS NULL\z/', $expression, $m) === 1) {
                return $this->add(static fn (array $row): bool => ($row[$m[1]] ?? null) === null);
            }
            if (preg_match("/\\AHEX\\(status\\) = '([0-9A-F]*)'\\z/", $expression, $m) === 1) {
                return $this->add(static fn (array $row): bool => strtoupper(bin2hex((string) $row['status'])) === $m[1]);
            }
            if (preg_match('/\AHEX\(status\) IN \((.*)\)\z/', $expression, $m) === 1) {
                preg_match_all("/'([0-9A-F]*)'/", $m[1], $hexes);
                return $this->add(static fn (array $row): bool => in_array(strtoupper(bin2hex((string) $row['status'])), $hexes[1], true));
            }
            throw new \RuntimeException('Unknown SQL predicate: ' . $expression);
        }
        public function orWhere(string $expr, mixed $val = null, bool $escape = true): self {
            if (! preg_match('/\A([a-z_]+) IS NULL\z/', $expr, $m)) { throw new \RuntimeException($expr); }
            return $this->add(static fn (array $row): bool => ($row[$m[1]] ?? null) === null, 'OR');
        }
        public function orderBy(string $field, string $direction): self {
            $this->orderField = $field;
            $this->direction = $direction;
            return $this;
        }
        public function limit(int $limit, int $offset): self { $this->limit = $limit; $this->offset = $offset; return $this; }
        /** @return list<array<string,mixed>> */
        private function filtered(): array {
            if (count($this->stack) !== 1) { throw new \LogicException('Unbalanced SQL groups'); }
            $pred = self::predicate($this->stack[0]);
            return array_values(array_filter($this->db->rows, $pred));
        }
        public function countAllResults(): int {
            $this->db->counts++;
            if ($this->db->failCount) { throw new \RuntimeException('Simulated count failure'); }
            return count($this->filtered());
        }
        public function get(): Be015aResult {
            $this->db->pages++;
            if ($this->db->failPage) { throw new \RuntimeException('Simulated page failure'); }
            $rows = $this->filtered();
            $field = $this->orderField;
            usort($rows, static fn (array $a, array $b): int => ((int) $a[$field] <=> (int) $b[$field]));
            if ($this->direction === 'DESC') { $rows = array_reverse($rows); }
            return new Be015aResult($this->limit === null ? $rows : array_slice($rows, $this->offset, $this->limit));
        }
    }
    final class Be015aResult
    {
        /** @param list<array<string,mixed>> $rows */
        public function __construct(private array $rows) {}
        /** @return list<array<string,mixed>> */
        public function getResultArray(): array { return $this->rows; }
    }
    final class Be015aPolicy implements ProductGroupListAuthorizationPort, ProductUomListAuthorizationPort, ProductMasterListSqlAuthorizationPort
    {
        public bool $state = false;
        public bool $baseAllowed = true;
        /** @var list<array{status:string}>|null */
        public ?array $buckets = [];
        public int $baseCalls = 0;
        public int $bucketCalls = 0;
        public int $batchCalls = 0;
        public int $rowCalls = 0;
        public int $rechecks = 0;
        /** @var list<int> */
        public array $operationIdentities = [];
        public bool $forceRecheckDeny = false;
        public bool $failRecheck = false;
        public function requiresRowStateAuthorization(): bool { return $this->state; }
        public function authorizeBaseView(): void {
            $this->baseCalls++;
            if (! $this->baseAllowed) { throw new DomainException('MASTER_AUTHORIZATION_DENIED'); }
        }
        public function rowVisible(array $row): bool {
            $this->rowCalls++;
            return in_array($row['status'], array_column($this->buckets ?? [], 'status'), true);
        }
        public function operation(): OperationContext {
            return new OperationContext(10, new EvaluationTime(new \DateTimeImmutable('2026-10-22T01:02:03Z')), 'test-015a', false);
        }
        public function authorizedBuckets(OperationContext $operation): ?array {
            $this->bucketCalls++;
            $this->operationIdentities[] = spl_object_id($operation);
            return $this->buckets;
        }
        public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed {
            $this->batchCalls++;
            $this->operationIdentities[] = spl_object_id($operation);
            return $read();
        }
        public function recheckRowVisible(OperationContext $operation, array $row): bool {
            $this->rechecks++;
            $this->operationIdentities[] = spl_object_id($operation);
            if ($this->failRecheck) { throw new DomainException('PBAC_RESOLUTION_FAILED'); }
            return ! $this->forceRecheckDeny && in_array($row['status'], array_column($this->buckets ?? [], 'status'), true);
        }
    }
    final class Be015aAssert
    {
        public static int $cases = 0;
        public static function run(string $name, callable $check): void { $check(); self::$cases++; echo '  ' . $name . ': PASS' . PHP_EOL; }
        public static function same(mixed $expected, mixed $actual, string $message): void {
            if ($expected !== $actual) { throw new \RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true)); }
        }
        public static function true(bool $value, string $message): void { if (! $value) { throw new \RuntimeException($message); } }
    }
    /** @return list<array<string,mixed>> */
    function be015aRows(bool $groups): array {
        $rows = [];
        foreach (range(1, 48) as $id) {
            $status = $id % 11 === 0 ? 'active' : ($id % 3 === 0 ? 'INACTIVE' : 'ACTIVE');
            $row = ['id' => $id, 'code' => sprintf('C%02d', $id), 'name' => 'Master ' . $id,
                'status' => $status, 'description' => null];
            if ($groups) { $row['parent_id'] = $id === 1 ? null : ($id % 4 === 0 ? 1 : null); }
            $rows[] = $row;
        }
        return $rows;
    }
    /** @param array<string,mixed> $query */
    function be015aExecute(bool $groups, bool $optimized, array $query, bool $state = false, array|false|null $buckets = null, bool $nested = false, bool $forceRecheckDeny = false): array {
        $rows = be015aRows($groups);
        $db = new BaseConnection();
        $db->rows = $rows;
        $policy = new Be015aPolicy();
        $policy->state = $state;
        $policy->buckets = $buckets === false ? null : ($buckets ?? [['status' => 'ACTIVE']]);
        $policy->forceRecheckDeny = $forceRecheckDeny;
        if ($nested) { $db->transDepth = 1; }
        $sql = new ProductMasterListSqlFastPath($db, $groups ? 'product_group' : 'product_uom', $policy,
            fn (): bool => $policy->requiresRowStateAuthorization(), fn () => $policy->authorizeBaseView());
        $service = $groups ? new ProductGroupService($rows) : new ProductUomMasterService($rows);
        $runner = $groups
            ? new ProductGroupListQueryService($service, $policy, null, $optimized ? $sql : null)
            : new ProductUomListQueryService($service, $policy, null, $optimized ? $sql : null);
        $result = $runner->list($query);
        return ['result' => $result, 'db' => $db, 'service' => $service, 'policy' => $policy];
    }

    foreach ([true, false] as $group) {
        $label = $group ? 'Group' : 'UOM';
        Be015aAssert::run($label . ' base id paging equals legacy without listAll', static function () use ($group): void {
            $query = ['page' => '2', 'page_size' => '7'];
            $original = be015aExecute($group, false, $query);
            $optimized = be015aExecute($group, true, $query);
            Be015aAssert::same($original['result'], $optimized['result'], 'default list parity');
            Be015aAssert::same(0, $optimized['service']->loads, 'SQL path must bypass listAll');
            Be015aAssert::same(1, $optimized['policy']->baseCalls, 'mandatory base view');
            Be015aAssert::same(1, $optimized['db']->counts, 'COUNT in SQL');
            Be015aAssert::same(1, $optimized['db']->pages, 'page in SQL');
            Be015aAssert::same(1, $optimized['db']->commits, 'read snapshot committed');
        });
        Be015aAssert::run($label . ' state bucket before COUNT and page', static function () use ($group): void {
            $query = ['page_size' => '6', 'page' => '2', 'sort' => '-id'];
            $original = be015aExecute($group, false, $query, true);
            $optimized = be015aExecute($group, true, $query, true);
            Be015aAssert::same($original['result'], $optimized['result'], 'authorized status parity');
            Be015aAssert::same(1, $optimized['policy']->bucketCalls, 'bucket compilation once');
            Be015aAssert::same(1, $optimized['policy']->batchCalls, 'single read batch');
            Be015aAssert::true($optimized['policy']->rechecks <= 6, 'page recheck must be bounded');
            Be015aAssert::same(0, $optimized['service']->loads, 'no full master list');
            Be015aAssert::same(0, $optimized['policy']->rowCalls, 'no per-row decision across entire table');
            Be015aAssert::same(0, $optimized['policy']->baseCalls, 'no state PBAC bypass');
            Be015aAssert::same(1, count(array_unique($optimized['policy']->operationIdentities)), 'one OperationContext across bucket and page');
        });
        Be015aAssert::run($label . ' two allowed Status buckets match exact PHP, excluding lowercase', static function () use ($group): void {
            $buckets = [['status' => 'ACTIVE'], ['status' => 'INACTIVE']];
            $query = ['page' => '2', 'page_size' => '5', 'filter' => ['status' => ['ACTIVE', 'INACTIVE']]];
            $old = be015aExecute($group, false, $query, true, $buckets);
            $optimized = be015aExecute($group, true, $query, true, $buckets);
            Be015aAssert::same($old['result'], $optimized['result'], 'multiple bucket parity');
            Be015aAssert::same(0, $optimized['service']->loads, 'multiple statuses stay in SQL');
        });
        Be015aAssert::run($label . ' exact SQL Status filter is applied after PBAC buckets', static function () use ($group): void {
            foreach (['ACTIVE', 'active', 'INACTIVE'] as $filter) {
                $query = ['filter' => ['status' => $filter], 'page_size' => '5'];
                $old = be015aExecute($group, false, $query, true);
                $optimized = be015aExecute($group, true, $query, true);
                Be015aAssert::same($old['result'], $optimized['result'], 'exact filter parity: ' . $filter);
                Be015aAssert::same(0, $optimized['service']->loads, 'canonical status filter can use SQL');
            }
        });
        if ($group) {
            Be015aAssert::run('Group exact NULL/parent filters retain persisted hierarchy', static function (): void {
                foreach ([['filter' => ['parent_id' => '']],
                    ['filter' => ['parent_id' => '1']],
                    ['filter' => ['parent_id' => ['', '1']]]] as $query) {
                    $old = be015aExecute(true, false, $query, true);
                    $optimized = be015aExecute(true, true, $query, true);
                    Be015aAssert::same($old['result'], $optimized['result'], 'parent/null filter parity');
                    Be015aAssert::same(0, $optimized['service']->loads, 'canonical parent filter uses SQL');
                }
            });
        }
        Be015aAssert::run($label . ' state case-fold collision cannot disclose rows', static function () use ($group): void {
            $value = be015aExecute($group, true, ['page_size' => '48'], true);
            foreach ($value['result']['items'] as $row) {
                Be015aAssert::same('ACTIVE', $row['status'], 'HEX(status) is exact');
            }
        });
        Be015aAssert::run($label . ' no approved State yields zero total', static function () use ($group): void {
            $value = be015aExecute($group, true, [], true, []);
            Be015aAssert::same('0', $value['result']['total'], 'state zero count');
            Be015aAssert::same([], $value['result']['items'], 'no items');
            Be015aAssert::same(0, $value['policy']->rechecks, 'no page to recheck');
        });
        Be015aAssert::run($label . ' uncertain bucket returns to full exact row scan', static function () use ($group): void {
            $value = be015aExecute($group, true, [], true, false);
            Be015aAssert::same(1, $value['service']->loads, 'unsafe bucket must materialize all rows');
            Be015aAssert::same(0, $value['db']->counts, 'no SQL count on uncertain buckets');
        });
        Be015aAssert::run($label . ' noncanonical/text query retains legacy compare', static function () use ($group): void {
            $queries = [['q' => 'mas'], ['sort' => 'name'], ['filter' => ['code' => 'C01']],
                ['filter' => ['name' => 'Master 1']]];
            if ($group) { $queries[] = ['filter' => ['parent_id' => '01']]; }
            foreach ($queries as $query) {
                $value = be015aExecute($group, true, $query, true);
                Be015aAssert::same(1, $value['service']->loads, 'fallback must materialize: ' . json_encode($query));
                Be015aAssert::same(0, $value['db']->counts, 'no unsafe SQL count');
            }
        });
        Be015aAssert::run($label . ' outer transaction refuses nested read snapshot', static function () use ($group): void {
            $value = be015aExecute($group, true, [], true, null, true);
            Be015aAssert::same(1, $value['service']->loads, 'nested fallback');
            Be015aAssert::same(0, $value['db']->begins, 'must not nest read transaction');
        });
        Be015aAssert::run($label . ' inconsistent page PBAC recheck returns to exact fallback', static function () use ($group): void {
            $value = be015aExecute($group, true, [], true, null, false, true);
            Be015aAssert::same(1, $value['service']->loads, 'mismatch must not return original count');
            Be015aAssert::same(1, $value['db']->commits, 'snapshot closes before fallback');
        });
    }
    Be015aAssert::run('feature flag is default OFF, no source-only proof enables production', static function (): void {
        $facade = file_get_contents(dirname(__DIR__) . '/app/Services/Api050ProductCatalogService.php');
        Be015aAssert::true(str_contains($facade, "getenv('ERP_PRODUCT_MASTER_LIST_SQL_OPT_IN') !== '1'"), 'guarded rollout required');
    });
    Be015aAssert::run('compiler accepts only authoritative MASTER for Group/UOM', static function (): void {
        $compiler = file_get_contents(dirname(__DIR__) . '/app/Authorization/EffectiveListBucketCompiler.php');
        Be015aAssert::true(str_contains($compiler, "'product_group' => ['table' => 'product_groups', 'domain' => 'MASTER', 'scoped' => false]"), 'Group compilation scope');
        Be015aAssert::true(str_contains($compiler, "'product_uom' => ['table' => 'product_uoms', 'domain' => 'MASTER', 'scoped' => false]"), 'UOM compilation scope');
    });
    Be015aAssert::run('SQL source rejects null unrestricted buckets for unrelated resources', static function (): void {
        try { new SqlAuthorizedBucketListSource(new BaseConnection(), 'department', null); }
        catch (\InvalidArgumentException) { return; }
        throw new \RuntimeException('Department must not receive unrestricted State SQL access');
    });
    Be015aAssert::run('SQL unsupported text and noncanonical filters cannot bypass fallback', static function (): void {
        $allow = new CommonListQueryAllowlist(['code', 'name'], ['status', 'parent_id', 'code', 'name'], ['id', 'code', 'name']);
        $adapter = new CommonListQueryAdapter();
        foreach ([['sort' => 'code'], ['q' => 'A'], ['filter' => ['parent_id' => '02']], ['filter' => ['code' => 'C01']]] as $query) {
            Be015aAssert::true(! SqlAuthorizedBucketListSource::supports('product_group', $adapter->parse($query, $allow)), 'unsafe SQL declined');
        }
    });
    Be015aAssert::run('base permission denial remains fail-closed before SQL count', static function (): void {
        $db = new BaseConnection(); $db->rows = be015aRows(false);
        $policy = new Be015aPolicy(); $policy->baseAllowed = false;
        $sql = new ProductMasterListSqlFastPath($db, 'product_uom', $policy,
            fn (): bool => false, fn () => $policy->authorizeBaseView());
        try { $sql->list([], new CommonListQueryAllowlist(['code'], ['status'], ['id'])); }
        catch (DomainException $exception) {
            Be015aAssert::same(0, $db->counts, 'no count on denied permission');
            Be015aAssert::same(1, $db->rollbacks, 'snapshot rolled back');
            return;
        }
        throw new \RuntimeException('Base permission denial not propagated');
    });
    Be015aAssert::run('SQL failure rolls back instead of hiding as empty success', static function (): void {
        $db = new BaseConnection(); $db->rows = be015aRows(true); $db->failPage = true;
        $policy = new Be015aPolicy();
        $sql = new ProductMasterListSqlFastPath($db, 'product_group', $policy,
            fn (): bool => false, fn () => $policy->authorizeBaseView());
        try { $sql->list([], new CommonListQueryAllowlist(['code'], ['status'], ['id'])); }
        catch (\RuntimeException) {
            Be015aAssert::same(1, $db->rollbacks, 'fail closed on SQL exception');
            return;
        }
        throw new \RuntimeException('SQL error swallowed');
    });
    echo 'BE-PERF-015A PRODUCT MASTER SQL OPTIMIZATION SOURCE PASS: ' . Be015aAssert::$cases
        . ' simulated cases (real MariaDB/Shield/HTTP performance NOT_RUN).' . PHP_EOL;
}
