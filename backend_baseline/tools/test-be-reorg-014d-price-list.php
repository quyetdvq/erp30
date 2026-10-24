<?php

declare(strict_types=1);

/**
 * BE-REORG-014D: vendor-free orchestration and parity vectors.
 * Uses recording DB/PBAC doubles, NOT a substitute for MariaDB/Shield tests.
 * Run: php backend/tools/test-be-reorg-014d-price-list.php
 */
namespace CodeIgniter\Database {
    interface ConnectionInterface {}
    class BaseConnection implements ConnectionInterface
    {
        public int $transDepth = 0;
        public bool $negativePriority = false;
        public bool $malformedPage = false;
        /** @var list<array<string,mixed>> */
        public array $rows = [];
    }
}
namespace CodeIgniter\HTTP\Exceptions {
    class BadRequestException extends \RuntimeException {}
}
namespace {
    $app = dirname(__DIR__) . '/app/';
    spl_autoload_register(static function (string $class) use ($app): void {
        if (! str_starts_with($class, 'App\\')) {
            return;
        }
        $file = $app . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
    if (! function_exists('mb_strtolower')) {
        function mb_strtolower(string $s): string { return strtolower($s); }
    }
    final class Be014dTrace
    {
        /** @var list<string> */
        public static array $events = [];
        public static function add(string $event): void { self::$events[] = $event; }
    }
    final class Be014dDb extends \CodeIgniter\Database\BaseConnection {}
    final class Be014dNonBaseDb implements \CodeIgniter\Database\ConnectionInterface
    {
        /** @param list<array<string,mixed>> $rows */
        public function __construct(public array $rows) {}
    }
}
namespace App\Modules\Pricing\PriceList {
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\Database\ConnectionInterface;

    final class PriceListRepository
    {
        public function __construct(private ?ConnectionInterface $db = null) {}
        public function withConsistentListSnapshot(callable $read): mixed
        {
            \Be014dTrace::add('snapshot-open');
            if ($this->db instanceof BaseConnection) {
                be014dAssert($this->db->transDepth === 0, 'nested snapshot must be skipped');
                $this->db->transDepth = 1;
            }
            try {
                return $read();
            } finally {
                if ($this->db instanceof BaseConnection) {
                    $this->db->transDepth = 0;
                }
                \Be014dTrace::add('snapshot-close');
            }
        }
    }
    final class PriceListService
    {
        public function __construct(private ?ConnectionInterface $db = null) {}
        /** @return list<array<string,mixed>> */
        public function listAll(): array
        {
            \Be014dTrace::add('price-lists-all');
            return $this->db->rows ?? [];
        }
    }
}
namespace App\Modules\Pricing\PriceList\Application {
    use App\Infrastructure\Api\AuthorizedArrayListSource;
    use App\Infrastructure\Api\AuthorizedListSource;
    use App\Infrastructure\Api\CommonListQuery;
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\Database\ConnectionInterface;

    /** Fake SQL execution; production SqlPriceListListSource is NOT replaced in the patch. */
    final class SqlPriceListListSource implements AuthorizedListSource
    {
        /** @param list<array{organization_id:int,status:string}> $buckets */
        public function __construct(private readonly ConnectionInterface $db, private readonly array $buckets) {}

        public static function supports(CommonListQuery $query): bool
        {
            if ($query->q !== null) {
                return false;
            }
            $sort = $query->sort;
            if (! ((count($sort) === 1 && $sort[0]->field === 'id')
                || (count($sort) === 2 && $sort[0]->field === 'priority' && $sort[1]->field === 'id'))) {
                return false;
            }
            foreach ($query->filters as $field => $values) {
                if ($field === 'status') {
                    continue;
                }
                if ($field !== 'organization_id') {
                    return false;
                }
                foreach ($values as $value) {
                    if (preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) !== 1
                        || strlen($value) > strlen((string) PHP_INT_MAX)
                        || (strlen($value) === strlen((string) PHP_INT_MAX)
                            && strcmp($value, (string) PHP_INT_MAX) > 0)) {
                        return false;
                    }
                }
            }
            return true;
        }

        public function canServe(CommonListQuery $query): bool
        {
            \Be014dTrace::add('priority-equivalence-probe');
            return self::supports($query)
                && ! ($query->sort[0]->field === 'priority' && ($this->db->negativePriority ?? false));
        }

        public function countAuthorized(CommonListQuery $query): int
        {
            \Be014dTrace::add('sql-count');
            return (new AuthorizedArrayListSource($this->allowedRows()))->countAuthorized($query);
        }

        public function fetchAuthorized(CommonListQuery $query): iterable
        {
            \Be014dTrace::add('sql-page');
            if ($this->db->malformedPage ?? false) {
                return ['malformed-row'];
            }
            return (new AuthorizedArrayListSource($this->allowedRows()))->fetchAuthorized($query);
        }

        /** @return list<array<string,mixed>> */
        private function allowedRows(): array
        {
            return array_values(array_filter($this->db->rows ?? [], function (array $row): bool {
                foreach ($this->buckets as $b) {
                    if ((int) $row['organization_id'] === $b['organization_id'] && $row['status'] === $b['status']) {
                        return true;
                    }
                }
                return false;
            }));
        }
    }
}
namespace App\Authorization {
    use App\Infrastructure\Mutation\OperationContext;
    final class Be014dDecision
    {
        public function __construct(private readonly bool $allow) {}
        public function isAllowed(): bool { return $this->allow; }
    }
    final class AuthorizationService
    {
        public function authorize(OperationContext $op, string $resource, string $action, EvaluationOrganizationRequest $request): Be014dDecision
        {
            \Be014dTrace::add('real-auth-' . (string) ($request->persistedTarget['id'] ?? 'unknown'));
            be014dSame('price_list', $resource, 'resource must be persisted Price List');
            be014dSame('view', $action, 'action must be view');
            be014dSame([], $request->input, 'client input cannot control scope');
            be014dSame(null, $request->clientEvaluationOrganizationId, 'client hint never grants scope');
            return new Be014dDecision(($request->persistedTarget['id'] ?? null) !== 2);
        }
        public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed
        {
            \Be014dTrace::add('real-batch-open');
            try { return $read(); }
            finally { \Be014dTrace::add('real-batch-close'); }
        }
    }
    final class PriceListAuthorizedBucketCompiler
    {
        public function __construct(private mixed $db, private AuthorizationService $authorization) {}
        public function compile(OperationContext $operation): ?array
        {
            \Be014dTrace::add('real-bucket-compile');
            return [['organization_id' => 11, 'status' => 'ACTIVE']];
        }
    }
}
namespace {
    use App\Bootstrap\PriceListListAuthorizationAdapter;
    use App\Exceptions\DomainException;
    use App\Infrastructure\Mutation\OperationContext;
    use App\Infrastructure\Time\EvaluationTime;
    use App\Modules\Pricing\PriceList\Application\PriceListQueryService;
    use App\Modules\Pricing\PriceList\Application\Ports\PriceListListAuthorizationPort;

