<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__ . '/M5SourceAcceptance.php';

/** M5.2 source/operation acceptance; does not claim HTTP or SQL integration. */
final class M52SourceAcceptance
{
    /** @return array<string,array{id:string,path:string,status:int}> */
    public static function operations(): array
    {
        return [
            'POST organizations' => ['id'=>'createOrganization', 'path'=>'/api/v1/organizations', 'status'=>201],
            'PATCH organizations/(:num)' => ['id'=>'updateOrganization', 'path'=>'/api/v1/organizations/{id}', 'status'=>200],
            'POST organizations/(:num)/actions/set-state' => ['id'=>'setOrganizationState', 'path'=>'/api/v1/organizations/{id}/actions/set-state', 'status'=>200],
        ];
    }

    /** @param array<string,array<string,array|string>> $registered */
    public static function validateRegistered(array $registered): void
    {
        $controller = 'App\\Modules\\Organization\\Mutation\\OrganizationMutationController';
        foreach (self::operations() as $key => $item) {
            [$verb,$path] = explode(' ', $key, 2);
            if (($registered[$verb]['api/v1/'.$path] ?? null) !== [$controller, $item['id']]) {
                throw new RuntimeException('M5.2 route missing or wrong handler: '.$key);
            }
        }
        $total=0;
        foreach ($registered as $verb=>$routes) if ($verb!=='OPTIONS') $total+=count($routes);
        if ($total!==93) throw new RuntimeException('M5.2 expected 93 registered routes, got '.$total);
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root): array
    {
        $old = M5SourceAcceptance::verify($root);
        if ($old['total'] !== 90) {
            throw new RuntimeException('M5.2 requires the reviewed M5.1 90-route footprint.');
        }
        $manifest = $root.'/docs/backend-rewrite/openapi-operations-v1.2.4.json';
        $decoded = json_decode((string)file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
        $baseline = [];
        foreach ($decoded['operations'] as $op) {
            $baseline[$op['method'].' '.$op['path']] = $op;
        }
        foreach (self::operations() as $key => $item) {
            [$verb] = explode(' ', $key, 2);
            $reference = $baseline[$verb.' '.$item['path']] ?? null;
            if ($reference === null || ($reference['operation_id'] ?? '') !== $item['id']
                || !in_array($item['status'], $reference['success_statuses'] ?? [], true)) {
                throw new RuntimeException('M5.2 operation not in pinned v1.2.4: '.$key);
            }
        }
        $routes = (string)file_get_contents($root.'/backend/app/Config/Routes.php');
        $routes .= (string)file_get_contents($root.'/backend/app/Config/Routes/ApiV1/OrganizationsRoutes.php');
        if ((\DinkERP\Tools\Routes\LegacyRouteSource::mounted($root, 'M52Routes.php') ? 1 : 0) !== 1) {
            throw new RuntimeException('M5.2 routes not included exactly once.');
        }
        foreach (["organizations'", "organizations/tree'", "organizations/(:num)'"] as $path) {
            if (!str_contains($routes, "\$routes->get('".$path)) {
                throw new RuntimeException('M5.2 removed a reviewed M2.1 GET Organization route: '.$path);
            }
        }
        $definition = (string)\DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M52Routes.php');
        $pattern = '~\$routes->(post|patch)\(\x27([^\x27]+)\x27,\s*\[\\\\App\\\\Modules\\\\Organization\\\\Mutation\\\\OrganizationMutationController::class,\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\]\);~';
        preg_match_all($pattern, $definition, $found, PREG_SET_ORDER);
        if (count($found)!==3 || substr_count($definition,'$routes->')!==3) {
            throw new RuntimeException('M5.2 must register exactly three owned routes.');
        }
        $controllerPath=$root.'/backend/app/Modules/Organization/Mutation/OrganizationMutationController.php';
        $controller=(string)file_get_contents($controllerPath);
        $seen=[];
        foreach ($found as $row) {
            $key = strtoupper($row[1]).' '.$row[2];
            $expected = self::operations()[$key] ?? null;
            if ($expected === null || $expected['id'] !== $row[3]
                || isset($seen[$key]) || !str_contains($controller, 'function '.$row[3].'(')) {
                throw new RuntimeException('M5.2 unreviewed controller route: '.$key);
            }
            $seen[$key]=true;
        }
        if (count($seen)!==3) throw new RuntimeException('M5.2 missing a reviewed operation.');
        $access=(string)file_get_contents($root.'/backend/app/Modules/Access/UnifiedAccessService.php');
        if (!str_contains($access, "if (\$type === 'organization')")
            || !str_contains($access, "\$target['id'] = \$target['organization_id'];")) {
            throw new RuntimeException('M5.2 create organization PBAC scope missing.');
        }
        return ['prior'=>90,'added'=>3,'total'=>93,'baseline'=>150];
    }
}
