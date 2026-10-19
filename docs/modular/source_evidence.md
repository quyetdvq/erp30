# Source evidence for DinkERP P1 target architecture

Archive: erp_v1.1.8.zip
All excerpts are static source evidence, not new runtime test results.
Line numbers below refer to the original source file.

## S01 - Backend routes

Path: `backend/app/Config/Routes.php`
SHA-256: `517fc95799d7588ec70acb0eebe429dd78e34ca43fb93ee36202befd71a5609b`

Lines 1-147:
```text
0001: <?php
0002: 
0003: use CodeIgniter\Router\RouteCollection;
0004: 
0005: /** @var RouteCollection $routes */
0006: $routes->get('/', 'Home::index');
0007: 
0008: $routes->group('api/v1', ['filter' => 'cors'], static function (RouteCollection $routes): void {
0009:     // Browsers preflight every API request carrying the canonical locale/request-id headers.
0010:     // Keep OPTIONS routeable even when the target resource itself is protected.
0011:     $routes->options('(:any)', 'Api\\Cors::options');
0012:     $routes->get('health', 'Api\\Health::index');
0013:     $routes->options('health', 'Api\\Health::options');
0014:     $routes->get('display-settings/runtime', 'Api\\DisplaySettings::runtime');
0015:     $routes->get('display-settings/definitions', 'Api\\DisplaySettings::definitions');
0016:     $routes->get('display-settings/values', 'Api\\DisplaySettings::values');
0017:     $routes->patch('display-settings', 'Api\\DisplaySettings::update');
0018:     $routes->post('display-settings/actions/reset', 'Api\\DisplaySettings::reset');
0019:     $routes->post('auth/login', 'Api\\Auth::login');
0020:     $routes->post('auth/logout', 'Api\\Auth::logout');
0021:     $routes->get('auth/csrf', 'Api\\Auth::csrf');
0022:     $routes->get('auth/me', 'Api\\Auth::me');
0023:     $routes->patch('auth/profile', 'Api\\Auth::profile');
0024:     $routes->options('auth/(:any)', 'Api\\Auth::options');
0025: 
0026:     foreach (['provinces' => 'province', 'wards' => 'ward', 'organizations' => 'organization', 'contacts' => 'contact'] as $path => $resource) {
0027:         $routes->get($path, 'Api\\FoundationOrganizationContact::list/' . $resource);
0028:         $routes->post($path, 'Api\\FoundationOrganizationContact::create/' . $resource);
0029:         $routes->get($path . '/(:num)', 'Api\\FoundationOrganizationContact::show/' . $resource . '/$1');
0030:         $routes->patch($path . '/(:num)', 'Api\\FoundationOrganizationContact::update/' . $resource . '/$1');
0031:         $routes->post($path . '/(:num)/actions/set-state', 'Api\\FoundationOrganizationContact::setState/' . $resource . '/$1');
0032:     }
0033:     foreach (['departments' => 'department', 'positions' => 'position', 'employees' => 'employee'] as $path => $resource) {
0034:         $routes->get($path, 'Api\\EmployeeStructure::list/' . $resource);
0035:         $routes->post($path, 'Api\\EmployeeStructure::create/' . $resource);
0036:         $routes->get($path . '/(:num)', 'Api\\EmployeeStructure::show/' . $resource . '/$1');
0037:         $routes->patch($path . '/(:num)', 'Api\\EmployeeStructure::update/' . $resource . '/$1');
0038:         $routes->post($path . '/(:num)/actions/set-state', 'Api\\EmployeeStructure::setState/' . $resource . '/$1');
0039:     }
0040:     $routes->get('employee-assignments', 'Api\\EmployeeStructure::list/employee_assignment');
0041:     $routes->post('employee-assignments', 'Api\\EmployeeStructure::create/employee_assignment');
0042:     $routes->get('employee-assignments/(:num)', 'Api\\EmployeeStructure::show/employee_assignment/$1');
0043:     $routes->patch('employee-assignments/(:num)', 'Api\\EmployeeStructure::update/employee_assignment/$1');
0044:     $routes->post('employee-assignments/(:num)/actions/set-state', 'Api\\EmployeeStructure::setState/employee_assignment/$1');
0045:     $routes->post('organizations/labels/resolve', 'Api\\OrganizationLookups::resolve');
0046:     $routes->get('organizations/tree', 'Api\\FoundationOrganizationContact::tree');
0047: 
0048:     $routes->get('security/users', 'Api\\AuthorizationWorkflow::securityUsers');
0049:     $routes->post('users', 'Api\\AuthorizationWorkflow::createUser');
0050:     $routes->patch('users/(:num)', 'Api\\AuthorizationWorkflow::updateUser/$1');
0051:     $routes->get('users/(:num)/avatar', 'Api\\UserAvatars::show/$1');
0052:     $routes->put('users/(:num)/avatar', 'Api\\UserAvatars::upload/$1');
0053:     $routes->delete('users/(:num)/avatar', 'Api\\UserAvatars::delete/$1');
0054:     $routes->post('users/(:num)/actions/set-state', 'Api\\AuthorizationWorkflow::setUserState/$1');
0055:     $routes->post('users/(:num)/actions/reset-password', 'Api\\AuthorizationWorkflow::resetUserPassword/$1');
0056:     $routes->get('users/(:num)/permissions', 'Api\\AuthorizationWorkflow::userPermissions/$1');
0057:     $routes->put('users/(:num)/permissions', 'Api\\AuthorizationWorkflow::replaceUserPermissions/$1');
0058: 
0059:     foreach (['authorization-groups' => 'authorization_group', 'resources' => 'resource', 'policies' => 'policy', 'authorization-logs' => 'authorization_log', 'workflow-states' => 'workflow_state', 'lifecycles' => 'lifecycle'] as $path => $resource) {
0060:         $routes->get($path, 'Api\\AuthorizationWorkflow::list/' . $resource);
0061:         if ($resource !== 'authorization_log') {
0062:             $routes->post($path, 'Api\\AuthorizationWorkflow::create/' . $resource);
0063:         }
0064:         $routes->get($path . '/(:num)', 'Api\\AuthorizationWorkflow::show/' . $resource . '/$1');
0065:         if ($resource !== 'authorization_log') {
0066:             $routes->patch($path . '/(:num)', 'Api\\AuthorizationWorkflow::update/' . $resource . '/$1');
0067:             if ($resource === 'policy') {
0068:                 $routes->delete($path . '/(:num)', 'Api\\AuthorizationWorkflow::deletePolicy/$1');
0069:             }
0070:         }
0071:         if (in_array($resource, ['authorization_group', 'workflow_state', 'lifecycle', 'policy', 'resource'], true)) {
0072:             $routes->post($path . '/(:num)/actions/set-state', 'Api\\AuthorizationWorkflow::setState/' . $resource . '/$1');
0073:         }
0074:     }
0075:     $routes->delete('resources/(:num)', 'Api\\AuthorizationWorkflow::deleteResource/$1');
0076:     $routes->get('resources/(:num)/numbering-series', 'Api\\AutoNumbering::list/$1');
0077:     $routes->post('resources/(:num)/numbering-series', 'Api\\AutoNumbering::create/$1');
0078:     $routes->get('resources/(:num)/numbering-series/(:num)', 'Api\\AutoNumbering::show/$1/$2');
0079:     $routes->patch('resources/(:num)/numbering-series/(:num)', 'Api\\AutoNumbering::update/$1/$2');
0080:     $routes->post('resources/(:num)/numbering-series/(:num)/actions/set-state', 'Api\\AutoNumbering::setState/$1/$2');
0081:     $routes->post('resources/(:num)/numbering-series/actions/validate-config', 'Api\\AutoNumbering::validateConfig/$1');
0082:     $routes->get('authorization-groups/(:num)/members', 'Api\\AuthorizationWorkflow::members/$1');
0083:     $routes->put('authorization-groups/(:num)/members', 'Api\\AuthorizationWorkflow::replaceMembers/$1');
0084:     $routes->get('authorization-groups/(:num)/permissions', 'Api\\AuthorizationWorkflow::permissions/$1');
0085:     $routes->put('authorization-groups/(:num)/permissions', 'Api\\AuthorizationWorkflow::replacePermissions/$1');
0086:     $routes->get('resources/(:num)/actions', 'Api\\AuthorizationWorkflow::actions/$1');
0087:     $routes->post('resources/(:num)/actions', 'Api\\AuthorizationWorkflow::createAction/$1');
0088:     $routes->patch('resources/(:num)/actions/(:num)', 'Api\\AuthorizationWorkflow::updateAction/$1/$2');
0089:     $routes->delete('resources/(:num)/actions/(:num)', 'Api\\AuthorizationWorkflow::deleteAction/$1/$2');
0090:     $routes->get('lifecycles/(:num)/states', 'Api\\AuthorizationWorkflow::states/$1');
0091:     $routes->put('lifecycles/(:num)/states', 'Api\\AuthorizationWorkflow::configureStates/$1');
0092:     $routes->get('lifecycles/(:num)/transitions', 'Api\\AuthorizationWorkflow::transitions/$1');
0093:     $routes->put('lifecycles/(:num)/transitions', 'Api\\AuthorizationWorkflow::configureStates/$1');
0094: 
0095:     $routes->get('product-groups', 'Api\\ProductCatalog::listGroups');
0096:     $routes->post('product-groups', 'Api\\ProductCatalog::createGroup');
0097:     $routes->get('product-groups/(:num)', 'Api\\ProductCatalog::showGroup/$1');
0098:     $routes->patch('product-groups/(:num)', 'Api\\ProductCatalog::updateGroup/$1');
0099:     $routes->post('product-groups/(:num)/actions/set-state', 'Api\\ProductCatalog::setGroupState/$1');
0100: 
0101:     $routes->get('product-uoms', 'Api\\ProductCatalog::listUoms');
0102:     $routes->post('product-uoms', 'Api\\ProductCatalog::createUom');
0103:     $routes->get('product-uoms/(:num)', 'Api\\ProductCatalog::showUom/$1');
0104:     $routes->patch('product-uoms/(:num)', 'Api\\ProductCatalog::updateUom/$1');
0105:     $routes->post('product-uoms/(:num)/actions/set-state', 'Api\\ProductCatalog::setUomState/$1');
0106: 
0107:     // Bounded, PBAC-filtered Product lookups (distinct from the P1-API-050 CRUD contract).
0108:     $routes->get('products/options', 'Api\\ProductLookups::options');
0109:     $routes->post('products/options/resolve', 'Api\\ProductLookups::resolve');
0110:     $routes->post('products/details/resolve', 'Api\\ProductLookups::resolveDetails');
0111:     $routes->post('products/relations-preview', 'Api\\ProductLookups::relationPreviews');
0112:     $routes->post('products/sales-uom-options/resolve', 'Api\\ProductLookups::salesUomOptions');
0113:     $routes->post('product-uoms/labels/resolve', 'Api\\ProductLookups::resolveUomLabels');
0114:     $routes->get('products', 'Api\\ProductCatalog::listProducts');
0115:     $routes->post('products', 'Api\\ProductCatalog::createProduct');
0116:     $routes->get('products/(:num)', 'Api\\ProductCatalog::showProduct/$1');
0117:     $routes->patch('products/(:num)', 'Api\\ProductCatalog::updateProduct/$1');
0118:     $routes->get('products/(:num)/image', 'Api\\ProductImages::show/$1');
0119:     $routes->post('products/(:num)/image', 'Api\\ProductImages::upload/$1');
0120:     $routes->delete('products/(:num)/image', 'Api\\ProductImages::delete/$1');
0121:     $routes->post('products/(:num)/actions/set-state', 'Api\\ProductCatalog::setProductState/$1');
0122:     $routes->get('products/(:num)/uom-conversions', 'Api\\ProductCatalog::listConversions/$1');
0123:     $routes->put('products/(:num)/uom-conversions', 'Api\\ProductCatalog::replaceConversions/$1');
0124:     $routes->get('products/(:num)/combo-items', 'Api\\ProductCatalog::listComboItems/$1');
0125:     $routes->put('products/(:num)/combo-items', 'Api\\ProductCatalog::replaceComboItems/$1');
0126: 
0127:     $routes->post('products/import-jobs', 'Api\\ProductImport::start');
0128:     // Business Lifecycle metadata respects record VIEW and each transition's PBAC action.
0129:     $routes->get('business-workflow/(:segment)/states', 'Api\\BusinessWorkflow::states/$1');
0130:     $routes->get('business-workflow/(:segment)/(:num)/options', 'Api\\BusinessWorkflow::options/$1/$2');
0131:     $routes->post('business-workflow/(:segment)/(:num)/actions/(:segment)', 'Api\\BusinessWorkflow::execute/$1/$2/$3');
0132: 
0133:     $routes->get('import-jobs', 'Api\\ProductImport::list');
0134:     $routes->get('import-jobs/(:num)', 'Api\\ProductImport::show/$1');
0135:     $routes->get('import-jobs/(:num)/rows', 'Api\\ProductImport::rows/$1');
0136: 
0137:     $routes->get('price-lists', 'Api\\ProductCatalog::listPriceLists');
0138:     $routes->post('price-lists', 'Api\\ProductCatalog::createPriceList');
0139:     $routes->get('price-lists/(:num)', 'Api\\ProductCatalog::showPriceList/$1');
0140:     $routes->patch('price-lists/(:num)', 'Api\\ProductCatalog::updatePriceList/$1');
0141:     $routes->post('price-lists/(:num)/actions/set-state', 'Api\\ProductCatalog::setPriceListState/$1');
0142:     $routes->get('price-lists/(:num)/items', 'Api\\ProductCatalog::listPriceListItems/$1');
0143:     $routes->put('price-lists/(:num)/items', 'Api\\ProductCatalog::replacePriceListItems/$1');
0144: 
0145: });
0146: 
0147: service('auth')->routes($routes);
```

