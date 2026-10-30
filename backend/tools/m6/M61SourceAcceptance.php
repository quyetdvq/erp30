<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__ . '/../m5/M55SourceAcceptance.php';

/** Exact ten API-050 Group/UOM routes; source-only, NOT HTTP/MariaDB acceptance. */
final class M61SourceAcceptance
{
    /** @return array<string,array{id:string,path:string,status:int}> */
    public static function operations(): array
    {
        return [
            'GET product-groups' => ['id'=>'listProductGroups','path'=>'/api/v1/product-groups','status'=>200],
            'POST product-groups' => ['id'=>'createProductGroup','path'=>'/api/v1/product-groups','status'=>201],
            'GET product-groups/(:num)' => ['id'=>'getProductGroup','path'=>'/api/v1/product-groups/{id}','status'=>200],
            'PATCH product-groups/(:num)' => ['id'=>'updateProductGroup','path'=>'/api/v1/product-groups/{id}','status'=>200],
            'POST product-groups/(:num)/actions/set-state' => ['id'=>'setProductGroupState','path'=>'/api/v1/product-groups/{id}/actions/set-state','status'=>200],
            'GET product-uoms' => ['id'=>'listProductUoms','path'=>'/api/v1/product-uoms','status'=>200],
            'POST product-uoms' => ['id'=>'createProductUom','path'=>'/api/v1/product-uoms','status'=>201],
            'GET product-uoms/(:num)' => ['id'=>'getProductUom','path'=>'/api/v1/product-uoms/{id}','status'=>200],
            'PATCH product-uoms/(:num)' => ['id'=>'updateProductUom','path'=>'/api/v1/product-uoms/{id}','status'=>200],
            'POST product-uoms/(:num)/actions/set-state' => ['id'=>'setProductUomState','path'=>'/api/v1/product-uoms/{id}/actions/set-state','status'=>200],
        ];
    }

    /** @param array<string,array<string,array|string>> $routes */
    public static function validateRegistered(array $routes): void
    {
        $owner = 'App\\Modules\\Product\\Catalog\\ProductCatalogController';
        foreach (self::operations() as $key => $item) {
            [$verb, $path] = explode(' ', $key, 2);
            if (($routes[$verb]['api/v1/' . $path] ?? null) !== [$owner, $item['id']]) {
                throw new RuntimeException('M6.1 Product catalog route missing or wrongly owned: ' . $key);
            }
        }
        $total = 0;
        foreach ($routes as $verb => $entries) if ($verb !== 'OPTIONS') $total += count($entries);
        if ($total !== 120) throw new RuntimeException('M6.1 expected exactly 120 routes, found ' . $total);
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root): array
    {
        $previous = M55SourceAcceptance::verify($root);
        if ($previous['total'] !== 110) throw new RuntimeException('M6.1 requires pinned 110-route M5.5 source footprint.');
        $manifest = json_decode((string) file_get_contents($root . '/docs/backend-rewrite/openapi-operations-v1.2.4.json'), true, 512, JSON_THROW_ON_ERROR);
        $catalog = [];
        foreach ($manifest['operations'] as $operation) $catalog[$operation['method'] . ' ' . $operation['path']] = $operation;
        if (count($catalog) !== 150) throw new RuntimeException('Frozen OpenAPI operation count changed.');
        $main = (string) file_get_contents($root . '/backend/app/Config/Routes.php');
        if ((\DinkERP\Tools\Routes\LegacyRouteSource::mounted($root, 'M61Routes.php') ? 1 : 0) !== 1 || str_contains($main, 'setAutoRoute(true)')) {
            throw new RuntimeException('M6.1 routes must be included once, with autoroute disabled.');
        }
        $routeFile = (string) \DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M61Routes.php');
        $pattern = '~\$routes->(get|post|patch)\(\x27([^\x27]+)\x27,\s*\[\\\\App\\\\Modules\\\\Product\\\\Catalog\\\\ProductCatalogController::class,\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\]\);~';
        preg_match_all($pattern, $routeFile, $matches, PREG_SET_ORDER);
        if (count($matches) !== 10 || substr_count($routeFile, '$routes->') !== 10) {
            throw new RuntimeException('M6.1 requires exactly ten reviewed Product catalog registrations.');
        }
        $controller = (string) file_get_contents($root . '/backend/app/Modules/Product/Catalog/ProductCatalogController.php');
        $seen = [];
        foreach ($matches as $match) {
            $key = strtoupper($match[1]) . ' ' . $match[2];
            if (isset($seen[$key]) || (self::operations()[$key]['id'] ?? null) !== $match[3]
                || ! str_contains($controller, 'function ' . $match[3] . '(')) {
                throw new RuntimeException('Unrecognized Product catalog route/handler: ' . $key);
            }
            $seen[$key] = true;
        }
        foreach (self::operations() as $key => $item) {
            [$verb] = explode(' ', $key, 2);
            $pin = $catalog[$verb . ' ' . $item['path']] ?? null;
            if ($pin === null || $pin['operation_id'] !== $item['id'] || ! in_array($item['status'], $pin['success_statuses'] ?? [], true)) {
                throw new RuntimeException('M6.1 violates frozen API-050: ' . $key);
            }
        }
        $service = (string) file_get_contents($root . '/backend/app/Modules/Product/Catalog/ProductCatalogService.php');
        $rules = (string) file_get_contents($root . '/backend/app/Modules/Product/Catalog/ProductCatalogRules.php');
        foreach (['canView(', 'decideMutation(', 'decideTransitionAction(', 'FOR UPDATE', 'transBegin()',
            'transCommit()', 'transRollback()', 'GET_LOCK(', 'RELEASE_LOCK(', 'assertAcyclicGroupParent(',
            'WF_CONCURRENT_STATE_CHANGE'] as $signal) {
            if (! str_contains($service, $signal)) throw new RuntimeException('M6.1 missing review guard: ' . $signal);
        }
        if (! str_contains($rules, "'product_group', 'product_uom'")
            || ! str_contains($controller, 'UNAUTHENTICATED')
            || ! str_contains($controller, "'UNAUTHENTICATED', 401")) {
            throw new RuntimeException('M6.1 catalog command authorization/write gate missing.');
        }
        if (! is_file($root . '/backend/app/Modules/Product/MODULE.md')
            || is_dir($root . '/backend/app/Modules/ProductManagement')) {
            throw new RuntimeException('M6.1 Product ownership must not be duplicated.');
        }
        return ['prior'=>110, 'added'=>10, 'total'=>120, 'baseline'=>150];
    }
}
