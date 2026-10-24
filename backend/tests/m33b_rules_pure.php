<?php

declare(strict_types=1);

require __DIR__ . '/../app/Exceptions/DomainException.php';
require __DIR__ . '/../app/Shared/Read/PositiveId.php';
require __DIR__ . '/../app/Modules/Access/ResourceStateCatalog.php';
require __DIR__ . '/../app/Modules/Access/Mutation/MutationRules.php';
// M3.3B1.1: require the exact original service, never an unverified replacement.
// The test-only snapshot is byte-identical to erp_v1.2.4.zip. It is NOT loaded
// by production PHP and is not evidence of live backend_basline parity.
$baselineRelative = 'app/Modules/Authorization/Administration/Groups/Application/AuthorizationGroupService.php';
$expectedBaselineSha256 = '7c4bdcfa439493b7602d9a2724c1a369c615abddd45b3f6817c3bf03d1821714';
$installedBaselineFile = dirname(__DIR__, 2) . '/backend_basline/' . $baselineRelative;
$snapshotBaselineFile = __DIR__ . '/fixtures/baseline_v1_2_4/' . $baselineRelative;
$requireInstalled = false;
$snapshotOnly = false;
foreach (array_slice($argv ?? [], 1) as $arg) {
    if ($arg === '--require-installed') {
        $requireInstalled = true;
    } elseif ($arg === '--snapshot-only') {
        $snapshotOnly = true;
    } else {
        fwrite(STDERR, "ERROR: unknown argument {$arg}\n");
        exit(2);
    }
}
if ($requireInstalled && $snapshotOnly) {
    fwrite(STDERR, "ERROR: cannot combine --require-installed and --snapshot-only.\n");
    exit(2);
}
if (! $snapshotOnly && is_file($installedBaselineFile)) {
    if (! is_readable($installedBaselineFile) || hash_file('sha256', $installedBaselineFile) !== $expectedBaselineSha256) {
        fwrite(STDERR, "ERROR: installed backend_basline AuthorizationGroupService differs from pinned v1.2.4: {$installedBaselineFile}\n");
        exit(2);
    }
    $baselineFile = $installedBaselineFile;
    echo "SOURCE: locally installed backend_basline AuthorizationGroupService; SHA-256 matches erp_v1.2.4.zip.\n";
} else {
    if ($requireInstalled) {
        fwrite(STDERR, "ERROR: installed backend_basline missing: {$installedBaselineFile}\n");
        exit(2);
    }
    if (! $snapshotOnly) {
        echo "NOTE: local backend_basline missing {$baselineRelative}.\n";
    }
    $baselineFile = $snapshotBaselineFile;
    echo "SOURCE: SHA-256-verified v1.2.4 test snapshot; NOT locally installed backend_basline.\n";
}
if (! is_file($baselineFile) || ! is_readable($baselineFile) || hash_file('sha256', $baselineFile) !== $expectedBaselineSha256) {
    fwrite(STDERR, "ERROR: pinned v1.2.4 baseline group source missing/modified: {$baselineFile}\n");
    exit(2);
}
require $baselineFile;

use App\Exceptions\DomainException;
use App\Modules\Access\Mutation\MutationRules as Rules;
use App\Modules\Authorization\Administration\Groups\Application\AuthorizationGroupService;

$count = 0;
function okB(string $label, bool $result): void
{
    global $count;
    if (! $result) { fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL); exit(1); }
    $count++;
    echo 'PASS: ' . $label . PHP_EOL;
}
function rejectB(string $label, callable $fn, string $code): void
{
    try { $fn(); }
    catch (DomainException $exception) {
        okB($label, $exception->getErrorCode() === $code);
        return;
    }
    okB($label, false);
}