## S02 - Resource and action contributions

Path: `backend/app/Database/Seeds/ResourceActionSeeder.php`
SHA-256: `aa3b07c37f023572aed2e1b685e9150f45d74e00b2782666c51e8f38dc609aaa`

Lines 9-49:
```text
0009: final class ResourceActionSeeder extends Seeder
0010: {
0011:     /** @var array<string, string> */
0012:     private const DOMAINS = [
0013:         'province' => 'MASTER', 'ward' => 'MASTER', 'department' => 'MASTER',
0014:         'position' => 'MASTER', 'employee' => 'MASTER', 'product_group' => 'MASTER',
0015:         'product_uom' => 'MASTER', 'product' => 'MASTER',
0016:         'user' => 'SYSTEM', 'authorization_group' => 'SYSTEM', 'resource' => 'SYSTEM',
0017:         'workflow_state' => 'SYSTEM', 'lifecycle' => 'SYSTEM',
0018:         'organization' => 'ORGANIZATION', 'contact' => 'ORGANIZATION',
0019:         'organization_contact' => 'ORGANIZATION', 'employee_contact' => 'ORGANIZATION',
0020:         'employee_assignment' => 'ORGANIZATION', 'policy' => 'ORGANIZATION',
0021:         'authorization_log' => 'ORGANIZATION', 'price_list' => 'ORGANIZATION',
0022:         'import_job' => 'ORGANIZATION', 'display_setting' => 'ORGANIZATION',
0023:     ];
0024: 
0025:     /** @var array<string, list<string>> */
0026:     private const MATRIX = [
0027:         'province' => ['view', 'create', 'edit', 'setstate', 'export'],
0028:         'ward' => ['view', 'create', 'edit', 'setstate', 'export'],
0029:         'contact' => ['view', 'create', 'edit', 'setstate', 'export'], // retired; retained for audit references
0030:         'organization_contact' => ['view', 'create', 'edit', 'setstate', 'export'],
0031:         'employee_contact' => ['view', 'create', 'edit', 'setstate', 'export'],
0032:         'organization' => ['view', 'create', 'edit', 'setstate', 'export'],
0033:         'department' => ['view', 'create', 'edit', 'setstate', 'export'],
0034:         'position' => ['view', 'create', 'edit', 'setstate', 'export'],
0035:         'employee' => ['view', 'create', 'edit', 'setstate', 'export'],
0036:         'employee_assignment' => ['view', 'create', 'edit', 'setstate'],
0037:         'user' => ['view', 'create', 'edit', 'setstate', 'reset_password', 'assign_permission'],
0038:         'authorization_group' => ['view', 'create', 'edit', 'setstate', 'assign_member', 'assign_permission'],
0039:         'resource' => ['view', 'create', 'edit', 'delete', 'configure_numbering', 'setstate'],
0040:         'policy' => ['view', 'create', 'edit', 'delete', 'setstate'],
0041:         'authorization_log' => ['view', 'export'],
0042:         'workflow_state' => ['view', 'create', 'edit', 'setstate'],
0043:         'lifecycle' => ['view', 'create', 'edit', 'setstate'],
0044:         'product_group' => ['view', 'create', 'edit', 'setstate', 'export'],
0045:         'product_uom' => ['view', 'create', 'edit', 'setstate', 'export'],
0046:         'product' => ['view', 'create', 'edit', 'setstate', 'import', 'export'],
0047:         'price_list' => ['view', 'create', 'edit', 'setstate', 'export'],
0048:         'import_job' => ['view', 'export'],
0049:         'display_setting' => ['view', 'edit'],
```

## S03 - Historical mixed-owner migration

Path: `backend/app/Database/Migrations/2026-10-05-000005_CreateResourceAuthorizationNumberingTables.php`
SHA-256: `687aa169dc8ee945a1f5f8ebc2b2a17c45c2d84eda6cdd89db5c1e551c061576`

Lines 13-24:
```text
0013:         $this->forge->addField([
0014:             'id' => [
0015:                 'type'           => 'BIGINT',
0016:                 'unsigned'       => true,
0017:                 'auto_increment' => true,
0018:             ],
0019:             'lifecycle_id' => [
0020:                 'type'     => 'BIGINT',
0021:                 'unsigned' => true,
0022:                 'null'     => true,
0023:             ],
0024:             'type' => [
```

Lines 57-63:
```text
0057:         ]);
0058:         $this->forge->addPrimaryKey('id');
0059:         $this->forge->addUniqueKey('type');
0060:         $this->forge->addKey('domain');
0061:         $this->forge->addKey('lifecycle_id');
0062:         $this->forge->addForeignKey('lifecycle_id', 'lifecycles', 'id');
0063:         $this->forge->createTable('resources', false, ['ENGINE' => 'InnoDB']);
```

Lines 104-109:
```text
0104:         ]);
0105:         $this->forge->addPrimaryKey('id');
0106:         $this->forge->addUniqueKey(['resource_id', 'code']);
0107:         $this->forge->addKey('code');
0108:         $this->forge->addForeignKey('resource_id', 'resources', 'id');
0109:         $this->forge->createTable('resource_actions', false, ['ENGINE' => 'InnoDB']);
```

Lines 260-325:
```text
0260:                 'null' => true,
0261:             ],
0262:             'status' => [
0263:                 'type'       => 'VARCHAR',
0264:                 'constraint' => 20,
0265:                 'null'       => false,
0266:             ],
0267:             'created_by' => [
0268:                 'type'     => 'BIGINT',
0269:                 'unsigned' => true,
0270:                 'null'     => true,
0271:             ],
0272:             'updated_by' => [
0273:                 'type'     => 'BIGINT',
0274:                 'unsigned' => true,
0275:                 'null'     => true,
0276:             ],
0277:             'created_at' => [
0278:                 'type' => 'DATETIME',
0279:                 'null' => false,
0280:             ],
0281:             'updated_at' => [
0282:                 'type' => 'DATETIME',
0283:                 'null' => true,
0284:             ],
0285:         ]);
0286:         $this->forge->addPrimaryKey('id');
0287:         $this->forge->addUniqueKey('code');
0288:         $this->forge->addKey('status');
0289:         $this->forge->createTable('groups', false, ['ENGINE' => 'InnoDB']);
0290: 
0291:         $this->forge->addField([
0292:             'group_id' => [
0293:                 'type'     => 'BIGINT',
0294:                 'unsigned' => true,
0295:                 'null'     => false,
0296:             ],
0297:             'resource_action_id' => [
0298:                 'type'     => 'BIGINT',
0299:                 'unsigned' => true,
0300:                 'null'     => false,
0301:             ],
0302:             'created_by' => [
0303:                 'type'     => 'BIGINT',
0304:                 'unsigned' => true,
0305:                 'null'     => true,
0306:             ],
0307:             'updated_by' => [
0308:                 'type'     => 'BIGINT',
0309:                 'unsigned' => true,
0310:                 'null'     => true,
0311:             ],
0312:             'created_at' => [
0313:                 'type' => 'DATETIME',
0314:                 'null' => false,
0315:             ],
0316:             'updated_at' => [
0317:                 'type' => 'DATETIME',
0318:                 'null' => true,
0319:             ],
0320:         ]);
0321:         $this->forge->addPrimaryKey(['group_id', 'resource_action_id']);
0322:         $this->forge->addKey('resource_action_id');
0323:         $this->forge->addForeignKey('group_id', 'groups', 'id');
0324:         $this->forge->addForeignKey('resource_action_id', 'resource_actions', 'id');
0325:         $this->forge->createTable('group_permissions', false, ['ENGINE' => 'InnoDB']);
```