    function be014dAssert(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new \RuntimeException('BE-REORG-014D: ' . $message);
        }
    }
    function be014dSame(mixed $want, mixed $got, string $message): void
    {
        if ($want !== $got) {
            throw new \RuntimeException('BE-REORG-014D: ' . $message . '\nExpected: ' . var_export($want, true) . '\nActual: ' . var_export($got, true));
        }
    }
    /** @return list<array<string,mixed>> */
    function be014dRows(): array
    {
        return [
            ['id' => 1, 'code' => '001', 'name' => 'Alpha', 'organization_id' => 11, 'status' => 'ACTIVE', 'priority' => 1, 'effective_from' => '2026-01-01 00:00:00', 'effective_to' => null],
            ['id' => 2, 'code' => '002', 'name' => 'Bravo', 'organization_id' => 12, 'status' => 'ACTIVE', 'priority' => 9, 'effective_from' => '2026-02-01 00:00:00', 'effective_to' => null],
            ['id' => 3, 'code' => '003', 'name' => 'Charlie', 'organization_id' => 11, 'status' => 'ACTIVE', 'priority' => 2, 'effective_from' => '2026-03-01 00:00:00', 'effective_to' => null],
            ['id' => 4, 'code' => '004', 'name' => 'Delta', 'organization_id' => 11, 'status' => 'INACTIVE', 'priority' => 4, 'effective_from' => '2026-04-01 00:00:00', 'effective_to' => null],
        ];
    }
    final class Be014dFakeAuthorization implements PriceListListAuthorizationPort
    {
        /** @var list<array{organization_id:int,status:string}>|null */
        public ?array $buckets = [['organization_id' => 11, 'status' => 'ACTIVE']];
        /** @var list<int> */
        public array $blockedSql = [];
        /** @var list<int> */
        public array $blockedFallback = [2];
        /** @var list<int> */
        public array $throwSql = [];
        /** @var list<int> */
        public array $throwFallback = [];
        public int $operationCount = 0;
        public readonly OperationContext $identity;
        public ?\CodeIgniter\Database\ConnectionInterface $expectedDb = null;
        public function __construct()
        {
            $this->identity = new OperationContext(7, new EvaluationTime(new \DateTimeImmutable('2026-10-22T00:00:00Z')), 'be014d', false);
        }
        public function operation(): OperationContext
        {
            $this->operationCount++;
            Be014dTrace::add('operation');
            return $this->identity;
        }
        public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed
        {
            be014dAssert($operation === $this->identity, 'read batch must reuse SQL OperationContext');
            Be014dTrace::add('batch-open');
            try { return $read(); }
            finally { Be014dTrace::add('batch-close'); }
        }
        public function authorizedBuckets(OperationContext $operation, \CodeIgniter\Database\ConnectionInterface $db): ?array
        {
            be014dAssert($operation === $this->identity, 'bucket compilation must reuse SQL OperationContext');
            be014dAssert($db === $this->expectedDb, 'bucket scanner must share snapshot DB connection');
            Be014dTrace::add('compile');
            return $this->buckets;
        }
        public function rowAllowed(array $row, ?OperationContext $operation = null): bool
        {
            $id = (int) $row['id'];
            if ($operation === null) {
                // Legacy facade recaptures a new operation for every PHP row.
                $this->operation();
                Be014dTrace::add('fallback-pbac-' . $id);
                if (in_array($id, $this->throwFallback, true)) {
                    throw new DomainException('PRICE_LIST_SCOPE_UNRESOLVED');
                }
                return ! in_array($id, $this->blockedFallback, true);
            }
            be014dAssert($operation === $this->identity, 'SQL row recheck must reuse OperationContext');
            Be014dTrace::add('recheck-' . $id);
            if (in_array($id, $this->throwSql, true)) {
                throw new DomainException('PRICE_LIST_SCOPE_UNRESOLVED');
            }
            return ! in_array($id, $this->blockedSql, true);
        }
    }
    /** @param list<array<string,mixed>>|null $rows */
    function be014dService(Be014dFakeAuthorization $auth, ?array $rows = null, ?Be014dDb $db = null): PriceListQueryService
    {
        $db ??= new Be014dDb();
        $db->rows = $rows ?? be014dRows();
        $auth->expectedDb = $db;
        return new PriceListQueryService(static function () use ($db): \CodeIgniter\Database\ConnectionInterface {
            Be014dTrace::add('db-connection');
            return $db;
        }, $auth);
    }
    function be014dThrows(callable $run, string $class, ?string $machineCode = null): void
    {
        try {
            $run();
        } catch (\Throwable $e) {
            be014dAssert($e instanceof $class, 'expected ' . $class . ', got ' . $e::class);
            if ($machineCode !== null) {
                be014dSame($machineCode, $e->getMessage(), 'exception machine code');
            }
            return;
        }
        throw new \RuntimeException('BE-REORG-014D: expected exception ' . $class);
    }
    $passed = 0;
    $check = static function (string $name, callable $fn) use (&$passed): void {
        Be014dTrace::$events = [];
        $fn();
        ++$passed;
        echo '  ' . $name . ': PASS' . PHP_EOL;
    };
    $check('authorized Owner/State SQL COUNT and page', static function (): void {
        $auth = new Be014dFakeAuthorization();
        $data = be014dService($auth)->list(['page' => '2', 'page_size' => '1']);
        be014dSame('2', $data['total'], 'total excludes other owner and inactive State');
        be014dSame(3, $data['items'][0]['id'], 'page is from authorized set');
        be014dSame(1, $auth->operationCount, 'single SQL context');
        be014dSame(['db-connection','operation','snapshot-open','batch-open','priority-equivalence-probe','compile',
            'sql-count','sql-page','recheck-3','batch-close','snapshot-close'], Be014dTrace::$events, 'SQL branch ordering');
    });
    $check('empty approved buckets count zero without PHP fallback', static function (): void {
        $auth = new Be014dFakeAuthorization();
        $auth->buckets = [];
        $data = be014dService($auth)->list(['page_size' => '1']);
        be014dSame(['items' => [], 'page' => 1, 'page_size' => 1, 'total' => '0'], $data, 'deny-all');
        be014dAssert(! in_array('price-lists-all', Be014dTrace::$events, true), 'verified empty bucket may use SQL count');
    });
    $check('unknown buckets fall back before count and page', static function (): void {
        $auth = new Be014dFakeAuthorization();
        $auth->buckets = null;
        $data = be014dService($auth)->list(['page' => '2', 'page_size' => '1']);
        be014dSame('3', $data['total'], 'fallback authorized total');
        be014dSame(3, $data['items'][0]['id'], 'fallback page');
        be014dAssert(! in_array('sql-count', Be014dTrace::$events, true), 'unknown buckets cannot count SQL');
        be014dSame(5, $auth->operationCount, 'one SQL attempt and four fallback row contexts');
        be014dAssert(array_search('price-lists-all', Be014dTrace::$events, true) > array_search('snapshot-close', Be014dTrace::$events, true), 'fallback after snapshot closes');
    });
    $check('SQL row recheck denial uses full authorized fallback', static function (): void {
        $auth = new Be014dFakeAuthorization();
        $auth->blockedSql = [1];
        $data = be014dService($auth)->list(['page_size' => '2']);
        be014dSame('3', $data['total'], 'complete fallback total');
        be014dSame([1, 3], array_column($data['items'], 'id'), 'full fallback keeps row when legacy per-row allows it');
        be014dAssert(in_array('recheck-1', Be014dTrace::$events, true), 'recheck attempted');
        be014dAssert(in_array('price-lists-all', Be014dTrace::$events, true), 'no partial page returned');
    });
    $check('SQL recheck DomainException returns to fallback', static function (): void {
        $auth = new Be014dFakeAuthorization();
        $auth->throwSql = [1];
        $data = be014dService($auth)->list(['page_size' => '2']);
        be014dSame('3', $data['total'], 'fallback total after uncertain persisted target');
    });
    $check('fallback DomainException preserves original error', static function (): void {
        $auth = new Be014dFakeAuthorization();
        $auth->buckets = null;
        $auth->throwFallback = [1];
        be014dThrows(static fn () => be014dService($auth)->list(), DomainException::class, 'PRICE_LIST_SCOPE_UNRESOLVED');
    });
    $check('non-array SQL page switches to full-list fallback', static function (): void {
        $auth = new Be014dFakeAuthorization();
        $db = new Be014dDb();
        $db->malformedPage = true;
        $data = be014dService($auth, null, $db)->list();
        be014dSame('3', $data['total'], 'fallback count');
        be014dAssert(in_array('price-lists-all', Be014dTrace::$events, true), 'malformed projection fallback');
    });
    $check('negative signed priority forces legacy comparator', static function (): void {
        $auth = new Be014dFakeAuthorization();
        $db = new Be014dDb();
        $db->negativePriority = true;
        $rows = be014dRows();
        $rows[0]['priority'] = -2;
        $data = be014dService($auth, $rows, $db)->list(['sort' => 'priority', 'page_size' => '2']);
        be014dSame('3', $data['total'], 'signed priority fallback total');
        be014dAssert(! in_array('sql-count', Be014dTrace::$events, true), 'signed priority SQL must be skipped');
        be014dAssert(in_array('price-lists-all', Be014dTrace::$events, true), 'PHP comparator used');
    });
    $check('noncanonical owner filter 01 retains PHP exact compare', static function (): void {
        $auth = new Be014dFakeAuthorization();
        $data = be014dService($auth)->list(['filter' => ['organization_id' => '011']]);
        be014dSame('0', $data['total'], '011 does not equal canonical 11');
        be014dSame([], $data['items'], 'no coerced owner match');
        be014dAssert(! in_array('sql-count', Be014dTrace::$events, true), 'noncanonical owner bypasses SQL');
    });
    $check('q text search uses authorized PHP read', static function (): void {
        $auth = new Be014dFakeAuthorization();
        $data = be014dService($auth)->list(['q' => 'char', 'page_size' => '1']);
        be014dSame('1', $data['total'], 'search total');
        be014dSame(3, $data['items'][0]['id'], 'search respects visible list');
    });
    $check('nested transaction declines read snapshot and retains fallback', static function (): void {
        $auth = new Be014dFakeAuthorization();
        $db = new Be014dDb();
        $db->transDepth = 1;
        $data = be014dService($auth, null, $db)->list();
        be014dSame('3', $data['total'], 'legacy fallback works under caller transaction');
        be014dAssert(! in_array('snapshot-open', Be014dTrace::$events, true), 'cannot nest SQL read snapshot');
    });
    $check('non-BaseConnection skips SQL read safely', static function (): void {
        $auth = new Be014dFakeAuthorization();
        $db = new Be014dNonBaseDb(be014dRows());
        $data = (new PriceListQueryService(static fn (): \CodeIgniter\Database\ConnectionInterface => $db, $auth))->list();
        be014dSame('3', $data['total'], 'non-SQL connection fallback');
        be014dAssert(! in_array('snapshot-open', Be014dTrace::$events, true), 'no BaseConnection => no snapshot');
    });
    $check('query syntax error precedes DB/PBAC effects', static function (): void {
        $auth = new Be014dFakeAuthorization();
        be014dThrows(static fn () => be014dService($auth)->list(['sort' => 'product_price']), \CodeIgniter\HTTP\Exceptions\BadRequestException::class);
        be014dSame([], Be014dTrace::$events, 'invalid query must not resolve DB or authorization');
        be014dThrows(static fn () => be014dService($auth)->list(['filter' => ['product_id' => '1']]), \CodeIgniter\HTTP\Exceptions\BadRequestException::class);
        be014dSame([], Be014dTrace::$events, 'invalid filter must not resolve DB');
    });
    $check('SQL result preserves raw values and stable pagination shape', static function (): void {
        $auth = new Be014dFakeAuthorization();
        $data = be014dService($auth)->list(['sort' => '-id', 'page_size' => '1']);
        be014dSame(['items' => [be014dRows()[2]], 'page' => 1, 'page_size' => 1, 'total' => '2'], $data, 'no numeric or effective-time transformation');
    });
    $check('Bootstrap adapter retains persisted owner/PBAC and callback precedence', static function (): void {
        $db = new Be014dDb();
        $op = new OperationContext(19, new EvaluationTime(new \DateTimeImmutable('2026-10-22T00:00:00Z')), 'adapter', false);
        $service = new \App\Authorization\AuthorizationService();
        $adapter = new PriceListListAuthorizationAdapter(
            static function () use ($op): OperationContext { Be014dTrace::add('operation-callback'); return $op; },
            static function () use ($service): \App\Authorization\AuthorizationService { Be014dTrace::add('auth-callback'); return $service; },
        );
        be014dAssert($adapter->rowAllowed(['id' => 1, 'organization_id' => 11, 'status' => 'ACTIVE']), 'authorized row');
        be014dSame(['auth-callback','operation-callback','real-auth-1'], Be014dTrace::$events, 'legacy decision evaluation order');
        Be014dTrace::$events = [];
        be014dAssert(! $adapter->rowAllowed(['id' => 2, 'organization_id' => 12, 'status' => 'ACTIVE']), 'denied persisted owner');
        be014dSame([['organization_id' => 11, 'status' => 'ACTIVE']], $adapter->authorizedBuckets($op, $db), 'compiler result');
        be014dAssert($adapter->withReadOnlyBatch($op, static fn (): bool => true), 'delegate read batch');
        be014dAssert(in_array('real-bucket-compile', Be014dTrace::$events, true), 'real compiler used');
        be014dAssert(in_array('real-batch-open', Be014dTrace::$events, true), 'real read batch used');
    });
    $check('facade delegates with Pricing ownership and no reverse import', static function (): void {
        $app = dirname(__DIR__) . '/app/';
        $facade = (string) file_get_contents($app . 'Services/Api050ProductCatalogService.php');
        $query = (string) file_get_contents($app . 'Modules/Pricing/PriceList/Application/PriceListQueryService.php');
        be014dAssert((bool) preg_match('/function listPriceLists\(array \$query = \[\]\): array\s*\{.*?new PriceListQueryService\(.*?new PriceListListAuthorizationAdapter\(/s', $facade), 'Price List facade delegation');
        be014dAssert(! str_contains($query, 'use App\\Services\\') && ! str_contains($query, 'use App\\Authorization\\') && ! str_contains($query, 'use App\\Modules\\Product\\'), 'Pricing query has no legacy or Product reverse coupling');
        be014dAssert(str_contains($query, 'withConsistentListSnapshot(') && str_contains($query, 'authorizedBuckets(') && str_contains($query, 'rowAllowed('), 'Pricing keeps snapshot/buckets/recheck');
        be014dAssert(str_contains($facade, 'function createPriceList(') && str_contains($facade, 'function getPriceList(') && str_contains($facade, 'function replacePriceListItems('), 'Price List command/other endpoints not moved');
    });

    echo 'BE-REORG-014D PRICE LIST QUERY SOURCE SEAM PASS: ' . $passed . ' vendor-free scenarios (MariaDB/Shield runtime NOT_RUN).' . PHP_EOL;
}
