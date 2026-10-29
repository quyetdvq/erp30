<?php

declare(strict_types=1);

/**
 * M19.24 routing-only regression: exact baseline + security-negative mutations.
 * No Composer/bootstrap, database connection, HTTP request, or side effects.
 */
require_once __DIR__ . '/../tools/routes/RouteInventory.php';
require_once __DIR__ . '/../tools/routes/LegacyRouteSource.php';

use DinkERP\Tools\Routes\LegacyRouteSource;
use DinkERP\Tools\Routes\RouteInventory;

$root = dirname(__DIR__, 2);
$checks = 0;
$ok = static function (bool $condition, string $name) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException('M19.24 ROUTE ARCHITECTURE FAILED: ' . $name);
    }
    $checks++;
};
$reject = static function (callable $test, string $name) use ($ok): void {
    try {
        $test();
    } catch (RuntimeException) {
        $ok(true, $name);
        return;
    }
    $ok(false, $name . ' (was accepted)');
};

$dir = $root . '/backend/app/Config/Routes/ApiV1';
$source = (string) file_get_contents($root . '/backend/app/Config/Routes.php');
$domainNames = [
    'IdentityRoutes.php', 'AccessRoutes.php', 'SettingsRoutes.php',
    'NumberingRoutes.php', 'WorkflowRoutes.php', 'LocationsRoutes.php',
    'OrganizationsRoutes.php', 'PeopleRoutes.php', 'ContactsRoutes.php',
    'ProductsRoutes.php', 'PricingRoutes.php', 'ImportsRoutes.php',
    'CustomersRoutes.php', 'NotificationsRoutes.php',
];
$approved = RouteInventory::approvedExtensions(
    $root . '/backend/tests/fixtures/routes_extensions_approved.json');
$files = glob($dir . '/*Routes.php');
$ok(is_array($files) && count($files) === 14 + count($approved['modules']),
    '14 baseline module-owned source files plus approved modules');
foreach (array_merge($domainNames, $approved['modules']) as $name) {
    $ok(is_file($dir . '/' . $name), 'module entrypoint ' . $name);
    $ok(substr_count($source, "require __DIR__ . '/Routes/ApiV1/" . $name . "';") === 1,
        'exactly one include ' . $name);
}
$ok(substr_count($source, '$routes->group(') === 1, 'single inherited group');
$ok(str_contains($source, "group('api/v1', ['filter' => 'cors']"), 'original CORS filter');
$ok(str_contains($source, '$routes->setAutoRoute(false)'), 'AutoRoute explicitly disabled');
$ok(substr_count($source, '$routes->options(') === 2, 'two reviewed OPTIONS routes');
$ok(substr_count($source, '$routes->get(') === 1, 'only health GET in dispatcher');
$ok(! str_contains($source, 'auth()->routes') && ! str_contains($source, 'service(\'auth\')->routes'),
    'Shield routes not enabled');

foreach (['M4', 'M5', 'M52', 'M53', 'M54', 'M55', 'M61', 'M62A', 'M62B', 'M63',
    'M63B', 'M64A', 'M71', 'M72', 'M73', 'M74', 'M8', 'CUS', 'WorkflowAssignee', 'M11'] as $old) {
    $ok(! file_exists($root . '/backend/app/Config/' . $old . 'Routes.php'), 'old ' . $old . ' unmounted/removed');
}

$fixtureFiles = [
    'routes_v1923.json' => 'd167acf501e222d07ed58c624126a5476952c608133e037b7d433058c63670aa',
    'routes_v1923_frozen150.json' => '131c54bde1c4825ae9a585fa453b1c08180a6fd2692baa3f6245c1b1f8ea341a',
    'routes_v1923_legacy_groups.json' => '212c36ea7c8d83aa837d24e778d6455c4c157140f4481fa05ec2a2c6ce9f29b5',
    'routes_v1923_core70.json' => '513286331478015bdd36980b7c4ca67eb6583ba278f484cb1f973d74e9d75860',
];
foreach ($fixtureFiles as $file => $checksum) {
    $ok(hash_file('sha256', $root . '/backend/tests/fixtures/' . $file) === $checksum,
        'frozen route baseline evidence ' . $file);
}

$records = RouteInventory::read($root);
$baseline = $root . '/backend/tests/fixtures/routes_v1923.json';
RouteInventory::assertBaseline($records, $baseline);
$ok(count($records) === 180 + count($approved['routes']),
    '180/180 frozen method-path-handler-CORS plus approved extensions');
$ok(count(array_filter($records, static fn (array $entry): bool => $entry['method'] === 'OPTIONS')) === 2,
    'exactly two preflight methods');
$ok(count(array_filter($records, static fn (array $entry): bool => $entry['method'] !== 'OPTIONS')) === 178 + count($approved['routes']),
    '178 non-OPTIONS baseline operations (150 P1 + 28 P2/M11), plus approved extensions');
$ok(count(array_filter($records, static fn (array $entry): bool => $entry['options'] === ['filter' => 'cors'])) === 180 + count($approved['routes']),
    'all registrations retain inherited CORS options');