## S04 - Authorization engine and batch scope

Path: `backend/app/Modules/Authorization/Decision/Application/PbacDecisionEngine.php`
SHA-256: `ea07e6032a2d2fcb6ac70bc4bff5ed86c3484d75e00ef54d6b595efdaeb1581b`

Lines 7-25:
```text
0007: use App\Authorization\AuthorizationDecision;
0008: use App\Authorization\EffectiveProfile;
0009: use App\Authorization\EffectiveProfileResolver;
0010: use App\Authorization\EvaluationOrganizationRequest;
0011: use App\Authorization\EvaluationOrganizationResolver;
0012: use App\Authorization\ReadOnlyAuthorizationContext;
0013: use App\Authorization\ResourceDomain;
0014: use App\Authorization\ShieldIdentityAdapter;
0015: use App\Modules\Authorization\Decision\Application\Ports\PbacDecisionPort;
0016: use App\Exceptions\DomainException;
0017: use App\Infrastructure\Id\PositiveId;
0018: use App\Infrastructure\Mutation\OperationContext;
0019: use App\Repositories\EmployeeRepository;
0020: use App\Repositories\OrganizationRepository;
0021: use App\Repositories\PolicyRepository;
0022: use App\Repositories\ResourceCatalogRepository;
0023: use App\Services\EmployeeAssignmentService;
0024: use App\Services\OrganizationService;
0025: use App\Services\ResourceWorkflowStateResolver;
```

Lines 50-156:
```text
0050:     private EffectiveProfileResolver $profiles;
0051:     private EvaluationOrganizationResolver $contexts;
0052:     private PolicyRepository $policies;
0053:     private ResourceCatalogRepository $catalog;
0054:     private EmployeeRepository $employees;
0055:     private EmployeeAssignmentService $assignments;
0056:     private OrganizationService $organizations;
0057:     private ShieldIdentityAdapter $identities;
0058:     private ResourceWorkflowStateResolver $workflowStates;
0059:     private ?ReadOnlyAuthorizationContext $readOnlyContext = null;
0060: 
0061:     /** @param ConnectionInterface<mixed, mixed>|null $db */
0062:     public function __construct(
0063:         ?EffectiveProfileResolver $profiles = null,
0064:         ?EvaluationOrganizationResolver $contexts = null,
0065:         ?PolicyRepository $policies = null,
0066:         ?EmployeeRepository $employees = null,
0067:         ?EmployeeAssignmentService $assignments = null,
0068:         ?OrganizationService $organizations = null,
0069:         ?ShieldIdentityAdapter $identities = null,
0070:         ?ConnectionInterface $db = null,
0071:         ?ResourceCatalogRepository $catalog = null,
0072:         ?ResourceWorkflowStateResolver $workflowStates = null,
0073:     ) {
0074:         $connection = $db ?? db_connect();
0075:         $this->profiles = $profiles ?? new EffectiveProfileResolver($connection);
0076:         $this->policies = $policies ?? new PolicyRepository($connection);
0077:         $this->catalog = $catalog ?? new ResourceCatalogRepository($connection);
0078:         $this->workflowStates = $workflowStates ?? new ResourceWorkflowStateResolver($connection);
0079:         $this->employees = $employees ?? new EmployeeRepository($connection);
0080:         $this->assignments = $assignments ?? new EmployeeAssignmentService(null, $connection);
0081:         $this->organizations = $organizations ?? new OrganizationService(null, $connection);
0082:         $this->identities = $identities ?? new ShieldIdentityAdapter($connection);
0083:         $this->contexts = $contexts ?? new EvaluationOrganizationResolver(
0084:             new OrganizationRepository($connection),
0085:             $this->employees,
0086:             null,
0087:             $connection,
0088:         );
0089:     }
0090: 
0091:     /**
0092:      * Evaluate multiple read-only records against one short-lived snapshot of
0093:      * IAM/assignment/policy lookups. Intended ONLY for list/read operations
0094:      * that perform no authorization-affecting writes inside $read. Never use
0095:      * for create/edit/setstate/delete or for a transaction that mutates rights.
0096:      * An exception cannot leak the snapshot into the next request or action.
0097:      *
0098:      * @template T
0099:      * @param callable(): T $read
0100:      * @return T
0101:      */
0102:     public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed
0103:     {
0104:         if ($this->readOnlyContext !== null) {
0105:             throw new \LogicException('Nested authorization read batches are not supported.');
0106:         }
0107:         $this->readOnlyContext = new ReadOnlyAuthorizationContext($operation);
0108:         try {
0109:             return $read();
0110:         } finally {
0111:             $this->readOnlyContext = null;
0112:         }
0113:     }
0114: 
0115:     /**
0116:      * @template T
0117:      * @param callable(): T $loader
0118:      * @return T
0119:      */
0120:     private function readThrough(?ReadOnlyAuthorizationContext $context, string $bucket, string $key, callable $loader): mixed
0121:     {
0122:         return $context === null ? $loader() : $context->remember($bucket, $key, $loader);
0123:     }
0124: 
0125:     public function authorize(
0126:         OperationContext $operation,
0127:         string $resourceType,
0128:         string $action,
0129:         EvaluationOrganizationRequest $contextRequest,
0130:         mixed $stateId = null,
0131:     ): AuthorizationDecision {
0132:         if ($operation->actorUserId === null) {
0133:             throw new DomainException(self::REASON_CONTEXT_REQUIRED, ['field' => 'actor_user_id']);
0134:         }
0135:         $userId = PositiveId::normalize($operation->actorUserId, 'user_id', self::REASON_CONTEXT_REQUIRED);
0136:         $resource = $this->token($resourceType, 'resource', self::ERROR_RESOURCE_INVALID);
0137:         $resourceAction = $this->token($action, 'action', self::ERROR_ACTION_INVALID);
0138:         // A mutation authorization must invalidate any accidental list
0139:         // snapshot, even if a caller incorrectly mixes reads and writes.
0140:         if ($resourceAction !== 'view') {
0141:             $this->readOnlyContext = null;
0142:         }
0143:         $suppliedState = $stateId === null
0144:             ? null
0145:             : PositiveId::normalize($stateId, 'state_id', self::ERROR_ACTION_INVALID);
0146: 
0147:         // No implicit process-wide or service-wide cache: opt-in only for
0148:         // repeated VIEW decisions inside the same explicit read-only batch.
0149:         $batch = $resourceAction === 'view' && $this->readOnlyContext?->matches($operation)
0150:             ? $this->readOnlyContext
0151:             : null;
0152:         // The old generic Contact Resource is retired. No caller (including an
0153:         // administrative reactivation) may use contact.* as a backdoor.
0154:         if ($resource === 'contact') {
0155:             throw new DomainException('CONTACT_LEGACY_RESOURCE_RETIRED');
0156:         }
```

## S05 - Workflow state provider dependencies

Path: `backend/app/Modules/Workflow/StateResolution/Infrastructure/Persistence/ExistingRepositoryStateReadAdapter.php`
SHA-256: `b61ac2c8f40bbed284b8e935d4997a35852b6e46a88b171a42f045ea98dce7de`

Lines 1-69:
```text
0001: <?php
0002: 
0003: declare(strict_types=1);
0004: 
0005: namespace App\Modules\Workflow\StateResolution\Infrastructure\Persistence;
0006: 
0007: use App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateReadPort;
0008: use App\Repositories\LifecycleRepository;
0009: use App\Repositories\PolicyRepository;
0010: use App\Repositories\ResourceCatalogRepository;
0011: 
0012: /** Preserve repository identity and raw read results; no SQL or business logic here. */
0013: final class ExistingRepositoryStateReadAdapter implements ResourceStateReadPort
0014: {
0015:     public function __construct(
0016:         private readonly ResourceCatalogRepository $resources,
0017:         private readonly LifecycleRepository $lifecycles,
0018:         private readonly PolicyRepository $policies,
0019:     ) {
0020:     }
0021: 
0022:     /** @return array<string,mixed>|null */
0023:     public function findResourceByType(string $type): ?array
0024:     {
0025:         return $this->resources->findResourceByType($type);
0026:     }
0027: 
0028:     /** @return array<string,mixed>|null */
0029:     public function findResource(int $id): ?array
0030:     {
0031:         return $this->resources->findResource($id);
0032:     }
0033: 
0034:     /** @return array<string,mixed>|null */
0035:     public function findLifecycle(int $id): ?array
0036:     {
0037:         return $this->lifecycles->findLifecycle($id);
0038:     }
0039: 
0040:     /** @return list<array<string,mixed>> */
0041:     public function findLifecycleStates(int $lifecycleId): array
0042:     {
0043:         return $this->lifecycles->findLifecycleStates($lifecycleId);
0044:     }
0045: 
0046:     /** @return array<string,mixed>|null */
0047:     public function findActiveWorkflowState(int $id): ?array
0048:     {
0049:         return $this->lifecycles->findActiveWorkflowState($id);
0050:     }
0051: 
0052:     /** @return array<string,mixed>|null */
0053:     public function findActiveWorkflowStateByCode(string $code): ?array
0054:     {
0055:         return $this->lifecycles->findActiveWorkflowStateByCode($code);
0056:     }
0057: 
0058:     /** @return array<string,mixed>|null */
0059:     public function findWorkflowStateByCode(string $code): ?array
0060:     {
0061:         return $this->lifecycles->findWorkflowStateByCode($code);
0062:     }
0063: 
0064:     /** @return list<array<string,mixed>> */
0065:     public function findAllPoliciesForResource(int $resourceId): array
0066:     {
0067:         return $this->policies->findAll(['resource_id' => $resourceId]);
0068:     }
0069: }
```

## S06 - Identity and group atomicity

