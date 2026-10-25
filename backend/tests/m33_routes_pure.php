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
    require_once dirname(__DIR__) . '/tools/m5/M5SourceAcceptance.php';
    require_once dirname(__DIR__) . '/tools/m5/M52SourceAcceptance.php';
    require_once dirname(__DIR__) . '/tools/m5/M53SourceAcceptance.php';
    require_once dirname(__DIR__) . '/tools/m5/M54SourceAcceptance.php';
    require_once dirname(__DIR__) . '/tools/m5/M55SourceAcceptance.php';
    require_once dirname(__DIR__) . '/tools/m6/M61SourceAcceptance.php';
    require_once dirname(__DIR__) . '/tools/m6/M62ASourceAcceptance.php';
    require_once dirname(__DIR__) . '/tools/m6/M62BSourceAcceptance.php';
    require_once dirname(__DIR__) . '/tools/m6/M63SourceAcceptance.php';
    require_once dirname(__DIR__) . '/tools/m6/M63BSourceAcceptance.php';
    require_once dirname(__DIR__) . '/tools/m6/M64ASourceAcceptance.php';
    require_once dirname(__DIR__) . '/tools/m7/M71SourceAcceptance.php';
    require_once dirname(__DIR__) . '/tools/m7/M72SourceAcceptance.php';
    require_once dirname(__DIR__) . '/tools/m7/M73SourceAcceptance.php';
    require_once dirname(__DIR__) . '/tools/m7/M74SourceAcceptance.php';
    require_once dirname(__DIR__) . '/tools/m8/M8SourceAcceptance.php';
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
    // M7.1 pins 134 routes. First replay its precise two-route footprint,
    // then independently replay the unchanged 132/129/... historical gates.
    // Validate all four frozen Import routes and replay the complete M7.4
    // footprint after subtracting exactly these four operations.
    M8SourceAcceptance::validateRegistered($routes->registered);
    $m74Projection = $routes->registered;
    foreach (M8SourceAcceptance::operations() as $newKey => $_operation) {
        [$verb,$path] = explode(' ', $newKey, 2);
        unset($m74Projection[$verb]['api/v1/'.$path]);
    }
    M74SourceAcceptance::validateRegistered($m74Projection);
    $m73Projection = $m74Projection;
    foreach (M74SourceAcceptance::operations() as $newKey => $_operation) {
        [$verb, $path] = explode(' ', $newKey, 2);
        unset($m73Projection[$verb]['api/v1/'.$path]);
    }
    M73SourceAcceptance::validateRegistered($m73Projection);
    $m72Projection = $m73Projection;
    foreach (M73SourceAcceptance::operations() as $pricingKey => $_operation) {
        [$verb, $path] = explode(' ', $pricingKey, 2);
        unset($m72Projection[$verb]['api/v1/'.$path]);
    }
    M72SourceAcceptance::validateRegistered($m72Projection);
    $m71Projection = $m72Projection;
    foreach (M72SourceAcceptance::operations() as $comboKey => $_operation) {
        [$verb,$path] = explode(' ', $comboKey, 2);
        unset($m71Projection[$verb]['api/v1/'.$path]);
    }
    M71SourceAcceptance::validateRegistered($m71Projection);
    $m64aProjection = $m71Projection;
    foreach (M71SourceAcceptance::operations() as $conversionKey => $_operation) {
        [$verb,$path] = explode(' ', $conversionKey, 2);
        unset($m64aProjection[$verb]['api/v1/'.$path]);
    }
    M64ASourceAcceptance::validateRegistered($m64aProjection);
    $m63bProjection = $m64aProjection;
    foreach (M64ASourceAcceptance::operations() as $lookupKey => $_operation) {
        [$verb,$path] = explode(' ', $lookupKey, 2);
        unset($m63bProjection[$verb]['api/v1/'.$path]);
    }
    M63BSourceAcceptance::validateRegistered($m63bProjection);
    $m63Projection = M63BSourceAcceptance::projectedPrevious($m63bProjection);
    // Check 127 exact routes then peel away two M6.3A media mutations and
    // three M6.2B writes, preserving all prior 125/122/120/... footprints.
    M63SourceAcceptance::validateRegistered($m63Projection);
    $m62bProjection = M63SourceAcceptance::projectedPrevious($m63Projection);
    M62BSourceAcceptance::validateRegistered($m62bProjection);
    $m62aProjection = M62BSourceAcceptance::projectedPrevious($m62bProjection);
    // Check 122 routes, then subtract exactly the two M6.2A reads to
    // re-prove all earlier pinned 120/110/105/.../70-route footprints.
    M62ASourceAcceptance::validateRegistered($m62aProjection);
    $m61Projection = $m62aProjection;
    foreach (M62ASourceAcceptance::operations() as $m62Key => $_item) {
        [$verb,$path] = explode(' ', $m62Key, 2);
        unset($m61Projection[$verb]['api/v1/'.$path]);
    }
    M61SourceAcceptance::validateRegistered($m61Projection);
    $m55Projection = $m61Projection;
    foreach (M61SourceAcceptance::operations() as $m61Key => $_item) {
        [$verb,$path] = explode(' ', $m61Key, 2);
        unset($m55Projection[$verb]['api/v1/'.$path]);
    }
    M55SourceAcceptance::validateRegistered($m55Projection);
    $m54Projection = $m55Projection;
    foreach (M55SourceAcceptance::operations() as $m55Key => $_item) {
        [$verb,$path] = explode(' ', $m55Key, 2);
        unset($m54Projection[$verb]['api/v1/'.$path]);
    }
    M54SourceAcceptance::validateRegistered($m54Projection);
    $m53Projection = $m54Projection;
    foreach (M54SourceAcceptance::operations() as $m54Key => $_item) {
        [$verb,$path] = explode(' ', $m54Key, 2);
        unset($m53Projection[$verb]['api/v1/'.$path]);
    }
    M53SourceAcceptance::validateRegistered($m53Projection);
    $m52Projection = $m53Projection;
    foreach (M53SourceAcceptance::operations() as $m53Key => $_item) {
        [$verb,$path] = explode(' ', $m53Key, 2);
        unset($m52Projection[$verb]['api/v1/'.$path]);
    }
    M52SourceAcceptance::validateRegistered($m52Projection);
    $m5Projection = $m52Projection;
    foreach (M52SourceAcceptance::operations() as $m52Key => $_item) {
        [$m52Verb, $m52Path] = explode(' ', $m52Key, 2);
        unset($m5Projection[$m52Verb]['api/v1/' . $m52Path]);
    }
    M5SourceAcceptance::validateRegistered($m5Projection);
    $m4Projection = $m5Projection;
    foreach (M5SourceAcceptance::operations() as $m5Key => $_item) {
        [$m5Verb, $m5Path] = explode(' ', $m5Key, 2);
        unset($m4Projection[$m5Verb]['api/v1/' . $m5Path]);
    }
    M4SourceAcceptance::validateRegistered($m4Projection);
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
    if ($count !== 150 || count($expected) !== 19 || count($mutations) !== 16 || count($userMutations) !== 5 || count($workflowMutations) !== 8) {
        throw new \RuntimeException('Unexpected registered operation count: ' . $count . ' (expected 70 M3 + 14 M4 + 6 M5.1 + 3 M5.2 + 6 M5.3 + 6 M5.4 + 5 M5.5 + 10 M6.1 + 2 M6.2A + 3 M6.2B + 2 M6.3A + 2 M6.3B + 3 M6.4A + 2 M7.1 + 2 M7.2 + 7 M7.3 + 3 M7.4 + 4 M8).');
    }
    echo 'PASS: 70 pinned M3.4D + 14 M4 + 6 M5.1 + 3 M5.2  + 6 M5.3 + 6 M5.4 + 5 M5.5 + 10 M6.1 + 2 M6.2A + 3 M6.2B + 2 M6.3A + 2 M6.3B + 3 M6.4A + 2 M7.1 + 2 M7.2 + 7 M7.3 + 3 M7.4 + 4 M8 = 150 approved routes' . PHP_EOL;
    echo 'TOTAL: ' . (++$passed) . ' M3.3 route assertions passed.' . PHP_EOL;
}
