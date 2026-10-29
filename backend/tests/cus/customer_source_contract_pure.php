<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/routes/LegacyRouteSource.php';

/** Source contract smoke checks; no Composer, DB, auth bypass or network required. */
$root=dirname(__DIR__,2);
$n=0;
$ok=static function(bool $condition,string $label) use (&$n):void {
    if(!$condition) throw new RuntimeException('CUS CONTRACT FAIL: '.$label);
    ++$n;
};
$read=static fn(string $path):string => (string)file_get_contents($root.'/'.$path);
$migration=$read('app/Database/Migrations/2026-10-28-000023_CreateCustomerCoreTables.php');
$ok(substr_count($migration, '->createTable(')===3,'exactly 3 CUS tables');
foreach(['customers','customer_company_usages','customer_interactions'] as $table)
    $ok(str_contains($migration,"->createTable('$table'"),'migration table '.$table);
$ok(str_contains($migration,"addForeignKey('corrects_interaction_id'"),'interaction correction FK');
$ok(str_contains($migration,"addPrimaryKey(['customer_id','company_organization_id'])"),'allowlist composite PK');
$ok(!str_contains($migration,'customer_addresses'),'no address table');
$helper=$read('app/Helpers/customer_helper.php');
foreach(['STANDARD','TMDT','VIP1','VIP2'] as $code)
    $ok(str_contains($helper,"'$code'"),'helper '.$code);
$ok(!str_contains($helper,"'VIP' =>"),'no obsolete VIP');
$routes=\DinkERP\Tools\Routes\LegacyRouteSource::source(dirname($root), 'CUSRoutes.php');
$ok(substr_count($routes,'$routes->')===7,'exactly 7 CUS routes');
$ok(\DinkERP\Tools\Routes\LegacyRouteSource::mounted(dirname($root), 'CUSRoutes.php'),'routes mounted');
$access=$read('app/Modules/Access/UnifiedAccessService.php');
foreach(['customer','customer_contact','customer_interaction'] as $type)
    $ok(str_contains($access,"'$type'"),'PBAC supports '.$type);
$contacts=$read('app/Modules/Contact/ContactSql.php');
$ok(str_contains($contacts, "= 'CUSTOMER'"),'separate CUSTOMER Contact branch');
$ok(str_contains($contacts,'cu.id = c.contact_ref_id'),'Contact Owner join');
$ok(str_contains($contacts,'$customerMaster->predicate'),'Customer Parent PBAC before page');
$service=$read('app/Modules/Customer/CustomerService.php');
$ok(str_contains($service,'corrects_interaction_id'),'app correction validation');
$ok(str_contains($service,"->where('company_organization_id'"),'Company interaction row scope');
$ok(str_contains($service,"'happened_at >='"),'event time lower bound');
$ok(str_contains($service,"'happened_at <'"),'event time exclusive upper bound');
$ok(str_contains($service,'GET_LOCK('),'serialized master mutation');
$seeder=$read('app/Database/Seeds/CustomerCoreSeeder.php');
$ok(!str_contains($seeder,"->table('permissions'") && !str_contains($seeder,"->table('auth_groups_users'"),'seeder grants no permissions');
$ok(str_contains($seeder,"'customer_interaction'=>['ORGANIZATION'"),'Company interaction security');
$numberingSeeder=$read('app/Database/Seeds/CustomerDefaultNumberingSeeder.php');
foreach (['CUS_DEFAULT', 'KH{SEQ:06}', 'AUTO_IF_EMPTY', 'GROUP', 'NEVER'] as $token)
    $ok(str_contains($numberingSeeder,$token),'explicit default numbering '.$token);
$ok(str_contains($numberingSeeder,'CUS_NUMBERING_SERIES_EXISTS_NO_DEFAULT_CONFIGURE_IN_UI'),
    'existing numbering configuration never overwritten');
$ok(str_contains($numberingSeeder,'FOR UPDATE'), 'resource row locked for numbering seed');
$ok(!str_contains($numberingSeeder,"->table('permissions'") && !str_contains($numberingSeeder,"->table('auth_groups_users'"),
    'numbering seeder grants no permissions');
$ok(str_contains($read('app/Config/Autoload.php'),"'customer'"),'helper autoload');
$ok(str_contains($read('app/Modules/Customer/CustomerService.php'),'CustomerNumberIssuer'),'P1 Numbering integration');
$ok(str_contains($service,'CustomerRules::initialContact($id,$fields)'),
    'initial Contact generated once during Customer POST');