Path: `backend/app/Services/ShieldUserIdentityService.php`
SHA-256: `7071288b42ee33abe2021d1dac5c73bceeed66c8a243747509097abf3d4775a2`

Lines 42-115:
```text
0042:         $this->db = $db ?? db_connect();
0043:     }
0044: 
0045:     /**
0046:      * @param array<string, mixed> $payload
0047:      * @return array<string, mixed>
0048:      */
0049:     public function create(array $payload): array
0050:     {
0051:         $operation = $this->operation();
0052:         $this->authorize($operation, 'create');
0053:         $this->assertFields($payload, ['username', 'email', 'password', 'state', 'name', 'group_ids']);
0054:         $username = $this->username($payload['username'] ?? null);
0055:         $email = $this->email($payload['email'] ?? null);
0056:         $state = $this->state($payload['state'] ?? null);
0057:         $password = $this->password($payload['password'] ?? null);
0058:         $name = array_key_exists('name', $payload) ? UserDisplayName::normalize($payload['name']) : null;
0059:         $groupIds = array_key_exists('group_ids', $payload)
0060:             ? UserGroupSelection::normalize($payload['group_ids']) : [];
0061:         $this->assertUnique($username, $email);
0062:         // Acquire target group row locks in a stable order across requests.
0063:         sort($groupIds, SORT_NUMERIC);
0064: 
0065:         $groups = new AuthorizationGroupService(db: $this->db);
0066:         // Check the complete request before a new identity is saved.
0067:         foreach ($groupIds as $groupId) {
0068:             $groups->assertAssignableForUserCreation((int) $groupId, $operation);
0069:         }
0070:         if ($groupIds !== [] && ! $this->db instanceof BaseConnection) {
0071:             throw new DomainException('IAM_PERSISTENCE_FAILED');
0072:         }
0073: 
0074:         $users = $this->users();
0075:         $user = $users->createNewUser([
0076:             'username' => $username,
0077:             'active' => $state === 'ACTIVE',
0078:             'status' => strtolower($state),
0079:             'full_name' => $name,
0080:         ]);
0081:         $user->setEmail($email);
0082:         $this->assertPassword($password, $user);
0083:         $user->setPassword($password);
0084: 
0085:         $this->transaction(function () use ($users, $user, $groups, $groupIds, $operation): void {
0086:             if (! $users->save($user)) {
0087:                 throw new DomainException('IAM_PERSISTENCE_FAILED');
0088:             }
0089:             // Shield's UserModel inserts a clone of the entity. The original
0090:             // User does not receive an ID from save(); the model holds it.
0091:             $insertedId = (int) $users->getInsertID();
0092:             if ($insertedId < 1) {
0093:                 throw new DomainException('IAM_PERSISTENCE_FAILED');
0094:             }
0095:             $user->id = $insertedId;
0096:             // Serialize group status changes while assigning. Membership is additive,
0097:             // NEVER a replacement of all existing members.
0098:             foreach ($groupIds as $groupId) {
0099:                 $groups->assertAssignableForUserCreation((int) $groupId, $operation, true);
0100:                 if (! $groups->assignMember((int) $groupId, (int) $user->id, true, $operation)) {
0101:                     throw new DomainException('IAM_PERSISTENCE_FAILED');
0102:                 }
0103:             }
0104:             // A newly created user without requested groups must have no implicit
0105:             // Shield group membership, irrespective of AuthGroups.defaultGroup.
0106:             if ($groupIds === [] && $this->db->table((string) config('Auth')->tables['groups_users'])
0107:                 ->where('user_id', (int) $user->id)->countAllResults() !== 0) {
0108:                 throw new DomainException('IAM_PERSISTENCE_FAILED');
0109:             }
0110:         });
0111: 
0112:         return $this->output($user->id);
0113:     }
0114: 
0115:     /**
```

## S07 - Authorization audit semantics

Path: `backend/app/Authorization/AuthorizationAuditService.php`
SHA-256: `ab912cbfa2880394313a1ca72ad334027ffb4b03e02bf7866cbc6c6af2cedc4d`

Lines 1-66:
```text
0001: <?php
0002: 
0003: declare(strict_types=1);
0004: 
0005: namespace App\Authorization;
0006: 
0007: use App\Exceptions\DomainException;
0008: use App\Infrastructure\Id\PositiveId;
0009: use App\Repositories\AuthorizationLogRepository;
0010: use App\Repositories\ResourceCatalogRepository;
0011: use App\Services\LifecycleAuditPort;
0012: use CodeIgniter\Database\ConnectionInterface;
0013: 
0014: /** Maps trusted internal decisions into the immutable P1 audit schema. */
0015: final class AuthorizationAuditService implements LifecycleAuditPort
0016: {
0017:     public const ERROR_DECISION_INVALID = 'AUTHORIZATION_AUDIT_DECISION_INVALID';
0018:     public const ERROR_RESOURCE_INVALID = 'AUTHORIZATION_AUDIT_RESOURCE_INVALID';
0019: 
0020:     private ResourceCatalogRepository $catalog;
0021:     private AuthorizationLogRepository $logs;
0022: 
0023:     /** @param ConnectionInterface<mixed, mixed>|null $db */
0024:     public function __construct(
0025:         ?AuthorizationLogRepository $logs = null,
0026:         ?ResourceCatalogRepository $catalog = null,
0027:         ?ConnectionInterface $db = null,
0028:     ) {
0029:         $connection = $db ?? db_connect();
0030:         $this->logs = $logs ?? new AuthorizationLogRepository($connection);
0031:         $this->catalog = $catalog ?? new ResourceCatalogRepository($connection);
0032:     }
0033: 
0034:     public function recordIfRequired(AuthorizationDecision $decision): ?int
0035:     {
0036:         if (! AuthorizationAuditScope::requiresPersistence($decision->resourceAction)) {
0037:             return null;
0038:         }
0039: 
0040:         $resource = $this->catalog->findResourceByType($decision->resourceType);
0041:         if ($resource === null) {
0042:             throw new DomainException(self::ERROR_RESOURCE_INVALID, ['resource' => $decision->resourceType]);
0043:         }
0044:         $resourceId = PositiveId::normalize($resource['id'] ?? null, 'resource_id', self::ERROR_RESOURCE_INVALID);
0045:         if (! in_array($decision->decision, ['ALLOW', 'DENY'], true)) {
0046:             throw new DomainException(self::ERROR_DECISION_INVALID, ['decision' => $decision->decision]);
0047:         }
0048: 
0049:         $matchedPolicyId = count($decision->decisivePolicyIds) === 1
0050:             ? $decision->decisivePolicyIds[0]
0051:             : null;
0052: 
0053:         return $this->logs->insert([
0054:             'user_id' => $decision->userId,
0055:             'organization_id' => $decision->evaluationOrganizationId,
0056:             'resource_id' => $resourceId,
0057:             'action' => $decision->resourceAction,
0058:             'state_id' => $decision->stateId,
0059:             'decision' => $decision->decision,
0060:             'matched_policy_id' => $matchedPolicyId,
0061:             'reason' => $decision->reasonCode,
0062:             'request_id' => $decision->requestId,
0063:             'created_at' => $decision->evaluationTime->utc()->format('Y-m-d H:i:s'),
0064:         ]);
0065:     }
0066: }
```

## S08 - Configuration audit persistence

Path: `backend/app/Modules/Settings/Display/Infrastructure/SqlConfigurationAuditEventRepository.php`
SHA-256: `b52c6d29d9506f51fca620cb2a96aed67b4ac00ff4645807a8288f2d49bb0304`

Lines 1-97:
```text
0001: <?php
0002: 
0003: declare(strict_types=1);
0004: 
0005: namespace App\Modules\Settings\Display\Infrastructure;
0006: 
0007: use App\Exceptions\DomainException;
0008: use App\Exceptions\PersistenceException;
0009: use App\Modules\Settings\Display\Application\Ports\ConfigurationAuditEventWriterInterface;
0010: use App\Modules\Settings\Display\Contracts\ConfigurationAuditErrors;
0011: use CodeIgniter\Database\BaseConnection;
0012: use CodeIgniter\Database\ConnectionInterface;
0013: use JsonException;
0014: 
0015: /** Immutable technical audit sink for configuration mutations. */
0016: final class SqlConfigurationAuditEventRepository implements ConfigurationAuditEventWriterInterface
0017: {
0018:     public const ERROR_PERSISTENCE_FAILED = ConfigurationAuditErrors::ERROR_PERSISTENCE_FAILED;
0019:     public const ERROR_FIELD_UNSUPPORTED = ConfigurationAuditErrors::ERROR_FIELD_UNSUPPORTED;
0020:     public const ERROR_FIELD_REQUIRED = ConfigurationAuditErrors::ERROR_FIELD_REQUIRED;
0021: 
0022:     /** @var list<string> */
0023:     private const FIELDS = [
0024:         'configuration_type', 'context_type', 'organization_id', 'setting_key',
0025:         'operation', 'old_value_json', 'new_value_json', 'actor_user_id',
0026:         'request_id', 'created_at',
0027:     ];
0028: 
0029:     /** @var ConnectionInterface<mixed, mixed> */
0030:     private ConnectionInterface $db;
0031: 
0032:     /** @param ConnectionInterface<mixed, mixed>|null $db */
0033:     public function __construct(?ConnectionInterface $db = null)
0034:     {
0035:         $this->db = $db ?? db_connect();
0036:     }
0037: 
0038:     /** @param array<string, mixed> $event */
0039:     public function insert(array $event): int
0040:     {
0041:         foreach ($event as $field => $_value) {
0042:             if (! in_array($field, self::FIELDS, true)) {
0043:                 throw new DomainException(self::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
0044:             }
0045:         }
0046:         foreach (['configuration_type', 'context_type', 'setting_key', 'operation', 'actor_user_id', 'created_at'] as $field) {
0047:             if (! array_key_exists($field, $event)) {
0048:                 throw new DomainException(self::ERROR_FIELD_REQUIRED, ['field' => $field]);
0049:             }
0050:         }
0051: 
0052:         try {
0053:             if (! $this->db->table('configuration_audit_events')->insert($event)) {
0054:                 throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
0055:             }
0056:             if (! $this->db instanceof BaseConnection) {
0057:                 throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
0058:             }
0059:             $id = (int) $this->db->insertID();
0060:         } catch (PersistenceException|DomainException $exception) {
0061:             throw $exception;
0062:         } catch (\Throwable $exception) {
0063:             throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert', $exception);
0064:         }
0065: 
0066:         if ($id < 1) {
0067:             throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
0068:         }
0069: 
0070:         return $id;
0071:     }
0072: 
0073:     /** @return list<array<string, mixed>> */
0074:     public function findAll(): array
0075:     {
0076:         $result = $this->db->table('configuration_audit_events')->orderBy('id', 'ASC')->get();
0077:         if ($result === false) {
0078:             throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAll');
0079:         }
0080: 
0081:         return array_values($result->getResultArray());
0082:     }
0083: 
0084:     /** @param mixed $value */
0085:     public static function encodeValue(mixed $value): ?string
0086:     {
0087:         if ($value === null) {
0088:             return null;
0089:         }
0090: 
0091:         try {
0092:             return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
0093:         } catch (JsonException $exception) {
0094:             throw new DomainException(self::ERROR_FIELD_REQUIRED, ['field' => 'value'], $exception);
0095:         }
0096:     }
0097: }
```

