<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('api/v1', ['filter' => 'cors'], static function (RouteCollection $routes): void {
    $routes->get('health', 'Api\\Health::index');
    $routes->options('health', 'Api\\Health::options');
    $routes->get('authorization/profile', 'Api\\Authorization::profile');
    $routes->get('authorization/check', 'Api\\Authorization::check');
    $routes->options('authorization/profile', 'Api\\Authorization::options');
    $routes->options('authorization/check', 'Api\\Authorization::options');

    $routes->post('auth/login', 'Api\\Auth::login');
    $routes->post('auth/logout', 'Api\\Auth::logout');
    $routes->get('auth/me', 'Api\\Auth::me');
    $routes->patch('auth/profile', 'Api\\Auth::profile');
    $routes->options('auth/(:any)', 'Api\\Auth::options');

    $routes->get('admin/users', 'Api\\Admin::users');
    $routes->post('admin/users', 'Api\\Admin::createUser');
    $routes->patch('admin/users/(:num)', 'Api\\Admin::updateUser/$1');
    $routes->get('admin/groups', 'Api\\Admin::groups');
    $routes->post('admin/groups', 'Api\\Admin::createGroup');
    $routes->patch('admin/groups/(:num)', 'Api\\Admin::updateGroup/$1');
    $routes->get('admin/groups/(:num)/permissions', 'Api\\Admin::groupPermissions/$1');
    $routes->put('admin/groups/(:num)/permissions', 'Api\\Admin::updateGroupPermissions/$1');
    $routes->get('admin/organizations', 'Api\\Admin::organizations');
    $routes->post('admin/organizations', 'Api\\Admin::createOrganization');
    $routes->patch('admin/organizations/(:num)', 'Api\\Admin::updateOrganization/$1');
    $routes->options('admin/(:any)', 'Api\\Admin::options');
});

service('auth')->routes($routes);
