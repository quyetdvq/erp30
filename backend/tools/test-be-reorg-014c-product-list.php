<?php

declare(strict_types=1);

/**
 * BE-REORG-014C vendor-free behavior/branch seam test.
 * This test deliberately replaces CI4/DB/PBAC with recording doubles. It
 * verifies orchestration, NOT MariaDB snapshots, Shield eligibility or p95.
 * Run: php backend/tools/test-be-reorg-014c-product-list.php
 */
namespace CodeIgniter\Database {
    interface ConnectionInterface {}
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
    // CI4 requires ext-mbstring; test harness only exercises ASCII comparisons.
    if (! function_exists('mb_strtolower')) {
        function mb_strtolower(string $value): string { return strtolower($value); }
    }
    final class Be014cTrace
    {
        /** @var list<string> */
        public static array $events = [];
        public static function add(string $event): void { self::$events[] = $event; }
    }
    final class Be014cConnection implements \CodeIgniter\Database\ConnectionInterface {}
}
namespace App\Modules\Product\Product {
    use App\Infrastructure\Api\CommonListQuery;
    final class ProductRepository
    {
        /** @param list<array<string,mixed>> $rows */
        public function __construct(public array $rows = [], public bool $sql = true, public bool $broken = false) {}
        public function supportsSqlProjection(CommonListQuery $query): bool
        {
            \Be014cTrace::add('supports-sql');
            if (($query->filters['parent_product_id'][0] ?? '') === '01') {
                return false;
            }
            return $this->sql;
        }
        public function withConsistentListSnapshot(callable $read): mixed
        {
            \Be014cTrace::add('snapshot-open');
            try {
                return $read();
            } finally {
                \Be014cTrace::add('snapshot-close');
            }
        }
    }
    final class ProductRoleService
    {
        public function __construct(?ProductRepository $repository = null, ?\CodeIgniter\Database\ConnectionInterface $db = null) {}
        /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
        public function describeList(array $rows): array
        {
            \Be014cTrace::add('roles-list');
            return $this->describe($rows);
        }
        /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
        public function describePage(array $rows): array
        {
            \Be014cTrace::add('roles-page');
            return $this->describe($rows);
        }
        /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
        private function describe(array $rows): array
        {
            return array_map(static function (array $row): array {
                $row['role'] = 'SIMPLE';
                $row['full_name'] = $row['product_name'];
                $row['selectable'] = ($row['status'] ?? null) === 'ACTIVE';
                $row['leaf_transactionable'] = true;
                return $row;
            }, $rows);
        }
    }
    final class ProductMasterService
    {
        /** @param list<array<string,mixed>> $rows */
        public function __construct(private array $rows = [], ?\CodeIgniter\Database\ConnectionInterface $db = null) {}
        /** @return list<array<string,mixed>> */
        public function listAll(): array
        {
            \Be014cTrace::add('products-all');
            return $this->rows;
        }
    }
}
namespace App\Infrastructure\Api {
    use App\Exceptions\DomainException;
    use App\Modules\Product\Product\ProductRepository;
    use App\Modules\Product\Product\ProductRoleService;
    /** Stub SQL source; use canonical parser/comparator for page/count. */
    final class SqlProductListSource implements AuthorizedListSource
    {
        /** @param list<string>|null $statuses */
        public function __construct(
            private readonly ProductRepository $repo,
            private readonly ProductRoleService $roles,
            private readonly ?array $statuses = null,
        ) {}
        public function countAuthorized(CommonListQuery $query): int
        {
            \Be014cTrace::add('sql-count');
            $this->integrity();
            return (new AuthorizedArrayListSource($this->authorizedRows()))->countAuthorized($query);
        }
        public function fetchAuthorized(CommonListQuery $query): iterable
        {
            \Be014cTrace::add('sql-page');
            $this->integrity();
            return $this->roles->describePage(array_values(iterator_to_array(
                (new AuthorizedArrayListSource($this->authorizedRows()))->fetchAuthorized($query),
            )));
        }
        private function integrity(): void
        {
            if ($this->repo->broken) {
                throw new DomainException('PRODUCT_INVALID_ROLE');
            }
        }
        /** @return list<array<string,mixed>> */
        private function authorizedRows(): array
        {
            return array_values(array_filter($this->repo->rows, fn (array $row): bool =>
                $this->statuses === null || in_array($row['status'], $this->statuses, true)));
        }
    }
}
namespace App\Authorization {
    use App\Infrastructure\Mutation\OperationContext;
    final class Be014cDecision
    {
        public function __construct(private readonly bool $allowed) {}
        public function isAllowed(): bool { return $this->allowed; }
    }
    final class AuthorizationService
    {
        /** @param array<string,mixed> $target */
        public function authorize(OperationContext $operation, string $resource, string $action, EvaluationOrganizationRequest $target): Be014cDecision
        {
            \Be014cTrace::add('real-adapter-decision');
            return new Be014cDecision(true);
        }
        public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed
        {
            \Be014cTrace::add('real-adapter-batch');
            return $read();
        }
    }
    final class EffectiveListBucketCompiler
    {
        public function __construct(mixed $db, AuthorizationService $authorization) {}
        public function compile(string $resource, OperationContext $operation, callable $onDenied): ?array
        {
            \Be014cTrace::add('real-adapter-compile');
            return [['status' => 'ACTIVE']];
        }
    }
}
namespace {
    use App\Bootstrap\ProductListAuthorizationAdapter;
    use App\Exceptions\DomainException;
    use App\Infrastructure\Mutation\OperationContext;
    use App\Infrastructure\Time\EvaluationTime;
    use App\Modules\Product\Product\Application\ProductListQueryService;
    use App\Modules\Product\Product\Application\Ports\ProductListAuthorizationPort;
    use App\Modules\Product\Product\ProductMasterService;
    use App\Modules\Product\Product\ProductRepository;
    use App\Modules\Product\Product\ProductRoleService;

