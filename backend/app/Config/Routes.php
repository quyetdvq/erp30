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

});

service('auth')->routes($routes);
