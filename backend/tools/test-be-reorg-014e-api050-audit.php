<?php

declare(strict_types=1);

/**
 * BE-REORG-014E source-only audit gate. No Composer, HTTP, SQL, or DB calls.
 * This tests inventory/compatibility assertions, NOT behavioral equivalence.
 * When an intentional refactor changes a recorded boundary, update the audit
 * and its characterization tests together rather than disabling this gate.
 */

$backend = dirname(__DIR__);
$app = $backend . '/app/';
$inventoryPath = __DIR__ . '/architecture/api050-orchestration-inventory.json';
$inventory = json_decode((string) file_get_contents($inventoryPath), true, 512, JSON_THROW_ON_ERROR);
$routes = (string) file_get_contents($app . 'Config/Routes.php');
$controller = (string) file_get_contents($app . 'Controllers/Api/ProductCatalog.php');
$facade = (string) file_get_contents($app . 'Services/Api050ProductCatalogService.php');
$openapi = (string) file_get_contents(dirname($backend) . '/contracts/openapi/openapi.yaml');

/** @return array<string,string> */
function auditMethods(string $php): array
{
    $tokens = token_get_all($php, TOKEN_PARSE);
    $methods = [];
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }
        $j = $i + 1;
        while ($j < $count && ((is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) || $tokens[$j] === '&')) {
            $j++;
        }
        if ($j >= $count || ! is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) {
            continue; // Closure; no callable name to audit.
        }
        $name = $tokens[$j][1];
        while ($j < $count && $tokens[$j] !== '{' && $tokens[$j] !== ';') {
            $j++;
        }
        if ($j === $count || $tokens[$j] === ';') {
            continue;
        }
        $depth = 1;
        $body = '';
        for ($j++; $j < $count && $depth > 0; $j++) {
            $token = $tokens[$j];
            if ($token === '{') {
                $depth++;
            } elseif ($token === '}') {
                $depth--;
            }
            if ($depth > 0) {
                $body .= is_array($token) ? $token[1] : $token;
            }
        }
        $methods[$name] = $body;
    }
    return $methods;
}

/** @return array<string,string> keyed by method + absolute API path */
function auditRouteMap(string $routes): array
{
    $map = [];
    foreach (explode("\n", $routes) as $line) {
        if (! str_contains($line, 'Api\\\\ProductCatalog::')) {
            continue;
        }
        if (preg_match('~\$routes->(get|post|patch|put|delete)\(\s*[\x27]([^\x27]+)[\x27]\s*,\s*[\x27]([^\x27]+)[\x27]\s*\)~', $line, $m) !== 1) {
            throw new RuntimeException('Unrecognized ProductCatalog route: ' . $line);
        }
        $target = $m[3];
        $prefix = 'Api\\\\ProductCatalog::';
        if (! str_starts_with($target, $prefix)) {
            throw new RuntimeException('Unrecognized ProductCatalog target: ' . $target);
        }
        $controllerMethod = explode('/', substr($target, strlen($prefix)), 2)[0];
        $key = strtoupper($m[1]) . ' /api/v1/' . $m[2];
        if (isset($map[$key])) {
            throw new RuntimeException('Duplicate ProductCatalog route: ' . $key);
        }
        $map[$key] = $controllerMethod;
    }
    return $map;
}

/** @param mixed $value */
function auditAssert(bool $condition, string $why): void
{
    if (! $condition) {
        throw new RuntimeException($why);
    }
}

$controllerBodies = auditMethods($controller);
$facadeBodies = auditMethods($facade);
$routeMap = auditRouteMap($routes);
$operations = $inventory['operations'] ?? null;
if (! is_array($operations)) {
    fwrite(STDERR, "BE-REORG-014E inventory missing operations; FAIL CLOSED\n");
    exit(1);
}

