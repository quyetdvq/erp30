<?php

declare(strict_types=1);

require_once __DIR__.'/M53SourceAcceptance.php';

/** Strict source-only gate for six M5.4 Department/Position mutations. */
final class M54SourceAcceptance
{
    /** @return array<string,array{id:string,path:string,status:int}> */
    public static function operations():array
    {
        return [
            'POST departments'=>['id'=>'createDepartment','path'=>'/api/v1/departments','status'=>201],
            'PATCH departments/(:num)'=>['id'=>'updateDepartment','path'=>'/api/v1/departments/{id}','status'=>200],
            'POST departments/(:num)/actions/set-state'=>['id'=>'setDepartmentState','path'=>'/api/v1/departments/{id}/actions/set-state','status'=>200],
            'POST positions'=>['id'=>'createPosition','path'=>'/api/v1/positions','status'=>201],
            'PATCH positions/(:num)'=>['id'=>'updatePosition','path'=>'/api/v1/positions/{id}','status'=>200],
            'POST positions/(:num)/actions/set-state'=>['id'=>'setPositionState','path'=>'/api/v1/positions/{id}/actions/set-state','status'=>200],
        ];
    }

    /** @param array<string,array<string,array|string>> $registered */
    public static function validateRegistered(array $registered):void
    {
        $handler='App\\Modules\\People\\Structure\\Mutation\\StructureMutationController';
        foreach(self::operations() as $key=>$item) {
            [$verb,$path]=explode(' ',$key,2);
            if(($registered[$verb]['api/v1/'.$path]??null)!==[$handler,$item['id']]) {
                throw new RuntimeException('M5.4 unreviewed or missing route: '.$key);
            }
        }
        $total=0;
        foreach($registered as $verb=>$paths) if($verb!=='OPTIONS') $total+=count($paths);
        if($total!==105) throw new RuntimeException('M5.4 expected 105 registered routes, found '.$total);
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root):array
    {
        $prior=M53SourceAcceptance::verify($root);
        if($prior['total']!==99) throw new RuntimeException('M5.4 requires accepted 99-route M5.3 footprint.');
        $manifest=$root.'/docs/backend-rewrite/openapi-operations-v1.2.4.json';
        $doc=json_decode((string)file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);
        $byPath=[];
        foreach($doc['operations'] as $op) $byPath[$op['method'].' '.$op['path']]=$op;
        foreach(self::operations() as $key=>$item) {
            [$verb]=explode(' ',$key,2);
            $pinned=$byPath[$verb.' '.$item['path']]??null;
            if($pinned===null || ($pinned['operation_id']??'')!==$item['id']
                || !in_array($item['status'],$pinned['success_statuses']??[],true)) {
                throw new RuntimeException('M5.4 operation deviates from API-020: '.$key);
            }
        }
        $routes=(string)file_get_contents($root.'/backend/app/Config/Routes.php');
        if(substr_count($routes,"require __DIR__ . '/M54Routes.php';")!==1 || str_contains($routes,'setAutoRoute(true)')) {
            throw new RuntimeException('M5.4 route file not included exactly once with disabled autoroutes.');
        }
        foreach(['departments','departments/(:num)','positions','positions/(:num)'] as $path) {
            if(!str_contains($routes,"\$routes->get('".$path."'")) {
                throw new RuntimeException('M5.4 changed reviewed M2.1 GET route: '.$path);
            }
        }
        $definitions=(string)file_get_contents($root.'/backend/app/Config/M54Routes.php');
        $controller=(string)file_get_contents($root.'/backend/app/Modules/People/Structure/Mutation/StructureMutationController.php');
        $regex='~\$routes->(post|patch)\(\x27([^\x27]+)\x27,\s*\[\\\\App\\\\Modules\\\\People\\\\Structure\\\\Mutation\\\\StructureMutationController::class,\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\]\);~';
        preg_match_all($regex,$definitions,$matches,PREG_SET_ORDER);
        if(count($matches)!==6 || substr_count($definitions,'$routes->')!==6) {
            throw new RuntimeException('M5.4 must register exactly six explicit mutation routes.');
        }
        $seen=[];
        foreach($matches as $match) {
            $key=strtoupper($match[1]).' '.$match[2];
            $expected=self::operations()[$key]??null;
            if($expected===null || $expected['id']!==$match[3] || isset($seen[$key])
                || !str_contains($controller,'function '.$match[3].'(')) {
                throw new RuntimeException('M5.4 unreviewed handler: '.$key);
            }
            $seen[$key]=true;
        }
        $service=(string)file_get_contents($root.'/backend/app/Modules/People/Structure/Mutation/StructureMutationService.php');
        foreach(['decideMutation(', 'decideTransitionAction(', 'ReadAccessBridge(', 'GET_LOCK(', 'RELEASE_LOCK(',
            'transBegin(', 'transCommit(', 'transRollback(', 'FOR UPDATE', 'assertDepartmentParent(',
            'WF_CONCURRENT_STATE_CHANGE', 'transitionTarget('] as $guard) {
            if(!str_contains($service,$guard)) throw new RuntimeException('M5.4 missing safety guard: '.$guard);
        }
        if(!str_contains($controller,"env('DINKERP_M33B_WRITE_MODE','off')")
            || !str_contains($controller,"'UNAUTHENTICATED',401")) {
            throw new RuntimeException('M5.4 must remain auth- and write-gated.');
        }
        $pbac=(string)file_get_contents($root.'/backend/app/Modules/Access/UnifiedAccessService.php');
        if(!str_contains($pbac, '$this->states->initialState($type)')) {
            throw new RuntimeException('M5.4 create PBAC does not use trusted Lifecycle initial State.');
        }
        return ['prior'=>99,'added'=>6,'total'=>105,'baseline'=>150];
    }
}
