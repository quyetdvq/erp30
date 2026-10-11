<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('api/v1', ['filter' => 'cors'], static function (RouteCollection $routes): void {
    // Browsers preflight every API request carrying the canonical locale/request-id headers.
    // Keep OPTIONS routeable even when the target resource itself is protected.
    $routes->options('(:any)', 'Api\\Cors::options');
    $routes->get('health', 'Api\\Health::index');
    $routes->options('health', 'Api\\Health::options');
    $routes->get('display-settings/runtime', 'Api\\DisplaySettings::runtime');
    $routes->get('display-settings/definitions', 'Api\\DisplaySettings::definitions');
    $routes->get('display-settings/values', 'Api\\DisplaySettings::values');
    $routes->patch('display-settings', 'Api\\DisplaySettings::update');
    $routes->post('display-settings/actions/reset', 'Api\\DisplaySettings::reset');
    $routes->post('auth/login', 'Api\\Auth::login');
    $routes->post('auth/logout', 'Api\\Auth::logout');
    $routes->get('auth/csrf', 'Api\\Auth::csrf');
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

    $routes->get('security/users', 'Api\\AuthorizationWorkflow::securityUsers');
    $routes->post('users', 'Api\\AuthorizationWorkflow::createUser');
    $routes->patch('users/(:num)', 'Api\\AuthorizationWorkflow::updateUser/$1');
    $routes->post('users/(:num)/actions/set-state', 'Api\\AuthorizationWorkflow::setUserState/$1');
    $routes->post('users/(:num)/actions/reset-password', 'Api\\AuthorizationWorkflow::resetUserPassword/$1');
    $routes->get('users/(:num)/permissions', 'Api\\AuthorizationWorkflow::userPermissions/$1');
    $routes->put('users/(:num)/permissions', 'Api\\AuthorizationWorkflow::replaceUserPermissions/$1');

    foreach (['authorization-groups' => 'authorization_group', 'resources' => 'resource', 'policies' => 'policy', 'authorization-logs' => 'authorization_log', 'workflow-states' => 'workflow_state', 'lifecycles' => 'lifecycle'] as $path => $resource) {
        $routes->get($path, 'Api\\AuthorizationWorkflow::list/' . $resource);
        if ($resource !== 'authorization_log') {
            $routes->post($path, 'Api\\AuthorizationWorkflow::create/' . $resource);
        }
        $routes->get($path . '/(:num)', 'Api\\AuthorizationWorkflow::show/' . $resource . '/$1');
        if ($resource !== 'authorization_log') {
            $routes->patch($path . '/(:num)', 'Api\\AuthorizationWorkflow::update/' . $resource . '/$1');
            if ($resource === 'policy') {
                $routes->delete($path . '/(:num)', 'Api\\AuthorizationWorkflow::deletePolicy/$1');
            }
        }
        if (in_array($resource, ['authorization_group', 'workflow_state', 'lifecycle', 'policy', 'resource'], true)) {
            $routes->post($path . '/(:num)/actions/set-state', 'Api\\AuthorizationWorkflow::setState/' . $resource . '/$1');
        }
    }
    $routes->delete('resources/(:num)', 'Api\\AuthorizationWorkflow::deleteResource/$1');
    $routes->get('resources/(:num)/numbering-series', 'Api\\AutoNumbering::list/$1');
    $routes->post('resources/(:num)/numbering-series', 'Api\\AutoNumbering::create/$1');
    $routes->get('resources/(:num)/numbering-series/(:num)', 'Api\\AutoNumbering::show/$1/$2');
    $routes->patch('resources/(:num)/numbering-series/(:num)', 'Api\\AutoNumbering::update/$1/$2');
    $routes->post('resources/(:num)/numbering-series/(:num)/actions/set-state', 'Api\\AutoNumbering::setState/$1/$2');
    $routes->post('resources/(:num)/numbering-series/actions/validate-config', 'Api\\AutoNumbering::validateConfig/$1');
    $routes->get('authorization-groups/(:num)/members', 'Api\\AuthorizationWorkflow::members/$1');
    $routes->put('authorization-groups/(:num)/members', 'Api\\AuthorizationWorkflow::replaceMembers/$1');
    $routes->get('authorization-groups/(:num)/permissions', 'Api\\AuthorizationWorkflow::permissions/$1');
    $routes->put('authorization-groups/(:num)/permissions', 'Api\\AuthorizationWorkflow::replacePermissions/$1');
    $routes->get('resources/(:num)/actions', 'Api\\AuthorizationWorkflow::actions/$1');
    $routes->post('resources/(:num)/actions', 'Api\\AuthorizationWorkflow::createAction/$1');
    $routes->patch('resources/(:num)/actions/(:num)', 'Api\\AuthorizationWorkflow::updateAction/$1/$2');
    $routes->delete('resources/(:num)/actions/(:num)', 'Api\\AuthorizationWorkflow::deleteAction/$1/$2');
    $routes->get('lifecycles/(:num)/states', 'Api\\AuthorizationWorkflow::states/$1');
    $routes->put('lifecycles/(:num)/states', 'Api\\AuthorizationWorkflow::configureStates/$1');
    $routes->get('lifecycles/(:num)/transitions', 'Api\\AuthorizationWorkflow::transitions/$1');
    $routes->put('lifecycles/(:num)/transitions', 'Api\\AuthorizationWorkflow::configureStates/$1');

    $routes->get('product-groups', 'Api\\ProductCatalog::listGroups');
    $routes->post('product-groups', 'Api\\ProductCatalog::createGroup');
    $routes->get('product-groups/(:num)', 'Api\\ProductCatalog::showGroup/$1');
    $routes->patch('product-groups/(:num)', 'Api\\ProductCatalog::updateGroup/$1');
    $routes->post('product-groups/(:num)/actions/set-state', 'Api\\ProductCatalog::setGroupState/$1');

    $routes->get('product-uoms', 'Api\\ProductCatalog::listUoms');
    $routes->post('product-uoms', 'Api\\ProductCatalog::createUom');
    $routes->get('product-uoms/(:num)', 'Api\\ProductCatalog::showUom/$1');
    $routes->patch('product-uoms/(:num)', 'Api\\ProductCatalog::updateUom/$1');
    $routes->post('product-uoms/(:num)/actions/set-state', 'Api\\ProductCatalog::setUomState/$1');

    $routes->get('products', 'Api\\ProductCatalog::listProducts');
    $routes->post('products', 'Api\\ProductCatalog::createProduct');
    $routes->get('products/(:num)', 'Api\\ProductCatalog::showProduct/$1');
    $routes->patch('products/(:num)', 'Api\\ProductCatalog::updateProduct/$1');
    $routes->post('products/(:num)/actions/set-state', 'Api\\ProductCatalog::setProductState/$1');
    $routes->get('products/(:num)/uom-conversions', 'Api\\ProductCatalog::listConversions/$1');
    $routes->put('products/(:num)/uom-conversions', 'Api\\ProductCatalog::replaceConversions/$1');
    $routes->get('products/(:num)/combo-items', 'Api\\ProductCatalog::listComboItems/$1');
    $routes->put('products/(:num)/combo-items', 'Api\\ProductCatalog::replaceComboItems/$1');

    $routes->post('products/import-jobs', 'Api\\ProductImport::start');
    $routes->get('import-jobs', 'Api\\ProductImport::list');
    $routes->get('import-jobs/(:num)', 'Api\\ProductImport::show/$1');
    $routes->get('import-jobs/(:num)/rows', 'Api\\ProductImport::rows/$1');

    $routes->get('price-lists', 'Api\\ProductCatalog::listPriceLists');
    $routes->post('price-lists', 'Api\\ProductCatalog::createPriceList');
    $routes->get('price-lists/(:num)', 'Api\\ProductCatalog::showPriceList/$1');
    $routes->patch('price-lists/(:num)', 'Api\\ProductCatalog::updatePriceList/$1');
    $routes->post('price-lists/(:num)/actions/set-state', 'Api\\ProductCatalog::setPriceListState/$1');
    $routes->get('price-lists/(:num)/items', 'Api\\ProductCatalog::listPriceListItems/$1');
    $routes->put('price-lists/(:num)/items', 'Api\\ProductCatalog::replacePriceListItems/$1');

});

service('auth')->routes($routes);