## S09 - Business timezone provider

Path: `backend/app/Modules/Numbering/Scope/Infrastructure/Settings/CodeIgniterBusinessTimezoneResolver.php`
SHA-256: `a12681abdc9a0b43ef2949114eac8dd4e1ad6bb3593b3453f6eb9dd8a76d2bcf`

Lines 1-66:
```text
0001: <?php
0002: 
0003: declare(strict_types=1);
0004: 
0005: namespace App\Modules\Numbering\Scope\Infrastructure\Settings;
0006: 
0007: use App\Modules\Numbering\Scope\Application\Ports\BusinessTimezoneProviderInterface;
0008: use CodeIgniter\Database\Exceptions\DatabaseException;
0009: use CodeIgniter\Settings\Settings;
0010: use DateTimeZone;
0011: use InvalidArgumentException;
0012: 
0013: final class CodeIgniterBusinessTimezoneResolver implements BusinessTimezoneProviderInterface
0014: {
0015:     public const DEFAULT_GROUP_TIMEZONE = 'Asia/Ho_Chi_Minh';
0016: 
0017:     public function __construct(private readonly Settings $settings)
0018:     {
0019:     }
0020: 
0021:     public function resolve(?int $companyId, ?int $groupId = null): ?DateTimeZone
0022:     {
0023:         $contexts = [];
0024:         if ($companyId !== null) {
0025:             $contexts[] = "company:$companyId";
0026:         }
0027:         if ($groupId !== null) {
0028:             $contexts[] = "group:$groupId";
0029:         }
0030:         foreach ($contexts as $context) {
0031:             $timezone = $this->readSetting($context);
0032:             if (is_string($timezone) && $timezone !== '') {
0033:                 return $this->timezone($timezone);
0034:             }
0035:         }
0036: 
0037:         $timezone = $this->readSetting();
0038:         if (is_string($timezone) && $timezone !== '') {
0039:             return $this->timezone($timezone);
0040:         }
0041: 
0042:         if ($groupId !== null) {
0043:             return $this->timezone(self::DEFAULT_GROUP_TIMEZONE);
0044:         }
0045: 
0046:         return null;
0047:     }
0048: 
0049:     private function timezone(string $name): DateTimeZone
0050:     {
0051:         try {
0052:             return new DateTimeZone($name);
0053:         } catch (\Exception $exception) {
0054:             throw new InvalidArgumentException('Invalid business timezone: ' . $name, 0, $exception);
0055:         }
0056:     }
0057: 
0058:     private function readSetting(?string $context = null): mixed
0059:     {
0060:         try {
0061:             return $this->settings->get('App.businessTimezone', $context);
0062:         } catch (DatabaseException) {
0063:             return null;
0064:         }
0065:     }
0066: }
```

## S10 - Settings organization inheritance

Path: `backend/app/Modules/Settings/Display/Application/DisplaySettingsResolver.php`
SHA-256: `9fcb2b77f302dd7e917d370f0dec5ac48c903f74bd4432ea6c9fa15aeea42c29`

Lines 1-96:
```text
0001: <?php
0002: 
0003: declare(strict_types=1);
0004: 
0005: namespace App\Modules\Settings\Display\Application;
0006: 
0007: use App\Infrastructure\Settings\DisplaySettingRegistry;
0008: use App\Infrastructure\Settings\DisplaySettingsContext;
0009: use App\Modules\Settings\Display\Application\Ports\DisplaySettingsStorageInterface;
0010: use App\Services\OrganizationService;
0011: use InvalidArgumentException;
0012: 
0013: /**
0014:  * Resolves presentation-only Display Settings along the canonical Organization path.
0015:  */
0016: final class DisplaySettingsResolver
0017: {
0018:     private readonly DisplaySettingsStorageInterface $storage;
0019:     private readonly OrganizationService $organizations;
0020: 
0021:     public function __construct(
0022:         DisplaySettingsStorageInterface $storage,
0023:         OrganizationService $organizations,
0024:     ) {
0025:         $this->storage = $storage;
0026:         $this->organizations = $organizations;
0027:     }
0028: 
0029:     /**
0030:      * Resolve one registered setting for an optional current Organization.
0031:      */
0032:     public function resolve(string $key, int|string|null $organizationId = null): mixed
0033:     {
0034:         DisplaySettingRegistry::definition($key);
0035: 
0036:         foreach ($this->contexts($organizationId) as $context) {
0037:             $value = $this->storage->get($key, $context);
0038:             if ($value === null) {
0039:                 continue;
0040:             }
0041: 
0042:             try {
0043:                 DisplaySettingRegistry::validate($key, $value);
0044:             } catch (InvalidArgumentException) {
0045:                 // Invalid legacy rows never override an inherited valid value.
0046:                 continue;
0047:             }
0048: 
0049:             return $value;
0050:         }
0051: 
0052:         return DisplaySettingRegistry::default($key);
0053:     }
0054: 
0055:     /**
0056:      * Resolve the complete registered presentation snapshot.
0057:      *
0058:      * @return array<string, mixed>
0059:      */
0060:     public function resolveAll(int|string|null $organizationId = null): array
0061:     {
0062:         $settings = [];
0063:         foreach (DisplaySettingRegistry::keys() as $key) {
0064:             $settings[$key] = $this->resolve($key, $organizationId);
0065:         }
0066: 
0067:         return $settings;
0068:     }
0069: 
0070:     /** @return list<DisplaySettingsContext> */
0071:     public function contextsFor(int|string|null $organizationId = null): array
0072:     {
0073:         return $this->contexts($organizationId);
0074:     }
0075: 
0076:     /** @return list<DisplaySettingsContext> */
0077:     private function contexts(int|string|null $organizationId): array
0078:     {
0079:         $contexts = [];
0080:         if ($organizationId !== null) {
0081:             $organization = DisplaySettingsContext::organization($organizationId);
0082:             $normalizedId = $organization->organizationId();
0083:             if ($normalizedId === null || filter_var($normalizedId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
0084:                 throw new InvalidArgumentException('Organization ID is outside the runtime integer range.');
0085:             }
0086: 
0087:             foreach (array_reverse($this->organizations->resolveAncestorChain((int) $normalizedId)) as $node) {
0088:                 $contexts[] = DisplaySettingsContext::organization($node['id'] ?? null);
0089:             }
0090:         }
0091: 
0092:         $contexts[] = DisplaySettingsContext::system();
0093: 
0094:         return $contexts;
0095:     }
0096: }
```

## S11 - Import job and rows queries

Path: `backend/app/Services/Api060ProductImportService.php`
SHA-256: `6b6baf5d5425b46107c340459b2c25f0af5abc1b4bd86448ca48c12418c919e2`

Lines 78-178:
```text
0078:     public function list(array $query = []): array
0079:     {
0080:         $stateAware = $this->requiresImportJobStateAuthorization();
0081:         if (! $stateAware) {
0082:             $this->authorizeView(['entity_type' => ProductImportJobService::ENTITY_PRODUCT]);
0083:         }
0084:         [$query, $dateFilters] = $this->normalizeJobQuery($query);
0085:         $this->validateFilterEnum($query, 'mode', [
0086:             ProductImportJobService::MODE_CREATE_ONLY,
0087:             ProductImportJobService::MODE_UPDATE_ONLY,
0088:             ProductImportJobService::MODE_UPSERT,
0089:         ]);
0090:         $this->validateFilterEnum($query, 'status', [
0091:             ProductImportJobService::STATUS_PENDING,
0092:             ProductImportJobService::STATUS_PROCESSING,
0093:             ProductImportJobService::STATUS_COMPLETED,
0094:             ProductImportJobService::STATUS_COMPLETED_WITH_ERRORS,
0095:             ProductImportJobService::STATUS_FAILED,
0096:         ]);
0097:         // Use a single actor/evaluation instant across all rows in this list.
0098:         $operation = $stateAware ? $this->operation() : null;
0099:         $rows = array_map(
0100:             fn (array $row): array => $this->jobOutput($row),
0101:             array_values(array_filter(
0102:                 $this->jobs()->findAll(),
0103:                 fn (array $row): bool => $this->isVisibleJob($row, $dateFilters)
0104:                     && (! $stateAware || $this->authorization()->authorize(
0105:                         $operation,
0106:                         'import_job',
0107:                         'view',
0108:                         new EvaluationOrganizationRequest(persistedTarget: $row),
0109:                     )->isAllowed()),
0110:             )),
0111:         );
0112: 
0113:         return $this->paginate(
0114:             $rows,
0115:             $query,
0116:             new CommonListQueryAllowlist(
0117:                 search: ['source_file_name', 'status', 'mode'],
0118:                 filters: ['mode', 'status', 'created_by'],
0119:                 sort: ['id', 'mode', 'status', 'created_by', 'created_at'],
0120:             ),
0121:         );
0122:     }
0123: 
0124:     /** @return array<string, mixed> */
0125:     public function get(int $id): array
0126:     {
0127:         $job = $this->requireJob($id);
0128:         $this->authorizeView($job);
0129: 
0130:         return $this->jobOutput($job);
0131:     }
0132: 
0133:     /** @param array<string, mixed> $query
0134:      *  @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
0135:      */
0136:     public function rows(int $id, array $query = []): array
0137:     {
0138:         $job = $this->requireJob($id);
0139:         $this->authorizeView($job);
0140:         $normalizedQuery = $query;
0141:         $this->validateFilterEnum($normalizedQuery, 'row_status', ['SUCCESS', 'ERROR', 'SKIPPED']);
0142:         if (! isset($normalizedQuery['sort'])) {
0143:             $normalizedQuery['sort'] = 'row_number';
0144:         }
0145:         $allowlist = new CommonListQueryAllowlist(
0146:             search: ['entity_key'],
0147:             filters: ['row_status'],
0148:             sort: ['row_number', 'row_status', 'entity_key', 'target_ref_id', 'created_at'],
0149:         );
0150:         $parsed = ($this->queryAdapter ?? new CommonListQueryAdapter())->parse($normalizedQuery, $allowlist);
0151:         if ($this->canPageImportRowsInSql($job, $parsed)) {
0152:             $statuses = $parsed->filterValues('row_status');
0153:             $direction = $parsed->sortTerms()[0]->direction;
0154:             $total = $this->rowRepository()->countPageRows($id, $statuses);
0155:             $page = $this->rowRepository()->findPageRows(
0156:                 $id, $parsed->pageSize, $parsed->offset, $direction, $statuses,
0157:             );
0158: 
0159:             return [
0160:                 'items' => array_map(fn (array $row): array => $this->rowOutput($row), $page),
0161:                 'page' => $parsed->page,
0162:                 'page_size' => $parsed->pageSize,
0163:                 'total' => (string) $total,
0164:             ];
0165:         }
0166: 
0167:         // Compatibility path: live jobs, Unicode search, non-numeric sorts,
0168:         // and noncanonical status values retain the original PHP semantics.
0169:         $rowData = array_map(
0170:             fn (array $row): array => $this->rowOutput($row),
0171:             $this->rowRepository()->findByJobId($id),
0172:         );
0173: 
0174:         return $this->paginate($rowData, $normalizedQuery, $allowlist);
0175:     }
0176: 
0177:     /**
0178:      * Safe SQL subset: a terminal job cannot acquire more row results during
```