$checks = [
    'manifest is an observed 26-operation inventory, not an approval' => static function () use ($inventory, $operations): void {
        auditAssert(($inventory['change_id'] ?? null) === 'BE-REORG-014E', 'Wrong change ID');
        auditAssert(($inventory['operation_count'] ?? null) === 26 && count($operations) === 26, 'Incorrect operation count');
        auditAssert(str_contains((string) ($inventory['classification'] ?? ''), 'NOT approval'), 'Must mark source-observed as unapproved');
    },
    'all 26 methods and paths match the current /api/v1 route table' => static function () use ($operations, $routeMap): void {
        auditAssert(count($routeMap) === 26, 'Unexpected route count');
        $seen = [];
        foreach ($operations as $item) {
            auditAssert(is_array($item), 'Invalid manifest item');
            $route = ($item['method'] ?? '') . ' ' . ($item['path'] ?? '');
            auditAssert(! isset($seen[$route]), 'Duplicate manifest route: ' . $route);
            $seen[$route] = true;
            auditAssert(($routeMap[$route] ?? null) === ($item['controller_method'] ?? null), 'Route mismatch: ' . $route);
        }
        auditAssert(count($seen) === count($routeMap), 'Missing ProductCatalog route');
    },
    'every controller operation delegates to the mapped facade method' => static function () use ($operations, $controllerBodies, $facadeBodies): void {
        foreach ($operations as $item) {
            $controller = $item['controller_method'];
            $method = $item['facade_method'];
            auditAssert(isset($controllerBodies[$controller]), 'Missing controller method: ' . $controller);
            auditAssert(isset($facadeBodies[$method]), 'Missing facade method: ' . $method);
            auditAssert(str_contains($controllerBodies[$controller], '->service()->' . $method . '('), 'Changed controller delegation: ' . $controller);
        }
    },
    '26 OpenAPI operationIds are present without duplicates' => static function () use ($operations, $openapi): void {
        preg_match_all('/^\s+operationId:\s*([A-Za-z][A-Za-z0-9_]*)\s*$/m', $openapi, $all);
        $counts = array_count_values($all[1] ?? []);
        $seen = [];
        foreach ($operations as $item) {
            $id = $item['operation_id'];
            auditAssert(! isset($seen[$id]), 'Duplicate inventory operationId: ' . $id);
            $seen[$id] = true;
            auditAssert(($counts[$id] ?? 0) === 1, 'Missing or duplicated OpenAPI operationId: ' . $id);
        }
    },
    'no additional unlisted public facade entrypoint is introduced' => static function () use ($operations, $facade): void {
        preg_match_all('/\bpublic\s+function\s+([A-Za-z_][A-Za-z_0-9]*)\s*\(/', $facade, $m);
        $public = $m[1] ?? [];
        $facadeMethods = array_column($operations, 'facade_method');
        $expected = array_merge($facadeMethods, ['__construct', 'productReadAccess', 'productForImage']);
        sort($expected);
        sort($public);
        auditAssert(count($expected) === count(array_unique($expected)), 'Duplicate facade method in inventory');
        auditAssert($public === $expected, 'Facade public surface changed; update compatibility audit');
    },
    'Product owns 19 operations; Pricing owns 7; no invented module' => static function () use ($operations): void {
        $counts = array_count_values(array_column($operations, 'module_owner'));
        auditAssert($counts === ['Product' => 19, 'Pricing' => 7], 'Module ownership inventory changed');
    },
    '014C/014D/014F/014G list queries delegate to their module owners' => static function () use ($facadeBodies, $inventory): void {
        auditAssert(str_contains($facadeBodies['listProducts'], 'new ProductListQueryService('), 'Product list no longer delegates');
        auditAssert(str_contains($facadeBodies['listPriceLists'], 'new PriceListQueryService('), 'Price list no longer delegates');
        auditAssert(str_contains($facadeBodies['listUoms'], 'new ProductUomListQueryService('), 'UOM list no longer delegates');
        auditAssert(str_contains($facadeBodies['listGroups'], 'new ProductGroupListQueryService('), 'Group list no longer delegates');
        auditAssert(($inventory['already_query_delegated'] ?? null) === ['listProducts', 'listPriceLists', 'listUoms', 'listGroups'], 'Delegation registry must track 014G');
    },
    'Group and UOM lists retain authorized materialization in their owners' => static function () use ($facadeBodies, $app, $operations): void {
        foreach ([
            ['listGroups', 'ProductGroup', 'ProductGroupListQueryService', 'product_group',
                "filters: ['code', 'name', 'parent_id', 'status']", "sort: ['id', 'code', 'name', 'parent_id', 'status']", 'DELEGATED_014G'],
            ['listUoms', 'Uom', 'ProductUomListQueryService', 'product_uom',
                "filters: ['code', 'name', 'status']", "sort: ['id', 'code', 'name', 'status']", 'DELEGATED_014F'],
        ] as [$method, $folder, $service, $resource, $filterFields, $sortFields, $boundary]) {
            $facadeBody = $facadeBodies[$method];
            auditAssert(str_contains($facadeBody, "requiresRowStateAuthorization('{$resource}')"), "{$method} State policy callback changed");
            auditAssert(str_contains($facadeBody, "->decision('{$resource}', 'view', \$row)"), "{$method} persisted-row PBAC callback changed");
            auditAssert(str_contains($facadeBody, "->authorize('{$resource}', 'view')"), "{$method} base PBAC callback changed");
            $query = (string) file_get_contents($app . "Modules/Product/{$folder}/Application/{$service}.php");
            $body = auditMethods($query)['list'] ?? '';
            $listAll = strpos($body, '->listAll()');
            $state = strpos($body, '->authorization->requiresRowStateAuthorization()');
            $rowAuth = strpos($body, '->authorization->rowVisible($row)');
            $page = strpos($body, '->paginate(');
            auditAssert($listAll !== false && $state !== false && $rowAuth !== false && $page !== false
                && $listAll < $state && $state < $rowAuth && $rowAuth < $page, "{$service} PBAC/count order changed");
            auditAssert(str_contains($body, '->authorization->authorizeBaseView()'), "{$service} base gate missing");
            auditAssert(str_contains($body, 'new AuthorizedArrayListSource($rows)'), "{$service} materialized authorized source changed");
            auditAssert(str_contains($body, $filterFields) && str_contains($body, $sortFields), "{$service} allowlists changed");
            $entry = array_values(array_filter($operations, static fn (array $op): bool => $op['facade_method'] === $method));
            auditAssert(count($entry) === 1 && $entry[0]['observed_boundary'] === $boundary, "{$service} manifest outdated");
        }
        $groups = (string) file_get_contents($app . 'Modules/Product/ProductGroup/Application/ProductGroupListQueryService.php');
        auditAssert(! str_contains($groups, 'resolveAncestor') && ! str_contains($groups, '->listChildren('),
            '014G must not re-parent, prune, or rebuild hierarchy during flat list read');
    },
    'createProduct KIR-001 keeps its command transaction while Numbering participates explicitly' => static function () use ($facadeBodies, $app): void {
        $method = $facadeBodies['createProduct'];
        foreach (["array_key_exists('combo_items'", '->transBegin()', '->products()->create(', '->combos()->replaceItems('] as $marker) {
            auditAssert(str_contains($method, $marker), 'COMBO create changed; KIR-001 matrix/ADR required: ' . $marker);
        }
        $allocator = (string) file_get_contents($app . 'Modules/Numbering/Allocation/Application/AutoNumberingCounterAllocator.php');
        auditAssert(str_contains($allocator, '$ownsTransaction = (int) $this->db->transDepth === 0'), 'KIR-001 own-or-participate decision missing');
        auditAssert(str_contains($allocator, '$ownsTransaction && ! $this->db->transCommit()'), 'KIR-001 caller-owned commit guard missing');
        auditAssert(! str_contains($allocator, 'if ((int) $this->db->transDepth > 0)'), 'Legacy KIR-001 nested-transaction rejection returned');
    },
    'read-after-write and Product media compatibility are documented by active code' => static function () use ($facadeBodies, $app): void {
        foreach (['updateGroup' => 'getGroupWithOperation', 'setGroupState' => 'getGroupWithOperation', 'updateUom' => 'getUomWithOperation',
            'setUomState' => 'getUomWithOperation', 'updateProduct' => 'getProductWithOperation', 'setProductState' => 'getProductWithOperation',
            'createPriceList' => 'getPriceListWithOperation', 'updatePriceList' => 'getPriceListWithOperation',
            'setPriceListState' => 'getPriceListWithOperation'] as $write => $read) {
            auditAssert(str_contains($facadeBodies[$write], '->' . $read . '('), 'Read-after-write boundary changed: ' . $write);
        }
        $mediaFactory = (string) file_get_contents($app . 'Bootstrap/ProductMediaFactory.php');
        $imagesController = (string) file_get_contents($app . 'Controllers/Api/ProductImages.php');
        auditAssert(str_contains($mediaFactory, '$catalog->productReadAccess()'), 'Media factory compatibility changed');
        auditAssert(str_contains($imagesController, '->productForImage('), 'Product image authorization entrypoint changed');
    },
    'operation() stays stateless while 014I pins one context per affected command' => static function () use ($facadeBodies): void {
        auditAssert(str_contains($facadeBodies['operation'], '$this->mutationContext->begin()'), 'OperationContext path changed; context parity must be revisited');
        auditAssert(substr_count($facadeBodies['createProduct'], '$this->operation()') === 1, 'createProduct must capture exactly one command context');
        auditAssert(str_contains($facadeBodies['createProduct'], '->getProductWithOperation($id, $operation)'), 'createProduct readback must reuse command context');
    },
];

$pass = 0;
foreach ($checks as $label => $check) {
    try {
        $check();
        echo "  {$label}: PASS\n";
        $pass++;
    } catch (Throwable $error) {
        fwrite(STDERR, "  {$label}: FAIL: {$error->getMessage()}\n");
        exit(1);
    }
}

echo "BE-REORG-014E API-050 SOURCE INVENTORY PASS: {$pass} checks; 26 routes, 4 list delegations and 22 other HTTP operations. No DB/Shield executed.\n";
