<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Modules/Import/Core/ImportTemplateCatalog.php';
require_once __DIR__.'/../app/Modules/Import/Core/ImportFileReader.php';
require_once __DIR__.'/../app/Modules/Import/Core/ImportRowPlanner.php';

use App\Exceptions\DomainException;
use App\Modules\Import\Core\ImportTemplateCatalog;
use App\Modules\Import\Core\ImportFileReader;
use App\Modules\Import\Core\ImportRowPlanner;

$n=0;
$ok=static function(bool $yes,string $name) use (&$n):void {
    if (!$yes) throw new RuntimeException('M8 READER FAILED: '.$name);
    $n++;
};
$bad=static function(callable $fn,string $code,string $name) use ($ok):void {
    try { $fn(); } catch (DomainException $e) {
        $ok($e->getErrorCode()===$code,$name.' exact domain error');return;
    }
    throw new RuntimeException('M8 READER accepted: '.$name);
};
$template=ImportTemplateCatalog::product();$parser=new ImportFileReader();
$temp=tempnam(sys_get_temp_dir(),'m8csv_');
if ($temp===false) throw new RuntimeException('Cannot allocate fixture file.');
try {
    file_put_contents($temp,"\xEF\xBB\xBFrecord_type,source_key,product_code,product_name\r\nPRODUCT,a,A01,Product A\r\nPRODUCT,b,B02,Product B\r\n");
    $parsed=$parser->read($temp,'batch.csv',$template);
    $ok($parsed['format']==='CSV','CSV type detected');
    $ok($parsed['header']===['record_type','source_key','product_code','product_name'],'UTF8 BOM stripped');
    $ok(count($parsed['rows'])===2,'skip blank trailing row');
    $ok($parsed['rows'][0]['number']===2 && $parsed['rows'][1]['number']===3,'source line numbers stable');
    $ok($parsed['rows'][0]['cells']['product_name']==='Product A','roundtrip field');
    $bad(static fn()=> $parser->read($temp,'batch.php',$template),'IMPORT_FILE_INVALID','extension whitelist');
    $bad(static fn()=> $parser->read($temp,'bad/path.csv',$template),'IMPORT_FILE_INVALID','unsafe name');
    $bad(static fn()=> $parser->read($temp,'anything.csv',[...$template,'max_bytes'=>8]),'IMPORT_M8_FILE_LIMIT_EXCEEDED','max bytes enforced');
    file_put_contents($temp,"record_type,source_key\nPRODUCT,=FORMULA()\n");
    $bad(static fn()=> $parser->read($temp,'batch.csv',$template),'IMPORT_FILE_INVALID','formula blocked');
    file_put_contents($temp,"record_type,source_key\nPRODUCT,@CMD\n");
    $bad(static fn()=> $parser->read($temp,'batch.csv',$template),'IMPORT_FILE_INVALID','at-prefixed injection blocked');
    file_put_contents($temp,"record_type,source_key\nPRODUCT,-cmd\n");
    $bad(static fn()=> $parser->read($temp,'batch.csv',$template),'IMPORT_FILE_INVALID','minus-formula blocked');
    file_put_contents($temp,"record_type,source_key\nPRODUCT,-1.500000\n");
    $ok($parser->read($temp,'batch.csv',$template)['rows'][0]['cells']['source_key']==='-1.500000','numeric negative string allowed');
    file_put_contents($temp,"record_type,source_key\nPRODUCT,__NULL__\n");
    $ok($parser->read($temp,'batch.csv',$template)['rows'][0]['cells']['source_key']===null,'null token');
    file_put_contents($temp,"record_type,source_key\nPRODUCT,a\nPRODUCT,b\n");
    $bad(static fn()=> $parser->read($temp,'batch.csv',[...$template,'max_rows'=>1]),'IMPORT_M8_ROW_LIMIT_EXCEEDED','maximum rows');
    file_put_contents($temp,"record_type,source_key\nPRODUCT,a,overflow\n");
    $bad(static fn()=> $parser->read($temp,'batch.csv',$template),'IMPORT_INVALID_TEMPLATE','extra cell beyond header');
    file_put_contents($temp,"record_type,source_key\n");
    $bad(static fn()=> $parser->read($temp,'batch.csv',$template),'IMPORT_FILE_INVALID','no data rows');
    file_put_contents($temp,"record_type,source_key\nPRODUCT,\xff\n");
    $bad(static fn()=> $parser->read($temp,'batch.csv',$template),'IMPORT_FILE_INVALID','invalid UTF8');
} finally { unlink($temp); }
$row=static function(int $number,string $type,string $key,?string $parent=null):array {
    return ['number'=>$number,'cells'=>['record_type'=>$type,'source_key'=>$key,'parent_source_key'=>$parent]];
};
$work=ImportRowPlanner::order([
    $row(2,'COMBO_ITEM','x'),$row(3,'PRODUCT','child','root'),$row(4,'PRODUCT','root'),
    $row(5,'UOM_CONVERSION','z'),
],$template['record_types']);
$ok(array_column($work,'number')===[4,3,5,2],'topological parents followed by UOM and COMBO');
$bad(static fn()=>ImportRowPlanner::order([$row(2,'PRODUCT','x'),$row(3,'PRODUCT','x')],$template['record_types']),
    'IMPORT_DUPLICATE_SOURCE_KEY','duplicate source refs');
$bad(static fn()=>ImportRowPlanner::order([$row(2,'PRODUCT','x','y'),$row(3,'PRODUCT','y','x')],$template['record_types']),
    'IMPORT_DEPENDENCY_CYCLE','parent cycle');
$bad(static fn()=>ImportRowPlanner::order([$row(2,'PRODUCT','x','missing')],$template['record_types']),
    'IMPORT_DEPENDENCY_INVALID','unresolved parent');
$bad(static fn()=>ImportRowPlanner::order([$row(2,'PRODUCT','')],$template['record_types']),
    'IMPORT_SOURCE_KEY_REQUIRED','missing source key');
$invalid=ImportRowPlanner::order([$row(2,'INVALID','x')],$template['record_types']);
$ok(count($invalid)===1 && $invalid[0]['cells']['record_type']==='INVALID','invalid row kept for row-level error');
echo 'M8 CSV/DEPENDENCY FIXTURE: '.$n.'/'.$n." PASS; no DB/HTTP\n";