## S12 - Frontend route owners

Path: `frontend/src/router/routes.ts`
SHA-256: `ce63bd3cb88df18ba557dc50554b4e33a6fce0ab5f3c0abe3f4609dff40576fc`

Lines 1-201:
```text
0001: import type { RouteRecordRaw } from 'vue-router'
0002: 
0003: import AppShell from '@/components/AppShell.vue'
0004: import { breadcrumbTrails } from '@/router/breadcrumbs'
0005: 
0006: // Preserve lazy imports, path priority, route names and metadata.
0007: // Authentication/dirty navigation behavior lives in router/index.ts.
0008: export const routes: RouteRecordRaw[] = [
0009:   {
0010:     path: '/login',
0011:     name: 'login',
0012:     component: () => import('@/features/auth/pages/LoginView.vue'),
0013:     meta: { public: true, closable: false },
0014:   },
0015:   {
0016:     path: '/',
0017:     component: AppShell,
0018:     children: [
0019:       {
0020:         path: '',
0021:         name: 'dashboard',
0022:         component: () => import('@/features/workspace/pages/DashboardView.vue'),
0023:         meta: { labelKey: 'nav.dashboard', closable: false },
0024:       },
0025:       {
0026:         path: 'profile',
0027:         name: 'profile',
0028:         component: () => import('@/features/account/pages/ProfileView.vue'),
0029:         meta: { labelKey: 'shell.profile', breadcrumbTrail: breadcrumbTrails.account },
0030:       },
0031:       {
0032:         path: 'profile/display-preferences',
0033:         name: 'display-preferences',
0034:         component: () => import('@/features/account/pages/DisplayPreferencesView.vue'),
0035:         meta: {
0036:           labelKey: 'shell.displayPreferences',
0037:           breadcrumbTrail: breadcrumbTrails.displayPreferences,
0038:         },
0039:       },
0040:       {
0041:         path: 'system/display-settings',
0042:         name: 'display-settings',
0043:         component: () => import('@/features/settings/pages/DisplaySettingsView.vue'),
0044:         meta: {
0045:           labelKey: 'nav.displaySettings',
0046:           breadcrumbTrail: breadcrumbTrails.configuration,
0047:         },
0048:       },
0049:       {
0050:         path: 'security/users',
0051:         name: 'security-users',
0052:         component: () => import('@/features/security/pages/SecurityUsersView.vue'),
0053:         meta: { labelKey: 'nav.securityUsers', breadcrumbTrail: breadcrumbTrails.security },
0054:       },
0055:       {
0056:         path: 'security/authorization-groups',
0057:         name: 'security-authorization-groups',
0058:         component: () => import('@/features/security/pages/SecurityAuthorizationGroupsView.vue'),
0059:         meta: { labelKey: 'nav.authorizationGroups', breadcrumbTrail: breadcrumbTrails.security },
0060:       },
0061:       {
0062:         path: 'security/resources',
0063:         name: 'security-resources',
0064:         component: () => import('@/features/security/pages/SecurityResourcesView.vue'),
0065:         meta: { labelKey: 'nav.resources', breadcrumbTrail: breadcrumbTrails.security },
0066:       },
0067:       {
0068:         path: 'security/policies',
0069:         name: 'security-policies',
0070:         component: () => import('@/features/security/pages/SecurityPoliciesView.vue'),
0071:         meta: { labelKey: 'nav.policies', breadcrumbTrail: breadcrumbTrails.security },
0072:       },
0073:       {
0074:         path: 'security/authorization-logs',
0075:         name: 'security-authorization-logs',
0076:         component: () => import('@/features/security/pages/SecurityAuthorizationLogsView.vue'),
0077:         meta: { labelKey: 'nav.authorizationLogs', breadcrumbTrail: breadcrumbTrails.security },
0078:       },
0079:       {
0080:         path: 'configuration/workflow-states',
0081:         name: 'configuration-workflow-states',
0082:         component: () => import('@/features/workflow/pages/WorkflowStatesView.vue'),
0083:         meta: { labelKey: 'nav.workflowStates', breadcrumbTrail: breadcrumbTrails.configuration },
0084:       },
0085:       {
0086:         path: 'configuration/lifecycles',
0087:         name: 'configuration-lifecycles',
0088:         component: () => import('@/features/workflow/pages/LifecyclesView.vue'),
0089:         meta: { labelKey: 'nav.lifecycles', breadcrumbTrail: breadcrumbTrails.configuration },
0090:       },
0091:       {
0092:         path: 'configuration/auto-numbering',
0093:         name: 'configuration-auto-numbering',
0094:         component: () => import('@/features/workflow/pages/AutoNumberingView.vue'),
0095:         meta: { labelKey: 'nav.autoNumbering', breadcrumbTrail: breadcrumbTrails.configuration },
0096:       },
0097:       {
0098:         path: 'products/groups',
0099:         name: 'products-groups',
0100:         component: () => import('@/features/catalog/pages/ProductGroupsView.vue'),
0101:         meta: { labelKey: 'nav.productGroups', breadcrumbTrail: breadcrumbTrails.products },
0102:       },
0103:       {
0104:         path: 'products/uoms',
0105:         name: 'products-uoms',
0106:         component: () => import('@/features/catalog/pages/ProductUomsView.vue'),
0107:         meta: { labelKey: 'nav.productUoms', breadcrumbTrail: breadcrumbTrails.products },
0108:       },
0109:       {
0110:         path: 'products/price-lists',
0111:         name: 'products-price-lists',
0112:         component: () => import('@/features/catalog/pages/PriceListsView.vue'),
0113:         meta: {
0114:           labelKey: 'nav.productPricePolicies',
0115:           breadcrumbTrail: breadcrumbTrails.products,
0116:         },
0117:       },
0118:       {
0119:         path: 'products/import',
0120:         name: 'products-import',
0121:         component: () => import('@/features/product-import/pages/ProductImportView.vue'),
0122:         meta: { labelKey: 'nav.productImport', breadcrumbTrail: breadcrumbTrails.products },
0123:       },
0124:       {
0125:         path: 'products/import-jobs/:id',
0126:         name: 'products-import-job-detail',
0127:         component: () => import('@/features/product-import/pages/ProductImportJobDetailView.vue'),
0128:         meta: {
0129:           labelKey: 'nav.productImportJobDetail',
0130:           breadcrumbTrail: breadcrumbTrails.importJobDetail,
0131:         },
0132:       },
0133:       {
0134:         path: 'products/import-jobs',
0135:         name: 'products-import-jobs',
0136:         component: () => import('@/features/product-import/pages/ProductImportJobsView.vue'),
0137:         meta: { labelKey: 'nav.productImportJobs', breadcrumbTrail: breadcrumbTrails.products },
0138:       },
0139:       {
0140:         path: 'products/new',
0141:         name: 'products-new',
0142:         component: () => import('@/features/catalog/pages/ProductEditorView.vue'),
0143:         meta: { labelKey: 'nav.productCreate', breadcrumbTrail: breadcrumbTrails.products },
0144:       },
0145:       {
0146:         path: 'products/:id',
0147:         name: 'products-edit',
0148:         component: () => import('@/features/catalog/pages/ProductEditorView.vue'),
0149:         meta: { labelKey: 'nav.productEdit', breadcrumbTrail: breadcrumbTrails.products },
0150:       },
0151:       {
0152:         path: 'products',
0153:         name: 'products',
0154:         component: () => import('@/features/catalog/pages/ProductsView.vue'),
0155:         meta: { labelKey: 'nav.products' },
0156:       },
0157:       {
0158:         path: 'organizations',
0159:         name: 'organizations',
0160:         component: () => import('@/features/organization/pages/OrganizationsView.vue'),
0161:         meta: { labelKey: 'nav.organizations', breadcrumbTrail: breadcrumbTrails.organization },
0162:       },
0163:       {
0164:         path: 'settings/locations/provinces',
0165:         name: 'locations-provinces',
0166:         component: () => import('@/features/organization/pages/LocationsView.vue'),
0167:         meta: { labelKey: 'nav.locations', breadcrumbTrail: breadcrumbTrails.locations },
0168:       },
0169:       {
0170:         path: 'settings/locations/wards',
0171:         name: 'locations-wards',
0172:         component: () => import('@/features/organization/pages/LocationsView.vue'),
0173:         meta: { labelKey: 'nav.wards', breadcrumbTrail: breadcrumbTrails.locations },
0174:       },
0175:       {
0176:         path: 'people/departments',
0177:         name: 'people-departments',
0178:         component: () => import('@/features/people/pages/PeopleDepartmentsView.vue'),
0179:         meta: { labelKey: 'nav.departments', breadcrumbTrail: breadcrumbTrails.people },
0180:       },
0181:       {
0182:         path: 'people/positions',
0183:         name: 'people-positions',
0184:         component: () => import('@/features/people/pages/PeoplePositionsView.vue'),
0185:         meta: { labelKey: 'nav.positions', breadcrumbTrail: breadcrumbTrails.people },
0186:       },
0187:       {
0188:         path: 'people/employees',
0189:         name: 'people-employees',
0190:         component: () => import('@/features/people/pages/PeopleEmployeesView.vue'),
0191:         meta: { labelKey: 'nav.employees', breadcrumbTrail: breadcrumbTrails.people },
0192:       },
0193:       {
0194:         path: ':pathMatch(.*)*',
0195:         name: 'not-found',
0196:         component: () => import('@/app/pages/NotFoundView.vue'),
0197:         meta: { labelKey: 'errors.status.404' },
0198:       },
0199:     ],
0200:   },
0201: ]
```

