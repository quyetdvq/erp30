<?php

declare(strict_types=1);

// SUP-T06 source proof, no database/HTTP. Never substitutes for MariaDB PBAC UAT.
$root=dirname(__DIR__,2);
$source=static function(string $path)use($root):string {
    $content=file_get_contents($root.'/'.$path);
    if (!is_string($content)||$content==='') throw new RuntimeException('Missing T06 source '.$path);
    return $content;
};
$n=0;
$ok=static function(bool $pass,string $label)use(&$n):void {
    if (!$pass) throw new RuntimeException('SUP-T06 SOURCE FAIL '.$label);
    ++$n;
};
$rules=$source('app/Modules/Contact/ContactRules.php');
$scope=$source('app/Modules/Contact/ContactScope.php');
$sql=$source('app/Modules/Contact/ContactSql.php');
$svc=$source('app/Modules/Contact/ContactService.php');
$verifier=$source('tools/m87/M87SqlVerifier.php');
$workflow=$source('app/Modules/Workflow/Execution/BusinessWorkflowService.php');
$assignees=$source('app/Modules/Notifications/WorkflowNotificationAssigneesService.php');
$parent=$source('app/Modules/Contact/SupplierContactParentView.php');
$event=$source('app/Modules/Notifications/Events/WorkflowActionRequiredHandler.php');
$catalog=$source('app/Modules/Access/UnifiedAccessService.php');
$registry=$source('app/Modules/Workflow/Execution/BusinessWorkflowRules.php');
$ok(str_contains($rules,"'SUPPLIER' => 'supplier_contact'"),'pure owner resource map');
$ok(str_contains($rules,'CONTACT_OWNER_TRANSFER_UNSUPPORTED'),'owner immutability');
$ok(str_contains($rules,'CONTACT_STATE_REQUIRES_SETSTATE'),'state mutation only via Workflow');
$ok(str_contains($scope,"$"."owner['type'] === 'SUPPLIER'"),'distinct MASTER owner branch before Employee');
$ok(str_contains($scope,"->table('suppliers')->select('id')"),'persisted parent exists, no arbitrary client scope');
$ok(str_contains($scope,"'resource' => 'supplier_contact', 'target' => ".'$row'),'no synthetic Org ID on Supplier Contact');
$ok(str_contains($svc,'...$this->customerScopes($access)'),'Customer scopes unchanged in production');
$ok(substr_count($svc,'...$this->supplierScopes($access)')===2,'Supplier scopes for COUNT/page and direct ID');
$ok(substr_count($svc,'trustedGroupId: $access->trustedGroupRootId()')===2,'trusted GROUP for both plans');
$ok(str_contains($svc,'supplierParentsForPage($rows)'),'Supplier list batch parent resolution');
$ok(str_contains($svc,'$readScope->resolve($row, $supplierParents)'),'list avoids per-row Supplier resolve SQL');
$ok(str_contains($svc,"->whereIn('id', array_keys(".'$ids'."))->get()"),'batch owner lookup');
$ok(str_contains($svc,'SupplierContactParentView::allowed($access, $parent)'),'canonical Supplier parent guard');
$ok(substr_count($svc,'!$this->supplierParentView($row,$access')===2,'page + detail explicitly check parent');
$ok(str_contains($svc,'! $this->supplierParentView($row, $this->access)'), 'mutation assertVisible rechecks Supplier parent VIEW');
$ok(str_contains($svc,'$scope = $this->assertVisible($old);'), 'edit/setstate use parent-guarded authorization');
$ok(str_contains($svc,'! $this->supplierParentView($payload,$this->access)'),'create checks parent VIEW');
$ok(str_contains($svc,'CONTACT_OWNER_TRANSFER_UNSUPPORTED')===false,'no bypass/exception swallowing in service');
$ok(str_contains($parent,'$access->canView(\'supplier\', $parent)'),'independent parent canonical VIEW');
$ok(str_contains($parent,'$access->trustedGroupRootId()'),'group from persisted authorization context');
$ok(!str_contains($parent,'all_companies') || str_contains($parent,'not a grant'),'Usage cannot grant Contact PBAC');
$ok(str_contains($sql,'su.group_organization_id = ?'),'SQL count/page/detail GROUP constraint');
$ok(str_contains($sql,"'supplier_contact' || ".'$supplierContact'."->domain !== 'MASTER'"),'strict scope resource/domain');
$ok(str_contains($sql,'$supplierMaster->resource !== \'supplier\''),'separate parent resource required');
$ok(str_contains($verifier,"'supplier_contact',".'$access->viewableMasterLifecycleStatuses(\'supplier_contact\')'),'audit Supplier Contact scope');
$ok(str_contains($verifier,"'supplier',".'$access->viewableMasterLifecycleStatuses(\'supplier\')'),'audit parent Supplier scope');
$ok(substr_count($verifier,'$customerContact,$customerMaster,$supplierContact,$supplierMaster,$group)')===2,'audit six scopes both read paths');
$ok(str_contains($verifier,'SupplierContactParentView::allowed($access, $parent)'),'independent oracle Supplier parent PBAC');
$ok(str_contains($verifier,'if ($target[\'resource\'] !== \'customer_contact\') return true;'),'old Customer oracle retained');
$ok(str_contains($verifier,'SET TRANSACTION READ ONLY') && str_contains($verifier,'transRollback()'),'audit remains READ ONLY');
$ok(str_contains($registry,"'supplier_contact'=>'contacts'") && str_contains($registry,"'supplier_contact' => 'SUPPLIER'"),'Workflow source and owner isolation');
$ok(str_contains($workflow,'if ($type === \'supplier_contact\')') && str_contains($workflow,"SupplierContactParentView::allowed"),'workflow action parent guard');
$ok(str_contains($assignees,'if ($resource === \'supplier_contact\')') && str_contains($assignees,"SupplierContactParentView::allowed"),'notification assignees owner guard');
$ok(str_contains($event,'$resource === \'supplier_contact\' && $e->getErrorCode() === \'WF_TARGET_NOT_FOUND\''),'denied parent recipient skipped');
$ok(str_contains($catalog,"'supplier_contact' => 'MASTER'"),'canonical PBAC domain');
$ok(str_contains($catalog,"['supplier', 'supplier_contact']"),'canonical Lifecycle states compiled');
$ok(!str_contains($source('tools/m87/M87Plan.php'),"'supplier_contact'"),'M8.7 nine-resource catalog not expanded');
$ok(!str_contains($source('app/Config/Routes/ApiV1/ContactsRoutes.php'),'suppliers'),'no new routing owner bypass');
$ok(str_contains($source('app/Modules/Access/Query/EffectiveRowActionHints.php'),"'supplier_contact' => ['edit', 'setstate']"),'action hint resource registered');

echo "SUP-T06 CONTACT SOURCE: {$n}/{$n} PASS (offline; NOT DB/HTTP)\n";
