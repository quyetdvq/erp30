<?php

declare(strict_types=1);

namespace DinkERP\Tools\Routes;

/**
 * Read-only legacy milestone source view for historically frozen acceptance
 * suites. Reassembles route declarations from their ACTUAL new domain owners.
 * Does not register routes, retain legacy route files or bypass live checks.
 * The authoritative runtime inventory is pinned separately by RouteInventory.
 */
final class LegacyRouteSource
{
    private const OWNER_DIRECTORY = '/backend/app/Config/Routes/ApiV1/';

    /** @return array<string,list<array{method:string,path:string}>> */
    private static function groups(string $root): array
    {
        $source = $root . '/backend/tests/fixtures/routes_v1923_legacy_groups.json';
        $groups = json_decode((string) file_get_contents($source), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($groups) || count($groups) !== 20) {
            throw new \RuntimeException('Corrupted reviewed legacy route grouping fixture.');
        }
        return $groups;
    }

    /** @return array<string,array{source:string,owner:string}> */
    private static function declarations(string $root): array
    {
        $main = (string) file_get_contents($root . '/backend/app/Config/Routes.php');
        $approved = json_decode((string) file_get_contents(
            $root . '/backend/tests/fixtures/routes_extensions_approved.json'), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($approved) || ! is_array($approved['modules'] ?? null)
            || ! is_array($approved['routes'] ?? null)) {
            throw new \RuntimeException('Invalid approved route extension manifest.');
        }
        $baselineNames = [
            'IdentityRoutes.php', 'AccessRoutes.php', 'SettingsRoutes.php',
            'NumberingRoutes.php', 'WorkflowRoutes.php', 'LocationsRoutes.php',
            'OrganizationsRoutes.php', 'PeopleRoutes.php', 'ContactsRoutes.php',
            'ProductsRoutes.php', 'PricingRoutes.php', 'ImportsRoutes.php',
            'CustomersRoutes.php', 'NotificationsRoutes.php',
        ];
        $expectedNames = array_merge($baselineNames, $approved['modules']);
        $files = glob($root . self::OWNER_DIRECTORY . '*Routes.php');
        if (! is_array($files) || count($files) !== count($expectedNames)
            || array_diff(array_map('basename', $files), $expectedNames)
            || array_diff($expectedNames, array_map('basename', $files))) {
            throw new \RuntimeException('Domain route ownership differs from the reviewed module inventory.');
        }
        $result = [];
        $rx = '~\$routes->(?P<verb>get|post|patch|put|delete)\(\x27(?P<path>[^\x27]+)\x27,\s*\[.*?::class,\s*\x27[^\x27]+\x27\]\);~s';
        foreach ($files as $file) {
            $name = basename($file);
            if (substr_count($main, "require __DIR__ . '/Routes/ApiV1/" . $name . "';") !== 1) {
                throw new \RuntimeException('Missing or duplicate domain mount: ' . $name);
            }
            $text = (string) file_get_contents($file);
            preg_match_all($rx, $text, $matches, PREG_SET_ORDER);
            if (count($matches) !== substr_count($text, '$routes->')) {
                throw new \RuntimeException('Unparsed route declaration: ' . $name);
            }
            foreach ($matches as $row) {
                $key = strtoupper($row['verb']) . ' ' . $row['path'];
                if (isset($result[$key])) {
                    throw new \RuntimeException('Duplicate domain registration: ' . $key);
                }
                $result[$key] = ['source' => $row[0], 'owner' => $name];
            }
        }
        if (count($result) !== 177 + count($approved['routes'])) {
            throw new \RuntimeException('Reviewed non-health + approved extension route footprint changed: ' . count($result));
        }
        return $result;
    }

    public static function source(string $root, string $legacyFile): string
    {
        $groups = self::groups($root);
        if (! isset($groups[$legacyFile])) {
            throw new \InvalidArgumentException('Unknown frozen milestone route group: ' . $legacyFile);
        }
        $available = self::declarations($root);
        $lines = [];
        foreach ($groups[$legacyFile] as $entry) {
            $key = $entry['method'] . ' ' . $entry['path'];
            $line = $available[$key]['source'] ?? null;
            if ($line === null) {
                throw new \RuntimeException('Reviewed route missing: ' . $key);
            }
            $lines[] = $line;
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * Reconstruct only the original 70 P1 core routes for the SHA-pinned M3.4D
     * acceptance harness, from REAL current module declarations. Historical
     * negative mutation tests still operate on synthetic copies of this view.
     */
    public static function core70(string $root): string
    {
        $fixture = json_decode((string) file_get_contents(
            $root . '/backend/tests/fixtures/routes_v1923_core70.json'), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($fixture) || count($fixture['routes'] ?? []) !== 72) {
            throw new \RuntimeException('Invalid pinned M3.4D direct-route inventory.');
        }
        $main = (string) file_get_contents($root . '/backend/app/Config/Routes.php');
        if (! str_contains($main, '$routes->setAutoRoute(false)')) {
            throw new \RuntimeException('AutoRoute guard missing from actual entrypoint.');
        }
        $available = self::declarations($root);
        $imports = [];
        foreach ($fixture['imports'] as $import) {
            if (! str_starts_with($import, 'use App\\') || ! str_ends_with($import, ';')) {
                throw new \RuntimeException('Invalid reviewed core controller import');
            }
            $fqcn = substr($import, 4, -1);
            $imports[substr($fqcn, strrpos($fqcn, '\\') + 1)] = $fqcn;
        }
        $lines = ["<?php", "", "declare(strict_types=1);", ""];
        array_push($lines, ...$fixture['imports']);
        $lines[] = '$routes->setAutoRoute(false);';
        foreach ($fixture['routes'] as $item) {
            $verb = strtolower($item['method']);
            $path = $item['path'];
            $handler = $item['action'];
            $controller = $item['controller'];
            $key = $item['method'] . ' ' . $path;
            if ($item['method'] === 'OPTIONS' || $path === 'health') {
                $expected = "\$routes->{$verb}('{$path}', [{$controller}::class, '{$handler}']);";
                if (! str_contains($main, $expected)) {
                    throw new \RuntimeException('Missing reviewed health/OPTIONS route: ' . $key);
                }
            } else {
                $actual = $available[$key]['source'] ?? null;
                if ($actual === null || ! str_contains($actual, '\\' . $imports[$controller] . '::class')
                    || ! str_contains($actual, "::class, '{$handler}'")) {
                    throw new \RuntimeException('Wrong core route owner: ' . $key);
                }
            }
            $lines[] = "    \$routes->{$verb}('{$path}', [{$controller}::class, '{$handler}']);";
        }
        return implode("\n", $lines) . "\n";
    }

    public static function mounted(string $root, string $legacyFile): bool
    {
        try {
            self::source($root, $legacyFile);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
