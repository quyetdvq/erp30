<?php

declare(strict_types=1);

/**
 * Compare M3.2 with v1.2.4 baseline decision-lane methods via reflection.
 * Use installed baseline if pinned source files are present; otherwise use
 * byte-for-byte test snapshots. Always print which source was loaded.
 * This verifies fixture lanes only, never live actor/DB/HTTP parity.
 */
require __DIR__ . '/../app/Shared/Read/PositiveId.php';
require __DIR__ . '/../app/Modules/Access/PbacDecision.php';

// M3.2.1 test-only source resolution. Do not touch production authorization.
// The two fixture sources were extracted byte-for-byte from erp_v1.2.4.zip.
// SHA-256 pins prevent silently passing against an altered decision engine.
$expected = [
    'app/Modules/Authorization/Decision/Application/Ports/PbacDecisionPort.php'
        => '47bb0e46f096f083bf13362976b1616650abfc2682a825c7aad2042aeeafb1c7',
    'app/Modules/Authorization/Decision/Application/PbacDecisionEngine.php'
        => '178a6bc4cf22ef2d9a36c3c9eeb9957df3177b4061c27f3414af20f226fc03d5',
];
$baselineRoot = dirname(__DIR__, 2) . '/backend_basline';
$snapshotRoot = __DIR__ . '/fixtures/baseline_v1_2_4';
$requireInstalled = false;
$snapshotOnly = false;
$customRoot = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--require-installed') {
        $requireInstalled = true;
    } elseif ($arg === '--snapshot-only') {
        $snapshotOnly = true;
    } elseif (str_starts_with($arg, '--baseline-root=')) {
        $baselineRoot = substr($arg, strlen('--baseline-root='));
        $requireInstalled = true;
        $customRoot = true;
    } else {
        fwrite(STDERR, "ERROR: unknown argument {$arg}\n");
        exit(2);
    }
}
if ($snapshotOnly && ($requireInstalled || $customRoot)) {
    fwrite(STDERR, "ERROR: --snapshot-only cannot be combined with installed-baseline options.\n");
    exit(2);
}

$missing = [];
if (! $snapshotOnly) {
    foreach (array_keys($expected) as $relative) {
        $candidate = rtrim($baselineRoot, '/\\') . '/' . $relative;
        if (! is_file($candidate)) {
            $missing[] = $relative;
            continue;
        }
        // Even when a partial baseline exists, never silently ignore drift.
        if (! is_readable($candidate) || hash_file('sha256', $candidate) !== $expected[$relative]) {
            fwrite(STDERR, "ERROR: installed baseline file differs from v1.2.4 or is unreadable: {$candidate}\n");
            exit(2);
        }
    }
}
if ($requireInstalled && $missing !== []) {
    fwrite(STDERR, "ERROR: installed backend_basline incomplete under {$baselineRoot}: " . implode(', ', $missing) . "\n");
    exit(2);
}
$usingSnapshot = $snapshotOnly || $missing !== [];
$sourceRoot = $usingSnapshot ? $snapshotRoot : $baselineRoot;
foreach ($expected as $relative => $hash) {
    $candidate = rtrim($sourceRoot, '/\\') . '/' . $relative;
    if (! is_file($candidate) || ! is_readable($candidate) || hash_file('sha256', $candidate) !== $hash) {
        fwrite(STDERR, "ERROR: pinned v1.2.4 baseline source missing or modified: {$candidate}\n");
        exit(2);
    }
}
if ($usingSnapshot) {
    if ($missing !== [] && ! $snapshotOnly) {
        echo 'NOTE: local backend_basline incomplete (missing: ' . implode(', ', $missing) . ").\n";
    }
    echo "SOURCE: SHA-256-verified v1.2.4 test snapshot; NOT the locally installed backend_basline.\n";
} else {
    echo "SOURCE: locally installed backend_basline; both source files match SHA-256 of v1.2.4.\n";
}

