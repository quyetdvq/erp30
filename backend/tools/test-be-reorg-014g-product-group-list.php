<?php

declare(strict_types=1);

/**
 * BE-REORG-014G — vendor-free Product Group List behavior/ownership characterization.
 * Real canonical CommonListQueryAdapter and AuthorizedArrayListSource run here.
 * Only DB, ProductGroupService, Workflow and PBAC are recording doubles.
 * This does NOT replace MariaDB/Shield/API integration and performance tests.
 * Run: php backend/tools/test-be-reorg-014g-product-group-list.php
 */
namespace CodeIgniter\Database {
    interface ConnectionInterface {}
}
namespace CodeIgniter\HTTP\Exceptions {
    class BadRequestException extends \RuntimeException {}
}
namespace App\Modules\Product\ProductGroup {
    final class ProductGroupService
    {
        /** @param list<array<string,mixed>> $rows */
        public function __construct(public array $rows = [], public ?string $failure = null) {}

        /** @return list<array<string,mixed>> */
        public function listAll(): array
        {
            \Be014gTrace::add('list-all');
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
            \Be014gTrace::add('lifecycle-' . $resource);
            return \Be014gFlags::$lifecycle ? 88 : null;
        }
        /** @return array{id:int} */
        public function resource(string $resource): array
        {
            \Be014gTrace::add('resource-' . $resource);
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
            \Be014gAssert::same(22, $resourceId, 'persisted resource ID is used for State policy');
            \Be014gTrace::add('policy-probe');
            return \Be014gFlags::$statePolicy;
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
            \Be014gTrace::add('pbac-' . ($id === null ? 'base' : $id));
            $this->calls[] = ['resource' => $resource, 'action' => $action, 'row' => $id,
                'request' => $request, 'operation' => $operation];
            if ($id !== null && $this->throwOnRow) {
                throw new \App\Exceptions\DomainException('PBAC_RESOLUTION_FAILED');
            }
            $allowed = $id === null ? $this->baseAllowed : ! in_array($id, $this->deniedIds, true);
            return new AuthorizationDecision($allowed ? 'ALLOW' : 'DENY', 'BE014G_TEST', 7,
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
    use App\Bootstrap\ProductGroupListAuthorizationAdapter;
    use App\Exceptions\DomainException;
    use App\Infrastructure\Api\AuthorizedArrayListSource;
    use App\Infrastructure\Api\CommonListQueryAdapter;
    use App\Infrastructure\Api\CommonListQueryAllowlist;
    use App\Infrastructure\Mutation\MutationContext;
    use App\Infrastructure\Time\Clock;
    use App\Modules\Product\ProductGroup\Application\Ports\ProductGroupListAuthorizationPort;
    use App\Modules\Product\ProductGroup\Application\ProductGroupListQueryService;
    use App\Modules\Product\ProductGroup\ProductGroupService;
    use App\Services\Api050ProductCatalogService;
    use CodeIgniter\Database\ConnectionInterface;
    use CodeIgniter\HTTP\Exceptions\BadRequestException;

    final class Be014gTrace
    {
        /** @var list<string> */
        public static array $events = [];
        public static function add(string $event): void { self::$events[] = $event; }
        public static function clear(): void { self::$events = []; }
    }
    final class Be014gFlags
    {
        public static bool $lifecycle = false;
        public static bool $statePolicy = false;
    }
    final class Be014gAssert
    {
        public static function true(bool $yes, string $reason): void
        {
            if (! $yes) { throw new \RuntimeException('BE-REORG-014G: ' . $reason); }
        }
        public static function same(mixed $expected, mixed $actual, string $reason): void
        {
            if ($expected !== $actual) {
                throw new \RuntimeException('BE-REORG-014G: ' . $reason . '\nExpected: '
                    . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
            }
        }
    }
    final class Be014gDb implements ConnectionInterface {}
    final class Be014gClock implements Clock
    {
        public int $captures = 0;
        public function now(): \DateTimeImmutable
        {
            $this->captures++;
            Be014gTrace::add('evaluation-time-' . $this->captures);
            return new \DateTimeImmutable('2026-10-22T00:00:00Z +' . $this->captures . ' seconds');
        }
    }
    final class Be014gFakePort implements ProductGroupListAuthorizationPort
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
            Be014gTrace::add('state-probe');
            return $this->state;
        }
        public function authorizeBaseView(): void
        {
            $this->baseCount++;
            Be014gTrace::add('base-view');
            if (! $this->baseAllowed) { throw new DomainException('PRODUCT_GROUP_AUTHORIZATION_DENIED', ['action' => 'view']); }
        }
        public function rowVisible(array $row): bool
        {
            $id = (int) $row['id'];
            $this->seen[] = $id;
            Be014gTrace::add('row-' . $id);
            if ($this->throwRow) { throw new DomainException('PBAC_RESOLUTION_FAILED'); }
            return ! in_array($id, $this->deniedIds, true);
        }
    }

    /** @return list<array<string,mixed>> */
    function be014gRows(): array
    {
        return [
            ['id' => 4, 'code' => 'KG', 'name' => 'Kitchen Goods', 'parent_id' => 1, 'status' => 'ACTIVE', 'description' => null],
            ['id' => 1, 'code' => 'G', 'name' => 'General', 'parent_id' => null, 'status' => 'ACTIVE', 'description' => 'root'],
            ['id' => 2, 'code' => 'BOX', 'name' => 'Boxes', 'parent_id' => null, 'status' => 'INACTIVE', 'description' => 'root'],
            ['id' => 3, 'code' => 'BAG', 'name' => 'Bags', 'parent_id' => 2, 'status' => 'ACTIVE', 'description' => null],
        ];
    }

    /**
     * Frozen algorithm from API-050 pre-014G using canonical production
     * query adapter. Both sides take separate doubles with identical setup.
     * @param array<string,mixed> $query
     * @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    function be014gLegacyList(ProductGroupService $groups, Be014gFakePort $policy, array $query): array
    {
        $rows = $groups->listAll();
        if ($policy->requiresRowStateAuthorization()) {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $policy->rowVisible($row)));
        } else {
            $policy->authorizeBaseView();
        }
        $result = (new CommonListQueryAdapter())->paginate($query,
            new CommonListQueryAllowlist(
                search: ['code', 'name'],
                filters: ['code', 'name', 'parent_id', 'status'],
                sort: ['id', 'code', 'name', 'parent_id', 'status'],
            ), new AuthorizedArrayListSource($rows));
        return ['items' => array_values(iterator_to_array($result->data)), 'page' => $result->query->page,
            'page_size' => $result->query->pageSize, 'total' => $result->total];
    }

    /** @param array<string,mixed> $query */
    function be014gParity(array $query, bool $state = false, array $deniedIds = []): array
    {
        $legacyPolicy = new Be014gFakePort();
        $legacyPolicy->state = $state;
        $legacyPolicy->deniedIds = $deniedIds;
        $expected = be014gLegacyList(new ProductGroupService(be014gRows()), $legacyPolicy, $query);
        Be014gTrace::clear();
        $newPolicy = new Be014gFakePort();
        $newPolicy->state = $state;
        $newPolicy->deniedIds = $deniedIds;
        $actual = (new ProductGroupListQueryService(new ProductGroupService(be014gRows()), $newPolicy))->list($query);
        Be014gAssert::same($expected, $actual, 'new list must match frozen legacy algorithm');
        Be014gAssert::same($legacyPolicy->seen, $newPolicy->seen, 'row PBAC targets and order must match');
        Be014gAssert::same($legacyPolicy->baseCount, $newPolicy->baseCount, 'base gate calls must match');
        return $actual;
    }

    /** @return array{0:Api050ProductCatalogService,1:AuthorizationService,2:Be014gClock} */
    function be014gFacade(ProductGroupService $groups): array
    {
        $clock = new Be014gClock();
        $authorization = new AuthorizationService();
        $service = new Api050ProductCatalogService(
            db: new Be014gDb(),
            authorization: $authorization,
            mutationContext: new MutationContext($clock, 7, 'group-list-014g', false),
            groups: $groups,
        );
        return [$service, $authorization, $clock];
    }

    /** @var array<string,\Closure():void> $cases */
    $cases = [
        'base-scope list: full materialization and exact legacy pagination' => static function (): void {
            $result = be014gParity(['page' => '2', 'page_size' => '2']);
            Be014gAssert::same(['3', '4'], array_map('strval', array_column($result['items'], 'id')), 'stable id ordering and second page');
            Be014gAssert::same('4', $result['total'], 'total remains decimal string including inactive GROUP');
            Be014gAssert::same(['list-all', 'state-probe', 'base-view'], Be014gTrace::$events, 'legacy evaluation order');
        },
        'state PBAC excludes denied rows before total/page' => static function (): void {
            $result = be014gParity(['page' => '1', 'page_size' => '2', 'sort' => '-id'], true, [4, 2]);
            Be014gAssert::same([3, 1], array_column($result['items'], 'id'), 'authorized page of state-scoped records');
            Be014gAssert::same('2', $result['total'], 'no count leakage of unauthorized rows');
        },
        'state PBAC checks every persisted row, not only the first page' => static function (): void {
            be014gParity(['page' => '2', 'page_size' => '1'], true, [2]);
            Be014gAssert::same(['list-all', 'state-probe', 'row-4', 'row-1', 'row-2', 'row-3'], Be014gTrace::$events, 'all decisions before page slice');
        },
        'all rows denied returns zero count and empty page' => static function (): void {
            $result = be014gParity(['page' => '1'], true, [4, 1, 2, 3]);
            Be014gAssert::same([], $result['items'], 'no row may escape denial');
            Be014gAssert::same('0', $result['total'], 'empty visible set');
        },
        'state path never uses unscoped base-only authorization' => static function (): void {
            $port = new Be014gFakePort(); $port->state = true; $port->baseAllowed = false;
            (new ProductGroupListQueryService(new ProductGroupService(be014gRows()), $port))->list([]);
            Be014gAssert::same(0, $port->baseCount, 'legacy per-row decision is the only state path');
        },
        'unscoped base permission denial retains machine code and action' => static function (): void {
            $port = new Be014gFakePort(); $port->baseAllowed = false;
            try { (new ProductGroupListQueryService(new ProductGroupService(be014gRows()), $port))->list([]); }
            catch (DomainException $e) {
                Be014gAssert::same('PRODUCT_GROUP_AUTHORIZATION_DENIED', $e->getErrorCode(), 'stable error code');
                Be014gAssert::same('view', $e->getContext()['action'], 'stable action context'); return;
            }
            throw new \RuntimeException('expected base view denial');
        },
        'a policy resolution error remains fail-closed' => static function (): void {
            $port = new Be014gFakePort(); $port->state = true; $port->throwRow = true;
            try { (new ProductGroupListQueryService(new ProductGroupService(be014gRows()), $port))->list([]); }
            catch (DomainException $e) { Be014gAssert::same('PBAC_RESOLUTION_FAILED', $e->getErrorCode(), 'no catch/allow fallback'); return; }
            throw new \RuntimeException('expected PBAC resolver failure');
        },
        'Group persistence failure is observed before policy or malformed query' => static function (): void {
            $port = new Be014gFakePort();
            try { (new ProductGroupListQueryService(new ProductGroupService([], 'PRODUCT_GROUP_CONNECTION_INVALID'), $port))->list(['sort' => 'bad-field']); }
            catch (DomainException $e) {
                Be014gAssert::same('PRODUCT_GROUP_CONNECTION_INVALID', $e->getErrorCode(), 'legacy failure precedence');
                Be014gAssert::same(['list-all'], Be014gTrace::$events, 'no PBAC/query after repository failure'); return;
            }
            throw new \RuntimeException('expected PRODUCT GROUP LIST failure');
        },
        'unauthorized base permission takes precedence over malformed sort' => static function (): void {
            $port = new Be014gFakePort(); $port->baseAllowed = false;
            try { (new ProductGroupListQueryService(new ProductGroupService(be014gRows()), $port))->list(['sort' => 'forbidden']); }
            catch (DomainException $e) { Be014gAssert::same('PRODUCT_GROUP_AUTHORIZATION_DENIED', $e->getErrorCode(), 'authorization before parse'); return; }
            throw new \RuntimeException('expected permission failure');
        },
        'invalid filter field retains canonical request rejection' => static function (): void {
            $port = new Be014gFakePort();
            try { (new ProductGroupListQueryService(new ProductGroupService(be014gRows()), $port))->list(['filter' => ['organization_id' => '1']]); }
            catch (BadRequestException $e) { Be014gAssert::true(str_contains($e->getMessage(), 'filter.organization_id'), 'filter allowlist'); return; }
            throw new \RuntimeException('expected invalid filter failure');
        },
        'invalid page size and unknown keys reject with original parser' => static function (): void {
            foreach ([['page_size' => '201'], ['page' => '0'], ['unknown' => 'X']] as $query) {
                try { (new ProductGroupListQueryService(new ProductGroupService(be014gRows()), new Be014gFakePort()))->list($query); }
                catch (BadRequestException) { continue; }
                throw new \RuntimeException('expected canonical list-query parser error');
            }
        },
        'search matches code and name before page' => static function (): void {
            $result = be014gParity(['q' => 'g', 'sort' => 'code']);
            Be014gAssert::same([3, 1, 4], array_column($result['items'], 'id'), 'case-insensitive search/sort parity');
        },
        'status filter and descending status sort retain legacy behavior' => static function (): void {
            $result = be014gParity(['filter' => ['status' => 'ACTIVE'], 'sort' => '-status,-name']);
            Be014gAssert::same([4, 1, 3], array_column($result['items'], 'id'), 'active Product Groups sorted by name descending');
        },
        'allowlist rejects description sorting even when persisted column exists' => static function (): void {
            try { be014gParity(['sort' => 'description']); }
            catch (BadRequestException) { return; }
            throw new \RuntimeException('expected sort.description rejection');
        },
        'signed filter string preserves exact match semantics' => static function (): void {
            $result = be014gParity(['filter' => ['code' => '01']]);
            Be014gAssert::same('0', $result['total'], 'do not coerce code filter to int');
        },
        'adapter dispatches only the selected authorization branch' => static function (): void {
            $calls = [];
            $adapter = new ProductGroupListAuthorizationAdapter(
                static function () use (&$calls): bool { $calls[] = 'state'; return true; },
                static function () use (&$calls): void { $calls[] = 'base'; },
                static function (array $row) use (&$calls): bool { $calls[] = 'row-' . $row['id']; return $row['id'] !== 2; },
            );
            $result = (new ProductGroupListQueryService(new ProductGroupService(be014gRows()), $adapter))->list([]);
            Be014gAssert::same(['state', 'row-4', 'row-1', 'row-2', 'row-3'], $calls, 'adapter does not synthesize PBAC decisions');
            Be014gAssert::same('3', $result['total'], 'filtered before pagination');
        },
        'facade base path uses same mutation/Authorization and persisted resource' => static function (): void {
            Be014gFlags::$lifecycle = false; Be014gFlags::$statePolicy = false;
            [$facade, $auth, $clock] = be014gFacade(new ProductGroupService(be014gRows()));
            $result = $facade->listGroups(['page' => '1', 'page_size' => '2']);
            Be014gAssert::same('4', $result['total'], 'facade response parity');
            Be014gAssert::same(1, count($auth->calls), 'one base decision');
            $request = $auth->calls[0]['request'];
            Be014gAssert::same(null, $request->persistedTarget, 'base gate has no forged target');
            Be014gAssert::same([], $request->input, 'no client scope input');
            Be014gAssert::same(null, $request->clientEvaluationOrganizationId, 'no client scope hint');
            Be014gAssert::same('product_group', $auth->calls[0]['resource'], 'resource identity preserved');
            Be014gAssert::same('view', $auth->calls[0]['action'], 'action identity preserved');
            Be014gAssert::same(1, $clock->captures, 'one old-style operation for base view');
            Be014gAssert::same(['list-all', 'lifecycle-product_group', 'resource-product_group', 'policy-probe', 'pbac-base'],
                array_values(array_filter(Be014gTrace::$events, static fn (string $e): bool => ! str_starts_with($e, 'evaluation-time'))), 'original state/authorization ordering');
        },
        'facade active state policy calls PBAC for each persisted Group' => static function (): void {
            Be014gFlags::$lifecycle = false; Be014gFlags::$statePolicy = true;
            [$facade, $auth, $clock] = be014gFacade(new ProductGroupService(be014gRows()));
            $auth->deniedIds = [4, 2];
            $result = $facade->listGroups(['page_size' => '1', 'page' => '2']);
            Be014gAssert::same('2', $result['total'], 'authorized row count');
            Be014gAssert::same([3], array_column($result['items'], 'id'), 'page sliced after PBAC');
            Be014gAssert::same([4, 1, 2, 3], array_column($auth->calls, 'row'), 'all persisted targets checked');
            Be014gAssert::same(4, $clock->captures, 'old per-row operation capture remains (CXT-001 out of scope)');
            foreach ($auth->calls as $call) {
                Be014gAssert::same([], $call['request']->input, 'no untrusted input');
                Be014gAssert::same(null, $call['request']->clientEvaluationOrganizationId, 'no untrusted scope hint');
                Be014gAssert::same(7, $call['operation']->actorUserId, 'same actor');
                Be014gAssert::same('group-list-014g', $call['operation']->requestId, 'same request id');
            }
        },
        'facade lifecycle alone enables state PBAC even without active policy' => static function (): void {
            Be014gFlags::$lifecycle = true; Be014gFlags::$statePolicy = false;
            [$facade, $auth, $clock] = be014gFacade(new ProductGroupService(be014gRows()));
            $auth->deniedIds = [2];
            $facade->listGroups([]);
            Be014gAssert::same(4, count($auth->calls), 'lifecycle state path checks all rows');
            Be014gAssert::true(! in_array('policy-probe', Be014gTrace::$events, true), 'lifecycle short-circuits policy repository');
        },
        'facade denial keeps machine code and row policy exceptions propagate' => static function (): void {
            Be014gFlags::$lifecycle = false; Be014gFlags::$statePolicy = false;
            [$facade, $auth] = be014gFacade(new ProductGroupService(be014gRows()));
            $auth->baseAllowed = false;
            $denied = false;
            try { $facade->listGroups([]); }
            catch (DomainException $e) {
                Be014gAssert::same('PRODUCT_GROUP_AUTHORIZATION_DENIED', $e->getErrorCode(), 'legacy error');
                $denied = true;
            }
            Be014gAssert::true($denied, 'base denial must throw');
            Be014gAssert::same(1, count($auth->calls), 'denied only after PBAC');
            Be014gFlags::$statePolicy = true;
            [$facade2, $auth2] = be014gFacade(new ProductGroupService(be014gRows()));
            $auth2->throwOnRow = true;
            try { $facade2->listGroups([]); }
            catch (DomainException $e) { Be014gAssert::same('PBAC_RESOLUTION_FAILED', $e->getErrorCode(), 'failed closed'); return; }
            throw new \RuntimeException('expected PBAC resolution failure');
        },
        'source boundary: facade delegates; no Product to legacy reverse import' => static function () use ($app): void {
            $facade = (string) file_get_contents($app . 'Services/Api050ProductCatalogService.php');
            $query = (string) file_get_contents($app . 'Modules/Product/ProductGroup/Application/ProductGroupListQueryService.php');
            $adapter = (string) file_get_contents($app . 'Bootstrap/ProductGroupListAuthorizationAdapter.php');
            Be014gAssert::true(str_contains($facade, 'new ProductGroupListQueryService('), 'facade does not delegate');
            Be014gAssert::true(! str_contains($query, 'App\\Services\\') && ! str_contains($query, 'App\\Authorization\\'), 'new Product implementation imports no legacy/PBAC code');
            Be014gAssert::true(str_contains($adapter, 'implements ProductGroupListAuthorizationPort'), 'adapter must implement consumer port');
            Be014gAssert::true(! str_contains($query, 'SqlProduct') && ! str_contains($query, 'db_connect'), 'no SQL or DB substitution');
        },
        'authorized child survives denial of its persisted parent' => static function (): void {
            // Previous API-050 filtered *rows*, not subtrees or closure paths.
            $page = be014gParity(['sort' => 'id'], true, [2]);
            Be014gAssert::same([1, 3, 4], array_column($page['items'], 'id'), 'denied parent 2 is filtered');
            $child = array_values(array_filter($page['items'], static fn (array $row): bool => $row['id'] === 3))[0];
            Be014gAssert::same(2, $child['parent_id'], 'visible child keeps original parent_id even if parent denied');
            Be014gAssert::same('3', $page['total'], 'total counts every independently visible child');
        },
        'hidden child does not hide its parent or sibling branch' => static function (): void {
            $page = be014gParity(['sort' => 'id'], true, [3, 4]);
            Be014gAssert::same([1, 2], array_column($page['items'], 'id'), 'parent visibility evaluated independently of children');
            Be014gAssert::same([null, null], array_column($page['items'], 'parent_id'), 'parent rows unchanged');
        },
        'parent_id filter matches stored child id without ancestor expansion' => static function (): void {
            $page = be014gParity(['filter' => ['parent_id' => '2']]);
            Be014gAssert::same([3], array_column($page['items'], 'id'), 'only direct child matches stored parent id');
            Be014gAssert::same('1', $page['total'], 'authorized total after parent filter');
        },
        'parent_id filter after state PBAC does not leak denied child count' => static function (): void {
            $page = be014gParity(['filter' => ['parent_id' => '2']], true, [3]);
            Be014gAssert::same([], $page['items'], 'denied row excluded from filtered result');
            Be014gAssert::same('0', $page['total'], 'filtered count does not leak denied group');
            Be014gAssert::same(['list-all', 'state-probe', 'row-4', 'row-1', 'row-2', 'row-3'], Be014gTrace::$events,
                'authorization precedes parent filter');
        },
        'noncanonical parent_id 02 is NOT coerced to numeric 2' => static function (): void {
            $page = be014gParity(['filter' => ['parent_id' => '02']]);
            Be014gAssert::same([], $page['items'], 'canonical PHP exact string comparison');
            Be014gAssert::same('0', $page['total'], 'parent id filter preserves exact string');
        },
        'null parent filter retains legacy empty-string behavior' => static function (): void {
            $page = be014gParity(['filter' => ['parent_id' => ''], 'sort' => 'id']);
            Be014gAssert::same([1, 2], array_column($page['items'], 'id'), 'PHP adapter treated null parent as empty string');
            Be014gAssert::same('2', $page['total'], 'two roots in visible set');
        },
        'parent_id sorting and page tie-break retain original comparator' => static function (): void {
            $page = be014gParity(['sort' => '-parent_id,-code', 'page' => '2', 'page_size' => '2']);
            Be014gAssert::same([1, 2], array_column($page['items'], 'id'), 'null parents follow numbered parents in DESC');
            Be014gAssert::same(2, $page['page'], 'original page shape');
            Be014gAssert::same(2, $page['page_size'], 'original page size');
        },
        'parent and status multi-value filters remain exact' => static function (): void {
            $page = be014gParity(['filter' => ['parent_id' => ['1', '2'], 'status' => ['ACTIVE']], 'sort' => 'id']);
            Be014gAssert::same([3, 4], array_column($page['items'], 'id'), 'multi-value and intersection filters');
            Be014gAssert::same('2', $page['total'], 'result total after filtering');
        },
        'multi-level branch is still a FLAT list even if middle ancestor denied' => static function (): void {
            $rows = [
                ['id' => 11, 'parent_id' => null, 'code' => 'ROOT', 'name' => 'Root', 'status' => 'ACTIVE'],
                ['id' => 12, 'parent_id' => 11, 'code' => 'MID', 'name' => 'Middle', 'status' => 'INACTIVE'],
                ['id' => 13, 'parent_id' => 12, 'code' => 'LEAF', 'name' => 'Leaf', 'status' => 'ACTIVE'],
                ['id' => 14, 'parent_id' => 13, 'code' => 'DEEP', 'name' => 'Deep', 'status' => 'ACTIVE'],
            ];
            $old = new Be014gFakePort(); $old->state = true; $old->deniedIds = [12];
            $expected = be014gLegacyList(new ProductGroupService($rows), $old, ['sort' => 'id']);
            $port = new Be014gFakePort(); $port->state = true; $port->deniedIds = [12];
            $actual = (new ProductGroupListQueryService(new ProductGroupService($rows), $port))->list(['sort' => 'id']);
            Be014gAssert::same($expected, $actual, 'multi-depth parity against frozen list implementation');
            Be014gAssert::same([11, 13, 14], array_column($actual['items'], 'id'), 'middle denied but grandchildren visible');
            Be014gAssert::same([null, 12, 13], array_column($actual['items'], 'parent_id'), 'parent ids never rewritten');
        },
        'missing parent reference is NOT silently repaired during listing' => static function (): void {
            $rows = [['id' => 21, 'parent_id' => 999, 'code' => 'ORPHAN', 'name' => 'Orphan', 'status' => 'ACTIVE']];
            $policy = new Be014gFakePort();
            $old = be014gLegacyList(new ProductGroupService($rows), $policy, []);
            $actual = (new ProductGroupListQueryService(new ProductGroupService($rows), new Be014gFakePort()))->list([]);
            Be014gAssert::same($old, $actual, 'no newly added tree validation on read');
            Be014gAssert::same(999, $actual['items'][0]['parent_id'], 'original missing-parent id exposed as legacy');
        },
        'empty repository result still applies base permission' => static function (): void {
            $port = new Be014gFakePort(); $port->baseAllowed = false;
            try { (new ProductGroupListQueryService(new ProductGroupService([]), $port))->list([]); }
            catch (DomainException $e) {
                Be014gAssert::same('PRODUCT_GROUP_AUTHORIZATION_DENIED', $e->getErrorCode(), 'empty list is not an authorization bypass');
                Be014gAssert::same(['list-all', 'state-probe', 'base-view'], Be014gTrace::$events, 'base gate applied after load'); return;
            }
            throw new \RuntimeException('empty group list should still check base permission');
        },
        'state eligibility with empty repository never falls back to base gate' => static function (): void {
            $port = new Be014gFakePort(); $port->state = true;
            $page = (new ProductGroupListQueryService(new ProductGroupService([]), $port))->list([]);
            Be014gAssert::same('0', $page['total'], 'empty visible state list');
            Be014gAssert::same([], $port->seen, 'no fabricated PBAC target');
            Be014gAssert::same(0, $port->baseCount, 'base gate not substituted for state mode');
        },
        'Group authorization and database reads do not depend on query pagination' => static function (): void {
            $port = new Be014gFakePort(); $port->state = true;
            $page = (new ProductGroupListQueryService(new ProductGroupService(be014gRows()), $port))->list(['page' => '99', 'page_size' => '1']);
            Be014gAssert::same([], $page['items'], 'out of range page empty');
            Be014gAssert::same('4', $page['total'], 'out of range page retains authorized total');
            Be014gAssert::same([4, 1, 2, 3], $port->seen, 'all rows passed PBAC despite high page');
        },
    ];

    $passed = 0;
    foreach ($cases as $label => $case) {
        Be014gTrace::clear();
        Be014gFlags::$lifecycle = false;
        Be014gFlags::$statePolicy = false;
        try {
            $case();
            echo '  ' . $label . ': PASS' . PHP_EOL;
            $passed++;
        } catch (\Throwable $error) {
            fwrite(STDERR, '  ' . $label . ': FAIL: ' . $error->getMessage() . PHP_EOL);
            exit(1);
        }
    }
    echo 'BE-REORG-014G PRODUCT GROUP LIST SOURCE SEAM PASS: ' . $passed
        . ' vendor-free scenarios (MariaDB/Shield and performance NOT_RUN).' . PHP_EOL;
}
