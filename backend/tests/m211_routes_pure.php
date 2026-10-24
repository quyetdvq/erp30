<?php

declare(strict_types=1);

/**
 * Vendor-free regression check for the exact CI4 route registrations.
 * Run with: php backend/tests/m211_routes_pure.php
 */
namespace CodeIgniter\Router {
    final class RouteCollection
    {
        /** @var array<string,array<string,array|string>> */
        public array $registered = [];
        private string $prefix = '';

        public function setAutoRoute(bool $enabled): void
        {
            if ($enabled) {
                throw new \RuntimeException('Autorouting must remain disabled.');
            }
        }

        public function group(string $prefix, array $options, callable $callback): void
        {
            $old = $this->prefix;
            $this->prefix = trim($old . '/' . trim($prefix, '/'), '/');
            $callback($this);
            $this->prefix = $old;
        }

        public function get(string $path, array|string $target): void
        {
            $this->capture('GET', $path, $target);
        }
        public function post(string $path, array|string $target): void
        {
            $this->capture('POST', $path, $target);
        }
        public function patch(string $path, array|string $target): void
        {
            $this->capture('PATCH', $path, $target);
        }
        public function put(string $path, array|string $target): void
        {
            $this->capture('PUT', $path, $target);
        }
        public function delete(string $path, array|string $target): void
        {
            $this->capture('DELETE', $path, $target);
        }
        public function options(string $path, array|string $target): void
        {
            $this->capture('OPTIONS', $path, $target);
        }

        private function capture(string $method, string $path, array|string $target): void
        {
            $key = trim($this->prefix . '/' . trim($path, '/'), '/');
            if (isset($this->registered[$method][$key])) {
                throw new \RuntimeException("Duplicate route: {$method} {$key}");
            }
            $this->registered[$method][$key] = $target;
        }
    }
}

namespace {
    use CodeIgniter\Router\RouteCollection;

    $routes = new RouteCollection();
    require dirname(__DIR__) . '/app/Config/Routes.php';

    $controller = 'App\\Modules\\ReadApi\\ReadCatalogController';
    $expected = [
        'provinces' => ['Provinces', 'Province'],
        'wards' => ['Wards', 'Ward'],
        'organizations' => ['Organizations', 'Organization'],
        'departments' => ['Departments', 'Department'],
        'positions' => ['Positions', 'Position'],
        'employees' => ['Employees', 'Employee'],
        'employee-assignments' => ['EmployeeAssignments', 'EmployeeAssignment'],
    ];
    $source = file_get_contents(dirname(__DIR__) . '/app/Modules/ReadApi/ReadCatalogController.php');
    if (! is_string($source)) {
        throw new \RuntimeException('Cannot load read controller source.');
    }
    $passes = 0;
    foreach ($expected as $base => [$plural, $singular]) {
        $pairs = [
            'api/v1/' . $base => 'list' . $plural,
            'api/v1/' . $base . '/(:num)' => 'show' . $singular,
        ];
        foreach ($pairs as $route => $method) {
            $registered = $routes->registered['GET'][$route] ?? null;
            if ($registered !== [$controller, $method]) {
                throw new \RuntimeException('Wrong GET controller handler for ' . $route);
            }
            if (preg_match('/public function ' . preg_quote($method, '/') . '\\(/', $source) !== 1) {
                throw new \RuntimeException('Missing public endpoint method ' . $method);
            }
            echo 'PASS: ' . $route . ' => ' . $method . PHP_EOL;
            $passes++;
        }
    }
    $nonOptions = 0;
    foreach ($routes->registered as $http => $entries) {
        if ($http !== 'OPTIONS') {
            $nonOptions += count($entries);
        }
    }
    // M2.1.1 owns 22 accepted operations; later milestones may add fixed
    // contract routes but must not alter any of those accepted route handlers.
    if ($nonOptions < 22) {
        throw new \RuntimeException('Previously accepted operations disappeared: ' . $nonOptions);
    }
    echo 'PASS: accepted M2.1.1 routes preserved; currently ' . $nonOptions . ' non-OPTIONS operations registered' . PHP_EOL;
    echo 'TOTAL: ' . (++$passes) . ' M2.1.1 route assertions passed.' . PHP_EOL;
}