$ref = (new ReflectionClass(AuthorizationGroupService::class))->newInstanceWithoutConstructor();
$oldNormalize = (new ReflectionClass(AuthorizationGroupService::class))->getMethod('normalizeGroup');
foreach ([
    [['code'=>' editors ','name'=>'  Wide Name  '],true],
    [['code'=>'MANAGER','name'=>'Normal','description'=>'Existing','status'=>'INACTIVE'],true],
    [['name'=>'  X  ','description'=>null],false],
    [['status'=>'ACTIVE'],false],
    [['id'=>99,'code'=>'READERS','created_at'=>'ignored','updated_by'=>42],false],
] as [$payload, $create]) {
    $legacy = $oldNormalize->invoke($ref, $payload, $create);
    $new = Rules::group($payload, $create);
    okB('Group normalized input matches v1.2.4 group service', $legacy === $new);
}
okB('Group code uppercase and trimmed', Rules::group(['code'=>' admin ','name'=>'Admin'],true)['code']==='ADMIN');
okB('Group name whitespace preserved exactly', Rules::group(['name'=>'  Test  '],false)['name']==='  Test  ');
okB('Group description null retained', Rules::group(['description'=>null],false)['description']===null);
okB('Group created ACTIVE when status omitted', Rules::group(['code'=>'A','name'=>'A'],true)['status']==='ACTIVE');
rejectB('Group create without code denied',fn()=>Rules::group(['name'=>'A'],true),'AUTH_GROUP_INVALID');
rejectB('Group create without name denied',fn()=>Rules::group(['code'=>'A'],true),'AUTH_GROUP_INVALID');
rejectB('Group whitespace-only code denied',fn()=>Rules::group(['code'=>'   ','name'=>'A'],true),'AUTH_GROUP_INVALID');
rejectB('Group whitespace-only name denied',fn()=>Rules::group(['name'=>'   '],false),'AUTH_GROUP_INVALID');
rejectB('Group invalid State denied',fn()=>Rules::group(['status'=>'DRAFT'],false),'AUTH_GROUP_INVALID');
rejectB('Group whitespace State denied (baseline)',fn()=>Rules::group(['status'=>' ACTIVE '],false),'AUTH_GROUP_INVALID');
rejectB('Group disallows unknown business keys',fn()=>Rules::group(['not_a_column'=>'x'],false),'AUTH_GROUP_FIELD_UNSUPPORTED');
rejectB('Group empty PATCH denied',fn()=>Rules::group([],false),'AUTH_GROUP_UPDATE_EMPTY');

$resource=Rules::resource(['type'=>' PRODUCT ','name'=>'  Product Access ','domain'=>' master ','lifecycle_id'=>'30'],true);
okB('Resource type lowercases', $resource['type']==='product');
okB('Resource name lowercases matching baseline', $resource['name']==='product access');
okB('Resource domain uppercases', $resource['domain']==='MASTER');
okB('Resource create forces ACTIVE', $resource['status']==='ACTIVE');
okB('Resource lifecycle id preserved', $resource['lifecycle_id']==='30');
okB('New resource requires Lifecycle', $resource['lifecycle_id']!==null);
okB('Native Group Resource may omit Lifecycle', Rules::resource(['type'=>'authorization_group','name'=>'Groups','domain'=>'SYSTEM'],true)['status']==='ACTIVE');
rejectB('Native cannot bind Lifecycle',fn()=>Rules::resource(['type'=>'policy','name'=>'Policy','domain'=>'SYSTEM','lifecycle_id'=>5],true),'RESOURCE_LIFECYCLE_INVALID');
rejectB('Unknown resource does not fall back to Native',fn()=>Rules::resource(['type'=>'future_type','name'=>'Future','domain'=>'MASTER'],true),'RESOURCE_LIFECYCLE_INVALID');
rejectB('Resource domain COMPANY rejected',fn()=>Rules::resource(['type'=>'product','name'=>'X','domain'=>'COMPANY','lifecycle_id'=>10],true),'RESOURCE_DOMAIN_INVALID');
rejectB('Resource create INACTIVE must use setstate',fn()=>Rules::resource(['type'=>'resource','name'=>'X','domain'=>'SYSTEM','status'=>'INACTIVE'],true),'NATIVE_STATE_REQUIRES_SETSTATE');
rejectB('Resource update cannot write State',fn()=>Rules::resource(['status'=>'INACTIVE'],false),'NATIVE_STATE_REQUIRES_SETSTATE');
rejectB('Resource type immutable',fn()=>Rules::resource(['type'=>'policy'],false),'RESOURCE_TYPE_IMMUTABLE');
rejectB('Resource domain immutable',fn()=>Rules::resource(['domain'=>'MASTER'],false),'RESOURCE_DOMAIN_IMMUTABLE');
rejectB('Resource empty PATCH rejected',fn()=>Rules::resource([],false),'VALIDATION_ERROR');
rejectB('Resource forbidden SQL key rejected',fn()=>Rules::resource(['sql_table'=>'users'],false),'VALIDATION_ERROR');

