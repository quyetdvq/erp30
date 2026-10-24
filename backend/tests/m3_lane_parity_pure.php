<?php

declare(strict_types=1);

// Deterministic policy matrix against the M2.1.1 reader whose Frontend
// behavior was accepted by the product owner. This compares lane outcomes,
// NOT database/actor equivalence against backend_basline.
require __DIR__ . '/../app/Shared/Read/PositiveId.php';
require __DIR__ . '/../app/Modules/Access/PolicyReadLanes.php';
require __DIR__ . '/../app/Modules/Access/PbacDecision.php';

use App\Modules\Access\PolicyReadLanes;
use App\Modules\Access\PbacDecision;

$types = ['USER','GROUP','ORGANIZATION','DEPARTMENT','POSITION'];
$refs = ['15','9','1','3','4'];
$effects = ['ALLOW','DENY','ABSOLUTE_DENY'];
$cases = 0;
for ($base = 0; $base < 81; $base++) {
    $value = $base;
    $rows = [];
    for ($i = 0; $i < 4; $i++) {
        $selector = $value % 3;
        $value = intdiv($value, 3);
        $type = $types[($base + $i) % 5];
        $ref = $refs[($base + $i) % 5];
        $rows[] = ['id'=>(string) ($i + 1), 'participant_type'=>$type,
            'participant_ref_id'=>$ref, 'effect'=>$effects[$selector]];
    }
    foreach ([[], [['organization_id'=>'2','department_id'=>'3','position_id'=>'4']],
        [['organization_id'=>'2','department_id'=>'3'],['organization_id'=>'2','position_id'=>'4']]] as $assignments) {
        foreach ([['1','2'], ['1'], ['1','2','55']] as $ancestors) {
            $old = PolicyReadLanes::allows($rows, $ancestors, '15', ['9'], $assignments);
            $new = PbacDecision::evaluate($rows, $ancestors, '15', ['9'], $assignments)['allowed'];
            if ($old !== $new) {
                fwrite(STDERR, "FAIL: PBAC lane decision differs on scenario {$cases}.\n");
                exit(1);
            }
            $cases++;
        }
    }
}
echo "PASS: {$cases}/{$cases} deterministic M3 PBAC lane decisions agree with M2.1.1 PolicyReadLanes.\n";
