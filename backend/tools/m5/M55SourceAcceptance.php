<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__ . '/M54SourceAcceptance.php';

/** Five Contact-owned API-010 routes. Source inventory only; no HTTP/SQL claim. */
final class M55SourceAcceptance
{
    /** @return array<string,array{id:string,path:string,status:int}> */
    public static function operations(): array
    {
        return [
            'GET contacts' => ['id' => 'listContacts', 'path' => '/api/v1/contacts', 'status' => 200],
            'POST contacts' => ['id' => 'createContact', 'path' => '/api/v1/contacts', 'status' => 201],
            'GET contacts/(:num)' => ['id' => 'getContact', 'path' => '/api/v1/contacts/{id}', 'status' => 200],
            'PATCH contacts/(:num)' => ['id' => 'updateContact', 'path' => '/api/v1/contacts/{id}', 'status' => 200],
            'POST contacts/(:num)/actions/set-state' => ['id' => 'setContactState', 'path' => '/api/v1/contacts/{id}/actions/set-state', 'status' => 200],
        ];
    }

    /** @param array<string,array<string,array|string>> $routes */
    public static function validateRegistered(array $routes): void
    {
        $handler = 'App\\Modules\\Contact\\ContactController';
        foreach (self::operations() as $key => $item) {
            [$method, $path] = explode(' ', $key, 2);
            if (($routes[$method]['api/v1/' . $path] ?? null) !== [$handler, $item['id']]) {
                throw new RuntimeException('M5.5 unreviewed or missing Contact route: ' . $key);
            }
        }
        $total = 0;
        foreach ($routes as $method => $paths) if ($method !== 'OPTIONS') $total += count($paths);
        if ($total !== 110) throw new RuntimeException('M5.5 expected 110 registered routes; found ' . $total);
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root): array
    {
        $prior = M54SourceAcceptance::verify($root);
        if ($prior['total'] !== 105) throw new RuntimeException('M5.5 requires 105-route M5.4 footprint.');
        $manifest = json_decode((string) file_get_contents($root . '/docs/backend-rewrite/openapi-operations-v1.2.4.json'),
            true, 512, JSON_THROW_ON_ERROR);
        $byKey = [];
        foreach ($manifest['operations'] as $op) $byKey[$op['method'] . ' ' . $op['path']] = $op;
        if (count($byKey) !== 150) throw new RuntimeException('Baseline OpenAPI operations changed.');
        foreach (self::operations() as $key => $item) {
            [$method] = explode(' ', $key, 2);
            $pin = $byKey[$method . ' ' . $item['path']] ?? null;
            if ($pin === null || ($pin['operation_id'] ?? '') !== $item['id']
                || ! in_array($item['status'], $pin['success_statuses'] ?? [], true)) {
                throw new RuntimeException('M5.5 operation is not pinned in v1.2.4: ' . $key);
            }
        }
        $routes = (string) file_get_contents($root . '/backend/app/Config/Routes.php');
        if ((\DinkERP\Tools\Routes\LegacyRouteSource::mounted($root, 'M55Routes.php') ? 1 : 0) !== 1
            || str_contains($routes, 'setAutoRoute(true)')) {
            throw new RuntimeException('M5.5 routes not registered exactly once.');
        }
        $definitions = (string) \DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M55Routes.php');
        $pattern = '~\$routes->(get|post|patch)\(\x27([^\x27]+)\x27,\s*\[\\\\App\\\\Modules\\\\Contact\\\\ContactController::class,\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\]\);~';
        preg_match_all($pattern, $definitions, $matches, PREG_SET_ORDER);
        if (count($matches) !== 5 || substr_count($definitions, '$routes->') !== 5) {
            throw new RuntimeException('Contact routes must have exactly five reviewed registrations.');
        }
        $controller = (string) file_get_contents($root . '/backend/app/Modules/Contact/ContactController.php');
        $seen = [];
        foreach ($matches as $entry) {
            $key = strtoupper($entry[1]) . ' ' . $entry[2];
            if (isset($seen[$key]) || (self::operations()[$key]['id'] ?? null) !== $entry[3]
                || ! str_contains($controller, 'function ' . $entry[3] . '(')) {
                throw new RuntimeException('M5.5 unexpected handler: ' . $key);
            }
            $seen[$key] = true;
        }
        $service = (string) file_get_contents($root . '/backend/app/Modules/Contact/ContactService.php');
        $scope = (string) file_get_contents($root . '/backend/app/Modules/Contact/ContactScope.php');
        foreach (['decideMutation(', 'decideTransitionAction(', 'canView(', 'FOR UPDATE',
            'transBegin()', 'transCommit()', 'transRollback()', 'assertVisible(',
            'WF_CONCURRENT_STATE_CHANGE', 'ContactRules::destination'] as $guard) {
            if (! str_contains($service, $guard)) throw new RuntimeException('M5.5 missing authorization/atomicity: ' . $guard);
        }
        if (! str_contains($scope, 'uniqueActivePrimary(')
            || ! str_contains($scope, 'AccessReadAdapters::people(')
            || ! str_contains($service, "'CONTACT_OWNER_NOT_FOUND'")) {
            throw new RuntimeException('M5.5 canonical owner facts/scope missing.');
        }
        if (! str_contains($controller, 'UNAUTHENTICATED')
            || ! str_contains($controller, "'UNAUTHENTICATED', 401")) {
            throw new RuntimeException('M5.5 mutations must be auth/write gated.');
        }
        if (! is_file($root . '/backend/app/Modules/Contact/MODULE.md')
            || is_dir($root . '/backend/app/Modules/ContactsManagement')) {
            throw new RuntimeException('M5.5 Contact module ownership missing or duplicated.');
        }
        return ['prior' => 105, 'added' => 5, 'total' => 110, 'baseline' => 150];
    }
}
