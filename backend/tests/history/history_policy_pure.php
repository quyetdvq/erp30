<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Modules/History/HistoryPolicy.php';

use App\Modules\History\HistoryPolicy as Policy;

$n=0;
$ok=static function(bool $truth,string $description) use (&$n):void {
    if (!$truth) throw new RuntimeException('HIS POLICY: '.$description);
    $n++;
};
$ok(Policy::allowsResource('customer'),'customer onboarded');
$ok(Policy::allowsResource('supplier'),'future supplier known');
$ok(!Policy::allowsResource('authorization_log'),'unknown resource denied');
$ok(!Policy::isOperational('customer','DRAFT'),'draft not operational');
$ok(!Policy::isOperational('customer','PENDING'),'pending not operational without explicit policy');
$ok(Policy::isOperational('customer','ACTIVE'),'active operational');
$ok(!Policy::shouldRecordUpdate('customer','DRAFT','DRAFT'),'draft update skipped');
$ok(Policy::shouldRecordUpdate('customer','ACTIVE','ACTIVE'),'active update captured');
$ok(Policy::shouldRecordUpdate('customer','ACTIVE','DRAFT'),'operational exit captured');
$ok(!Policy::shouldRecordUpdate('customer','PENDING','PENDING'),'preapproval skip');
$old=['name'=>'Công ty A','phone'=>'0912345678','address'=>'Secret Street','tax_code'=>'ABC123','customer_group_code'=>'STANDARD',
    'customer_code'=>'CUS-10','customer_type'=>'ORGANIZATION','company_ids'=>['1','2'],'status'=>'ACTIVE', 'password'=>'a-secret'];
$new=[...$old,'name'=>'Công ty B','phone'=>'+84912345678','address'=>'Other Address',
    'tax_code'=>'DEF999','customer_group_code'=>'VIP1','company_ids'=>['2','3'], 'password'=>'b-secret'];
$diff=Policy::diff('customer',$old,$new);
$serialized=json_encode($diff,JSON_THROW_ON_ERROR);
$ok($diff['customer_group_code']===['STANDARD','VIP1'],'approved codes present');
$ok($diff['name'] instanceof stdClass, 'name redacted');
$ok($diff['phone'] instanceof stdClass, 'phone redacted');
$ok($diff['address'] instanceof stdClass, 'address redacted');
$ok($diff['tax_code'] instanceof stdClass, 'tax code redacted');
$ok(isset($diff['company_ids'])&&$diff['company_ids']===[2,2],'Company ID values redacted');
foreach(['Công ty A','Công ty B','Secret Street','0912345678','84912345678','ABC123','DEF999','a-secret','b-secret','"1"','"3"'] as $forbidden) {
    $ok(!str_contains($serialized,$forbidden),'excluded raw sensitive data: '.$forbidden);
}
$ok(!array_key_exists('password',$diff),'unreviewed field never emitted');
$ok(Policy::diff('customer',$old,[...$old,'company_ids'=>['2','1']])===[],'scope set ignores order');
$ok(Policy::diff('customer',$old,$old)===[],'unchanged update has no history');
$flagOld=[...$old,'all_companies'=>1,'province_id'=>4];
$flagSame=[...$flagOld,'all_companies'=>'1','province_id'=>'4'];
$ok(Policy::diff('customer',$flagOld,$flagSame)===[], 'DB int/string flag and ID parity is a no-op');
$flagChange=[...$flagOld,'all_companies'=>'0'];
$ok(Policy::diff('customer',$flagOld,$flagChange)['all_companies']===[1,0],
    'Company usage mode flag transition normalized');
$ok(str_contains($serialized, '"name":{}') && str_contains($serialized, '"phone":{}'), 'redacted markers are JSON objects, never arrays');
try {Policy::diff('customer',$flagOld,[...$flagOld,'all_companies'=>'maybe']); throw new RuntimeException('Bad usage flag accepted');}
catch (InvalidArgumentException) {$ok(true, 'invalid flag rejected');}
$checkpoint=Policy::checkpoint('customer',$new);
$point=json_encode($checkpoint,JSON_THROW_ON_ERROR);
$ok(($checkpoint['name_present']??false)===true,'checkpoint records presence only');
$ok(($checkpoint['phone_present']??false)===true,'phone presence only');
$ok(($checkpoint['customer_group_code']??null)==='VIP1','checkpoint allowlisted code');
$ok(!str_contains($point,'Công ty B')&&!str_contains($point,'Other Address'),'checkpoint excludes PII');
try { Policy::diff('bogus',$old,$new); throw new RuntimeException('Accepted unknown resource.'); }
catch (InvalidArgumentException) { $ok(true,'invalid resource rejected'); }
try { Policy::diff('customer',[...$old,'company_ids'=>'1,2'],$new); throw new RuntimeException('Accepted untrusted scope.'); }
catch (InvalidArgumentException) { $ok(true,'malformed scope rejected'); }
echo "HIS-01 POLICY: {$n}/{$n} PASS\n";
