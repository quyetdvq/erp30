<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use App\Shared\Http\HealthController;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);

// The only API group. Domain route files inherit this CORS filter and the
// unchanged global CSRF, requestmeta, Shield session and secure-header filters.
$routes->group('api/v1', ['filter' => 'cors'], static function (RouteCollection $routes): void {
    // Pinned health and CORS preflight registration (including catch-all).
    $routes->options('(:any)', [HealthController::class, 'options']);
    $routes->options('health', [HealthController::class, 'options']);
    $routes->get('health', [HealthController::class, 'index']);

    // One route entrypoint per bounded-context owner. No Mxx milestone routes.
    require __DIR__ . '/Routes/ApiV1/IdentityRoutes.php';
    require __DIR__ . '/Routes/ApiV1/AccessRoutes.php';
    require __DIR__ . '/Routes/ApiV1/SettingsRoutes.php';
    require __DIR__ . '/Routes/ApiV1/NumberingRoutes.php';
    require __DIR__ . '/Routes/ApiV1/WorkflowRoutes.php';
    require __DIR__ . '/Routes/ApiV1/LocationsRoutes.php';
    require __DIR__ . '/Routes/ApiV1/OrganizationsRoutes.php';
    require __DIR__ . '/Routes/ApiV1/PeopleRoutes.php';
    require __DIR__ . '/Routes/ApiV1/ContactsRoutes.php';
    require __DIR__ . '/Routes/ApiV1/ProductsRoutes.php';
    require __DIR__ . '/Routes/ApiV1/PricingRoutes.php';
    require __DIR__ . '/Routes/ApiV1/ImportsRoutes.php';
    require __DIR__ . '/Routes/ApiV1/CustomersRoutes.php';
    require __DIR__ . '/Routes/ApiV1/HistoryRoutes.php';
    require __DIR__ . '/Routes/ApiV1/NotificationsRoutes.php';
});

// Shield built-in web routes remain unregistered. Never enable AutoRoute.