    final class Be014cFakeAuthorization implements ProductListAuthorizationPort
    {
        public bool $state = false;
        public bool $baseAllowed = true;
        /** @var list<string>|null */
        public ?array $statuses = ['ACTIVE'];
        /** @var list<int> */
        public array $blocked = [];
        public int $operationCount = 0;
        public int $recheckCount = 0;
        public readonly OperationContext $identity;

        public function __construct()
        {
            $this->identity = new OperationContext(7,
                new EvaluationTime(new \DateTimeImmutable('2026-10-22T00:00:00Z')), 'be014c', false);
        }
        public function requiresRowStateAuthorization(): bool
        {
            Be014cTrace::add('requires-state');
            return $this->state;
        }
        public function operation(): OperationContext
        {
            $this->operationCount++;
            Be014cTrace::add('operation');
            return $this->identity;
        }
        public function authorizeBaseView(): void
        {
            Be014cTrace::add('base-view');
            if (! $this->baseAllowed) {
                throw new DomainException('PRODUCT_AUTHORIZATION_DENIED', ['action' => 'view']);
            }
        }
        public function rowVisible(array $row): bool
        {
            Be014cTrace::add('row-pbac-' . $row['id']);
            return ! in_array($row['id'], $this->blocked, true);
        }
        public function recheckRowVisible(OperationContext $operation, array $row): bool
        {
            be014cAssert($operation === $this->identity, 'recheck must share the compiled OperationContext instance');
            $this->recheckCount++;
            Be014cTrace::add('recheck-' . $row['id']);
            return ! in_array($row['id'], $this->blocked, true);
        }
        public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed
        {
            be014cAssert($operation === $this->identity, 'read-only batch must share OperationContext instance');
            Be014cTrace::add('batch-open');
            try { return $read(); }
            finally { Be014cTrace::add('batch-close'); }
        }
        public function authorizedStatuses(OperationContext $operation): ?array
        {
            be014cAssert($operation === $this->identity, 'bucket compiler must share OperationContext instance');
            Be014cTrace::add('compile');
            return $this->statuses;
        }
    }

