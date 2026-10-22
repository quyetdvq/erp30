<?php

declare(strict_types=1);

/**
 * BE-REORG-014F — vendor-free UOM List behavior/ownership characterization.
 * Real canonical CommonListQueryAdapter and AuthorizedArrayListSource run here.
 * Only DB, ProductUomMasterService, Workflow and PBAC are recording doubles.
 * This does NOT replace MariaDB/Shield/API integration and performance tests.
 * Run: php backend/tools/test-be-reorg-014f-uom-list.php
 */
namespace CodeIgniter\Database {
    interface ConnectionInterface {}
}
namespace CodeIgniter\HTTP\Exceptions {
    class BadRequestException extends \RuntimeException {}
}
namespace App\Modules\Product\Uom {
    final class ProductUomMasterService
    {
        /** @param list<array<string,mixed>> $rows */
        public function __construct(public array $rows = [], public ?string $failure = null) {}

        /** @return list<array<string,mixed>> */
        public function listAll(): array
        {
            \Be014fTrace::add('list-all');
            if ($this->failure !== null) {
                throw new \App\Exceptions\DomainException($this->failure);
            }
            return $this->rows;
        }
    }
}
namespace App\Services {
    final class ResourceWorkflowStateResolver
    {
        public function __construct(\CodeIgniter\Database\ConnectionInterface $db) {}
        public function lifecycleId(string $resource): ?int
        {
            \Be014fTrace::add('lifecycle-' . $resource);
            return \Be014fFlags::$lifecycle ? 88 : null;
        }
        /** @return array{id:int} */
        public function resource(string $resource): array
        {
            \Be014fTrace::add('resource-' . $resource);
            return ['id' => 22];
        }
    }
}
namespace App\Repositories {
    final class PolicyRepository
    {
        public function __construct(\CodeIgniter\Database\ConnectionInterface $db) {}
        public function hasActiveStateScopedPolicies(int $resourceId): bool
        {
            \Be014fAssert::same(22, $resourceId, 'persisted resource ID is used for State policy');
            \Be014fTrace::add('policy-probe');
            return \Be014fFlags::$statePolicy;
        }
    }
}
namespace App\Authorization {
    use App\Infrastructure\Mutation\OperationContext;
    final class AuthorizationService
    {
        public bool $baseAllowed = true;
        /** @var list<int> */
        public array $deniedIds = [];
        public bool $throwOnRow = false;
        /** @var list<array{resource:string,action:string,row:int|null,request:EvaluationOrganizationRequest,operation:OperationContext}> */
        public array $calls = [];
        public function authorize(OperationContext $operation, string $resource, string $action, EvaluationOrganizationRequest $request): AuthorizationDecision
        {
            $id = $request->persistedTarget['id'] ?? null;
            \Be014fTrace::add('pbac-' . ($id === null ? 'base' : $id));
            $this->calls[] = ['resource' => $resource, 'action' => $action, 'row' => $id,
                'request' => $request, 'operation' => $operation];
            if ($id !== null && $this->throwOnRow) {
                throw new \App\Exceptions\DomainException('PBAC_RESOLUTION_FAILED');
            }
            $allowed = $id === null ? $this->baseAllowed : ! in_array($id, $this->deniedIds, true);
            return new AuthorizationDecision($allowed ? 'ALLOW' : 'DENY', 'BE014F_TEST', 7,
                $resource, $action, 1, null, $operation->evaluationTime, [], [], [], [], $operation->requestId);
        }
    }
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
        function mb_strtolower(string $value): string { return strtolower($value); }
    }

    use App\Authorization\AuthorizationService;
    use App\Bootstrap\ProductUomListAuthorizationAdapter;
    use App\Exceptions\DomainException;
    use App\Infrastructure\Api\AuthorizedArrayListSource;
    use App\Infrastructure\Api\CommonListQueryAdapter;
    use App\Infrastructure\Api\CommonListQueryAllowlist;
    use App\Infrastructure\Mutation\MutationContext;
    use App\Infrastructure\Time\Clock;
    use App\Modules\Product\Uom\Application\Ports\ProductUomListAuthorizationPort;
    use App\Modules\Product\Uom\Application\ProductUomListQueryService;
    use App\Modules\Product\Uom\ProductUomMasterService;
    use App\Services\Api050ProductCatalogService;
    use CodeIgniter\Database\ConnectionInterface;
    use CodeIgniter\HTTP\Exceptions\BadRequestException;

    final class Be014fTrace
    {
        /** @var list<string> */
        public static array $events = [];
        public static function add(string $event): void { self::$events[] = $event; }
        public static function clear(): void { self::$events = []; }
    }
    final class Be014fFlags
    {
        public static bool $lifecycle = false;
        public static bool $statePolicy = false;
    }
    final class Be014fAssert
    {
        public static function true(bool $yes, string $reason): void
        {
            if (! $yes) { throw new \RuntimeException('BE-REORG-014F: ' . $reason); }
        }
        public static function same(mixed $expected, mixed $actual, string $reason): void
        {
            if ($expected !== $actual) {
                throw new \RuntimeException('BE-REORG-014F: ' . $reason . '\nExpected: '
                    . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
            }
        }
    }
    final class Be014fDb implements ConnectionInterface {}
    final class Be014fClock implements Clock
    {
        public int $captures = 0;
        public function now(): \DateTimeImmutable
        {
            $this->captures++;
            Be014fTrace::add('evaluation-time-' . $this->captures);
            return new \DateTimeImmutable('2026-10-22T00:00:00Z +' . $this->captures . ' seconds');
        }
    }
    final class Be014fFakePort implements ProductUomListAuthorizationPort
    {
        public bool $state = false;
        public bool $baseAllowed = true;
        /** @var list<int> */
        public array $deniedIds = [];
        public bool $throwRow = false;
        public int $baseCount = 0;
        /** @var list<int> */
        public array $seen = [];
        public function requiresRowStateAuthorization(): bool
        {
            Be014fTrace::add('state-probe');
            return $this->state;
        }
        public function authorizeBaseView(): void
        {
            $this->baseCount++;
            Be014fTrace::add('base-view');
            if (! $this->baseAllowed) { throw new DomainException('PRODUCT_UOM_AUTHORIZATION_DENIED', ['action' => 'view']); }
        }
        public function rowVisible(array $row): bool
        {
            $id = (int) $row['id'];
            $this->seen[] = $id;
            Be014fTrace::add('row-' . $id);
            if ($this->throwRow) { throw new DomainException('PBAC_RESOLUTION_FAILED'); }
            return ! in_array($id, $this->deniedIds, true);
        }
    }

    /** @return list<array<string,mixed>> */
    function be014fRows(): array
    {
        return [
            ['id' => 4, 'code' => 'KG', 'name' => 'Kilogram', 'status' => 'ACTIVE', 'description' => null],
            ['id' => 1, 'code' => 'G', 'name' => 'Gram', 'status' => 'ACTIVE', 'description' => 'small'],
            ['id' => 2, 'code' => 'BOX', 'name' => 'Box', 'status' => 'INACTIVE', 'description' => 'old'],
            ['id' => 3, 'code' => 'BAG', 'name' => 'Bag', 'status' => 'ACTIVE', 'description' => null],
        ];
    }

    /**
     * Frozen algorithm from API-050 pre-014F using canonical production
     * query adapter. Both sides take separate doubles with identical setup.
     * @param array<string,mixed> $query
     * @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    function be014fLegacyList(ProductUomMasterService $uoms, Be014fFakePort $policy, array $query): array
    {
        $rows = $uoms->listAll();
        if ($policy->requiresRowStateAuthorization()) {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $policy->rowVisible($row)));
        } else {
            $policy->authorizeBaseView();
        }
        $result = (new CommonListQueryAdapter())->paginate($query,
            new CommonListQueryAllowlist(
                search: ['code', 'name'],
                filters: ['code', 'name', 'status'],
                sort: ['id', 'code', 'name', 'status'],
            ), new AuthorizedArrayListSource($rows));
        return ['items' => array_values(iterator_to_array($result->data)), 'page' => $result->query->page,
            'page_size' => $result->query->pageSize, 'total' => $result->total];
    }

    /** @param array<string,mixed> $query */
    function be014fParity(array $query, bool $state = false, array $deniedIds = []): array
    {
        $legacyPolicy = new Be014fFakePort();
        $legacyPolicy->state = $state;
        $legacyPolicy->deniedIds = $deniedIds;
        $expected = be014fLegacyList(new ProductUomMasterService(be014fRows()), $legacyPolicy, $query);
        Be014fTrace::clear();
        $newPolicy = new Be014fFakePort();
        $newPolicy->state = $state;
        $newPolicy->deniedIds = $deniedIds;
        $actual = (new ProductUomListQueryService(new ProductUomMasterService(be014fRows()), $newPolicy))->list($query);
        Be014fAssert::same($expected, $actual, 'new list must match frozen legacy algorithm');
        Be014fAssert::same($legacyPolicy->seen, $newPolicy->seen, 'row PBAC targets and order must match');
        Be014fAssert::same($legacyPolicy->baseCount, $newPolicy->baseCount, 'base gate calls must match');
        return $actual;
    }

    /** @return array{0:Api050ProductCatalogService,1:AuthorizationService,2:Be014fClock} */
    function be014fFacade(ProductUomMasterService $uoms): array
    {
        $clock = new Be014fClock();
        $authorization = new AuthorizationService();
        $service = new Api050ProductCatalogService(
            db: new Be014fDb(),
            authorization: $authorization,
            mutationContext: new MutationContext($clock, 7, 'uom-list-014f', false),
            uoms: $uoms,
        );
        return [$service, $authorization, $clock];
    }

    /** @var array<string,\Closure():void> $cases */
    $cases = [
        'base-scope list: full materialization and exact legacy pagination' => static function (): void {
            $result = be014fParity(['page' => '2', 'page_size' => '2']);
            Be014fAssert::same(['3', '4'], array_map('strval', array_column($result['items'], 'id')), 'stable id ordering and second page');
            Be014fAssert::same('4', $result['total'], 'total remains decimal string including inactive UOM');
            Be014fAssert::same(['list-all', 'state-probe', 'base-view'], Be014fTrace::$events, 'legacy evaluation order');
        },
        'state PBAC excludes denied rows before total/page' => static function (): void {
            $result = be014fParity(['page' => '1', 'page_size' => '2', 'sort' => '-id'], true, [4, 2]);
            Be014fAssert::same([3, 1], array_column($result['items'], 'id'), 'authorized page of state-scoped records');
            Be014fAssert::same('2', $result['total'], 'no count leakage of unauthorized rows');
        },
        'state PBAC checks every persisted row, not only the first page' => static function (): void {
            be014fParity(['page' => '2', 'page_size' => '1'], true, [2]);
            Be014fAssert::same(['list-all', 'state-probe', 'row-4', 'row-1', 'row-2', 'row-3'], Be014fTrace::$events, 'all decisions before page slice');
        },
        'all rows denied returns zero count and empty page' => static function (): void {
            $result = be014fParity(['page' => '1'], true, [4, 1, 2, 3]);
            Be014fAssert::same([], $result['items'], 'no row may escape denial');
            Be014fAssert::same('0', $result['total'], 'empty visible set');
        },
        'state path never uses unscoped base-only authorization' => static function (): void {
            $port = new Be014fFakePort(); $port->state = true; $port->baseAllowed = false;
            (new ProductUomListQueryService(new ProductUomMasterService(be014fRows()), $port))->list([]);
            Be014fAssert::same(0, $port->baseCount, 'legacy per-row decision is the only state path');
        },
        'unscoped base permission denial retains machine code and action' => static function (): void {
            $port = new Be014fFakePort(); $port->baseAllowed = false;
            try { (new ProductUomListQueryService(new ProductUomMasterService(be014fRows()), $port))->list([]); }
            catch (DomainException $e) {
                Be014fAssert::same('PRODUCT_UOM_AUTHORIZATION_DENIED', $e->getErrorCode(), 'stable error code');
                Be014fAssert::same('view', $e->getContext()['action'], 'stable action context'); return;
            }
            throw new \RuntimeException('expected base view denial');
        },
        'a policy resolution error remains fail-closed' => static function (): void {
            $port = new Be014fFakePort(); $port->state = true; $port->throwRow = true;
            try { (new ProductUomListQueryService(new ProductUomMasterService(be014fRows()), $port))->list([]); }
            catch (DomainException $e) { Be014fAssert::same('PBAC_RESOLUTION_FAILED', $e->getErrorCode(), 'no catch/allow fallback'); return; }
            throw new \RuntimeException('expected PBAC resolver failure');
        },
        'UOM persistence failure is observed before policy or malformed query' => static function (): void {
            $port = new Be014fFakePort();
            try { (new ProductUomListQueryService(new ProductUomMasterService([], 'PRODUCT_UOM_CONNECTION_INVALID'), $port))->list(['sort' => 'bad-field']); }
            catch (DomainException $e) {
                Be014fAssert::same('PRODUCT_UOM_CONNECTION_INVALID', $e->getErrorCode(), 'legacy failure precedence');
                Be014fAssert::same(['list-all'], Be014fTrace::$events, 'no PBAC/query after repository failure'); return;
            }
            throw new \RuntimeException('expected UOM list failure');
        },
        'unauthorized base permission takes precedence over malformed sort' => static function (): void {
            $port = new Be014fFakePort(); $port->baseAllowed = false;
            try { (new ProductUomListQueryService(new ProductUomMasterService(be014fRows()), $port))->list(['sort' => 'forbidden']); }
            catch (DomainException $e) { Be014fAssert::same('PRODUCT_UOM_AUTHORIZATION_DENIED', $e->getErrorCode(), 'authorization before parse'); return; }
            throw new \RuntimeException('expected permission failure');
        },
        'invalid filter field retains canonical request rejection' => static function (): void {
            $port = new Be014fFakePort();
            try { (new ProductUomListQueryService(new ProductUomMasterService(be014fRows()), $port))->list(['filter' => ['organization_id' => '1']]); }
            catch (BadRequestException $e) { Be014fAssert::true(str_contains($e->getMessage(), 'filter.organization_id'), 'filter allowlist'); return; }
            throw new \RuntimeException('expected invalid filter failure');
        },
        'invalid page size and unknown keys reject with original parser' => static function (): void {
            foreach ([['page_size' => '201'], ['page' => '0'], ['unknown' => 'X']] as $query) {
                try { (new ProductUomListQueryService(new ProductUomMasterService(be014fRows()), new Be014fFakePort()))->list($query); }
                catch (BadRequestException) { continue; }
                throw new \RuntimeException('expected canonical list-query parser error');
            }
        },
        'search matches code and name before page' => static function (): void {
            $result = be014fParity(['q' => 'g', 'sort' => 'code']);
            Be014fAssert::same([3, 1, 4], array_column($result['items'], 'id'), 'case-insensitive search/sort parity');
        },
        'status filter and descending status sort retain legacy behavior' => static function (): void {
            $result = be014fParity(['filter' => ['status' => 'ACTIVE'], 'sort' => '-status,-name']);
            Be014fAssert::same([4, 1, 3], array_column($result['items'], 'id'), 'active UOMs sorted by name descending');
        },
        'allowlist rejects description sorting even when persisted column exists' => static function (): void {
            try { be014fParity(['sort' => 'description']); }
            catch (BadRequestException) { return; }
            throw new \RuntimeException('expected sort.description rejection');
        },
        'signed filter string preserves exact match semantics' => static function (): void {
            $result = be014fParity(['filter' => ['code' => '01']]);
            Be014fAssert::same('0', $result['total'], 'do not coerce code filter to int');
        },
        'adapter dispatches only the selected authorization branch' => static function (): void {
            $calls = [];
            $adapter = new ProductUomListAuthorizationAdapter(
                static function () use (&$calls): bool { $calls[] = 'state'; return true; },
                static function () use (&$calls): void { $calls[] = 'base'; },
                static function (array $row) use (&$calls): bool { $calls[] = 'row-' . $row['id']; return $row['id'] !== 2; },
            );
            $result = (new ProductUomListQueryService(new ProductUomMasterService(be014fRows()), $adapter))->list([]);
            Be014fAssert::same(['state', 'row-4', 'row-1', 'row-2', 'row-3'], $calls, 'adapter does not synthesize PBAC decisions');
            Be014fAssert::same('3', $result['total'], 'filtered before pagination');
        },
        'facade base path uses same mutation/Authorization and persisted resource' => static function (): void {
            Be014fFlags::$lifecycle = false; Be014fFlags::$statePolicy = false;
            [$facade, $auth, $clock] = be014fFacade(new ProductUomMasterService(be014fRows()));
            $result = $facade->listUoms(['page' => '1', 'page_size' => '2']);
            Be014fAssert::same('4', $result['total'], 'facade response parity');
            Be014fAssert::same(1, count($auth->calls), 'one base decision');
            $request = $auth->calls[0]['request'];
            Be014fAssert::same(null, $request->persistedTarget, 'base gate has no forged target');
            Be014fAssert::same([], $request->input, 'no client scope input');
            Be014fAssert::same(null, $request->clientEvaluationOrganizationId, 'no client scope hint');
            Be014fAssert::same('product_uom', $auth->calls[0]['resource'], 'resource identity preserved');
            Be014fAssert::same('view', $auth->calls[0]['action'], 'action identity preserved');
            Be014fAssert::same(1, $clock->captures, 'one old-style operation for base view');
            Be014fAssert::same(['list-all', 'lifecycle-product_uom', 'resource-product_uom', 'policy-probe', 'pbac-base'],
                array_values(array_filter(Be014fTrace::$events, static fn (string $e): bool => ! str_starts_with($e, 'evaluation-time'))), 'original state/authorization ordering');
        },
        'facade active state policy calls PBAC for each persisted UOM' => static function (): void {
            Be014fFlags::$lifecycle = false; Be014fFlags::$statePolicy = true;
            [$facade, $auth, $clock] = be014fFacade(new ProductUomMasterService(be014fRows()));
            $auth->deniedIds = [4, 2];
            $result = $facade->listUoms(['page_size' => '1', 'page' => '2']);
            Be014fAssert::same('2', $result['total'], 'authorized row count');
            Be014fAssert::same([3], array_column($result['items'], 'id'), 'page sliced after PBAC');
            Be014fAssert::same([4, 1, 2, 3], array_column($auth->calls, 'row'), 'all persisted targets checked');
            Be014fAssert::same(4, $clock->captures, 'old per-row operation capture remains (CXT-001 out of scope)');
            foreach ($auth->calls as $call) {
                Be014fAssert::same([], $call['request']->input, 'no untrusted input');
                Be014fAssert::same(null, $call['request']->clientEvaluationOrganizationId, 'no untrusted scope hint');
                Be014fAssert::same(7, $call['operation']->actorUserId, 'same actor');
                Be014fAssert::same('uom-list-014f', $call['operation']->requestId, 'same request id');
            }
        },
        'facade lifecycle alone enables state PBAC even without active policy' => static function (): void {
            Be014fFlags::$lifecycle = true; Be014fFlags::$statePolicy = false;
            [$facade, $auth, $clock] = be014fFacade(new ProductUomMasterService(be014fRows()));
            $auth->deniedIds = [2];
            $facade->listUoms([]);
            Be014fAssert::same(4, count($auth->calls), 'lifecycle state path checks all rows');
            Be014fAssert::true(! in_array('policy-probe', Be014fTrace::$events, true), 'lifecycle short-circuits policy repository');
        },
        'facade denial keeps machine code and row policy exceptions propagate' => static function (): void {
            Be014fFlags::$lifecycle = false; Be014fFlags::$statePolicy = false;
            [$facade, $auth] = be014fFacade(new ProductUomMasterService(be014fRows()));
            $auth->baseAllowed = false;
            $denied = false;
            try { $facade->listUoms([]); }
            catch (DomainException $e) {
                Be014fAssert::same('PRODUCT_UOM_AUTHORIZATION_DENIED', $e->getErrorCode(), 'legacy error');
                $denied = true;
            }
            Be014fAssert::true($denied, 'base denial must throw');
            Be014fAssert::same(1, count($auth->calls), 'denied only after PBAC');
            Be014fFlags::$statePolicy = true;
            [$facade2, $auth2] = be014fFacade(new ProductUomMasterService(be014fRows()));
            $auth2->throwOnRow = true;
            try { $facade2->listUoms([]); }
            catch (DomainException $e) { Be014fAssert::same('PBAC_RESOLUTION_FAILED', $e->getErrorCode(), 'failed closed'); return; }
            throw new \RuntimeException('expected PBAC resolution failure');
        },
        'source boundary: facade delegates; no Product to legacy reverse import' => static function () use ($app): void {
            $facade = (string) file_get_contents($app . 'Services/Api050ProductCatalogService.php');
            $query = (string) file_get_contents($app . 'Modules/Product/Uom/Application/ProductUomListQueryService.php');
            $adapter = (string) file_get_contents($app . 'Bootstrap/ProductUomListAuthorizationAdapter.php');
            Be014fAssert::true(str_contains($facade, 'new ProductUomListQueryService('), 'facade does not delegate');
            Be014fAssert::true(! str_contains($query, 'App\\Services\\') && ! str_contains($query, 'App\\Authorization\\'), 'new Product implementation imports no legacy/PBAC code');
            Be014fAssert::true(str_contains($adapter, 'implements ProductUomListAuthorizationPort'), 'adapter must implement consumer port');
            Be014fAssert::true(! str_contains($query, 'SqlProduct') && ! str_contains($query, 'db_connect'), 'no SQL or DB substitution');
        },
    ];

    $passed = 0;
    foreach ($cases as $label => $case) {
        Be014fTrace::clear();
        Be014fFlags::$lifecycle = false;
        Be014fFlags::$statePolicy = false;
        try {
            $case();
            echo '  ' . $label . ': PASS' . PHP_EOL;
            $passed++;
        } catch (\Throwable $error) {
            fwrite(STDERR, '  ' . $label . ': FAIL: ' . $error->getMessage() . PHP_EOL);
            exit(1);
        }
    }
    echo 'BE-REORG-014F UOM LIST SOURCE SEAM PASS: ' . $passed
        . ' vendor-free scenarios (MariaDB/Shield and performance NOT_RUN).' . PHP_EOL;
}
