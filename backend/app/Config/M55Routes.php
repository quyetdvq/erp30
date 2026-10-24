<?php

declare(strict_types=1);

/** M5.5: five exact Contacts API-010 routes; one canonical Contact owner. */
$routes->get('contacts', [\App\Modules\Contact\ContactController::class, 'listContacts']);
$routes->post('contacts', [\App\Modules\Contact\ContactController::class, 'createContact']);
$routes->get('contacts/(:num)', [\App\Modules\Contact\ContactController::class, 'getContact']);
$routes->patch('contacts/(:num)', [\App\Modules\Contact\ContactController::class, 'updateContact']);
$routes->post('contacts/(:num)/actions/set-state', [\App\Modules\Contact\ContactController::class, 'setContactState']);