    function be014cAssert(bool $condition, string $description): void
    {
        if (! $condition) {
            throw new \RuntimeException('BE-REORG-014C: ' . $description);
        }
    }
    function be014cSame(mixed $expected, mixed $actual, string $description): void
    {
        be014cAssert($actual === $expected, $description . ': actual=' . var_export($actual, true));
    }
    /** @param callable():mixed $action */
    function be014cThrows(callable $action, string $class, ?string $code = null): void
    {
        try {
            $action();
        } catch (\Throwable $exception) {
            be014cAssert($exception instanceof $class, 'wrong exception: ' . get_class($exception));
            if ($code !== null) {
                be014cSame($code, $exception->getErrorCode(), 'machine error code');
            }
            return;
        }
        throw new \RuntimeException('BE-REORG-014C: expected exception ' . $class);
    }
    /** @return list<array<string,mixed>> */
    function be014cRows(): array
    {
        return [
            ['id' => 1, 'code' => 'A-001', 'product_name' => 'Alpha', 'status' => 'ACTIVE', 'serial_management_enabled' => '1', 'image_filename' => 'hidden.jpg', 'image_mime' => 'image/png', 'image_size_bytes' => 12, 'parent_product_id' => null],
            ['id' => 2, 'code' => 'B-002', 'product_name' => 'Bravo', 'status' => 'INACTIVE', 'serial_management_enabled' => '0', 'image_filename' => null, 'parent_product_id' => null],
            ['id' => 3, 'code' => 'C-003', 'product_name' => 'Charlie', 'status' => 'ACTIVE', 'serial_management_enabled' => 1, 'parent_product_id' => '1'],
        ];
    }
    function be014cService(ProductRepository $repository, Be014cFakeAuthorization $security): ProductListQueryService
    {
        $db = new Be014cConnection();
        return new ProductListQueryService($db, $security, null, $repository,
            new ProductRoleService(), new ProductMasterService($repository->rows));
    }

    $passed = 0;
    $check = static function (string $name, callable $case) use (&$passed): void {
        Be014cTrace::$events = [];
        $case();
        $passed++;
        echo '  ' . $name . ': PASS' . PHP_EOL;
    };

