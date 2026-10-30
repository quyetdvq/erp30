<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Modules/History/HistoryEventPresenter.php';
require_once __DIR__ . '/../../app/Shared/Http/RequestMetadata.php';
require_once __DIR__ . '/../../app/Shared/Http/ApiEnvelope.php';

use App\Modules\History\HistoryEventPresenter;
use App\Shared\Http\ApiEnvelope;

$record=[
    'id'=>9223372036854775806, 'resource_id'=>42, 'actor_user_id'=>1,
    'actor_username'=>' thangdv ', 'actor_full_name'=>' Nguyễn Văn A ',
    'changes_json'=>'{"phone":{},"status":["DRAFT","ACTIVE"]}',
    'resource_code'=>' CUS-0042 ', 'resource_name'=>' Công ty ABC ',
    'checkpoint_json'=>'{"status":"ACTIVE"',
    'created_at'=>'2026-10-29 09:52:19',
];
$checks=0;
$ok=static function(bool $v,string $message) use(&$checks):void {
    if (!$v) throw new RuntimeException('HIS PRESENTER: '.$message); ++$checks;
};
$record['checkpoint_json']='{"status":"ACTIVE"}';
$p=HistoryEventPresenter::present($record);
$ok($p['id']==='9223372036854775806', 'big id remains exact');
$ok($p['resource_id']==='42' && $p['actor_user_id']==='1', 'IDs string');
$ok($p['actor_username']==='thangdv' && $p['actor_full_name']==='Nguyễn Văn A', 'identity names normalized');
$ok($p['resource_code']==='CUS-0042' && $p['resource_name']==='Công ty ABC', 'resource label projected');
$ok($p['created_at']==='2026-10-29T09:52:19Z', 'UTC not local ambiguous');
$ok(json_encode($p['changes']['phone']) === '{}' && $p['changes']['status'] === ['DRAFT','ACTIVE'], 'sanitized diff decoded');
$ok($p['checkpoint']['status']==='ACTIVE', 'checkpoint decoded');
// Regression for the REAL HTTP pipeline: Presenter alone kept {}, but the
// shared ApiEnvelope previously turned each empty stdClass into PHP [] and
// emitted changes.phone=[] to Vue. Test the final JSON, not just $p.
$wire = json_encode(ApiEnvelope::success([$p]), JSON_THROW_ON_ERROR);
$decodedWire = json_decode($wire, false, 64, JSON_THROW_ON_ERROR);
$wireChanges = $decodedWire->data[0]->changes;
$ok($wireChanges->phone instanceof stdClass && get_object_vars($wireChanges->phone) === [],
    'wire redacted History marker is JSON object, not array');
$ok($wireChanges->status === ['DRAFT', 'ACTIVE'], 'wire status diff remains two-item tuple');
$ok(str_contains($wire, '"phone":{}') && !str_contains($wire, '"phone":[]'),
    'final API JSON contains literal phone:{}');
$ok($decodedWire->data[0]->checkpoint->status === 'ACTIVE', 'checkpoint remains present on API wire');
$ok(!isset($p['changes_json']) && !isset($p['checkpoint_json']), 'raw JSON omitted');
$p=HistoryEventPresenter::present([...$record,'actor_user_id'=>null,'actor_username'=>null,'actor_full_name'=>null]);
$ok($p['actor_user_id']===null && $p['actor_username']===null && $p['actor_full_name']===null, 'unknown/removed actor');
try { HistoryEventPresenter::present([...$record,'created_at'=>'2026-10-29 09:52:19+07:00']); throw new RuntimeException('accepted ambiguous date'); }
catch(UnexpectedValueException) { $ok(true,'reject unknown datetime format'); }
foreach (['{"phone":{"changed":true}}','{"status":{"before":"DRAFT","after":"ACTIVE"}}',
          '{"company_ids":{"before_count":0,"after_count":2}}','{"phone":[]}',
          '{"status":["DRAFT"]}','["DRAFT","ACTIVE"]','{"password":[[1],2]}'] as $old) {
    try { HistoryEventPresenter::present([...$record, 'changes_json'=>$old]);
        throw new RuntimeException('accepted legacy or malformed changes'); }
    catch (UnexpectedValueException) { $ok(true, 'only compact schema accepted'); }
}
echo "HIS-02 PRESENTER: {$checks}/{$checks} PASS\n";
