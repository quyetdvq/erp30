<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__ . '/M63BSourceAcceptance.php';

/** Acceptance gate for exactly three frozen M6.4A Product lookup operations. */
final class M64ASourceAcceptance
{
    /** @return array<string,array{id:string,path:string,status:int}> */
    public static function operations(): array
    {
        return [
            'GET products/options' => ['id'=>'searchProductOptions','path'=>'/api/v1/products/options','status'=>200],
            'POST products/options/resolve' => ['id'=>'resolveProductOptions','path'=>'/api/v1/products/options/resolve','status'=>200],
            'POST product-uoms/labels/resolve' => ['id'=>'resolveProductUomLabels','path'=>'/api/v1/product-uoms/labels/resolve','status'=>200],
        ];
    }

    /** @param array<string,array<string,mixed>> $routes */
    public static function validateRegistered(array $routes): void
    {
        $previous = $routes;
        $owner = 'App\\Modules\\Product\\Lookup\\ProductLookupController';
        foreach (self::operations() as $key=>$op) {
            [$verb, $path] = explode(' ', $key, 2);
            $routeKey = 'api/v1/' . $path;
            if (($previous[$verb][$routeKey] ?? null) !== [$owner, $op['id']]) {
                throw new RuntimeException('Missing/wrong M6.4A route: '.$key);
            }
            unset($previous[$verb][$routeKey]);
        }
        $total = 0;
        foreach ($routes as $verb=>$set) if ($verb !== 'OPTIONS') $total += count($set);
        if ($total !== 132) throw new RuntimeException('M6.4A requires 132 pinned routes; got '.$total);
        M63BSourceAcceptance::validateRegistered($previous);
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root): array
    {
        $prior = M63BSourceAcceptance::verify($root);
        if ($prior['total'] !== 129) throw new RuntimeException('M6.4A requires accepted 129-route M6.3B source.');
        $main = (string)file_get_contents($root.'/backend/app/Config/Routes.php');
        if ((\DinkERP\Tools\Routes\LegacyRouteSource::mounted($root, 'M64ARoutes.php') ? 1 : 0) !== 1
            || !str_contains($main, '$routes->setAutoRoute(false)')) {
            throw new RuntimeException('M6.4A route include or autoroute guard invalid.');
        }
        $source = (string)\DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M64ARoutes.php');
        $pattern = '~\\$routes->(get|post)\(\x27([^\x27]+)\x27,\s*\[\\\\App\\\\Modules\\\\Product\\\\Lookup\\\\ProductLookupController::class,\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\]\);~';
        preg_match_all($pattern, $source, $matches, PREG_SET_ORDER);
        if (count($matches) !== 3 || substr_count($source, '$routes->') !== 3) {
            throw new RuntimeException('M6.4A must register exactly three reviewed routes.');
        }
        $controller = (string)file_get_contents($root.'/backend/app/Modules/Product/Lookup/ProductLookupController.php');
        $service = (string)file_get_contents($root.'/backend/app/Modules/Product/Lookup/ProductLookupService.php');
        $rules = (string)file_get_contents($root.'/backend/app/Modules/Product/Lookup/ProductLookupRules.php');
        $manifest = json_decode((string)file_get_contents($root.'/docs/backend-rewrite/openapi-operations-v1.2.4.json'), true, 512, JSON_THROW_ON_ERROR);
        $pins = [];
        foreach ($manifest['operations'] as $op) $pins[$op['method'].' '.$op['path']] = $op;
        if (count($pins) !== 150) throw new RuntimeException('Frozen OpenAPI operation count modified.');
        $seen = [];
        foreach ($matches as $match) {
            $key = strtoupper($match[1]).' '.$match[2];
            $expected = self::operations()[$key] ?? null;
            if ($expected === null || isset($seen[$key]) || $expected['id'] !== $match[3]
                || !str_contains($controller, 'function '.$match[3].'(')) {
                throw new RuntimeException('Unreviewed M6.4A handler: '.$key);
            }
            $frozen = $pins[strtoupper($match[1]).' '.$expected['path']] ?? null;
            if ($frozen === null || ($frozen['operation_id'] ?? null) !== $expected['id']
                || !in_array($expected['status'], $frozen['success_statuses'] ?? [], true)) {
                throw new RuntimeException('M6.4A differs from frozen API-050 contract: '.$key);
            }
            $seen[$key] = true;
        }
        foreach (["'UNAUTHENTICATED', 401", 'JsonRequest::readObject(', 'new ProductLookupService('] as $guard) {
            if (!str_contains($controller, $guard)) throw new RuntimeException('M6.4A controller missing '.$guard);
        }
        foreach (['hasBaseViewPermission(', "viewableMasterLifecycleStatuses('product')",
            "viewableMasterLifecycleStatuses('product_uom')", 'ProductLookupSql::options(',
            'ProductLookupSql::productsByIds(', 'ProductLookupSql::uomsByIds(',
            'ProductLookupProjection::fromJoined(', 'assertAuthorizedRow(',
            'ProductLookupRules::resolveProducts(', 'ProductLookupRules::resolveUoms('] as $guard) {
            if (!str_contains($service, $guard)) throw new RuntimeException('M6.4A SQL/PBAC safeguard missing: '.$guard);
        }
        $sql = (string) file_get_contents($root.'/backend/app/Modules/Product/Lookup/ProductLookupSql.php');
        $projection = (string) file_get_contents($root.'/backend/app/Modules/Product/Lookup/ProductLookupProjection.php');
        $access = (string) file_get_contents($root.'/backend/app/Modules/Access/UnifiedAccessService.php');
        foreach (['UPPER(TRIM(', 'COUNT(*) AS total', 'LIMIT ? OFFSET ?', "BINARY p.status = 'ACTIVE'", 'p.id IN (', 'u.id IN ('] as $guard) {
            if (!str_contains($sql, $guard)) throw new RuntimeException('M6.4A SQL pushdown missing: '.$guard);
        }
        foreach (['has_children', 'parent_type', 'CHILD', 'SIMPLE', 'PARENT', 'COMBO'] as $guard) {
            if (!str_contains($projection, $guard)) throw new RuntimeException('M6.4A indexed projection missing: '.$guard);
        }
        foreach (['viewableMasterLifecycleStatuses(', 'viewDecisionForState(', "!== 'MASTER'", 'policies->applicable('] as $guard) {
            if (!str_contains($access, $guard)) throw new RuntimeException('M6.4A policy compiler missing: '.$guard);
        }
        if (str_contains($service, 'ProductReadRules::describe(')
            || str_contains($service, "->table('products')->orderBy('id', 'ASC')->get()")) {
            throw new RuntimeException('M6.4A may not materialize full Product catalog before PBAC/page.');
        }
        foreach (['count($raw) > 100', "'page_size'", "'parent_product'", "'ACTIVE'", "'selectable'",
            'array_slice(', 'count($selected)', 'PositiveId::value('] as $guard) {
            if (!str_contains($rules, $guard)) throw new RuntimeException('M6.4A projection/bounds missing: '.$guard);
        }
        if (str_contains($service, 'ACCESS_MUTATION_NOT_ENABLED') || str_contains($controller, 'ACCESS_MUTATION_NOT_ENABLED')
            || str_contains($source, '$routes->put(') || str_contains($source, '$routes->delete(')) {
            throw new RuntimeException('M6.4A is read-only and may not inherit mutation gate.');
        }
        return ['prior'=>129,'added'=>3,'total'=>132,'baseline'=>150];
    }
}
