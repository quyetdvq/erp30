<?php

declare(strict_types=1);

/** M8: four frozen Product Import operations; no template download API. */
$routes->post('products/import-jobs', [\App\Modules\Product\Import\ProductImportController::class, 'startProductImport']);
$routes->get('import-jobs', [\App\Modules\Product\Import\ProductImportController::class, 'listImportJobs']);
$routes->get('import-jobs/(:num)', [\App\Modules\Product\Import\ProductImportController::class, 'getImportJob']);
$routes->get('import-jobs/(:num)/rows', [\App\Modules\Product\Import\ProductImportController::class, 'listImportJobRows']);
