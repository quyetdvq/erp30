<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('api/v1', ['filter' => 'cors'], static function (RouteCollection $routes): void {
    $routes->get('health', 'Api\\Health::index');
    $routes->options('health', 'Api\\Health::options');
    $routes->post('auth/login', 'Api\\Auth::login');
    $routes->post('auth/logout', 'Api\\Auth::logout');
    $routes->get('auth/me', 'Api\\Auth::me');
    $routes->patch('auth/profile', 'Api\\Auth::profile');
    $routes->options('auth/(:any)', 'Api\\Auth::options');

    foreach (['provinces' => 'province', 'wards' => 'ward', 'organizations' => 'organization', 'contacts' => 'contact'] as $path => $resource) {
        $routes->get($path, 'Api\\FoundationOrganizationContact::list/' . $resource);
        $routes->post($path, 'Api\\FoundationOrganizationContact::create/' . $resource);
        $routes->get($path . '/(:num)', 'Api\\FoundationOrganizationContact::show/' . $resource . '/$1');
        $routes->patch($path . '/(:num)', 'Api\\FoundationOrganizationContact::update/' . $resource . '/$1');
        $routes->post($path . '/(:num)/actions/set-state', 'Api\\FoundationOrganizationContact::setState/' . $resource . '/$1');
    }
    foreach (['departments' => 'department', 'positions' => 'position', 'employees' => 'employee'] as $path => $resource) {
        $routes->get($path, 'Api\\EmployeeStructure::list/' . $resource);
        $routes->post($path, 'Api\\EmployeeStructure::create/' . $resource);
        $routes->get($path . '/(:num)', 'Api\\EmployeeStructure::show/' . $resource . '/$1');
        $routes->patch($path . '/(:num)', 'Api\\EmployeeStructure::update/' . $resource . '/$1');
        $routes->post($path . '/(:num)/actions/set-state', 'Api\\EmployeeStructure::setState/' . $resource . '/$1');
    }
    $routes->get('employee-assignments', 'Api\\EmployeeStructure::list/employee_assignment');
    $routes->post('employee-assignments', 'Api\\EmployeeStructure::create/employee_assignment');
    $routes->get('employee-assignments/(:num)', 'Api\\EmployeeStructure::show/employee_assignment/$1');
    $routes->patch('employee-assignments/(:num)', 'Api\\EmployeeStructure::update/employee_assignment/$1');
    $routes->post('employee-assignments/(:num)/actions/set-state', 'Api\\EmployeeStructure::setState/employee_assignment/$1');
    $routes->get('organizations/tree', 'Api\\FoundationOrganizationContact::tree');

});

service('auth')->routes($routes);