## S13 - Frontend verification and import boundary

Path: `frontend/package.json`
SHA-256: `363f8a1f7efd3bfa94baff1aaa7b8255aa864aba5fbba13ae256b34dbd58d256`

Lines 1-34:
```text
0001: {
0002:   "name": "vue-project",
0003:   "version": "0.0.0",
0004:   "private": true,
0005:   "type": "module",
0006:   "scripts": {
0007:     "dev": "vite",
0008:     "build": "run-p type-check \"build-only {@}\" --",
0009:     "preview": "vite preview",
0010:     "test:unit": "vitest",
0011:     "test:e2e": "playwright test",
0012:     "build-only": "vite build",
0013:     "bundle:report": "node scripts/report-bundle.mjs",
0014:     "type-check": "vue-tsc --build",
0015:     "lint": "run-s \"lint:*\"",
0016:     "lint:oxlint": "oxlint .",
0017:     "check:imports": "node scripts/check-imports.cjs",
0018:     "check:refactor-baseline": "node --test scripts/refactor/paths.test.cjs scripts/refactor/hash-policy.test.cjs && node scripts/refactor/check-equivalence.cjs",
0019:     "lint:eslint": "eslint . --no-cache",
0020:     "format": "prettier --write --experimental-cli src/",
0021:     "format:check": "prettier --check src/ e2e/",
0022:     "openapi:lint": "redocly lint ../contracts/openapi/openapi.yaml --config ../contracts/openapi/redocly.yaml",
0023:     "openapi:generate": "openapi-ts -f openapi-ts.config.ts",
0024:     "openapi:generate:check": "pnpm run openapi:generate && node scripts/check-openapi-generated.mjs",
0025:     "user:v12-ui:logic:test": "node --experimental-strip-types --test src/features/security/user-editor/__tests__/userCreateFlow.node.mjs src/services/__tests__/userAvatarRules.node.mjs src/services/__tests__/userAvatarTransport.node.mjs src/features/account/profile/profileFields.node.mjs",
0026:     "openapi:v12-contract:test": "node --test scripts/check-user-v12-openapi.test.mjs",
0027:     "check:refactor-external": "node scripts/refactor/check-equivalence.cjs --strict-external",
0028:     "check:v2-baseline": "node --test scripts/refactor/v2-checkpoint.test.cjs && node scripts/refactor/check-v2-checkpoint.cjs --strict",
0029:     "diff:v2-baseline": "node scripts/refactor/check-v2-checkpoint.cjs --report",
0030:     "check:v2-1-shell": "node --test scripts/refactor/v2-1-shell.test.cjs",
0031:     "check:v2-2-product-editor": "node --test scripts/refactor/v2-2-product-editor.test.cjs",
0032:     "check:v2-3-employees": "node --test scripts/refactor/v2-3-employees.test.cjs",
0033:     "check:v2-4-policies": "node --test scripts/refactor/v2-4-policies.test.cjs"
0034:   },
```

Path: `frontend/scripts/check-imports.cjs`
SHA-256: `c43236d78002d7652caab78dc8fc6f1cd13b61a93d2f32be267390bc3798f361`

Lines 1-62:
```text
0001: #!/usr/bin/env node
0002: /** Validate local module boundaries and alias paths without starting Vite or the API. */
0003: const fs = require('node:fs')
0004: const path = require('node:path')
0005: const ts = require('typescript')
0006: const src = path.resolve(__dirname, '../src')
0007: const errors = []
0008: let files = 0
0009: let localImports = 0
0010: const extensions = ['', '.ts', '.tsx', '.vue', '.js', '.mjs', '.json', '.css', '.scss', '/index.ts', '/index.vue', '/index.js']
0011: 
0012: function visitDirectory(dir) {
0013:   for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
0014:     const filename = path.join(dir, entry.name)
0015:     if (entry.isDirectory()) {
0016:       visitDirectory(filename)
0017:     } else if (/\.(?:ts|tsx|vue)$/.test(filename)) {
0018:       checkFile(filename)
0019:     }
0020:   }
0021: }
0022: function checkFile(filename) {
0023:   let code = fs.readFileSync(filename, 'utf8')
0024:   if (filename.endsWith('.vue')) {
0025:     const script = code.match(/<script(?:\s+setup)?[^>]*>([\s\S]*?)<\/script>/)
0026:     if (!script) return
0027:     code = script[1]
0028:   }
0029:   const ast = ts.createSourceFile(filename, code, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS)
0030:   files++
0031:   if (ast.parseDiagnostics.length) errors.push(`Invalid TypeScript syntax: ${path.relative(src, filename)}`)
0032:   function verify(specifier) {
0033:     if (!specifier.startsWith('@/') && !specifier.startsWith('.')) return
0034:     localImports++
0035:     if (specifier.startsWith('@/views/')) {
0036:       errors.push(`Old view alias: ${path.relative(src, filename)} -> ${specifier}`)
0037:     }
0038:     const base = specifier.startsWith('@/')
0039:       ? path.join(src, specifier.slice(2))
0040:       : path.resolve(path.dirname(filename), specifier)
0041:     if (!extensions.some((ext) => fs.existsSync(base + ext) && fs.statSync(base + ext).isFile())) {
0042:       errors.push(`Unresolved local import: ${path.relative(src, filename)} -> ${specifier}`)
0043:     }
0044:     const rel = path.relative(src, filename).replaceAll('\\', '/')
0045:     if (rel.startsWith('services/') && specifier.startsWith('@/features/')) {
0046:       errors.push(`Forbidden service -> feature import: ${rel} -> ${specifier}`)
0047:     }
0048:     if (rel.startsWith('api/generated/') && specifier.startsWith('@/')) {
0049:       errors.push(`Generated SDK must not import hand-written code: ${rel} -> ${specifier}`)
0050:     }
0051:   }
0052:   function walk(node) {
0053:     if ((ts.isImportDeclaration(node) || ts.isExportDeclaration(node)) && node.moduleSpecifier && ts.isStringLiteral(node.moduleSpecifier)) verify(node.moduleSpecifier.text)
0054:     if (ts.isCallExpression(node) && node.arguments.length >= 1 && node.arguments[0] && ts.isStringLiteral(node.arguments[0]) && (node.expression.kind === ts.SyntaxKind.ImportKeyword || (ts.isIdentifier(node.expression) && node.expression.text === 'require'))) verify(node.arguments[0].text)
0055:     ts.forEachChild(node, walk)
0056:   }
0057:   walk(ast)
0058: }
0059: visitDirectory(src)
0060: for (const err of errors) console.error(err)
0061: console.log(`Frontend import gate: ${files} TS/Vue source files; ${localImports} local import edges; ${errors.length} errors`)
0062: if (errors.length) process.exitCode = 1
```

## S14 - Dependency declaration and Decimal

Path: `backend/composer.json`
SHA-256: `23cb63c60c02c0df820fec8e9793ec5830ff12f77f896a8294600954196b054c`

Lines 1-48:
```text
0001: {
0002:     "name": "codeigniter4/appstarter",
0003:     "description": "CodeIgniter4 starter app",
0004:     "license": "MIT",
0005:     "type": "project",
0006:     "homepage": "https://codeigniter.com",
0007:     "support": {
0008:         "forum": "https://forum.codeigniter.com/",
0009:         "source": "https://github.com/codeigniter4/CodeIgniter4",
0010:         "slack": "https://codeigniterchat.slack.com"
0011:     },
0012:     "require": {
0013:         "php": "^8.2",
0014:         "codeigniter4/framework": "^4.7",
0015:         "codeigniter4/shield": "^1.4",
0016:         "phpoffice/phpspreadsheet": "^5.10",
0017:         "smalot/pdfparser": "^2.12"
0018:     },
0019:     "require-dev": {
0020:         "codeigniter/phpstan-codeigniter": "^2.1",
0021:         "fakerphp/faker": "^1.9",
0022:         "mikey179/vfsstream": "^1.6",
0023:         "phpstan/phpstan": "^2.2",
0024:         "phpunit/phpunit": "^10.5.16"
0025:     },
0026:     "autoload": {
0027:         "psr-4": {
0028:             "App\\": "app/",
0029:             "Config\\": "app/Config/"
0030:         },
0031:         "exclude-from-classmap": [
0032:             "**/Database/Migrations/**"
0033:         ]
0034:     },
0035:     "autoload-dev": {
0036:         "psr-4": {
0037:             "Tests\\Support\\": "tests/_support"
0038:         }
0039:     },
0040:     "config": {
0041:         "optimize-autoloader": true,
0042:         "preferred-install": "dist",
0043:         "sort-packages": true
0044:     },
0045:     "scripts": {
0046:         "test": "phpunit"
0047:     }
0048: }
```

Path: `backend/app/Infrastructure/Decimal/Decimal.php`
SHA-256: `cd952c3faae60ffbbcbae47a077291963b1c4af8108d228e175f009c70ffe4ee`

