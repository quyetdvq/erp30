<?php

declare(strict_types=1);

// Reuse the SHA-256 pinned v1.2.4 OpenAPI operation manifest, and the entire
// M3.3A/B1/B2 route regression; never infer contracts from the frontend alone.
require __DIR__ . '/m33b_contract_pure.php';

$n = 0;
function checkUContract(string $label, bool $ok): void
{
    global $n;
    if (! $ok) { throw new RuntimeException('FAIL: ' . $label); }
    $n++;
    echo 'PASS: ' . $label . PHP_EOL;
}
$statuses = ['POST users'=>201,'PATCH users/(:num)'=>200,
    'POST users/(:num)/actions/set-state'=>200,
    'POST users/(:num)/actions/reset-password'=>200,
    'PUT users/(:num)/permissions'=>200];
foreach ($statuses as $route => $status) {
    [$method, $path] = explode(' ', $route, 2);
    $template = str_replace('(:num)', '{id}', '/api/v1/' . $path);
    $contractRow = $indexed[$method . ' ' . $template] ?? null;
    checkUContract('Baseline OpenAPI ' . $method . ' ' . $template . ' => ' . $status,
        is_array($contractRow) && in_array($status, $contractRow['success_statuses'] ?? [], true));
}
$base = dirname(__DIR__) . '/app/';
$controller = (string) file_get_contents($base . 'Modules/Identity/Mutation/SecurityUserMutationController.php');
$service = (string) file_get_contents($base . 'Modules/Identity/Mutation/SecurityUserMutationService.php');
$repository = (string) file_get_contents($base . 'Modules/Identity/Mutation/SecurityUserMutationRepository.php');
$guard = (string) file_get_contents($base . 'Modules/Access/UnifiedAccessService.php');
$checks = [
 'Only five User mutation routes' => count($userMutations) === 5,
 'Shield authentication required' => str_contains($controller, 'UNAUTHENTICATED'),
 'No temporary global write toggle' => !str_contains($controller, 'ACCESS_MUTATION_NOT_ENABLED'),
 'Auth before creating service' => strpos($controller, '! $actor instanceof User') < strpos($controller,'new SecurityUserMutationService($actor)'),
 'Controller never accepts file upload here' => !str_contains($controller, 'avatarFilename'),
 'Reset password uses distinct action' => str_contains($service, "'user', 'reset_password'"),
 'User edit uses exact edit action' => str_contains($service, "'user', 'edit'"),
 'User setstate uses exact setstate action' => str_contains($service, "'user', 'setstate'"),
 'Direct permissions uses assign_permission' => str_contains($service, "'user', 'assign_permission'"),
 'Group membership requires independent assign_member' => str_contains($service, "'authorization_group', 'assign_member'"),
 'Group member targets verified with VIEW' => str_contains($service, "canView('authorization_group', " . '$group)'),
 'Target visibility checked before mutations' => str_contains($service, "canView('user', " . '$row)'),
 'M3.3B write PBAC approves reset_password' => str_contains($guard, "'assign_permission', 'reset_password'"),
 'Model persist uses Shield' => str_contains($repository, 'model(UserModel::class'),
 'Shield password hashes through entity' => str_contains($service, '->setPassword($password)') && str_contains($service,'->setPassword($data[\'password\'])'),
 'No SQL sets password_hash' => !str_contains($repository, "'password_hash' =>"),
 'Private avatar filename never serialized' => !str_contains($repository, "'avatar_filename' =>"),
 'Full User create is transactional' => str_contains($service, '$this->transaction(function () use ($data): string'),
 'No implicit Shield default group' => str_contains($service, 'assertNoDefaultGroup($id)'),
 'All assigned groups locked' => str_contains($service, 'group($groupId, true)'),
 'Self-inactivation triggers logout' => str_contains($service, "auth()->logout()"),
 'Permission replacement checks live catalog' => str_contains($repository, '$this->permissionCatalog()'),
 'Permission writes in transaction' => str_contains($service, '$this->users->replaceDirectPermissions($id, $permissions)'),
 'CSRF remains required' => str_contains((string) file_get_contents($base.'Config/Filters.php'), 'csrf'),
];
foreach ($checks as $label => $ok) { checkUContract($label, $ok); }
echo 'TOTAL: ' . $n . ' M3.3B3 Shield User contract/guard assertions passed.' . PHP_EOL;
