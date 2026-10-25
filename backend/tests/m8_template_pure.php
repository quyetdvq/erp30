<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Modules/Import/Core/ImportTemplateCatalog.php';

use App\Exceptions\DomainException;
use App\Modules\Import\Core\ImportTemplateCatalog;

$n=0;
$ok=static function(bool $yes,string $name) use (&$n):void {
    if (!$yes) throw new RuntimeException('M8 TEMPLATE FAILED: '.$name);
    $n++;
};
$bad=static function(callable $fn,string $name) use ($ok):void {
    try { $fn(); } catch (DomainException) { $ok(true,$name); return; }
    throw new RuntimeException('M8 TEMPLATE accepted invalid header: '.$name);
};
$manifest=ImportTemplateCatalog::product();
$ok($manifest['entity_type']==='PRODUCT','correct adapter resource');
$ok($manifest['version']==='1','versioned template schema');
$ok($manifest['record_types']===['PRODUCT','UOM_CONVERSION','COMBO_ITEM'],'canonical row types');
$ok($manifest['max_rows']===1000,'row cap');
$ok($manifest['max_bytes']===2097152,'file cap');
$ok(count($manifest['columns'])===31,'canonical width');
$ok($manifest['columns'][0]==='record_type','first row type');
$ok(count($manifest['columns'])===count(array_unique($manifest['columns'])),'unique columns');
$csv=__DIR__.'/../../frontend/public/import-templates/product-v1.csv';
$stream=fopen($csv,'rb');
$header=fgetcsv($stream,0,',','"','');
$other=fgetcsv($stream,0,',','"','');
fclose($stream);
$ok($header===$manifest['columns'],'CSV columns exactly canonical');
$ok($other===false,'CSV ships header only');
$ok(preg_match('/\A[\x20-\x7e\r\n,]*\z/D',(string)file_get_contents($csv))===1,'CSV ASCII safe header');
$xlsx=__DIR__.'/../../frontend/public/import-templates/product-v1.xlsx';
$ok(is_file($xlsx) && filesize($xlsx)>1024,'XLSX generated');
$stream=fopen($xlsx,'rb');$magic=fread($stream,4);fclose($stream);
$ok($magic==="PK\x03\x04",'XLSX ZIP signature');
$wizard=(string)file_get_contents(__DIR__.'/../../frontend/src/features/import/shared/ImportWizard.vue');
$ok(str_contains($wizard,'templatePrefix') && str_contains($wizard,'templateCsv') && str_contains($wizard,'templateXlsx'),'shared UI template links');
$ok(str_contains((string)file_get_contents(__DIR__.'/../../tools/import_templates/build.py'),'product-v1.json'),'manifest drives Excel and CSV generation');
ImportTemplateCatalog::assertHeader(['record_type','source_key'], $manifest['columns']);
$ok(true,'bounded optional subset');
$bad(static fn()=>ImportTemplateCatalog::assertHeader([],$manifest['columns']),'empty');
$bad(static fn()=>ImportTemplateCatalog::assertHeader(['source_key'],$manifest['columns']),'missing record_type');
$bad(static fn()=>ImportTemplateCatalog::assertHeader(['record_type','record_type'],$manifest['columns']),'duplicate column');
$bad(static fn()=>ImportTemplateCatalog::assertHeader(['record_type','missing'],$manifest['columns']),'unknown column');
$bad(static fn()=>ImportTemplateCatalog::assertHeader(['record_type',''],$manifest['columns']),'empty name');
$bad(static fn()=>ImportTemplateCatalog::assertHeader([...$manifest['columns'],'extra'],$manifest['columns']),'overflow header');
echo 'M8 SHARED TEMPLATE: '.$n.'/'.$n." PASS\n";
