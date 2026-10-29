<?php

declare(strict_types=1);

/**
 * Contacts API v1 route ownership. Loaded once inside the reviewed
 * api/v1 CORS group in Config/Routes.php. No automatic routing.
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->get('contacts', [\App\Modules\Contact\ContactController::class, 'listContacts']);
$routes->post('contacts', [\App\Modules\Contact\ContactController::class, 'createContact']);
$routes->get('contacts/(:num)', [\App\Modules\Contact\ContactController::class, 'getContact']);
$routes->patch('contacts/(:num)', [\App\Modules\Contact\ContactController::class, 'updateContact']);
$routes->post('contacts/(:num)/actions/set-state', [\App\Modules\Contact\ContactController::class, 'setContactState']);
