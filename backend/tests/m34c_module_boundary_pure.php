<?php

declare(strict_types=1);

/** M3.4C fail-closed module ownership plus pinned M3.4B.5 HTTP footprint. */
$root = dirname(__DIR__) . '/app/';
$count = 0;
$pass = static function (string $label, bool $ok) use (&$count): void {
    if (! $ok) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    ++$count;
    echo 'PASS: ' . $label . PHP_EOL;
};
$read = static function (string $path) use ($root): string {
    $source = file_get_contents($root . $path);
    if (! is_string($source)) {
        throw new RuntimeException('Missing module class: ' . $path);
    }
    return $source;
};
require_once __DIR__ . '/../tools/routes/LegacyRouteSource.php';
$routes = \DinkERP\Tools\Routes\LegacyRouteSource::core70(dirname(__DIR__, 2));
$matched = preg_match_all("/\\\$routes->(get|post|patch|put|delete)\\('([^']+)', \\[[^,]+, '([^']+)'\\]\\);/", $routes, $matches, PREG_SET_ORDER);
$pass('All reviewed API routes parse statically', $matched === 70);
$footprint = [];
foreach ($matches as $row) {
    $footprint[] = strtoupper($row[1]) . ' /api/v1/' . $row[2] . ' #' . $row[3];
}
sort($footprint, SORT_STRING);
$pass('HTTP verb/path/method immutable vs M3.4B.5', hash('sha256', implode("\n", $footprint))
    === '136b7c3c9263a4ceb3ee7c35d398c1d6cebf4e34ad0fecfc30de731339314315');
$pass('Autrouting still disabled', str_contains($routes, '$routes->setAutoRoute(false)'));

$owner = [
    'Identity' => [
        'Management/IdentityReadController.php',
        'Management/IdentityReadService.php',
        'Management/IdentityReadRepository.php',
        'Mutation/SecurityUserMutationController.php',
        'Mutation/SecurityUserMutationService.php',
        'Mutation/SecurityUserMutationRepository.php',
        'Mutation/SecurityUserMutationRules.php',
    ],
    'Workflow' => [
        'Management/WorkflowReadContract.php',
        'Management/WorkflowReadController.php',
        'Management/WorkflowReadService.php',
        'Management/WorkflowReadRepository.php',
        'Mutation/WorkflowMutationController.php',
        'Mutation/WorkflowMutationService.php',
        'Mutation/WorkflowMutationRepository.php',
        'Mutation/WorkflowMutationRules.php',
    ],
];
foreach ($owner as $module => $paths) {
    foreach ($paths as $path) {
        $source = $read('Modules/' . $module . '/' . $path);
        $pass($module . ' owns ' . basename($path), str_contains($source, 'namespace App\\Modules\\' . $module . '\\')
            && ! str_contains($source, 'class_alias('));
    }
}
foreach (['SecurityUserMutationController','SecurityUserMutationService','SecurityUserMutationRepository','SecurityUserMutationRules',
          'WorkflowMutationController','WorkflowMutationService','WorkflowMutationRepository','WorkflowMutationRules'] as $class) {
    $pass('No obsolete Access implementation for ' . $class,
        ! is_file($root . 'Modules/Access/Mutation/' . $class . '.php'));
}
$accessRoot = $root . 'Modules/Access';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($accessRoot, FilesystemIterator::SKIP_DOTS));
$accessSource = '';
foreach ($iterator as $file) {
    if ($file->getExtension() === 'php') {
        $accessSource .= (string) file_get_contents($file->getPathname()) . "\n";
    }
}
$pass('Access depends on neither Identity nor Workflow implementation',
    ! str_contains($accessSource, 'App\\Modules\\Identity\\')
    && ! str_contains($accessSource, 'App\\Modules\\Workflow\\'));
$pass('No Identity or Workflow CRUD remains in Access',
    ! preg_match('/\\b(class (?:SecurityUserMutation|WorkflowMutation)|function (?:securityUsers|userPermissions|listWorkflowStates|showWorkflowState|listLifecycles|showLifecycle|lifecycleStates|lifecycleTransitions))\\b/', $accessSource));
$pass('Identity GET routes owned by Identity',
    str_contains($routes, "[IdentityReadController::class, 'securityUsers']")
    && str_contains($routes, "[IdentityReadController::class, 'userPermissions']"));
$pass('Workflow GET routes owned by Workflow',
    str_contains($routes, "[WorkflowReadController::class, 'listWorkflowStates']")
    && str_contains($routes, "[WorkflowReadController::class, 'lifecycleTransitions']"));
$pass('Five User mutation routes owned by Identity',
    substr_count($routes, '[SecurityUserMutationController::class,') === 5
    && str_contains($routes, 'use App\\Modules\\Identity\\Mutation\\SecurityUserMutationController;'));
$pass('Eight Workflow mutation routes owned by Workflow',
    substr_count($routes, '[WorkflowMutationController::class,') === 8
    && str_contains($routes, 'use App\\Modules\\Workflow\\Mutation\\WorkflowMutationController;'));
$pass('Access owns just four Access catalog tables',
    str_contains($read('Modules/Access/Management/AccessReadContract.php'), "'authorization_group' => 'groups'")
    && ! str_contains($read('Modules/Access/Management/AccessReadContract.php'), "'lifecycle' =>"));
$pass('Workflow owns exactly its two master catalogs',
    str_contains($read('Modules/Workflow/Management/WorkflowReadContract.php'), "'lifecycle' => 'lifecycles'")
    && str_contains($read('Modules/Workflow/Management/WorkflowReadContract.php'), "'workflow_state' => 'workflow_states'"));
$pass('Identity read uses central PBAC, not hand-rolled permissions',
    str_contains($read('Modules/Identity/Management/IdentityReadService.php'), "canView('user', "));
$pass('Workflow read uses central persisted PBAC',
    str_contains($read('Modules/Workflow/Management/WorkflowReadService.php'), 'canView($type, $row)'));
$pass('Workflow owns independent native State normalization',
    ! str_contains($read('Modules/Workflow/Mutation/WorkflowMutationRules.php'), 'MutationRules::status')
    && ! str_contains($read('Modules/Workflow/Mutation/WorkflowMutationService.php'), 'AccessReadService'));
$pass('User write retains Shield model and centralized mutation PBAC',
    str_contains($read('Modules/Identity/Mutation/SecurityUserMutationService.php'), 'new UnifiedAccessService(')
    && str_contains($read('Modules/Identity/Mutation/SecurityUserMutationRepository.php'), 'model(UserModel::class'));
$pass('Workflow write retains centralized mutation PBAC and SQL rollback',
    str_contains($read('Modules/Workflow/Mutation/WorkflowMutationService.php'), 'new UnifiedAccessService(')
    && str_contains($read('Modules/Workflow/Mutation/WorkflowMutationService.php'), 'transRollback()'));
$pass('Default mutation gate unchanged in both owner controllers',
    str_contains($read('Modules/Identity/Mutation/SecurityUserMutationController.php'), "env('DINKERP_M33B_WRITE_MODE', 'off')")
    && str_contains($read('Modules/Workflow/Mutation/WorkflowMutationController.php'), "env('DINKERP_M33B_WRITE_MODE', 'off')"));
$pass('M33 verify still samples all six master catalogs plus Users',
    str_contains($read('Commands/M33Verify.php'), 'WorkflowReadContract::TABLES')
    && str_contains($read('Commands/M33Verify.php'), '$identity->securityUsers()'));
echo 'TOTAL: ' . $count . ' M3.4C architecture/route assertions passed.' . PHP_EOL;