$ok(count(array_filter($records, static fn (array $entry): bool =>
    $entry['method'] === 'DELETE' && $entry['path'] === 'api/v1/notifications/push/subscriptions/(:num)'
    && $entry['handler'][1] === 'unsubscribe/$1')) === 1, 'special handler parameter unchanged');

$frozen = json_decode((string) file_get_contents(
    $root . '/backend/tests/fixtures/routes_v1923_frozen150.json'), true, 512, JSON_THROW_ON_ERROR);
$keys = array_fill_keys(array_map(static fn (array $entry): string =>
    $entry['method'] . ' ' . $entry['path'], $records), true);
// Validate handler executability without depending on Python or Composer.
$controllerSource = [];
foreach ($records as $entry) {
    $className = $entry['handler'][0];
    $methodName = explode('/', $entry['handler'][1], 2)[0];
    $file = $root . '/backend/app/' . str_replace('\\', '/', substr($className, 4)) . '.php';
    $ok(str_starts_with($className, 'App\\') && is_file($file),
        'registered controller exists: ' . $className);
    if (! isset($controllerSource[$file])) {
        $controllerSource[$file] = (string) file_get_contents($file);
    }
    $ok(preg_match('/\bpublic\s+function\s+' . preg_quote($methodName, '/') . '\s*\(/',
        $controllerSource[$file]) === 1, 'registered handler exists: ' . $className . '::' . $methodName);
}

$ok(count($frozen) === 150, '150 pinned P1 operations');
foreach ($frozen as $entry) {
    if (! isset($keys[$entry['method'] . ' ' . $entry['path']])) {
        throw new RuntimeException('Missing frozen P1 route: ' . json_encode($entry));
    }
}
$checks++;

$legacyGroups = json_decode((string) file_get_contents(
    $root . '/backend/tests/fixtures/routes_v1923_legacy_groups.json'), true, 512, JSON_THROW_ON_ERROR);
$ok(count($legacyGroups) === 20, '20 legacy historical groups preserved as test-only fixtures');
foreach (array_keys($legacyGroups) as $legacy) {
    $ok(LegacyRouteSource::mounted($root, $legacy), 'historical contract still mounted: ' . $legacy);
}
$ok(substr_count(LegacyRouteSource::core70($root), '$routes->') === 73,
    'M3.4D core 70 retained through independently pinned runtime owners');

$change = static function (array $row) use ($records): array {
    $copy = $records;
    $copy[3] = $row;
    return $copy;
};
$invalid = $records[3];
$invalid['method'] = $invalid['method'] === 'POST' ? 'PUT' : 'POST';
$reject(static fn () => RouteInventory::assertBaseline($change($invalid), $baseline), 'reject changed method');
$invalid = $records[3];
$invalid['path'] .= '/new';
$reject(static fn () => RouteInventory::assertBaseline($change($invalid), $baseline), 'reject changed path');
$invalid = $records[3];
$invalid['handler'][1] = 'unknownMethod';
$reject(static fn () => RouteInventory::assertBaseline($change($invalid), $baseline), 'reject changed handler');
$invalid = $records[3];
$invalid['options']['filter'] = 'none';
$reject(static fn () => RouteInventory::assertBaseline($change($invalid), $baseline), 'reject changed CORS filter');
$missing = $records;
array_pop($missing);
$reject(static fn () => RouteInventory::assertBaseline($missing, $baseline), 'reject missing route');
$extra = $records;
$extra[] = ['method' => 'GET', 'path' => 'api/v1/rogue',
    'handler' => ['App\\RogueController', 'index'], 'options' => ['filter' => 'cors']];
$reject(static fn () => RouteInventory::assertBaseline($extra, $baseline), 'reject undocumented operation');
$duplicate = $records;
$duplicate[] = $records[3];
$reject(static fn () => RouteInventory::assertBaseline($duplicate, $baseline), 'reject duplicate operation');
$shadow = [
    ['method' => 'GET', 'path' => 'api/v1/resources/(:segment)', 'handler' => ['App\\X', 'one'], 'options' => ['filter' => 'cors']],
    ['method' => 'GET', 'path' => 'api/v1/resources/detail', 'handler' => ['App\\X', 'two'], 'options' => ['filter' => 'cors']],
];
$reject(static fn () => RouteInventory::assertNoShadow($shadow), 'reject dynamic/static ambiguity');
$shadowNum = [
    ['method' => 'GET', 'path' => 'api/v1/resources/(:num)', 'handler' => ['App\\X', 'one'], 'options' => ['filter' => 'cors']],
    ['method' => 'GET', 'path' => 'api/v1/resources/42', 'handler' => ['App\\X', 'two'], 'options' => ['filter' => 'cors']],
];
$reject(static fn () => RouteInventory::assertNoShadow($shadowNum), 'reject numeric/static ambiguity');

// Preserve versioned API group and reject unversioned route registrations.
$ok(! str_contains($source, "group('api'"), 'no unversioned API group');
echo 'M19.24 ROUTE ARCHITECTURE: ' . $checks . '/' . $checks . " PASS\n";
