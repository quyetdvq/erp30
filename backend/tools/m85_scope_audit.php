<?php

declare(strict_types=1);

/** Conservative source inventory; read-only. No database/network/filesystem writes. */
$root=dirname(__DIR__,2);
$rows=[
 ['Product Master list/detail','backend/app/Modules/Product/Master/ProductReadService.php','ProductReadSql::collection(','SQL_VIEW_PUSHDOWN_M85'],
 ['Product Selector','backend/app/Modules/Product/Lookup/ProductLookupService.php','viewableMasterLifecycleStatuses(','SQL_VIEW_PUSHDOWN_LEGACY'],
 ['Product Relations','backend/app/Modules/Product/Lookup/ProductRelationshipService.php','viewableMasterLifecycleStatuses(','SQL_VIEW_PUSHDOWN_LEGACY'],
 ['Pricing list','backend/app/Modules/Pricing/PriceList/PriceListService.php','authorizedBuckets(','SQL_VIEW_PUSHDOWN_LEGACY_DISCOVERY'],
 ['Product UOM/Group lists','backend/app/Modules/Product/Catalog/ProductCatalogService.php','ProductCatalogSql::collection(','SQL_VIEW_PUSHDOWN_M86A'],
 ['Contact list/detail','backend/app/Modules/Contact/ContactService.php','ContactSql::collection(','SQL_VIEW_PUSHDOWN_M86E'],
 ['Organization/Employee Assignment mandatory SQL','backend/app/Controllers/Api/ReadCatalogController.php',"in_array($" . "resource, ['organization', 'employee_assignment'], true)",'SQL_VIEW_PUSHDOWN_M86B_ALWAYS'],
 ['Employee/Department/Position mandatory SQL','backend/app/Controllers/Api/ReadCatalogController.php',"in_array($" . "resource, ['employee', 'department', 'position'], true)",'SQL_VIEW_PUSHDOWN_M86C_ALWAYS'],
 ['Access Management','backend/app/Modules/Access/Management/AccessReadService.php','AuthorizedListPage::paginate(','IN_MEMORY_BACKLOG'],
 ['Shield User list','backend/app/Modules/Identity/Management/IdentityReadService.php','AuthorizedListPage::paginate(','IN_MEMORY_BACKLOG'],
 ['Workflow catalog','backend/app/Modules/Workflow/Management/WorkflowReadService.php','AuthorizedListPage::paginate(','IN_MEMORY_BACKLOG'],
];
foreach ($rows as [$label,$path,$signal,$status]) {
    $source=@file_get_contents($root.'/'.$path);
    if (!is_string($source) || !str_contains($source,$signal)) {
        fwrite(STDERR,"AUDIT SOURCE DRIFT: $label ($path)\n");exit(1);
    }
    echo $status.' | '.$label.' | '.$path.PHP_EOL;
}
echo "M8.5/M8.6A/M8.6B/M8.6C/M8.6D/M8.6E AUDIT PASS: source inventory only. Remaining modules require dedicated scope providers and live PBAC parity; no DB/HTTP operations.\n";
