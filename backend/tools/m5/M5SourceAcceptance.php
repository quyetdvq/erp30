<?php

declare(strict_types=1);

/** Source-only acceptance for M5.1. No database, Composer or HTTP required. */
require_once dirname(__DIR__) . '/m4/M4SourceAcceptance.php';

final class M5SourceAcceptance
{
    /** @return array<string,array{method:string,path:string,id:string,status:int}> */
    public static function operations(): array
    {
        return [
            'POST provinces' => ['method'=>'POST','path'=>'/api/v1/provinces', 'id'=>'createProvince','status'=>201],
            'PATCH provinces/(:num)' => ['method'=>'PATCH','path'=>'/api/v1/provinces/{id}', 'id'=>'updateProvince','status'=>200],
            'POST provinces/(:num)/actions/set-state' => ['method'=>'POST','path'=>'/api/v1/provinces/{id}/actions/set-state', 'id'=>'setProvinceState','status'=>200],
            'POST wards' => ['method'=>'POST','path'=>'/api/v1/wards', 'id'=>'createWard','status'=>201],
            'PATCH wards/(:num)' => ['method'=>'PATCH','path'=>'/api/v1/wards/{id}', 'id'=>'updateWard','status'=>200],
            'POST wards/(:num)/actions/set-state' => ['method'=>'POST','path'=>'/api/v1/wards/{id}/actions/set-state', 'id'=>'setWardState','status'=>200],
        ];
    }

    /** @param array<string,array<string,array|string>> $registered */
    public static function validateRegistered(array $registered): void
    {
        $class = 'App\\Modules\\Locations\\Mutation\\LocationMutationController';
        foreach (self::operations() as $key => $item) {
            [$verb, $path] = explode(' ', $key, 2);
            if (($registered[$verb]['api/v1/' . $path] ?? null) !== [$class, $item['id']]) {
                throw new RuntimeException('M5.1 missing or unreviewed route: ' . $key);
            }
        }
        $total = 0;
        foreach ($registered as $method => $routes) {
            if ($method !== 'OPTIONS') $total += count($routes);
        }
        if ($total !== 90) {
            throw new RuntimeException('M5.1 expected exactly 90 installed routes, found ' . $total);
        }
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root): array
    {
        $prior = M4SourceAcceptance::verify($root);
        if (($prior['total_routes'] ?? null) !== 84) {
            throw new RuntimeException('M5.1 requires the reviewed 84-route M4 footprint.');
        }
        $manifest = $root . '/docs/backend-rewrite/openapi-operations-v1.2.4.json';
        if (! is_file($manifest) || ! hash_equals(M34Contract::MANIFEST_SHA256, (string) hash_file('sha256', $manifest))) {
            throw new RuntimeException('M5.1 pinned baseline manifest changed.');
        }
        $document = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
        $operations = [];
        foreach ($document['operations'] as $operation) {
            $operations[$operation['method'] . ' ' . $operation['path']] = $operation;
        }
        if (count($operations) !== 150) {
            throw new RuntimeException('M5.1 baseline must contain exactly 150 operations.');
        }
        $expected = self::operations();
        foreach ($expected as $item) {
            $op = $operations[$item['method'].' '.$item['path']] ?? null;
            if ($op === null || $op['operation_id'] !== $item['id']
                || ! in_array($item['status'], $op['success_statuses'] ?? [], true)) {
                throw new RuntimeException('M5.1 route differs from pinned OpenAPI: ' . $item['id']);
            }
        }
        $routesFile = $root . '/backend/app/Config/Routes.php';
        $routes = (string) file_get_contents($routesFile);
        if (substr_count($routes, "require __DIR__ . '/M5Routes.php';") !== 1 || str_contains($routes, 'setAutoRoute(true)')) {
            throw new RuntimeException('M5.1 routes are not included exactly once under API group.');
        }
        $m5 = (string) file_get_contents($root . '/backend/app/Config/M5Routes.php');
        $pattern = '~\$routes->(post|patch)\(\s*\x27([^\x27]+)\x27,\s*\[\\\\App\\\\Modules\\\\Locations\\\\Mutation\\\\LocationMutationController::class,\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\]\);~';
        preg_match_all($pattern, $m5, $matches, PREG_SET_ORDER);
        if (count($matches) !== 6 || substr_count($m5, '$routes->') !== 6) {
            throw new RuntimeException('M5.1 registered routes must be exactly six, with no unreviewed endpoints.');
        }
        $seen = [];
        $controller = (string) file_get_contents($root . '/backend/app/Modules/Locations/Mutation/LocationMutationController.php');
        foreach ($matches as $match) {
            $key = strtoupper($match[1]) . ' ' . $match[2];
            $handler = $match[3];
            $item = $expected[$key] ?? null;
            if ($item === null || $handler !== $item['id'] || isset($seen[$key])
                || ! preg_match('~public function ' . preg_quote($handler, '~') . '\(~', $controller)) {
                throw new RuntimeException('M5.1 unrecognized or missing handler: ' . $key);
            }
            $seen[$key] = true;
        }
        if (count($seen) !== count($expected)) {
            throw new RuntimeException('M5.1 expected operations are missing.');
        }
        foreach (['get' => ['provinces', 'provinces/(:num)', 'wards', 'wards/(:num)']] as $verb => $paths) {
            foreach ($paths as $path) {
                if (! str_contains($routes, '$routes->' . $verb . "('" . $path . "', [ReadCatalogController::class,")) {
                    throw new RuntimeException('M5.1 changed an accepted M2.1 location read route: ' . $path);
                }
            }
        }
        return ['prior'=>84, 'added'=>6, 'total'=>90, 'baseline'=>150];
    }
}
