<?php

declare(strict_types=1);

/**
 * SUP-T07 Core: seven additive Supplier API registrations.
 * The independent opt-in switch defaults OFF pending security/DB/HTTP gates.
 * Routes inherit the existing versioned API group, CORS and global CSRF.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->get('suppliers', [\App\Modules\Supplier\SupplierController::class, 'index']);
$routes->post('suppliers', [\App\Modules\Supplier\SupplierController::class, 'create']);
$routes->get('suppliers/(:num)', [\App\Modules\Supplier\SupplierController::class, 'show']);
$routes->patch('suppliers/(:num)', [\App\Modules\Supplier\SupplierController::class, 'update']);
$routes->post('suppliers/(:num)/actions/set-state', [\App\Modules\Supplier\SupplierController::class, 'setState']);
$routes->get('suppliers/(:num)/company-usages', [\App\Modules\Supplier\SupplierController::class, 'companyUsages']);
$routes->put('suppliers/(:num)/company-usages', [\App\Modules\Supplier\SupplierController::class, 'replaceCompanyUsages']);
