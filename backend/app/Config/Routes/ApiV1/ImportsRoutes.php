<?php

declare(strict_types=1);

/**
 * Imports API v1 route ownership. Loaded once inside the reviewed
 * api/v1 CORS group in Config/Routes.php. No automatic routing.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->post('products/import-jobs', [\App\Modules\Product\Import\ProductImportController::class, 'startProductImport']);
$routes->get('import-jobs', [\App\Modules\Product\Import\ProductImportController::class, 'listImportJobs']);
$routes->get('import-jobs/(:num)', [\App\Modules\Product\Import\ProductImportController::class, 'getImportJob']);
$routes->get('import-jobs/(:num)/rows', [\App\Modules\Product\Import\ProductImportController::class, 'listImportJobRows']);
