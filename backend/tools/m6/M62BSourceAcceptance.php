<?php

declare(strict_types=1);

require_once __DIR__ . '/M62ASourceAcceptance.php';

/** M6.2B: exact Product Master mutations, retaining the 122-route read baseline. */
final class M62BSourceAcceptance
{
    /** @return array<string,array{id:string,path:string,status:int}> */
    public static function operations(): array
    {
        return [
            'POST products' => ['id'=>'createProduct','path'=>'/api/v1/products','status'=>201],
            'PATCH products/(:num)' => ['id'=>'updateProduct','path'=>'/api/v1/products/{id}','status'=>200],
            'POST products/(:num)/actions/set-state' => ['id'=>'setProductState','path'=>'/api/v1/products/{id}/actions/set-state','status'=>200],
        ];
    }

    /** @param array<string,array<string,mixed>> $registered */
    public static function projectedPrevious(array $registered): array
    {
        foreach (self::operations() as $key => $_) {
            [$verb,$path]=explode(' ',$key,2);
            unset($registered[$verb]['api/v1/'.$path]);
        }
        return $registered;
    }

    /** @param array<string,array<string,mixed>> $registered */
    public static function validateRegistered(array $registered): void
    {
        $owner='App\\Modules\\Product\\Master\\ProductMutationController';
        foreach (self::operations() as $key=>$op) {
            [$verb,$path]=explode(' ',$key,2);
            if (($registered[$verb]['api/v1/'.$path]??null)!==[$owner,$op['id']]) {
                throw new RuntimeException('M6.2B unreviewed Product mutation: '.$key);
            }
        }
        $count=0; foreach ($registered as $verb=>$set) if ($verb!=='OPTIONS') $count+=count($set);
        if ($count!==125) throw new RuntimeException('M6.2B requires 125 exact routes, got '.$count);
        M62ASourceAcceptance::validateRegistered(self::projectedPrevious($registered));
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root): array
    {
        $old=M62ASourceAcceptance::verify($root);
        if ($old['total']!==122) throw new RuntimeException('M6.2B requires 122-route GET baseline');
        $main=(string)file_get_contents($root.'/backend/app/Config/Routes.php');
        if (substr_count($main,"require __DIR__ . '/M62BRoutes.php';")!==1 || str_contains($main,'setAutoRoute(true)')) {
            throw new RuntimeException('M6.2B routing is not explicitly reviewed');
        }
        $source=(string)file_get_contents($root.'/backend/app/Config/M62BRoutes.php');
        $pattern='~\$routes->(post|patch)\(\x27([^\x27]+)\x27,\s*\[\\\\App\\\\Modules\\\\Product\\\\Master\\\\ProductMutationController::class,\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\]\);~';
        preg_match_all($pattern,$source,$entries,PREG_SET_ORDER);
        if (count($entries)!==3 || substr_count($source,'$routes->')!==3) throw new RuntimeException('M6.2B has wrong route count');
        $controller=(string)file_get_contents($root.'/backend/app/Modules/Product/Master/ProductMutationController.php');
        $manifest=json_decode((string)file_get_contents($root.'/docs/backend-rewrite/openapi-operations-v1.2.4.json'),true,512,JSON_THROW_ON_ERROR);
        $api=[]; foreach ($manifest['operations'] as $op) $api[$op['method'].' '.$op['path']]=$op;
        if (count($api)!==150) throw new RuntimeException('Frozen 150-route manifest changed');
        $seen=[];
        foreach ($entries as $match) {
            $key=strtoupper($match[1]).' '.$match[2];
            $expected=self::operations()[$key]??null;
            if ($expected===null || isset($seen[$key]) || $match[3]!==$expected['id']
                || !str_contains($controller,'function '.$match[3].'(')) {
                throw new RuntimeException('Unapproved Product mutation route/owner: '.$key);
            }
            $pin=$api[strtoupper($match[1]).' '.$expected['path']]??null;
            if ($pin===null || $pin['operation_id']!==$expected['id']
                || !in_array($expected['status'],$pin['success_statuses']??[],true)) {
                throw new RuntimeException('M6.2B route differs from frozen API-050: '.$key);
            }
            $seen[$key]=true;
        }
        $service=(string)file_get_contents($root.'/backend/app/Modules/Product/Master/ProductMutationService.php');
        $issuer=(string)file_get_contents($root.'/backend/app/Modules/Product/Master/ProductNumberIssuer.php');
        $rules=(string)file_get_contents($root.'/backend/app/Modules/Product/Master/ProductMutationRules.php');
        foreach (['decideMutation(', 'decideTransitionAction(', 'transBegin()', 'transCommit()', 'transRollback()',
            'GET_LOCK(', 'RELEASE_LOCK(', 'FOR UPDATE','assertConversionEligible(', 'insertCombo(', 'assertVisible('] as $guard) {
            if (!str_contains($service,$guard)) throw new RuntimeException('Product mutation lost guard: '.$guard);
        }
        foreach (['auto_numbering_counters','getRowArray()', 'ProductNumberIssuer','NUM_SCOPE_CONTEXT_REQUIRED'] as $guard) {
            if (!str_contains($issuer,$guard)) throw new RuntimeException('Product numbering lost guard: '.$guard);
        }
        foreach (['assertStructure(','assertEditBoundary(','PRODUCT_STATUS_REQUIRES_SETSTATE','combo_items'] as $guard) {
            if (!str_contains($rules,$guard)) throw new RuntimeException('Product domain rules lost guard: '.$guard);
        }
        foreach (['en','vi'] as $locale) {
            $translations = $root.'/backend/app/Language/'.$locale.'/ProductRuntime.php';
            if (!is_file($translations)) throw new RuntimeException('M6.2B Product runtime locale missing: '.$locale);
            $dictionary=require $translations;
            foreach (['PRODUCT_INVALID_FIELD','PRODUCT_AUTHORIZATION_DENIED','PRODUCT_IDENTIFIER_DUPLICATE',
                'PRODUCT_STATUS_REQUIRES_SETSTATE','PRODUCT_SEMANTIC_HISTORY_LOCKED'] as $code) {
                if (!is_string($dictionary[$code]??null) || strlen($dictionary[$code])<12) {
                    throw new RuntimeException('M6.2B unreviewed bilingual Product message: '.$locale.' '.$code);
                }
            }
        }
        $responder=(string)file_get_contents($root.'/backend/app/Shared/Http/ApiResponder.php');
        if (!str_contains($responder, "'ProductRuntime.'")) {
            throw new RuntimeException('M6.2B Product EN/VI responder mapping is missing');
        }
        if (!str_contains($controller,"env('DINKERP_M33B_WRITE_MODE','off')")
            || !str_contains($controller,"'UNAUTHENTICATED',401")
            || is_dir($root.'/backend/app/Modules/ProductManagement')) {
            throw new RuntimeException('Product mutation write/auth/ownership boundary is missing');
        }
        return ['prior'=>122,'added'=>3,'total'=>125,'baseline'=>150];
    }
}
