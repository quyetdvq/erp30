<?php

declare(strict_types=1);

namespace CodeIgniter\Router {
    final class RouteCollection
    {
        /** @var array<string,array<string,array|string>> */
        public array $registered = [];
        private string $prefix = '';

        public function setAutoRoute(bool $enabled): void
        {
            if ($enabled) { throw new \RuntimeException('CI4 auto routing enabled.'); }
        }
        public function group(string $prefix, array $options, callable $callback): void
        {
            $old = $this->prefix;
            $this->prefix = trim($old . '/' . trim($prefix, '/'), '/');
            $callback($this);
            $this->prefix = $old;
        }
        public function get(string $route, array|string $handler): void { $this->capture('GET', $route, $handler); }
        public function post(string $route, array|string $handler): void { $this->capture('POST', $route, $handler); }
        public function patch(string $route, array|string $handler): void { $this->capture('PATCH', $route, $handler); }
        public function put(string $route, array|string $handler): void { $this->capture('PUT', $route, $handler); }
        public function delete(string $route, array|string $handler): void { $this->capture('DELETE', $route, $handler); }
        public function options(string $route, array|string $handler): void { $this->capture('OPTIONS', $route, $handler); }
        private function capture(string $method, string $route, array|string $handler): void
        {
            $key = trim($this->prefix . '/' . trim($route, '/'), '/');
            if (isset($this->registered[$method][$key])) {
                throw new \RuntimeException('Duplicate ' . $method . ' ' . $key);
            }
            $this->registered[$method][$key] = $handler;
        }
    }
}

namespace {
    require_once dirname(__DIR__) . '/tools/m4/M4SourceAcceptance.php';
    $routes = new \CodeIgniter\Router\RouteCollection();
    require dirname(__DIR__) . '/app/Config/Routes.php';
    $controller = 'App\\Modules\\Access\\Management\\AccessReadController';
    $identityController = 'App\\Modules\\Identity\\Management\\IdentityReadController';
    $workflowReadController = 'App\\Modules\\Workflow\\Management\\WorkflowReadController';
    $catalog = [
        'authorization-groups' => ['AuthorizationGroups','AuthorizationGroup'],
        'resources' => ['Resources','Resource'],
        'policies' => ['Policies','Policy'],
        'authorization-logs' => ['AuthorizationLogs','AuthorizationLog'],
        'workflow-states' => ['WorkflowStates','WorkflowState'],
        'lifecycles' => ['Lifecycles','Lifecycle'],
    ];
    $expected = ['security/users' => 'securityUsers', 'users/(:num)/permissions' => 'userPermissions'];
    foreach ($catalog as $path => [$plural, $singular]) {
        $expected[$path] = 'list' . $plural;
        $expected[$path . '/(:num)'] = 'show' . $singular;
    }
    $expected += [
        'authorization-groups/(:num)/members' => 'groupMembers',
        'authorization-groups/(:num)/permissions' => 'groupPermissions',
        'resources/(:num)/actions' => 'resourceActions',
        'lifecycles/(:num)/states' => 'lifecycleStates',
        'lifecycles/(:num)/transitions' => 'lifecycleTransitions',
    ];
    $source = file_get_contents(dirname(__DIR__) . '/app/Modules/Access/Management/AccessReadController.php');
    $identitySource = file_get_contents(dirname(__DIR__) . '/app/Modules/Identity/Management/IdentityReadController.php');
    $workflowReadSource = file_get_contents(dirname(__DIR__) . '/app/Modules/Workflow/Management/WorkflowReadController.php');
    if (! is_string($source) || ! is_string($identitySource) || ! is_string($workflowReadSource)) {
        throw new \RuntimeException('Missing owner-module Read Controller');
    }
    $passed = 0;
    foreach ($expected as $path => $method) {
        $actual = $routes->registered['GET']['api/v1/' . $path] ?? null;
        $owner = str_starts_with($path, 'workflow-states') || str_starts_with($path, 'lifecycles')
            ? $workflowReadController
            : (str_starts_with($path, 'security/users') || str_starts_with($path, 'users/')
                ? $identityController : $controller);
        $ownerSource = $owner === $workflowReadController ? $workflowReadSource
            : ($owner === $identityController ? $identitySource : $source);
        if ($actual !== [$owner, $method]) {
            throw new \RuntimeException('Incorrect module owner for GET ' . $path . ': ' . json_encode($actual));
        }
        if (preg_match('/public function ' . preg_quote($method, '/') . '\(/', $ownerSource) !== 1) {
            throw new \RuntimeException('Unimplemented CI4 method ' . $method);
        }
        echo 'PASS: GET /api/v1/' . $path . ' => ' . $method . PHP_EOL;
        $passed++;
    }
    // M3.3A's nineteen read operations must remain fixed after M3.3B.
    // The new write operations must be on an explicitly reviewed allowlist.
    $mutations = [
        'POST authorization-groups' => 'createAuthorizationGroup',
        'PATCH authorization-groups/(:num)' => 'updateAuthorizationGroup',
        'POST authorization-groups/(:num)/actions/set-state' => 'setAuthorizationGroupState',
        'PUT authorization-groups/(:num)/members' => 'replaceAuthorizationGroupMembers',
        'PUT authorization-groups/(:num)/permissions' => 'replaceAuthorizationGroupPermissions',
        'POST resources' => 'createResource',
        'PATCH resources/(:num)' => 'updateResource',
        'POST resources/(:num)/actions/set-state' => 'setResourceState',
        'DELETE resources/(:num)' => 'deleteResource',
        'POST resources/(:num)/actions' => 'createResourceAction',
        'PATCH resources/(:num)/actions/(:num)' => 'updateResourceAction',
        'DELETE resources/(:num)/actions/(:num)' => 'deleteResourceAction',
        'POST policies' => 'createPolicy',
        'PATCH policies/(:num)' => 'updatePolicy',
        'POST policies/(:num)/actions/set-state' => 'setPolicyState',
        'DELETE policies/(:num)' => 'deletePolicy',
    ];
    $userMutations = [
        'POST users' => 'createUser',
        'PATCH users/(:num)' => 'updateUser',
        'POST users/(:num)/actions/set-state' => 'setUserState',
        'POST users/(:num)/actions/reset-password' => 'resetUserPassword',
        'PUT users/(:num)/permissions' => 'replaceUserPermissions',
    ];
    $workflowMutations = [
        'POST workflow-states' => 'createWorkflowState',
        'PATCH workflow-states/(:num)' => 'updateWorkflowState',
        'POST workflow-states/(:num)/actions/set-state' => 'setWorkflowStateState',
        'POST lifecycles' => 'createLifecycle',
        'PATCH lifecycles/(:num)' => 'updateLifecycle',
        'POST lifecycles/(:num)/actions/set-state' => 'setLifecycleState',
        'PUT lifecycles/(:num)/states' => 'configureLifecycleStates',
        'PUT lifecycles/(:num)/transitions' => 'configureLifecycleTransitions',
    ];
    $writeController = 'App\\Modules\\Access\\Mutation\\AccessMutationController';
    $userController = 'App\\Modules\\Identity\\Mutation\\SecurityUserMutationController';
    $workflowController = 'App\\Modules\\Workflow\\Mutation\\WorkflowMutationController';
    $workflowSource = file_get_contents(dirname(__DIR__) . '/app/Modules/Workflow/Mutation/WorkflowMutationController.php');
    if (! is_string($workflowSource)) { throw new \RuntimeException('Missing Workflow Mutation Controller'); }
    foreach ($workflowMutations as $key => $methodName) {
        [$verb, $path] = explode(' ', $key, 2);
        $actual = $routes->registered[$verb]['api/v1/' . $path] ?? null;
        if ($actual !== [$workflowController, $methodName]
            || ! str_contains($workflowSource, 'public function ' . $methodName . '(')) {
            throw new \RuntimeException('Unreviewed Workflow handler ' . $key);
        }
    }
    $userSource = file_get_contents(dirname(__DIR__) . '/app/Modules/Identity/Mutation/SecurityUserMutationController.php');
    if (! is_string($userSource)) { throw new \RuntimeException('Missing Shield User Mutation Controller'); }
    foreach ($userMutations as $key => $methodName) {
        [$verb, $path] = explode(' ', $key, 2);
        $actual = $routes->registered[$verb]['api/v1/' . $path] ?? null;
        if ($actual !== [$userController, $methodName]
            || ! str_contains($userSource, 'public function ' . $methodName . '(')) {
            throw new \RuntimeException('Unreviewed Shield User handler ' . $key);
        }
    }

