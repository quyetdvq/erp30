<?php

declare(strict_types=1);

require_once __DIR__.'/../m7/M74SourceAcceptance.php';

/** M8 shared Import Framework + Product Adapter: four frozen API-060 operations. */
final class M8SourceAcceptance
{
    /** @return array<string,array{id:string,path:string}> */
    public static function operations(): array
    {
        return [
            'POST products/import-jobs'=>['id'=>'startProductImport','path'=>'/api/v1/products/import-jobs'],
            'GET import-jobs'=>['id'=>'listImportJobs','path'=>'/api/v1/import-jobs'],
            'GET import-jobs/(:num)'=>['id'=>'getImportJob','path'=>'/api/v1/import-jobs/{id}'],
            'GET import-jobs/(:num)/rows'=>['id'=>'listImportJobRows','path'=>'/api/v1/import-jobs/{id}/rows'],
        ];
    }

    /** @param array<string,array<string,mixed>> $registered */
    public static function validateRegistered(array $registered): void
    {
        $previous=$registered;
        foreach (self::operations() as $key=>$op) {
            [$verb,$path]=explode(' ',$key,2);
            $route='api/v1/'.$path;
            if (($previous[$verb][$route]??null)!==[
                'App\\Modules\\Product\\Import\\ProductImportController',$op['id'],
            ]) throw new RuntimeException('M8 unexpected import route: '.$key);
            unset($previous[$verb][$route]);
        }
        $count=0;
        foreach ($registered as $method=>$entries) {
            if ($method!=='OPTIONS') $count+=count($entries);
        }
        if ($count!==150) throw new RuntimeException('M8 expects 150 operations, got '.$count);
        M74SourceAcceptance::validateRegistered($previous);
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root): array
    {
        $previous=M74SourceAcceptance::verify($root);
        if ($previous['total']!==146) throw new RuntimeException('Prior M7.4 not source accepted.');
        $app=$root.'/backend/app/';
        $routes=(string)file_get_contents($app.'Config/Routes.php');
        if (substr_count($routes,"require __DIR__ . '/M8Routes.php';")!==1
            || !str_contains($routes,'$routes->setAutoRoute(false)')) {
            throw new RuntimeException('Missing reviewed M8 routes/autoroute guard.');
        }
        $source=(string)file_get_contents($app.'Config/M8Routes.php');
        preg_match_all('~\\$routes->(get|post)\\(\x27([^\x27]+)\x27,\\s*\\[\\\\App\\\\Modules\\\\Product\\\\Import\\\\ProductImportController::class,\\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\\]\\);~',
            $source,$matches,PREG_SET_ORDER);
        if (count($matches)!==4 || substr_count($source,'$routes->')!==4) {
            throw new RuntimeException('M8 must register exactly four reviewed methods.');
        }
        $frozen=json_decode((string)file_get_contents(
            $root.'/docs/backend-rewrite/openapi-operations-v1.2.4.json'),true,512,JSON_THROW_ON_ERROR);
        if (count($frozen['operations'])!==150) throw new RuntimeException('Frozen contract changed.');
        $pins=[];
        foreach ($frozen['operations'] as $op) $pins[$op['method'].' '.$op['path']]=$op['operation_id'];
        $controller=(string)file_get_contents($app.'Modules/Product/Import/ProductImportController.php');
        $seen=[];
        foreach ($matches as $match) {
            $key=strtoupper($match[1]).' '.$match[2];
            $op=self::operations()[$key]??null;
            if ($op===null || isset($seen[$key]) || $op['id']!==$match[3]
                || ($pins[strtoupper($match[1]).' '.$op['path']]??null)!==$op['id']
                || !str_contains($controller,'public function '.$op['id'].'(')) {
                throw new RuntimeException('M8 route or handler not frozen: '.$key);
            }
            $seen[$key]=true;
        }
        foreach ([
            ['Modules/Import/Core/ImportAdapter.php','interface ImportAdapter'],
            ['Modules/Import/Core/ImportJobService.php','ImportRowPlanner::order('],
            ['Modules/Import/Core/ImportJobService.php','assertInnoDb('],
            ['Modules/Import/Core/ImportJobService.php','viewableJobStatuses('],
            ['Modules/Import/Core/ImportJobSql.php','COUNT(*) AS total'],
            ['Modules/Import/Core/ImportJobSql.php','BINARY status IN'],
            ['Modules/Import/Core/ImportFileReader.php','TYPE_FORMULA'],
            ['Modules/Product/Import/ProductImportAdapter.php','ProductMutationService('],
            ['Modules/Product/Import/ProductImportAdapter.php','ProductConversionService('],
            ['Modules/Product/Import/ProductImportAdapter.php','ProductComboService('],
            ['Modules/Access/UnifiedAccessService.php','canStartProductImport('],
            ['Modules/Access/UnifiedAccessService.php','viewableProductImportStatuses('],
        ] as [$path,$token]) {
            if (!str_contains((string)file_get_contents($app.$path),$token)) {
                throw new RuntimeException('Missing M8 core invariant: '.$path.' '.$token);
            }
        }
        foreach (['DINKERP_M33B_WRITE_MODE','UNAUTHENTICATED','ImportJobService('] as $term) {
            if (!str_contains($controller,$term)) throw new RuntimeException('M8 controller safety missing: '.$term);
        }
        $json=$app.'Modules/Import/Templates/product-v1.json';
        $manifest=json_decode((string)file_get_contents($json),true,512,JSON_THROW_ON_ERROR);
        if (($manifest['entity_type']??'')!=='PRODUCT' || ($manifest['version']??'')!=='1'
            || ($manifest['max_rows']??null)!==1000 || ($manifest['max_bytes']??null)!==2097152
            || !is_array($manifest['columns']??null) || count($manifest['columns'])!==31) {
            throw new RuntimeException('M8 canonical versioned manifest missing.');
        }
        $csv=$root.'/frontend/public/import-templates/product-v1.csv';
        $handle=fopen($csv,'rb');
        if ($handle===false) throw new RuntimeException('M8 CSV template missing.');
        try { $columns=fgetcsv($handle,0,',','"',''); $after=fgetcsv($handle,0,',','"',''); }
        finally { fclose($handle); }
        if ($columns!==$manifest['columns'] || $after!==false) {
            throw new RuntimeException('M8 CSV template diverges from adapter schema.');
        }
        $xlsx=$root.'/frontend/public/import-templates/product-v1.xlsx';
        if (!is_file($xlsx) || filesize($xlsx)<100 || file_get_contents($xlsx,false,null,0,2)!=='PK') {
            throw new RuntimeException('M8 Excel template missing or not a ZIP container.');
        }
        $wizard=(string)file_get_contents($root.'/frontend/src/features/import/shared/ImportWizard.vue');
        $frontend=(string)file_get_contents($root.'/frontend/src/features/product-import/pages/ProductImportView.vue');
        foreach (['templateCsv','templateXlsx','templatePrefix','canStart','emit(\'submit\''] as $term) {
            if (!str_contains($wizard,$term)) throw new RuntimeException('M8 shared UI missing: '.$term);
        }
        if (!str_contains($frontend,'<ImportWizard') || !str_contains($frontend,'template-prefix="product"')) {
            throw new RuntimeException('Product UI not using reusable wizard.');
        }
        return ['prior'=>146,'added'=>4,'total'=>150,'baseline'=>150];
    }
}