$ok(str_contains($service,'createCustomerInOwningTransaction($contactInput)'),
    'Contact creation is inside parent Customer transaction');
$ok(str_contains($service,"'primary_contact_id'=>".'$contactId'),
    'main Contact linked as Customer primary');
$ok(str_contains($service,'CUSTOMER_PRIMARY_CONTACT_PERSISTENCE_FAILED'),
    'link failure rolls back Customer transaction');
$ok(str_contains($service,'$this->db->transRollback()'),
    'Customer transaction rolls back all creation steps');
$contactService=$read('app/Modules/Contact/ContactService.php');
$ok(str_contains($contactService,'function createCustomerInOwningTransaction('),
    'P1 Contact exposes write borrowed from owning transaction');
$ok(str_contains($contactService,'transDepth !== 1'),
    'borrowed Contact insert requires existing root transaction');
$ok(str_contains($contactService,"$"."this->permit($"."scope['resource'], 'create', null, $"."scope['target'])"),
    'Contact canonical create permission required');
$ok(str_contains($contactService,'ContactRules::payload($input, true)'),
    'canonical Contact input validation reused');
$ok(str_contains($contactService,"$"."this->states->initialState('customer_contact')"),
    'Contact initial status uses P1 Lifecycle');
$ok(str_contains($contactService,'$this->assertAddress($payload)'),
    'Contact address validation reused');
$ok(str_contains($contactService,'...$this->createdAudit()'),
    'Contact actor/time audit reused');
$ok(str_contains($read('app/Modules/Customer/CustomerRules.php'),"'description'=>self::MAIN_CONTACT_LABEL"),
    'main Contact label produced on backend');
// CUS v1.9.4: SQL-first authorized list and canonical row action hints.
$scopeProvider=$read('app/Modules/Customer/CustomerScopeProvider.php');
$ok(str_contains($scopeProvider, "return 'MASTER'"), 'Customer SQL scope is GROUP MASTER');
$ok(str_contains($scopeProvider, "viewableMasterLifecycleStatuses('customer')"), 'P1 canonical state authorization source');
$service=$read('app/Modules/Customer/CustomerService.php');
$ok(str_contains($service,'AuthorizationSqlScopeCompiler($access)'), 'Customer PBAC SQL compiler');
$ok(str_contains($service,'CustomerSql::collection('), 'Customer list uses SQL plan');
$ok(str_contains($service,"$" . "this->db->query($" . "plan['count_sql']"), 'SQL-scoped count execution');
$ok(str_contains($service,"$" . "this->db->query($" . "plan['rows_sql']"), 'SQL-scoped page execution');
$ok(str_contains($service,"$" . "row['allowed_actions']"), 'Customer list has row action hints');
$ok(str_contains($service,'$byState[$state]'), 'Customer same-State request-local hint cache');
$hints=$read('app/Modules/Access/Query/EffectiveRowActionHints.php');
$ok(str_contains($hints,"'customer' => ['edit', 'setstate']"), 'Customer canonical P1 action hints');
$ok(str_contains($hints,"'customer_contact' => ['edit', 'setstate']"), 'Customer Contact canonical P1 action hints');
$sql=$read('app/Modules/Customer/CustomerSql.php');
$ok(str_contains($sql,'SELECT COUNT(*) AS total'), 'COUNT at DB layer');
$ok(str_contains($sql,'LIMIT ? OFFSET ?'), 'bounded SQL page');
$ok(str_contains($sql,'EXISTS (SELECT 1'), 'indexed EXISTS Company usage filter');
$ok(str_contains($sql,'ESCAPE'), 'escaped SQL LIKE');
$ok(str_contains($sql,'Customer database table prefix'), 'safe DBPrefix guard');
$ok(str_contains($read('tools/test-unit.ps1'), 'customer_sql_plan_pure.php'), 'CUS runner includes SQL tests');
$openapi=$read('../contracts/openapi/p2-cus.design.openapi.yaml');
$ok(str_contains($openapi,'CustomerRowActionHints:'), 'OpenAPI boolean row hint schema');
$ok(str_contains($openapi,'CustomerListResponse:'), 'OpenAPI typed Customer list');
$ok(str_contains($openapi,'CustomerItemResponse:'), 'OpenAPI typed Customer detail');
$ok(str_contains($openapi,"[edit, setstate]"), 'OpenAPI edit/setstate both required');
echo 'P2-CUS-G1 SOURCE CONTRACT: '.$n.'/'.$n." PASS\n";