    $mutSource = file_get_contents(dirname(__DIR__) . '/app/Modules/Access/Mutation/AccessMutationController.php');
    if (! is_string($mutSource)) { throw new \RuntimeException('Missing Access Mutation Controller'); }
    foreach ($mutations as $key => $methodName) {
        [$verb, $path] = explode(' ', $key, 2);
        $actual = $routes->registered[$verb]['api/v1/' . $path] ?? null;
        if ($actual !== [$writeController, $methodName]) {
            throw new \RuntimeException('Unexpected write route ' . $key . ': ' . json_encode($actual));
        }
        if (! str_contains($mutSource, 'public function ' . $methodName . '(')) {
            throw new \RuntimeException('Missing handler ' . $methodName);
        }
    }
    $m4Expected = M4SourceAcceptance::expected();
    M4SourceAcceptance::validateRegistered($routes->registered);
    M4SourceAcceptance::verify(dirname(__DIR__, 2));
    foreach ($routes->registered as $verb => $entries) {
        foreach ($entries as $path => $handler) {
            if (! in_array($verb, ['GET','OPTIONS'], true) &&
                preg_match('~\\Aapi/v1/(?:resources|policies|authorization-groups|authorization-logs|workflow-states|lifecycles|security/users|users/\\(\\:num\\)/permissions)(?:/|\\z)~', $path)) {
                $short = $verb . ' ' . substr($path, strlen('api/v1/'));
                $accessApproved = isset($mutations[$short]) && $handler === [$writeController, $mutations[$short]];
                $userApproved = isset($userMutations[$short]) && $handler === [$userController, $userMutations[$short]];
                $workflowApproved = isset($workflowMutations[$short]) && $handler === [$workflowController, $workflowMutations[$short]];
                $m4Approved = isset($m4Expected[$short])
                    && $handler === [$m4Expected[$short][0], $m4Expected[$short][1]];
                if (! $accessApproved && ! $userApproved && ! $workflowApproved && ! $m4Approved) {
                    throw new \RuntimeException('Unreviewed security mutation: ' . $short);
                }
            }
        }
    }
    $count = 0;
    foreach ($routes->registered as $method => $entries) {
        if ($method !== 'OPTIONS') { $count += count($entries); }
    }
    if ($count !== 84 || count($expected) !== 19 || count($mutations) !== 16 || count($userMutations) !== 5 || count($workflowMutations) !== 8) {
        throw new \RuntimeException('Unexpected registered operation count: ' . $count . ' (expected 70 legacy + 14 pinned M4).');
    }
    echo 'PASS: 70 pinned M3.4D routes + 14 exact baseline M4 routes, 84 total' . PHP_EOL;
    echo 'TOTAL: ' . (++$passed) . ' M3.3 route assertions passed.' . PHP_EOL;
}
