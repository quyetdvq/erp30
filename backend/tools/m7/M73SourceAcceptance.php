<?php

declare(strict_types=1);

require_once __DIR__.'/M72SourceAcceptance.php';

/** M7.3: exactly seven frozen Pricing CRUD/Price List Item route contracts. */
final class M73SourceAcceptance
{
    /** @return array<string,array{id:string,path:string}> */
    public static function operations():array
    {
        return [
            'GET price-lists'=>['id'=>'listPriceLists','path'=>'/api/v1/price-lists'],
            'POST price-lists'=>['id'=>'createPriceList','path'=>'/api/v1/price-lists'],
            'GET price-lists/(:num)'=>['id'=>'getPriceList','path'=>'/api/v1/price-lists/{id}'],
            'PATCH price-lists/(:num)'=>['id'=>'updatePriceList','path'=>'/api/v1/price-lists/{id}'],
            'POST price-lists/(:num)/actions/set-state'=>['id'=>'setPriceListState','path'=>'/api/v1/price-lists/{id}/actions/set-state'],
            'GET price-lists/(:num)/items'=>['id'=>'listPriceListItems','path'=>'/api/v1/price-lists/{id}/items'],
            'PUT price-lists/(:num)/items'=>['id'=>'replacePriceListItems','path'=>'/api/v1/price-lists/{id}/items'],
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
                'App\\Modules\\Pricing\\PriceList\\PriceListController',$op['id']]) {
                throw new RuntimeException('M7.3 Price List route absent/changed: '.$key);
            }
            unset($previous[$method][$route]);
        }
        $count=0;
        foreach ($registered as $method=>$entries) if ($method!=='OPTIONS')$count+=count($entries);
        if ($count!==143) throw new RuntimeException('M7.3 expects exactly 143 routes, got '.$count);
        M72SourceAcceptance::validateRegistered($previous);
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root):array
    {
        $before=M72SourceAcceptance::verify($root);
        if ($before['total']!==136) throw new RuntimeException('Prior M7.2 source not accepted.');
        $app=$root.'/backend/app/';
        $routes=(string)file_get_contents($app.'Config/Routes.php');
        if (substr_count($routes,"require __DIR__ . '/M73Routes.php';")!==1 || !str_contains($routes,'$routes->setAutoRoute(false)')) {
            throw new RuntimeException('M7.3 route include/auto-route guard is invalid.');
        }
        $source=(string)file_get_contents($app.'Config/M73Routes.php');
        $pattern='~\\$routes->(get|post|patch|put)\(\x27([^\x27]+)\x27,\s*\[\\\\App\\\\Modules\\\\Pricing\\\\PriceList\\\\PriceListController::class,\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\]\);~';
        preg_match_all($pattern,$source,$matches,PREG_SET_ORDER);
        if (count($matches)!==7 || substr_count($source,'$routes->')!==7) {
            throw new RuntimeException('Exactly seven M7.3 routes required.');
        }
        $frozen=json_decode((string)file_get_contents($root.'/docs/backend-rewrite/openapi-operations-v1.2.4.json'),true,512,JSON_THROW_ON_ERROR);
        $pins=[];
        foreach ($frozen['operations'] as $op) $pins[$op['method'].' '.$op['path']]=$op;
        if (count($pins)!==150) throw new RuntimeException('Frozen OpenAPI baseline count changed.');
        $controller=(string)file_get_contents($app.'Modules/Pricing/PriceList/PriceListController.php');
        $service=(string)file_get_contents($app.'Modules/Pricing/PriceList/PriceListService.php');
        $sql=(string)file_get_contents($app.'Modules/Pricing/PriceList/PriceListSql.php');
        $rules=(string)file_get_contents($app.'Modules/Pricing/PriceList/PriceListRules.php');
        $plan=(string)file_get_contents($app.'Modules/Pricing/PriceList/PriceListItemPlan.php');
        $access=(string)file_get_contents($app.'Modules/Access/UnifiedAccessService.php');
        $seen=[];
        foreach ($matches as $row) {
            $key=strtoupper($row[1]).' '.$row[2];
            $op=self::operations()[$key]??null;
            if ($op===null || isset($seen[$key]) || $row[3]!==$op['id']
                || !str_contains($controller,'public function '.$row[3].'(')
                || ($pins[strtoupper($row[1]).' '.$op['path']]['operation_id']??null)!==$op['id']) {
                throw new RuntimeException('Unreviewed Price List endpoint: '.$key);
            }
            $seen[$key]=true;
        }
        foreach (["'UNAUTHENTICATED',401", "'ACCESS_MUTATION_NOT_ENABLED',403",
            "env('DINKERP_M33B_WRITE_MODE','off')", 'JsonRequest::readObject(',
            "!array_key_exists('items',\$body)", 'PriceListService($actor)'] as $gate) {
            if (!str_contains($controller,$gate))throw new RuntimeException('M7.3 HTTP guard missing: '.$gate);
        }
        foreach (["hasBaseViewPermission('price_list')", "canView('price_list'",
            "decideMutation('price_list'", "decideTransitionAction('price_list'",
            "initialState('price_list')", "transitionTarget('price_list'",'PriceListItemPlan::build(',
            'authorizedBuckets(', 'PriceListSql::collection(', 'MAX_BUCKETS','MAX_ITEMS',
            'information_schema.TABLES','INNODB','GET_LOCK(', ' FOR UPDATE','transBegin(',
            'transCommit(', 'transRollback(', 'resetTransStatus(', 'REPEATABLE READ'] as $gate) {
            if (!str_contains($service,$gate) && !in_array($gate,['MAX_ITEMS','MAX_BUCKETS'],true)) {
                throw new RuntimeException('M7.3 PBAC/transaction guard missing: '.$gate);
            }
        }
        if (!str_contains($access,"'price_list' => 'ORGANIZATION'")) {
            throw new RuntimeException('Pricing must use ORGANIZATION resource domain.');
        }
        foreach (['BINARY p.status', 'COUNT(*) AS total', ' LIMIT ? OFFSET ?',
            'organization_id = ?', 'rows_binds', 'count_binds', "ESCAPE '!'"] as $gate) {
            if (!str_contains($sql,$gate)) throw new RuntimeException('M7.3 SQL pushdown guard missing: '.$gate);
        }
        foreach (['MAX_ITEMS = 500','MAX_BUCKETS = 512','samePrice(',
            "'PRICE_LIST_STATUS_REQUIRES_SETSTATE'",'PositiveId::value(', 'dateTime('] as $gate) {
            if (!str_contains($rules,$gate))throw new RuntimeException('M7.3 rules missing: '.$gate);
        }
        foreach (['deletes','updates','creates','samePrice('] as $gate) {
            if (!str_contains($plan,$gate))throw new RuntimeException('M7.3 plan missing: '.$gate);
        }
        foreach (['en','vi'] as $locale) {
            $catalog=(string)file_get_contents($app.'Language/'.$locale.'/PriceListRuntime.php');
            foreach (['PRICE_LIST_NOT_FOUND','PRICE_LIST_AUTHORIZATION_DENIED',
                'PRICE_LIST_INVALID_PRICE','PRICE_LIST_CONNECTION_INVALID'] as $code) {
                if (!str_contains($catalog,$code))throw new RuntimeException('Missing Pricing locale '.$locale.': '.$code);
            }
        }
        return ['prior'=>136,'added'=>7,'total'=>143,'baseline'=>150];
    }
}