Lines 1-40:
```text
0001: <?php
0002: 
0003: declare(strict_types=1);
0004: 
0005: namespace App\Infrastructure\Decimal;
0006: 
0007: use InvalidArgumentException;
0008: use RuntimeException;
0009: 
0010: final readonly class Decimal
0011: {
0012:     public function __construct(public string $value)
0013:     {
0014:         if (! preg_match('/\A-?(?:0|[1-9]\d*)(?:\.\d+)?\z/', $value)) {
0015:             throw new InvalidArgumentException('Decimal values must be canonical base-10 strings.');
0016:         }
0017:         if (! function_exists('bcadd')) {
0018:             throw new RuntimeException('BCMath is required for authoritative decimal arithmetic.');
0019:         }
0020:     }
0021: 
0022:     public function add(self|string $other, int $scale = 20): self
0023:     {
0024:         return new self(bcadd(self::numeric($this->value), self::valueOf($other), $scale));
0025:     }
0026: 
0027:     public function subtract(self|string $other, int $scale = 20): self
0028:     {
0029:         return new self(bcsub(self::numeric($this->value), self::valueOf($other), $scale));
0030:     }
0031: 
0032:     public function multiply(self|string $other, int $scale = 20): self
0033:     {
0034:         return new self(bcmul(self::numeric($this->value), self::valueOf($other), $scale));
0035:     }
0036: 
0037:     public function divide(self|string $other, int $scale = 20): self
0038:     {
0039:         if (bccomp(self::valueOf($other), self::numeric('0'), $scale) === 0) {
0040:             throw new InvalidArgumentException('Cannot divide by zero.');
```

## S15 - Product and Pricing boundaries

Path: `backend/app/Modules/Product/Product/ProductMasterService.php`
SHA-256: `9c6e6d208838498fcd7f6b84f6ee2baedb57fb3a1563305b2b370ed67cd0586c`

Lines 7-35:
```text
0007: use App\Authorization\AuthorizationAuditService;
0008: use App\Authorization\AuthorizationDecision;
0009: use App\Authorization\AuthorizationService;
0010: use App\Authorization\EvaluationOrganizationContext;
0011: use App\Authorization\EvaluationOrganizationRequest;
0012: use App\Exceptions\ConcurrencyException;
0013: use App\Exceptions\DomainException;
0014: use App\Infrastructure\Decimal\Decimal;
0015: use App\Infrastructure\Id\PositiveId;
0016: use App\Infrastructure\Lock\DomainLockService;
0017: use App\Infrastructure\Lock\MariaDbDomainLockService;
0018: use App\Infrastructure\Mutation\MutationContext;
0019: use App\Infrastructure\Mutation\OperationContext;
0020: use App\Infrastructure\Time\Clock;
0021: use App\Modules\Product\ProductGroup\ProductGroupService;
0022: use App\Modules\Product\Uom\ProductUomMasterService;
0023: use App\Modules\Product\Uom\ProductUomService;
0024: use App\Repositories\AutoNumberingSeriesRepository;
0025: use App\Repositories\OrganizationRepository;
0026: use App\Services\AutoNumberService;
0027: use App\Services\AutoNumberingBusinessMutation;
0028: use App\Services\NumberingScopePeriodContext;
0029: use CodeIgniter\Database\BaseConnection;
0030: use CodeIgniter\Database\ConnectionInterface;
0031: 
0032: /** Authorized Product master mutations for Product Model V2. */
0033: final class ProductMasterService
0034: {
0035:     public const ERROR_NOT_FOUND = 'PRODUCT_NOT_FOUND';
```

Lines 103-139:
```text
0103:     {
0104:         $operation ??= $this->operation();
0105:         $clientHint = $data['evaluation_organization_id'] ?? null;
0106:         // Also covers Import: it calls the domain service directly instead of
0107:         // entering through Api050ProductCatalogService's HTTP normalizer.
0108:         $data = (new \App\Services\InitialCreateStateNormalizer(
0109:             new \App\Services\ResourceWorkflowStateResolver($this->db),
0110:         ))->normalize('product', $data);
0111:         $payload = $this->normalizeCreate($data);
0112:         $decision = $this->authorize($operation, 'create', null, $payload, $clientHint);
0113:         $this->validateCreateReferences($payload);
0114:         $context = $this->numberingContext($decision->evaluationOrganizationId);
0115: 
0116:         $parentId = $payload['parent_product_id'] ?? null;
0117:         if ($parentId !== null) {
0118:             return $this->withStructureLock($parentId, function () use ($payload, $operation, $context): int {
0119:                 return $this->issueAndPersist($payload, $operation, $context);
0120:             });
0121:         }
0122: 
0123:         return $this->issueAndPersist($payload, $operation, $context);
0124:     }
0125: 
0126:     /** @param array<string, mixed> $data */
0127:     public function update(int $id, array $data, ?OperationContext $operation = null): bool
0128:     {
0129:         $operation ??= $this->operation();
0130:         $targetId = PositiveId::normalize($id, 'product_id', self::ERROR_NOT_FOUND);
0131:         $current = $this->existing($targetId);
0132:         $clientHint = $data['evaluation_organization_id'] ?? null;
0133:         if (array_key_exists('status', $data)) {
0134:             throw new DomainException(self::ERROR_STATUS_REQUIRES_SETSTATE, ['field' => 'status']);
0135:         }
0136:         $payload = $this->normalizeUpdate($data);
0137:         $this->authorize($operation, 'edit', $current, $payload, $clientHint);
0138:         $merged = array_merge($current, $payload);
0139:         $this->assertReparentBoundary($current, $merged);
```

Path: `backend/app/Modules/Pricing/Resolution/PricingService.php`
SHA-256: `b7e5c9d3ce444a771389c5255c8ca16adce489513886dedf7d028da75d32ec55`

Lines 1-57:
```text
0001: <?php
0002: 
0003: declare(strict_types=1);
0004: 
0005: namespace App\Modules\Pricing\Resolution;
0006: 
0007: use App\Exceptions\DomainException;
0008: use App\Infrastructure\Id\PositiveId;
0009: use App\Infrastructure\Time\Clock;
0010: use App\Infrastructure\Time\EvaluationTime;
0011: use App\Modules\Pricing\PriceList\PriceListRepository;
0012: use App\Modules\Product\Product\ProductRoleService;
0013: use App\Modules\Product\Uom\ProductUomService;
0014: use App\Services\OrganizationService;
0015: 
0016: /** Canonical read-only Product/UOM/Company selling-price resolution. */
0017: final class PricingService
0018: {
0019:     public const ERROR_NO_VALID_PRICE = 'PRICING_NO_VALID_PRICE';
0020: 
0021:     private const SOURCE_COMPANY = 'PRICE_LIST_COMPANY';
0022:     private const SOURCE_GROUP = 'PRICE_LIST_GROUP';
0023:     private const SOURCE_DEFAULT = 'PRODUCT_DEFAULT';
0024: 
0025:     public function __construct(
0026:         private readonly ?PriceListRepository $priceLists = null,
0027:         private readonly ?ProductRoleService $roles = null,
0028:         private readonly ?ProductUomService $productUoms = null,
0029:         private readonly ?OrganizationService $organizations = null,
0030:         private readonly ?Clock $clock = null,
0031:     ) {
0032:     }
0033: 
0034:     /**
0035:      * Resolve one selling price without authorization, mutation, or caching.
0036:      *
0037:      * @return array{product_id:int,uom_id:int,company_organization_id:int,unit_price:string,price_source:string,price_list_id:int|null}
0038:      */
0039:     public function resolve(
0040:         mixed $productId,
0041:         mixed $uomId,
0042:         mixed $companyOrganizationId,
0043:         ?EvaluationTime $evaluationTime = null,
0044:     ): array {
0045:         $productId = PositiveId::normalize($productId, 'product_id', 'INVALID_REFERENCE');
0046:         $uomId = PositiveId::normalize($uomId, 'uom_id', 'INVALID_REFERENCE');
0047:         $companyOrganizationId = PositiveId::normalize(
0048:             $companyOrganizationId,
0049:             'company_organization_id',
0050:             'INVALID_REFERENCE',
0051:         );
0052:         $evaluationTime ??= EvaluationTime::capture($this->clock ?? service('clock'));
0053: 
0054:         $groupId = $this->validateCompanyAndResolveGroup($companyOrganizationId);
0055:         $product = $this->validateProduct($productId);
0056:         $sellingUom = $this->validateSellingUom($product, $uomId);
0057:         $evaluationInstant = $evaluationTime->utc()->format('Y-m-d H:i:s');
```

Path: `backend/app/Modules/Pricing/ComboAllocation/ComboAllocationService.php`
SHA-256: `65cfaff52401d2ea5924d982db90a4eabc0866861dd435f34c9d92f9715ef7bc`

Lines 1-42:
```text
0001: <?php
0002: 
0003: declare(strict_types=1);
0004: 
0005: namespace App\Modules\Pricing\ComboAllocation;
0006: 
0007: use App\Exceptions\DomainException;
0008: use App\Infrastructure\Decimal\Decimal;
0009: use App\Infrastructure\Id\PositiveId;
0010: use App\Infrastructure\Time\Clock;
0011: use App\Infrastructure\Time\EvaluationTime;
0012: use App\Modules\Pricing\Resolution\PricingService;
0013: use App\Modules\Product\Combo\ComboExpansionService;
0014: use App\Modules\Product\Product\ProductRoleService;
0015: use App\Modules\Product\Uom\ProductUomService;
0016: 
0017: /** Read-only allocation of directly selected COMBO selling amounts to leaves. */
0018: final class ComboAllocationService
0019: {
0020:     public const ERROR_INVALID_ROOT = 'COMBO_ALLOCATION_INVALID_ROOT';
0021:     public const ERROR_INVALID_QUANTITY = 'COMBO_ALLOCATION_INVALID_QUANTITY';
0022:     public const ERROR_INVALID_LEAF_PRICE = 'COMBO_ALLOCATION_INVALID_LEAF_PRICE';
0023:     public const ERROR_TOTAL_WEIGHT = 'COMBO_ALLOCATION_TOTAL_WEIGHT_INVALID';
0024: 
0025:     private const MONEY_SCALE = 2;
0026:     private const DIVISION_SCALE = 40;
0027: 
0028:     public function __construct(
0029:         private readonly ?PricingService $pricing = null,
0030:         private readonly ?ComboExpansionService $expansion = null,
0031:         private readonly ?ProductRoleService $roles = null,
0032:         private readonly ?Clock $clock = null,
0033:     ) {
0034:     }
0035: 
0036:     /**
0037:      * Allocate each directly selected COMBO and aggregate equal leaves.
0038:      *
0039:      * @param list<mixed> $selectedRoots
0040:      * @return list<array{product_id:int,base_uom_id:int,quantity:string,allocated_amount:string,unit_price:string}>
0041:      */
0042:     public function allocate(
```
