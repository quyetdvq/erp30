<?php

declare(strict_types=1);

require_once __DIR__.'/M73SourceAcceptance.php';

/** M7.4: exactly three frozen API-050 Product relationship lookup operations. */
final class M74SourceAcceptance
{
    /** @return array<string,array{id:string,path:string}> */
    public static function operations(): array
    {
        return [
            'POST products/details/resolve' => [
                'id'=>'resolveProductDetails', 'path'=>'/api/v1/products/details/resolve',
            ],
            'POST products/relations-preview' => [
                'id'=>'previewProductRelations', 'path'=>'/api/v1/products/relations-preview',
            ],
            'POST products/sales-uom-options/resolve' => [
                'id'=>'resolveProductSalesUomOptions', 'path'=>'/api/v1/products/sales-uom-options/resolve',
            ],
        ];
    }

    /** @param array<string,array<string,mixed>> $registered */
    public static function validateRegistered(array $registered): void
    {
        $previous = $registered;
        foreach (self::operations() as $key => $op) {
            [$method,$uri] = explode(' ', $key, 2);
            $route = 'api/v1/'.$uri;
            if (($previous[$method][$route] ?? null) !== [
                'App\\Modules\\Product\\Lookup\\ProductRelationshipController', $op['id'],
            ]) throw new RuntimeException('M7.4 unexpected Product relationship route: '.$key);
            unset($previous[$method][$route]);
        }
        $count = 0;
        foreach ($registered as $method => $entries) {
            if ($method !== 'OPTIONS') $count += count($entries);
        }
        if ($count !== 146) throw new RuntimeException('M7.4 expects 146 operations; got '.$count);
        M73SourceAcceptance::validateRegistered($previous);
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root): array
    {
        $previous = M73SourceAcceptance::verify($root);
        if ($previous['total'] !== 143) throw new RuntimeException('Prior M7.3 not source accepted.');
        $app = $root.'/backend/app/';
        $routes = (string)file_get_contents($app.'Config/Routes.php');
        if (substr_count($routes, "require __DIR__ . '/M74Routes.php';") !== 1
            || !str_contains($routes, '$routes->setAutoRoute(false)')) {
            throw new RuntimeException('Missing M7.4 explicit routing/auto-route guard.');
        }
        $source = (string)file_get_contents($app.'Config/M74Routes.php');
        $regex = '~\\$routes->post\(\x27([^\x27]+)\x27,\s*\[\\\\App\\\\Modules\\\\Product\\\\Lookup\\\\ProductRelationshipController::class,\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\]\);~';
        preg_match_all($regex, $source, $matches, PREG_SET_ORDER);
        if (count($matches) !== 3 || substr_count($source, '$routes->') !== 3) {
            throw new RuntimeException('Exactly three M7.4 POST routes required.');
        }
        $frozen = json_decode((string)file_get_contents(
            $root.'/docs/backend-rewrite/openapi-operations-v1.2.4.json'), true, 512, JSON_THROW_ON_ERROR);
        $pins = [];
        foreach ($frozen['operations'] as $op) $pins[$op['method'].' '.$op['path']] = $op;
        if (count($pins) !== 150) throw new RuntimeException('Frozen contract count changed.');
        $controller = (string)file_get_contents($app.'Modules/Product/Lookup/ProductRelationshipController.php');
        $service = (string)file_get_contents($app.'Modules/Product/Lookup/ProductRelationshipService.php');
        $rules = (string)file_get_contents($app.'Modules/Product/Lookup/ProductRelationshipRules.php');
        $sql = (string)file_get_contents($app.'Modules/Product/Lookup/ProductRelationshipSql.php');
        $projection = (string)file_get_contents($app.'Modules/Product/Lookup/ProductRelationshipProjection.php');
        $seen = [];
        foreach ($matches as $match) {
            $key = 'POST '.$match[1];
            $operation = self::operations()[$key] ?? null;
            if ($operation === null || isset($seen[$key]) || $match[2] !== $operation['id']
                || !str_contains($controller, 'public function '.$operation['id'].'(')
                || ($pins['POST '.$operation['path']]['operation_id'] ?? '') !== $operation['id']) {
                throw new RuntimeException('M7.4 route handler not frozen: '.$key);
            }
            $seen[$key] = true;
        }
        foreach (["'UNAUTHENTICATED', 401", 'JsonRequest::readObject(', 'ProductRelationshipService($actor)'] as $term) {
            if (!str_contains($controller, $term)) throw new RuntimeException('M7.4 controller guard missing: '.$term);
        }
        foreach (["hasBaseViewPermission('product')", "viewableMasterLifecycleStatuses('product')",
            "viewableMasterLifecycleStatuses('product_uom')", "canView('product'", "canView('product_uom'",
            'ProductRelationshipSql::productsByIds(', 'ProductRelationshipSql::relationWindows(',
            'ProductRelationshipSql::salesUoms(', 'ProductRelationshipProjection::detail(',
            'ProductRelationshipRules::relationPreviews(', 'ProductRelationshipRules::salesOptions('] as $term) {
            if (!str_contains($service, $term)) throw new RuntimeException('M7.4 PBAC/SQL gate missing: '.$term);
        }
        foreach (['MAX_DETAILS = 100', 'MAX_RELATIONS = 50', 'MAX_SALES_UOMS = 50',
            'limitPerProduct(', 'ProductLookupRules::ids('] as $term) {
            if (!str_contains($rules, $term)) throw new RuntimeException('M7.4 batch gate missing: '.$term);
        }
        foreach (['ROW_NUMBER() OVER', 'COUNT(*) OVER', 'JOIN ', 'COLLATE utf8mb4_bin',
            "BINARY u.status = 'ACTIVE'", "BINARY c.status = 'ACTIVE'", 'product_combo_items',
            'product_uom_conversions', 'ORDER BY candidate.product_id ASC', 'WHERE ('] as $term) {
            if (!str_contains($sql, $term)) throw new RuntimeException('M7.4 SQL scope/count gate missing: '.$term);
        }
        foreach (['ProductLookupProjection::fromJoined(', 'ImageUrl::for(',
            "'leaf_transactionable'", "'serial_management_enabled'", "'image_url'"] as $term) {
            if (!str_contains($projection, $term)) throw new RuntimeException('M7.4 Product DTO gate missing: '.$term);
        }
        foreach ([$service,$sql] as $component) {
            if (str_contains($component, "->table('products')->orderBy('id', 'ASC')->get()")
                || str_contains($component, 'ProductReadRules::describe(')) {
                throw new RuntimeException('M7.4 unbounded Product catalog read forbidden.');
            }
        }
        return ['prior'=>143, 'added'=>3, 'total'=>146, 'baseline'=>150];
    }
}
