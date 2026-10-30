<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/routes/LegacyRouteSource.php';

/** CUS-IMP-07: shared Workflow adapters and bounded assignee candidate search. */
require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../../app/Modules/Access/ResourceStateCatalog.php';
require_once __DIR__ . '/../../app/Modules/Workflow/Execution/BusinessWorkflowRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/NotificationDeliveryRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/WorkflowAssigneeCandidateQuery.php';

use App\Exceptions\DomainException;
use App\Modules\Workflow\Execution\BusinessWorkflowRules as Workflow;
use App\Modules\Notifications\WorkflowAssigneeCandidateQuery as Query;

$count = 0;
$ok = static function (bool $result, string $label) use (&$count): void {
    if (!$result) throw new RuntimeException('FAILED: ' . $label);
    ++$count;
};
$denied = static function (array $input) use ($ok): void {
    try { Query::parse($input); }
    catch (DomainException $e) {
        $ok($e->getErrorCode() === 'NOTIFICATION_INVALID_REQUEST', 'Invalid query rejected');
        return;
    }
    throw new RuntimeException('INVALID_QUERY_ACCEPTED: ' . json_encode($input));
};

foreach (['province'=>'provinces','organization_contact'=>'contacts','employee_contact'=>'contacts',
    'customer_contact'=>'contacts','customer'=>'customers'] as $resource=>$table) {
    $ok(Workflow::table($resource) === $table, 'Fixed table: '.$resource);
}
foreach (['organization_contact'=>'ORGANIZATION','employee_contact'=>'EMPLOYEE','customer_contact'=>'CUSTOMER'] as $resource=>$owner) {
    $ok(Workflow::matchesTargetOwner($resource, ['contact_type'=>$owner]), 'Matching owner: '.$resource);
    $ok(!Workflow::matchesTargetOwner($resource, ['contact_type'=>'WRONG']), 'Wrong owner denied: '.$resource);
    $ok(!Workflow::matchesTargetOwner($resource, []), 'Missing owner denied: '.$resource);
}
$ok(Workflow::matchesTargetOwner('customer', []), 'Non-polymorphic Customer allowed');
$ok(Workflow::transitionForAction([['action_code'=>'setstate','destination'=>'ACTIVE']], 'setstate')['destination'] === 'ACTIVE',
    'Transition destination always comes from configured server row');
$ok(Query::parse([]) === ['q'=>'','page'=>1,'page_size'=>25,'ids'=>[]], 'Defaults');
$ok(Query::parse(['q'=>'  quYEt  ','page'=>'2','page_size'=>'50']) === ['q'=>'quYEt','page'=>2,'page_size'=>50,'ids'=>[]], 'Bounded search');
$ok(Query::parse(['ids'=>'1,123,4294967295'])['ids'] === ['1','123','4294967295'], 'Existing selected IDs');
$ok(count(Query::parse(['ids'=>implode(',', range(1,25))])['ids']) === 25, '25 IDs maximum');
foreach ([
    ['sort'=>'id'], ['q'=>[]], ['q'=>str_repeat('x', 101)], ['ids'=>'1,1'],
    ['ids'=>'1,0'], ['ids'=>'01'], ['ids'=>'4294967296'], ['ids'=>'1;DROP'],
    ['ids'=>implode(',', range(1,26))], ['ids'=>'1','q'=>'quyet'],
    ['page'=>'0'], ['page'=>'-1'], ['page'=>'10001'], ['page'=>[]],
    ['page_size'=>'0'], ['page_size'=>'51'], ['page_size'=>[]], ['ids'=>[]],
] as $case) { $denied($case); }

// Fail-closed source contracts: owner check in both mutation paths and event delivery.
$source = static fn(string $path): string => file_get_contents(__DIR__ . '/../../app/' . $path) ?: '';
foreach ([
    'Modules/Workflow/Execution/BusinessWorkflowService.php',
    'Modules/Notifications/WorkflowNotificationAssigneesService.php',
    'Modules/Notifications/Events/WorkflowActionRequiredHandler.php',
] as $file) {
    $ok(str_contains($source($file), 'BusinessWorkflowRules::matchesTargetOwner'), 'Owner checked: ' . $file);
}
$ok(str_contains($source('Modules/Workflow/Execution/BusinessWorkflowService.php'), "canView('customer'"), 'Workflow checks parent Customer VIEW');
$ok(str_contains($source('Modules/Workflow/Execution/BusinessWorkflowService.php'), '->changeCustomerState(')
    && str_contains($source('Shared/Workflow/ProductionWorkflowTransitionPort.php'), 'new CustomerService($actor, $db)')
    && str_contains($source('Shared/Workflow/ProductionWorkflowTransitionPort.php'), '->setState($id,'),
    'Customer Workflow delegates to canonical CustomerService state mutation via application port');
$ok(str_contains($source('Modules/Customer/CustomerService.php'), 'NotificationOutboxProducer::enqueueWorkflowTransition'),
    'Canonical Customer state mutation optionally enqueues M11 event atomically');
$ok(str_contains($source('Modules/Notifications/WorkflowNotificationAssigneesService.php'), "canView('customer'"), 'Assignee API checks parent Customer VIEW');
$ok(str_contains($source('Modules/Notifications/WorkflowNotificationAssigneesService.php'), '$this->authorize($resource, $row)'), 'Candidate lookup checks target VIEW and SETSTATE');
$ok(str_contains($source('Modules/Notifications/WorkflowNotificationAssigneesService.php'), '->limit($params['), 'Candidate query paginates server-side');
$ok(str_contains($source('Modules/Notifications/WorkflowNotificationAssigneesService.php'), '->where(\'deleted_at\', null)'), 'Candidate query filters deleted users');
$ok(str_contains($source('Modules/Notifications/WorkflowNotificationAssigneesService.php'), '->where(\'active\', 1)'), 'Candidate query filters inactive users');
$ok(str_contains($source('Modules/Notifications/WorkflowNotificationAssigneesService.php'), '(clone $builder)->countAllResults()'), 'Candidate count scoped server-side');
$ok(str_contains(\DinkERP\Tools\Routes\LegacyRouteSource::source(dirname(__DIR__, 3), 'WorkflowAssigneeRoutes.php'), 'assignee-candidates'), 'Exact route registered');

echo 'CUS WORKFLOW/ASSIGNEES PURE: ' . $count . '/' . $count . " PASS\n";
