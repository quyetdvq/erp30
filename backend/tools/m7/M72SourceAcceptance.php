<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__.'/M71SourceAcceptance.php';

/** Source-only gate for exactly two frozen API-050 M7.2 COMBO routes. */
final class M72SourceAcceptance
{
    /** @return array<string,array{id:string,path:string}> */
    public static function operations():array
    {
        return [
            'GET products/(:num)/combo-items'=>['id'=>'listComboItems','path'=>'/api/v1/products/{id}/combo-items'],
            'PUT products/(:num)/combo-items'=>['id'=>'replaceComboItems','path'=>'/api/v1/products/{id}/combo-items'],
        ];
    }

    /** @param array<string,array<string,mixed>> $registered */
    public static function validateRegistered(array $registered):void
    {
        $previous=$registered;
        foreach (self::operations() as $key=>$op) {
            [$method,$path]=explode(' ',$key,2);
            $route='api/v1/'.$path;
            if (($previous[$method][$route]??null)!==[
                'App\\Modules\\Product\\Combo\\ProductComboController',$op['id']]) {
                throw new RuntimeException('M7.2 route absent/changed: '.$key);
            }
            unset($previous[$method][$route]);
        }
        $count=0;
        foreach ($registered as $method=>$entries) if ($method!=='OPTIONS') $count+=count($entries);
        if ($count!==136) throw new RuntimeException('M7.2 expects exactly 136 operations, got '.$count);
        M71SourceAcceptance::validateRegistered($previous);
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root):array
    {
        $before=M71SourceAcceptance::verify($root);
        if ($before['total']!==134) throw new RuntimeException('M7.1 source not accepted.');
        $base=$root.'/backend/app/';
        $routes=(string)file_get_contents($base.'Config/Routes.php');
        if ((\DinkERP\Tools\Routes\LegacyRouteSource::mounted($root, 'M72Routes.php') ? 1 : 0)!==1
            || !str_contains($routes,'$routes->setAutoRoute(false)')) {
            throw new RuntimeException('M7.2 include/autoroute guard invalid.');
        }
        $source=(string)\DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M72Routes.php');
        $regex='~\\$routes->(get|put)\(\x27([^\x27]+)\x27,\s*\[\\\\App\\\\Modules\\\\Product\\\\Combo\\\\ProductComboController::class,\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\]\);~';
        preg_match_all($regex,$source,$matches,PREG_SET_ORDER);
        if (count($matches)!==2 || substr_count($source,'$routes->')!==2) {
            throw new RuntimeException('Exactly two M7.2 route declarations required.');
        }
        $frozen=json_decode((string)file_get_contents(
            $root.'/docs/backend-rewrite/openapi-operations-v1.2.4.json'),true,512,JSON_THROW_ON_ERROR);
        $pins=[];
        foreach ($frozen['operations'] as $op) $pins[$op['method'].' '.$op['path']]=$op;
        if (count($pins)!==150) throw new RuntimeException('Frozen operation count changed.');
        $controller=(string)file_get_contents($base.'Modules/Product/Combo/ProductComboController.php');
        $service=(string)file_get_contents($base.'Modules/Product/Combo/ProductComboService.php');
        $rules=(string)file_get_contents($base.'Modules/Product/Combo/ProductComboRules.php');
        $graph=(string)file_get_contents($base.'Modules/Product/Combo/ProductComboGraph.php');
        $plan=(string)file_get_contents($base.'Modules/Product/Combo/ProductComboPlan.php');
        $sql=(string)file_get_contents($base.'Modules/Product/Combo/ProductComboSql.php');
        $seen=[];
        foreach ($matches as $row) {
            $key=strtoupper($row[1]).' '.$row[2];
            $op=self::operations()[$key]??null;
            if ($op===null || isset($seen[$key]) || $row[3]!==$op['id']
                || !str_contains($controller,'public function '.$row[3].'(')
                || ($pins[strtoupper($row[1]).' '.$op['path']]['operation_id']??null)!==$op['id']) {
                throw new RuntimeException('Unreviewed M7.2 handler: '.$key);
            }
            $seen[$key]=true;
        }
        foreach (["'UNAUTHENTICATED', 401", "'ACCESS_MUTATION_NOT_ENABLED', 403",
            "env('DINKERP_M33B_WRITE_MODE','off')", 'JsonRequest::readObject(', 'replacementBody(',
            'BadRequestException'] as $gate) {
            if (!str_contains($controller,$gate)) throw new RuntimeException('Missing M7.2 HTTP gate: '.$gate);
        }
        foreach (["viewableMasterLifecycleStatuses('product')", "hasBaseViewPermission('product')",
            'ProductComboSql::authorizedOwner(', "canView('product'", "decideMutation('product','edit'",
            'assertTransactionEngines(', 'GET_LOCK(', ' FOR UPDATE', 'transBegin(', 'transRollback(',
            'transCommit(', 'resetTransStatus(', 'ProductComboPlan::build(', 'ProductComboGraph::assertAcyclic(',
            'reachableEdges(', "where('combo_product_id'", "'product_combo_items'"] as $gate) {
            if (!str_contains($service,$gate)) throw new RuntimeException('Missing M7.2 PBAC/transaction gate: '.$gate);
        }
        foreach (['MAX_ITEMS = 250', 'array_is_list(', 'PositiveId::value(', "'component_product_id'",
            "'quantity'", 'sameQuantity(', 'is_string($quantity)'] as $gate) {
            if (!str_contains($rules,$gate)) throw new RuntimeException('M7.2 input precision gate missing: '.$gate);
        }
        foreach (['MAX_NODES', 'MAX_EDGES', 'COMBO_SELF_REFERENCE', 'COMBO_CYCLE'] as $gate) {
            if (!str_contains($graph,$gate)) throw new RuntimeException('M7.2 graph gate missing: '.$gate);
        }
        foreach (['deletes','updates','creates','sameQuantity('] as $gate) {
            if (!str_contains($plan,$gate)) throw new RuntimeException('M7.2 ID-preserving plan gate missing: '.$gate);
        }
        if (!str_contains($sql,'ProductConversionSql::authorizedProduct(')) {
            throw new RuntimeException('M7.2 SQL PBAC pushdown missing.');
        }
        if (str_contains($service,"->table('products')->orderBy('id'") || str_contains($routes,'setAutoRoute(true)')) {
            throw new RuntimeException('M7.2 full-catalog read or dynamic routing forbidden.');
        }
        return ['prior'=>134,'added'=>2,'total'=>136,'baseline'=>150];
    }
}