// The snapshot is test-only; no baseline PHP files are loaded by application code.
require $sourceRoot . '/app/Modules/Authorization/Decision/Application/Ports/PbacDecisionPort.php';
require $sourceRoot . '/app/Modules/Authorization/Decision/Application/PbacDecisionEngine.php';

use App\Modules\Access\PbacDecision;
use App\Modules\Authorization\Decision\Application\PbacDecisionEngine;

$ref = new ReflectionClass(PbacDecisionEngine::class);
$baseline = $ref->newInstanceWithoutConstructor();
$matcher = $ref->getMethod('matching');
$lane = $ref->getMethod('evaluateLane');
$uniq = $ref->getMethod('uniqueSorted');
$types = ['ORGANIZATION','USER','GROUP','DEPARTMENT','POSITION'];
$refs = [2,9,5,12,21];
$effects = [null, 'ALLOW','DENY','ABSOLUTE_DENY'];
$checked=0;
for ($mask=0; $mask<1024; $mask++) {
    $n=$mask;
    $policies=[];
    $newPolicies=[];
    for ($i=0; $i<count($types); $i++) {
        $effect=$effects[$n%4]; $n=intdiv($n,4);
        if ($effect===null) continue;
        $policyId=100+$i;
        $policies[]=['policy'=>['id'=>$policyId,'participant_type'=>$types[$i],
            'participant_ref_id'=>$refs[$i]],'effect'=>$effect];
        $newPolicies[]=['id'=>(string)$policyId,'participant_type'=>$types[$i],
            'participant_ref_id'=>(string)$refs[$i],'effect'=>$effect];
    }
    // An unmatched participant may never broaden or veto a decision.
    $policies[]=['policy'=>['id'=>900,'participant_type'=>'ORGANIZATION','participant_ref_id'=>888],
        'effect'=>'ABSOLUTE_DENY'];
    $newPolicies[]=['id'=>'900','participant_type'=>'ORGANIZATION','participant_ref_id'=>'888',
        'effect'=>'ABSOLUTE_DENY'];
    $common=$matcher->invoke($baseline,$policies,[['ORGANIZATION',[1,2,3]]]);
    $actor=$lane->invoke($baseline,array_merge($common, $matcher->invoke($baseline,$policies,[['USER',9],['GROUP',5]])));
    $dept=$lane->invoke($baseline,array_merge($common, $matcher->invoke($baseline,$policies,[['DEPARTMENT',12],['POSITION',21]])));
    $lanes=[$actor,$dept];
    $absolute=$uniq->invoke($baseline,array_merge($actor['absolute'],$dept['absolute']));
    $denied = $absolute!==[] || count(array_filter($lanes,static fn ($l)=>$l['denied']))===count($lanes);
    $decisive=$absolute!==[]?$absolute:($denied
        ? $uniq->invoke($baseline,array_merge($actor['decisive'],$dept['decisive']))
        : $uniq->invoke($baseline,array_merge(...array_map(
            static fn ($l)=>$l['denied']?[]:$l['decisive'],$lanes))));
    $matched=$uniq->invoke($baseline,array_merge($actor['matched'],$dept['matched']));
    $new=PbacDecision::evaluate($newPolicies,['1','2','3'],'9',['5'],
        [['organization_id'=>'2','department_id'=>'12','position_id'=>'21']]);
    if ($new['allowed'] === $denied
        || $new['matched_policy_ids'] !== array_map('strval',$matched)
        || $new['decisive_policy_ids'] !== array_map('strval',$decisive)) {
        fwrite(STDERR,"FAIL: backend_basline policy-lane mismatch case {$mask}\n");
        exit(1);
    }
    $checked++;
}
echo "PASS: {$checked}/{$checked} M3.2 policy-lane fixture cases agree with v1.2.4 PbacDecisionEngine methods ({$sourceRoot}).\n";
echo "NOTE: pure lane parity, not actor/scope/HTTP parity on live backend_basline.\n";
