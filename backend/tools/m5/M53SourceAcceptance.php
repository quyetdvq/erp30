<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__.'/M52SourceAcceptance.php';

/** Source-only contract gate for six API-020 Employee/Assignment mutations. */
final class M53SourceAcceptance
{
    /** @return array<string,array{id:string,path:string,status:int}> */
    public static function operations(): array
    {
        return [
            'POST employees' => ['id'=>'createEmployee','path'=>'/api/v1/employees','status'=>201],
            'PATCH employees/(:num)' => ['id'=>'updateEmployee','path'=>'/api/v1/employees/{id}','status'=>200],
            'POST employees/(:num)/actions/set-state' => ['id'=>'setEmployeeState','path'=>'/api/v1/employees/{id}/actions/set-state','status'=>200],
            'POST employee-assignments' => ['id'=>'createEmployeeAssignment','path'=>'/api/v1/employee-assignments','status'=>201],
            'PATCH employee-assignments/(:num)' => ['id'=>'updateEmployeeAssignment','path'=>'/api/v1/employee-assignments/{id}','status'=>200],
            'POST employee-assignments/(:num)/actions/set-state' => ['id'=>'setEmployeeAssignmentState','path'=>'/api/v1/employee-assignments/{id}/actions/set-state','status'=>200],
        ];
    }

    /** @param array<string,array<string,array|string>> $registered */
    public static function validateRegistered(array $registered): void
    {
        $handler='App\\Modules\\People\\Mutation\\PeopleMutationController';
        foreach (self::operations() as $key=>$item) {
            [$verb,$path]=explode(' ',$key,2);
            if (($registered[$verb]['api/v1/'.$path] ?? null)!==[$handler,$item['id']]) {
                throw new RuntimeException('M5.3 missing or unreviewed route: '.$key);
            }
        }
        $total=0;
        foreach ($registered as $verb=>$paths) if ($verb!=='OPTIONS') $total+=count($paths);
        if ($total!==99) throw new RuntimeException('M5.3 expected 99 registered routes; found '.$total);
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root): array
    {
        $prior=M52SourceAcceptance::verify($root);
        if ($prior['total']!==93) throw new RuntimeException('M5.3 requires accepted M5.2 93-route source.');
        $manifest=$root.'/docs/backend-rewrite/openapi-operations-v1.2.4.json';
        $doc=json_decode((string)file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);
        $byPath=[];
        foreach ($doc['operations'] as $operation) {
            $byPath[$operation['method'].' '.$operation['path']]=$operation;
        }
        foreach (self::operations() as $key=>$item) {
            [$verb]=explode(' ',$key,2);
            $pinned=$byPath[$verb.' '.$item['path']] ?? null;
            if ($pinned===null || ($pinned['operation_id'] ?? '')!==$item['id']
                || !in_array($item['status'],$pinned['success_statuses'] ?? [],true)) {
                throw new RuntimeException('M5.3 unpinned API-020 operation: '.$key);
            }
        }
        $routes=(string)file_get_contents($root.'/backend/app/Config/Routes.php');
        $routes .= (string)file_get_contents($root.'/backend/app/Config/Routes/ApiV1/PeopleRoutes.php');
        if ((\DinkERP\Tools\Routes\LegacyRouteSource::mounted($root, 'M53Routes.php') ? 1 : 0)!==1) {
            throw new RuntimeException('M5.3 route extension not included once.');
        }
        foreach (['employees','employees/(:num)','employee-assignments','employee-assignments/(:num)'] as $path) {
            if (!str_contains($routes,"\$routes->get('".$path."'")) {
                throw new RuntimeException('M5.3 removed a reviewed M2.1 GET: '.$path);
            }
        }
        $definition=(string)\DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M53Routes.php');
        $controllerPath=$root.'/backend/app/Modules/People/Mutation/PeopleMutationController.php';
        $controller=(string)file_get_contents($controllerPath);
        $regex='~\$routes->(post|patch)\(\x27([^\x27]+)\x27,\s*\[\\\\App\\\\Modules\\\\People\\\\Mutation\\\\PeopleMutationController::class,\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\]\);~';
        preg_match_all($regex,$definition,$matches,PREG_SET_ORDER);
        if (count($matches)!==6 || substr_count($definition,'$routes->')!==6) {
            throw new RuntimeException('M5.3 must register exactly six explicit mutations.');
        }
        $seen=[];
        foreach ($matches as $match) {
            $key=strtoupper($match[1]).' '.$match[2];
            $expected=self::operations()[$key] ?? null;
            if ($expected===null || $expected['id']!==$match[3] || isset($seen[$key])
                || !str_contains($controller,'function '.$match[3].'(')) {
                throw new RuntimeException('M5.3 unrecognized handler: '.$key);
            }
            $seen[$key]=true;
        }
        $service=(string)file_get_contents($root.'/backend/app/Modules/People/Mutation/PeopleMutationService.php');
        foreach (['decideMutation(', 'decideTransitionAction(', 'ReadAccessBridge(', 'GET_LOCK(',
            'RELEASE_LOCK(', 'transBegin(', 'transCommit(', 'transRollback(',
            'EMP_PRIMARY_ASSIGNMENT_OVERLAP', 'assertManagerChain(', 'assertAssignmentReferences('] as $required) {
            if (!str_contains($service,$required)) throw new RuntimeException('M5.3 missing safety gate: '.$required);
        }
        if (!str_contains($controller,"env('DINKERP_M33B_WRITE_MODE','off')")
            || !str_contains($controller,"'UNAUTHENTICATED',401")) {
            throw new RuntimeException('M5.3 auth and write-gate must be fail-closed.');
        }
        return ['prior'=>93,'added'=>6,'total'=>99,'baseline'=>150];
    }
}