$action=Rules::resourceAction(['code'=>' SetState ','description'=>'manual'],true);
okB('Resource Action code lowercases', $action['code']==='setstate');
okB('Resource Action description kept', $action['description']==='manual');
rejectB('Resource Action requires code',fn()=>Rules::resourceAction(['description'=>'x'],true),'VALIDATION_ERROR');
rejectB('Resource Action PATCH empty rejected',fn()=>Rules::resourceAction([],false),'VALIDATION_ERROR');
rejectB('Resource Action arbitrary SQL key rejected',fn()=>Rules::resourceAction(['resource_id'=>8],false),'VALIDATION_ERROR');

okB('Empty group member replacement valid', Rules::idList(['user_ids'=>[]],'user_ids')===[]);
okB('Group IDs deduplicated without float', Rules::idList(['user_ids'=>[2,'002',2,4]],'user_ids')===['2','4']);
okB('Unsigned BIGINT normalized', Rules::id('18446744073709551615','id')==='18446744073709551615');
rejectB('Group IDs must be list',fn()=>Rules::idList(['user_ids'=>['a'=>5]],'user_ids'),'VALIDATION_ERROR');
rejectB('Group IDs cannot be float',fn()=>Rules::idList(['user_ids'=>[2.0]],'user_ids'),'VALIDATION_ERROR');
rejectB('Group ID cannot be zero',fn()=>Rules::idList(['user_ids'=>[0]],'user_ids'),'VALIDATION_ERROR');
rejectB('Group ID cannot exceed BIGINT',fn()=>Rules::idList(['user_ids'=>['18446744073709551616']],'user_ids'),'VALIDATION_ERROR');
rejectB('Malformed permission list denied',fn()=>Rules::idList(['resource_action_ids'=>null],'resource_action_ids'),'VALIDATION_ERROR');

okB('Native setstate code normalized',Rules::status('inactive')==='INACTIVE');
rejectB('Only ACTIVE/INACTIVE supported for independent State',fn()=>Rules::status('PENDING'),'VALIDATION_ERROR');

$source=file_get_contents(__DIR__.'/../app/Modules/Access/Mutation/AccessMutationService.php');
okB('Access mutation starts DB transaction',str_contains($source,'transBegin()'));
okB('Access mutation commits',str_contains($source,'transCommit()'));
okB('Access mutation rolls back',str_contains($source,'transRollback()'));
okB('Access mutation locks persisted target',str_contains($source, '->row($type, $id, true)'));
okB('Access mutation uses action-specific PBAC',str_contains($source,'->decideMutation('));
okB('Resource Lifecycle reassignment validates policies',str_contains($source,'->assertReassignmentSafe('));
okB('Group member replacement uses Shield tables',str_contains(file_get_contents(__DIR__.'/../app/Modules/Access/Mutation/AccessMutationRepository.php'),"['groups_users']"));
$controller=file_get_contents(__DIR__.'/../app/Modules/Access/Mutation/AccessMutationController.php');
okB('Write endpoints require explicit opt-in',str_contains($controller,"DINKERP_M33B_WRITE_MODE"));
okB('Write endpoints default closed',str_contains($controller,"'off'"));

echo 'TOTAL: '.$count.' M3.3B1 pure assertions passed.'.PHP_EOL;
