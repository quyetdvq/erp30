<?php

declare(strict_types=1);

require_once __DIR__ . '/../fixtures/m33_ci_http_exception_stub.php';
require_once __DIR__ . '/../../app/Modules/History/HistorySql.php';

use App\Modules\History\HistorySql as Sql;
use CodeIgniter\HTTP\Exceptions\BadRequestException;

$n=0;
$ok=static function(bool $truth,string $description)use(&$n):void{
    if(!$truth)throw new RuntimeException('HIS SQL: '.$description); $n++;
};
$bad=static function(callable $task)use($ok):void{
    try{$task();}catch(BadRequestException){$ok(true,'unsafe pagination rejected');return;}
    throw new RuntimeException('Unsafe history parameter accepted');
};
$p=Sql::params(['page'=>'2','page_size'=>'25']);
$ok($p===['page'=>2,'page_size'=>25],'validated pagination');
$plan=Sql::forVisibleParent('customer','42',$p,'dinktech_');
$ok(str_contains($plan['rows_sql'],'`dinktech_business_history_events`'),'DBPrefix quoted');
$ok(str_contains($plan['count_sql'], 'h.resource_type = ? AND h.resource_id = ?'),'bounded exact parent');
$ok(str_contains($plan['count_sql'], 'h.owner_domain = \'MASTER\''),'owner constrained');
$ok(str_ends_with($plan['rows_sql'],'ORDER BY h.id DESC LIMIT ? OFFSET ?'),'indexed page');
$ok($plan['count_binds']===['customer','42'],'count binds');
$ok($plan['rows_binds']===['customer','42',25,25],'page bounds');
$ok(substr_count($plan['count_sql'],'?')===count($plan['count_binds']),'all count values bound');
$ok(substr_count($plan['rows_sql'],'?')===count($plan['rows_binds']),'all row values bound');
$ok(!str_contains($plan['rows_sql'],'SELECT h.*'),'narrow projection');
foreach([['page'=>0],['page'=>[]],['page_size'=>51],['page'=>1000000],['sort'=>'status'],['resource'=>'supplier'],['page'=>'1 OR 1=1']] as $invalid){$bad(static fn()=>Sql::params($invalid));}
foreach([['supplier','42','dinktech_'],['customer','0','dinktech_'],['customer','42','x`evil'],['customer','42','a-b']] as [$resource,$id,$prefix]){
  try{Sql::forVisibleParent($resource,$id,$p,$prefix);throw new RuntimeException('Unreviewed history SQL approved');}
  catch(InvalidArgumentException){$ok(true,'unreviewed parent/prefix denied');}
}
echo "HIS-01 SQL: {$n}/{$n} PASS\n";