    $check('base-scope SQL snapshot and pagination', static function (): void {
        $repo = new ProductRepository(be014cRows());
        $auth = new Be014cFakeAuthorization();
        $response = be014cService($repo, $auth)->list(['page' => '2', 'page_size' => '1', 'sort' => 'id']);
        be014cSame('3', $response['total'], 'SQL count');
        be014cSame(2, $response['items'][0]['id'], 'page two');
        be014cSame('B-002', $response['items'][0]['product_code'], 'public product code');
        be014cSame(false, $response['items'][0]['serial_management_enabled'], 'string zero to JSON bool');
        be014cAssert(! array_key_exists('image_filename', $response['items'][0]), 'storage path redaction');
        be014cSame(['requires-state', 'base-view', 'supports-sql', 'snapshot-open', 'sql-count', 'sql-page', 'roles-page', 'snapshot-close'], Be014cTrace::$events, 'base SQL order');
        be014cSame(0, $auth->operationCount, 'base-scope operation delegated inside PBAC adapter');
    });
    $check('state buckets filter before COUNT and page', static function (): void {
        $auth = new Be014cFakeAuthorization();
        $auth->state = true;
        $response = be014cService(new ProductRepository(be014cRows()), $auth)->list(['page' => '2', 'page_size' => '1']);
        be014cSame('2', $response['total'], 'authorized total');
        be014cSame(3, $response['items'][0]['id'], 'state-filtered page');
        be014cSame(1, $auth->operationCount, 'one operation on SQL path');
        be014cSame(1, $auth->recheckCount, 'each returned row rechecked');
        be014cSame(['requires-state', 'supports-sql', 'operation', 'snapshot-open', 'batch-open', 'compile', 'sql-count', 'sql-page', 'roles-page', 'recheck-3', 'batch-close', 'snapshot-close'], Be014cTrace::$events, 'state SQL order');
    });
    $check('no permitted State yields authorized zero total', static function (): void {
        $auth = new Be014cFakeAuthorization();
        $auth->state = true;
        $auth->statuses = [];
        $response = be014cService(new ProductRepository(be014cRows()), $auth)->list(['page_size' => '1']);
        be014cSame(['items' => [], 'page' => 1, 'page_size' => 1, 'total' => '0'], $response, 'deny-all result');
        be014cSame(0, $auth->recheckCount, 'no unauthorized rows exposed');
    });
    $check('uncertain State buckets fall back with PBAC before page', static function (): void {
        $auth = new Be014cFakeAuthorization();
        $auth->state = true;
        $auth->statuses = null;
        $auth->blocked = [2];
        $response = be014cService(new ProductRepository(be014cRows()), $auth)->list(['page' => '2', 'page_size' => '1']);
        be014cSame('2', $response['total'], 'fallback authorized total');
        be014cSame(3, $response['items'][0]['id'], 'fallback visible page');
        $enumerateAt = array_search('products-all', Be014cTrace::$events, true);
        $deniedAt = array_search('row-pbac-2', Be014cTrace::$events, true);
        be014cAssert(is_int($enumerateAt) && is_int($deniedAt) && $deniedAt > $enumerateAt, 'row PBAC occurs after Product enumeration');
        be014cAssert(in_array('batch-close', Be014cTrace::$events, true), 'read batch close');
        be014cAssert(array_search('row-pbac-3', Be014cTrace::$events, true) !== false, 'all rows authorized');
    });
    $check('SQL query allowlist filtering and stable descending page', static function (): void {
        $auth = new Be014cFakeAuthorization();
        $response = be014cService(new ProductRepository(be014cRows()), $auth)
            ->list(['sort' => '-id', 'filter' => ['status' => 'ACTIVE'], 'page' => '2', 'page_size' => '1']);
        be014cSame('2', $response['total'], 'filtered total');
        be014cSame(1, $response['items'][0]['id'], 'stable descending page');
        be014cAssert(in_array('sql-count', Be014cTrace::$events, true), 'SQL count used');
    });
    $check('legacy fallback retains search/filter and stable page', static function (): void {
        $auth = new Be014cFakeAuthorization();
        $response = be014cService(new ProductRepository(be014cRows(), false), $auth)
            ->list(['q' => 'bravo', 'sort' => '-id', 'page_size' => '1']);
        be014cSame('1', $response['total'], 'case-insensitive fallback total');
        be014cSame(2, $response['items'][0]['id'], 'fallback matched row');
        be014cAssert(! in_array('sql-count', Be014cTrace::$events, true), 'no SQL source on fallback');
    });
    $check('noncanonical numeric filter preserves exact PHP fallback', static function (): void {
        $auth = new Be014cFakeAuthorization();
        $response = be014cService(new ProductRepository(be014cRows()), $auth)
            ->list(['filter' => ['parent_product_id' => '01'], 'page_size' => '1']);
        be014cSame('0', $response['total'], '01 must not match 1');
        be014cSame([], $response['items'], 'no unsafe numeric cast');
        be014cAssert(! in_array('sql-count', Be014cTrace::$events, true), 'noncanonical does not run SQL COUNT');
    });
    $check('base permission denial before any list SQL', static function (): void {
        $auth = new Be014cFakeAuthorization();
        $auth->baseAllowed = false;
        be014cThrows(static fn () => be014cService(new ProductRepository(be014cRows()), $auth)->list(), DomainException::class, 'PRODUCT_AUTHORIZATION_DENIED');
        be014cAssert(! in_array('sql-count', Be014cTrace::$events, true), 'denied before COUNT');
        be014cAssert(! in_array('supports-sql', Be014cTrace::$events, true), 'denied before projection query');
    });
    $check('recheck denial fails closed with original machine code', static function (): void {
        $auth = new Be014cFakeAuthorization();
        $auth->state = true;
        $auth->blocked = [1];
        be014cThrows(static fn () => be014cService(new ProductRepository(be014cRows()), $auth)->list(['page_size' => '1']), DomainException::class, 'PRODUCT_AUTHORIZATION_DENIED');
        be014cAssert(in_array('snapshot-close', Be014cTrace::$events, true), 'snapshot unwound on denial');
    });
    $check('invalid filter and sort rejected by original query adapter', static function (): void {
        be014cThrows(static fn () => be014cService(new ProductRepository(be014cRows()), new Be014cFakeAuthorization())
            ->list(['sort' => 'unit_price']), \CodeIgniter\HTTP\Exceptions\BadRequestException::class);
        be014cThrows(static fn () => be014cService(new ProductRepository(be014cRows()), new Be014cFakeAuthorization())
            ->list(['filter' => ['organization_id' => '10']]), \CodeIgniter\HTTP\Exceptions\BadRequestException::class);
    });
    $check('broken Product structure is not hidden by a one-row page', static function (): void {
        be014cThrows(static fn () => be014cService(new ProductRepository(be014cRows(), true, true), new Be014cFakeAuthorization())
            ->list(['page_size' => '1']), DomainException::class, 'PRODUCT_INVALID_ROLE');
    });
    $check('Product API projection matches frozen legacy mapping', static function (): void {
        $inputs = [
            ['code' => '0001', 'serial_management_enabled' => '0', 'image_filename' => '', 'image_mime' => 'image/jpeg'],
            ['code' => 'x', 'serial_management_enabled' => 1, 'image_filename' => 'picture.png', 'image_size_bytes' => 10],
            ['code' => null, 'serial_management_enabled' => null],
            ['serial_management_enabled' => true, 'image_filename' => '0'],
            ['serial_management_enabled' => false],
        ];
        foreach ($inputs as $row) {
            $old = $row;
            if (array_key_exists('image_filename', $old)) {
                $old['image_available'] = is_string($old['image_filename']) && $old['image_filename'] !== '';
            }
            unset($old['image_filename'], $old['image_mime'], $old['image_size_bytes']);
            if (array_key_exists('serial_management_enabled', $old)) {
                $old['serial_management_enabled'] = in_array($old['serial_management_enabled'], [true, 1, '1'], true);
            }
            if (array_key_exists('code', $old)) {
                $old['product_code'] = $old['code'];
                unset($old['code']);
            }
            be014cSame($old, \App\Modules\Product\Product\Application\AuthorizedProductReadService::projectForApi($row), 'DTO projection');
        }
    });
    $check('Bootstrap adapter resolves Authorization before legacy operation', static function (): void {
        $db = new Be014cConnection();
        $context = new OperationContext(7, new EvaluationTime(new \DateTimeImmutable('2026-10-22T00:00:00Z')), 'adapter', false);
        $auth = new \App\Authorization\AuthorizationService();
        $adapter = new ProductListAuthorizationAdapter(
            $db,
            static function () use ($context): OperationContext { Be014cTrace::add('operation-callback'); return $context; },
            static function () use ($auth): \App\Authorization\AuthorizationService { Be014cTrace::add('authorization-callback'); return $auth; },
            static fn (): bool => true,
        );
        $adapter->authorizeBaseView();
        be014cSame(['authorization-callback', 'operation-callback', 'real-adapter-decision'], Be014cTrace::$events, 'legacy precedence');
        Be014cTrace::$events = [];
        be014cSame(['ACTIVE'], $adapter->authorizedStatuses($context), 'adapter status projection');
        be014cSame(['authorization-callback', 'real-adapter-compile'], Be014cTrace::$events, 'compiler ownership');
    });
    $check('facade delegates and Product has no reverse legacy dependency', static function (): void {
        $app = dirname(__DIR__) . '/app/';
        $api = (string) file_get_contents($app . 'Services/Api050ProductCatalogService.php');
        $query = (string) file_get_contents($app . 'Modules/Product/Product/Application/ProductListQueryService.php');
        be014cAssert((bool) preg_match('/function listProducts\(array \$query = \[\]\): array\s*\{.*?new ProductListAuthorizationAdapter\(.*?new ProductListQueryService\(/s', $api), 'API-050 delegates Product List');
        be014cAssert(! str_contains($query, 'use App\\Services\\') && ! str_contains($query, 'use App\\Authorization\\'), 'query service uses Product-owned PBAC port');
        be014cAssert(str_contains($query, 'withConsistentListSnapshot(') && str_contains($query, 'authorizedStatuses('), 'Product retains SQL snapshot/buckets');
    });

    echo 'BE-REORG-014C PRODUCT LIST SOURCE SEAM PASS: ' . $passed . ' vendor-free scenarios (DB/Shield runtime NOT_RUN).' . PHP_EOL;
}
