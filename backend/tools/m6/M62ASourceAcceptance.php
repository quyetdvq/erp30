<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__ . '/M61SourceAcceptance.php';

/** M6.2A: exact Product Master GET list + detail; no mutation routes. */
final class M62ASourceAcceptance
{
    /** @return array<string,array{id:string,path:string,status:int}> */
    public static function operations(): array
    {
        return [
            'GET products' => ['id'=>'listProducts','path'=>'/api/v1/products','status'=>200],
            'GET products/(:num)' => ['id'=>'getProduct','path'=>'/api/v1/products/{id}','status'=>200],
        ];
    }

    /** @param array<string,array<string,array|string>> $routes */
    public static function validateRegistered(array $routes): void
    {
        $owner = 'App\\Modules\\Product\\Master\\ProductReadController';
        foreach (self::operations() as $key => $item) {
            [$verb, $path] = explode(' ', $key, 2);
            if (($routes[$verb]['api/v1/' . $path] ?? null) !== [$owner, $item['id']]) {
                throw new RuntimeException('M6.2A Product Master GET route not owned: ' . $key);
            }
        }
        $total = 0;
        foreach ($routes as $verb => $entries) if ($verb !== 'OPTIONS') $total += count($entries);
        if ($total !== 122) throw new RuntimeException('M6.2A requires exactly 122 registered APIs; got ' . $total);
        // Deny unreviewed Product mutations even if they share a legitimate Product handler.
        foreach (['POST','PATCH','PUT','DELETE'] as $method) {
            foreach ($routes[$method] ?? [] as $path => $_handler) {
                if (preg_match('~\Aapi/v1/products(?:/|\z)~', $path) === 1) {
                    throw new RuntimeException('M6.2A Product mutations must remain unregistered: ' . $method . ' ' . $path);
                }
            }
        }
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root): array
    {
        $prior = M61SourceAcceptance::verify($root);
        if ($prior['total'] !== 120) throw new RuntimeException('M6.2A requires pinned M6.1 120-route footprint.');
        $routes = (string) file_get_contents($root . '/backend/app/Config/Routes.php');
        if ((\DinkERP\Tools\Routes\LegacyRouteSource::mounted($root, 'M62ARoutes.php') ? 1 : 0) !== 1
            || str_contains($routes, 'setAutoRoute(true)')) {
            throw new RuntimeException('M6.2A reviewed routes must be included once, autoroute disabled.');
        }
        $file = (string) \DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M62ARoutes.php');
        $pattern = '~\$routes->get\(\x27([^\x27]+)\x27,\s*\[\\\\App\\\\Modules\\\\Product\\\\Master\\\\ProductReadController::class,\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\]\);~';
        preg_match_all($pattern, $file, $found, PREG_SET_ORDER);
        if (count($found) !== 2 || substr_count($file, '$routes->') !== 2) {
            throw new RuntimeException('M6.2A must register only two reviewed GET operations.');
        }
        $controller = (string) file_get_contents($root . '/backend/app/Modules/Product/Master/ProductReadController.php');
        foreach ($found as $entry) {
            $key = 'GET ' . $entry[1];
            if ((self::operations()[$key]['id'] ?? null) !== $entry[2]
                || ! str_contains($controller, 'function ' . $entry[2] . '(')) {
                throw new RuntimeException('Unapproved Product GET endpoint/handler: ' . $key);
            }
        }
        $manifest = json_decode((string) file_get_contents($root . '/docs/backend-rewrite/openapi-operations-v1.2.4.json'), true, 512, JSON_THROW_ON_ERROR);
        $map = [];
        foreach ($manifest['operations'] as $op) $map[$op['method'] . ' ' . $op['path']] = $op;
        if (count($map) !== 150) throw new RuntimeException('Frozen API-050 manifest modified.');
        foreach (self::operations() as $item) {
            $pin = $map['GET ' . $item['path']] ?? null;
            if ($pin === null || $pin['operation_id'] !== $item['id'] || ! in_array($item['status'], $pin['success_statuses'] ?? [], true)) {
                throw new RuntimeException('M6.2A violates frozen Product GET contract.');
            }
        }
        $service = (string) file_get_contents($root . '/backend/app/Modules/Product/Master/ProductReadService.php');
        $rules = (string) file_get_contents($root . '/backend/app/Modules/Product/Master/ProductReadRules.php');
        // M8.5: the earlier in-memory paging has been retired. The historical
        // read gate now requires its stronger SQL-scoped replacement instead.
        foreach (['hasBaseViewPermission(', 'canView(', 'AuthorizationSqlScopeCompiler',
            'ProductReadSql::collection(', 'ProductReadSql::detail(',
            'ProductReadRules::describeJoined(', 'EffectiveActionHintCache'] as $guard) {
            if (! str_contains($service, $guard)) throw new RuntimeException('Product Master lacks read safeguard: ' . $guard);
        }
        if (str_contains($service, 'AuthorizedListPage::paginate(')
            || str_contains($service, 'function allRows(')) {
            throw new RuntimeException('Product Master must not paginate a complete catalog in PHP.');
        }
        foreach (["'PRODUCT_INVALID_ROLE'", "'full_name'", "'selectable'", "'leaf_transactionable'", "'image_url'", "'product_code'"] as $guard) {
            if (! str_contains($rules, $guard)) throw new RuntimeException('Product read projection missing: ' . $guard);
        }
        if (! str_contains($controller, "'UNAUTHENTICATED', 401")
            || is_dir($root . '/backend/app/Modules/ProductManagement')
            || str_contains($file, '->post(') || str_contains($file, '->patch(')) {
            throw new RuntimeException('Product Master read ownership/auth boundary violated.');
        }
        return ['prior'=>120, 'added'=>2, 'total'=>122, 'baseline'=>150];
    }
}
