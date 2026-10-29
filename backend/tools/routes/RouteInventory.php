<?php

declare(strict_types=1);

/**
 * Vendor-free route inventory for the reviewed, explicit CI4 route files.
 * The collection below records registrations (including group filters), rather
 * than guessing routes from grep. Production never loads this test tool.
 */
namespace CodeIgniter\Router {
    final class RouteCollection
    {
        /** @var list<array{method:string,path:string,handler:list<string>,options:array<string,mixed>}> */
        public array $registered = [];
        private string $prefix = '';
        /** @var array<string,mixed> */
        private array $groupOptions = [];
        private bool $autoRouteDisabled = false;

        public function setAutoRoute(bool $enabled): void
        {
            if ($enabled) {
                throw new \RuntimeException('AutoRoute enabled.');
            }
            $this->autoRouteDisabled = true;
        }

        public function group(string $prefix, array $options, callable $callback): void
        {
            $oldPrefix = $this->prefix;
            $oldOptions = $this->groupOptions;
            $this->prefix = trim($oldPrefix . '/' . trim($prefix, '/'), '/');
            $this->groupOptions = array_replace($oldOptions, $options);
            try {
                $callback($this);
            } finally {
                $this->prefix = $oldPrefix;
                $this->groupOptions = $oldOptions;
            }
        }

        public function get(string $path, array|string $handler): void { $this->capture('GET', $path, $handler); }
        public function post(string $path, array|string $handler): void { $this->capture('POST', $path, $handler); }
        public function patch(string $path, array|string $handler): void { $this->capture('PATCH', $path, $handler); }
        public function put(string $path, array|string $handler): void { $this->capture('PUT', $path, $handler); }
        public function delete(string $path, array|string $handler): void { $this->capture('DELETE', $path, $handler); }
        public function options(string $path, array|string $handler): void { $this->capture('OPTIONS', $path, $handler); }

        private function capture(string $method, string $path, array|string $handler): void
        {
            if (! $this->autoRouteDisabled) {
                throw new \RuntimeException('Routes registered before explicit AutoRoute shutdown.');
            }
            if (! is_array($handler) || count($handler) !== 2 || ! is_string($handler[0]) || ! is_string($handler[1])) {
                throw new \RuntimeException('Unexpected route handler: ' . $method . ' ' . $path);
            }
            $full = trim($this->prefix . '/' . trim($path, '/'), '/');
            foreach ($this->registered as $entry) {
                if ($entry['method'] === $method && $entry['path'] === $full) {
                    throw new \RuntimeException('Duplicate route: ' . $method . ' ' . $full);
                }
            }
            $this->registered[] = [
                'method' => $method,
                'path' => $full,
                'handler' => [$handler[0], $handler[1]],
                'options' => $this->groupOptions,
            ];
        }
    }
}

namespace DinkERP\Tools\Routes {
    use CodeIgniter\Router\RouteCollection;

    final class RouteInventory
    {
        /** @return list<array{method:string,path:string,handler:list<string>,options:array<string,mixed>}> */
        public static function read(string $projectRoot): array
        {
            $source = rtrim($projectRoot, '/\\') . '/backend/app/Config/Routes.php';
            if (! is_file($source)) {
                throw new \RuntimeException('Missing CodeIgniter route entrypoint.');
            }
            $routes = new RouteCollection();
            require $source;
            return $routes->registered;
        }

        /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
        public static function normalized(array $rows): array
        {
            usort($rows, static fn (array $a, array $b): int =>
                [$a['method'], $a['path']] <=> [$b['method'], $b['path']]);
            return $rows;
        }

