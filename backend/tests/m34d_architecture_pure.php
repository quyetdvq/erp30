<?php

declare(strict_types=1);

require_once __DIR__.'/../tools/m34d/M34DAcceptance.php';
$root=dirname(__DIR__,2);
$n=0;
function checkDArch(string $description, bool $okay): void {
    global $n;
    if (!$okay) throw new RuntimeException('FAIL: '.$description);
    ++$n; echo 'PASS: '.$description.PHP_EOL;
}
function rejectsDArch(callable $action): bool {
    try { $action(); } catch (M34DViolation $e) { return true; }
    return false;
}
$gates=M34DAcceptance::source($root);
checkDArch('All six independent static acceptance gates PASS',
    count($gates)===6 && count(array_filter($gates,static fn($g)=>$g['status']==='PASS'))===6);
$graph=$gates['MODULE_GRAPH']['details']['dependency_graph'];
checkDArch('Access does not depend on Identity or Workflow',
    !in_array('Identity',$graph['Access'],true) && !in_array('Workflow',$graph['Access'],true));
checkDArch('Identity and Workflow only call central Access PBAC',
    in_array('Access',$graph['Identity'],true) && in_array('Access',$graph['Workflow'],true));
checkDArch('Identity does not depend on Workflow',!in_array('Workflow',$graph['Identity'],true));
checkDArch('Workflow does not depend on Identity',!in_array('Identity',$graph['Workflow'],true));
checkDArch('Access contains 24 runtime PHP files', $gates['MODULE_GRAPH']['details']['module_files']['Access']===24);
checkDArch('Identity owns 13 runtime PHP files', $gates['MODULE_GRAPH']['details']['module_files']['Identity']===13);
checkDArch('Workflow owns its 8 master/management files plus 3 M4 execution files', $gates['MODULE_GRAPH']['details']['module_files']['Workflow']===11);
checkDArch('No cycle in real module dependency graph',
    (M34DAcceptance::assertAcyclic($graph)===null));
checkDArch('Synthetic mutual cycle rejected', rejectsDArch(fn()=>M34DAcceptance::assertAcyclic(['Access'=>['Identity'],'Identity'=>['Access']])));
checkDArch('Synthetic longer cycle rejected', rejectsDArch(fn()=>M34DAcceptance::assertAcyclic(['Access'=>['Organization'],'Organization'=>['Workflow'],'Workflow'=>['Access']])));
checkDArch('Unknown imported module rejected', rejectsDArch(fn()=>M34DAcceptance::assertAcyclic(['Access'=>['NotInstalled']])));
$code = <<<'CODE'
<?php
namespace App\Modules\Access;
use App\Modules\Organization\OrganizationReadRepository;
/* use App\Modules\Identity\BadDependency; */
$txt='App\\Modules\\Workflow\\BadDependency';
CODE;
$ref=M34DAcceptance::moduleReferences($code);
checkDArch('Real FQCN import detected', in_array('Organization',$ref,true));
checkDArch('Commented/quoted module reference ignored',
    !in_array('Identity',$ref,true) && !in_array('Workflow',$ref,true));
checkDArch('Eight native Resource types remain independent', $gates['PBAC_STATE']['details']['native']===8);
checkDArch('Exactly two All States resource types remain independent', $gates['PBAC_STATE']['details']['all_states']===2);
checkDArch('All 74 baseline locale files match SHA-256 pins', $gates['BASELINE_LOCALES']['details']['baseline_catalogs']===74);
checkDArch('Default write mode stays OFF on all three mutation controllers',
    $gates['HTTP_SECURITY']['status']==='PASS');
echo 'TOTAL: '.$n.' M3.4D architecture/security assertions passed.'.PHP_EOL;