        /**
         * Reject route shadowing using deterministic representative strings.
         * This catches dynamic/fixed collisions before compiled CI4 HTTP UAT.
         * @param list<array<string,mixed>> $rows
         */
        public static function assertNoShadow(array $rows): void
        {
            foreach ($rows as $i => $a) {
                if ($a['method'] === 'OPTIONS') {
                    // Existing global OPTIONS catch-all is a reviewed exception.
                    continue;
                }
                $pattern = preg_quote($a['path'], '~');
                $pattern = str_replace(['\(\:num\)', '\(\:segment\)', '\(\:any\)'],
                    ['[0-9]+', '[^/]+', '.+'], $pattern);
                foreach ($rows as $j => $b) {
                    if ($i === $j || $a['method'] !== $b['method']) {
                        continue;
                    }
                    $candidate = str_replace(['(:num)', '(:segment)', '(:any)'], ['42', 'sample', 'sample/path'], $b['path']);
                    if (preg_match('~^' . $pattern . '$~D', $candidate) === 1) {
                        throw new \RuntimeException('Route shadow ambiguity: ' . $a['method'] . ' ' . $a['path'] . ' vs ' . $b['path']);
                    }
                }
            }
        }

        /**
         * Reviewed additions live in a separate manifest. Never rewrite the
         * original 180-route snapshot to onboard a new SUP/PUR/etc module.
         * @return array{modules:list<string>,routes:list<array<string,mixed>>}
         */
        public static function approvedExtensions(string $file): array
        {
            $approved = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($approved) || ($approved['schema_version'] ?? null) !== 1
                || ($approved['baseline'] ?? null) !== 'v1.9.23'
                || ! is_array($approved['modules'] ?? null) || ! array_is_list($approved['modules'])
                || ! is_array($approved['routes'] ?? null) || ! array_is_list($approved['routes'])) {
                throw new \RuntimeException('Invalid approved route extension manifest.');
            }
            $moduleNames = [];
            foreach ($approved['modules'] as $name) {
                if (! is_string($name) || preg_match('/\A[A-Z][A-Za-z0-9]*Routes\.php\z/D', $name) !== 1
                    || isset($moduleNames[$name])) {
                    throw new \RuntimeException('Invalid or duplicate approved route module.');
                }
                $moduleNames[$name] = true;
            }
            $routeNames = [];
            foreach ($approved['routes'] as $entry) {
                if (! is_array($entry) || array_keys($entry) !== ['method', 'path', 'handler', 'options']
                    || ! in_array($entry['method'], ['GET','POST','PUT','PATCH','DELETE'], true)
                    || ! is_string($entry['path']) || ! str_starts_with($entry['path'], 'api/v1/')
                    || ! preg_match('~\Aapi/v1/[a-z0-9_./():-]+\z~D', $entry['path'])
                    || ! is_array($entry['handler']) || ! array_is_list($entry['handler'])
                    || count($entry['handler']) !== 2
                    || ! is_string($entry['handler'][0]) || ! str_starts_with($entry['handler'][0], 'App\\')
                    || ! is_string($entry['handler'][1])
                    || ! preg_match('~\A[A-Za-z_][A-Za-z0-9_]*(?:/\$[1-9])?\z~D', $entry['handler'][1])
                    || $entry['options'] !== ['filter' => 'cors']) {
                    throw new \RuntimeException('Invalid approved route signature.');
                }
                $key = $entry['method'] . ' ' . $entry['path'];
                if (isset($routeNames[$key])) {
                    throw new \RuntimeException('Duplicate approved route signature.');
                }
                $routeNames[$key] = true;
            }
            return ['modules' => $approved['modules'], 'routes' => $approved['routes']];
        }

        /** @param list<array<string,mixed>> $current */
        public static function assertBaseline(array $current, string $snapshotFile): void
        {
            $old = json_decode((string) file_get_contents($snapshotFile), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($old) || ! isset($old['routes']) || ! is_array($old['routes'])
                || count($old['routes']) !== 180) {
                throw new \RuntimeException('Invalid frozen v1.9.23 route inventory.');
            }
            $extension = self::approvedExtensions(dirname($snapshotFile) . '/routes_extensions_approved.json');
            $expected = self::normalized(array_merge($old['routes'], $extension['routes']));
            $actual = self::normalized($current);
            if ($expected !== $actual) {
                $left = array_map('json_encode', $expected);
                $right = array_map('json_encode', $actual);
                throw new \RuntimeException('Route registry mismatch against v1.9.23 + approved extensions: ' .
                    implode(' | ', array_slice(array_diff($left, $right), 0, 3)) .
                    ' != ' . implode(' | ', array_slice(array_diff($right, $left), 0, 3)));
            }
            self::assertNoShadow($current);
        }
    }
}
