<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Modules/Product/Lookup/ProductRelationshipSql.php';
use App\Modules\Product\Lookup\ProductRelationshipSql as S;

$n=0;
$ok = static function(bool $pass, string $name) use (&$n):void {
    if (!$pass) throw new RuntimeException('M7.4 SQL: '.$name);
    ++$n;
};
$invalid=static function(callable $fn,string $name)use($ok):void {
    try{$fn();throw new RuntimeException('Expected SQL guard to reject '.$name);}
    catch(InvalidArgumentException){$ok(true,$name);}
};
$ids=['1','3','18446744073709551615'];
$p=S::productsByIds($ids,['ACTIVE','DRAFT']);
$ok(str_contains($p['sql'],'SELECT p.*, pr.product_type AS parent_type'),'full Product DTO select');
$ok(str_contains($p['sql'],'EXISTS (SELECT 1 FROM products ch'),'derived has-children EXISTS');
$ok(str_contains($p['sql'],'LEFT JOIN products pr'),'child parent-name join');
$ok(str_contains($p['sql'],'UPPER(TRIM(p.status)) COLLATE utf8mb4_bin IN (?, ?)'),'state authorization in WHERE');
$ok(str_contains($p['sql'],'p.id IN (?, ?, ?)'),'bounded numeric ID batch');
$ok($p['binds']===['ACTIVE','DRAFT',...$ids],'exact bigint bind values');
$ok(count($p['binds'])===substr_count($p['sql'],'?'),'full details bind count');
$ok(!str_contains($p['sql'],'18446744073709551615'),'never interpolate user bigint ID');
$none=S::productsByIds([],[]);
$ok(str_contains($none['sql'],'1 = 0') && str_contains($none['sql'],'IN (NULL)'),'empty scope/IDs fail closed');
$rel=S::relationWindows(['2','3'],['7'],['ACTIVE','INACTIVE'],2);
$ok(str_contains($rel['sql'],'ROW_NUMBER() OVER (PARTITION BY rel.owner_id ORDER BY rel.product_id ASC)'),'one rank per owner');
$ok(str_contains($rel['sql'],'COUNT(*) OVER (PARTITION BY rel.owner_id)'),'authorization filtered count');
$ok(strpos($rel['sql'],'WHERE (UPPER(TRIM(child.status))') < strpos($rel['sql'],') ranked WHERE'),'PBAC state before windowed rank count');
$ok(str_contains($rel['sql'],'parent_product_id IN (?, ?)'),'PARENT edges limited');
$ok(str_contains($rel['sql'],'combo_product_id IN (?)'),'COMBO edges limited');
$ok(str_contains($rel['sql'],'JOIN products child'),'child State evaluated from persisted child');
$ok(str_contains($rel['sql'],'ranked.relation_rank <= ?'),'server-side per-owner limit');
$ok($rel['binds']===['2','3','7','ACTIVE','INACTIVE',2],'window bind order');
$ok(count($rel['binds'])===substr_count($rel['sql'],'?'),'relation bind count');
$ok(!str_contains($rel['sql'],'LIMIT 2'),'no global limit mistaken for per-owner limit');
$emptyRel=S::relationWindows([],[],[],1);
$ok(str_contains($emptyRel['sql'],'IN (NULL)') && str_contains($emptyRel['sql'],'1 = 0'),'empty relations fail closed');
$uom=S::salesUoms(['3','4'],['3'],['ACTIVE','DRAFT']);
$ok(str_contains($uom['sql'],'FROM products p WHERE p.id IN (?, ?)'),'only visible Product base UOMs');
$ok(str_contains($uom['sql'],'FROM product_uom_conversions c WHERE c.product_id IN (?)'),'only non-COMBO alternate UOMs');
$ok(str_contains($uom['sql'],"BINARY c.status = 'ACTIVE'"),'exact conversion state');
$ok(str_contains($uom['sql'],"BINARY c.usage_type IN ('SALES', 'BOTH')"),'SALES/BOTH only');
$ok(str_contains($uom['sql'],"BINARY u.status = 'ACTIVE'"),'UOM must be active');
$ok(str_contains($uom['sql'],'UPPER(TRIM(u.status)) COLLATE utf8mb4_bin IN (?, ?)'),'independent UOM PBAC pushed down');
$ok(str_contains($uom['sql'],'ORDER BY candidate.product_id ASC, candidate.priority ASC, candidate.sequence ASC'),'base-first deterministic order');
$ok($uom['binds']===['3','4','3','ACTIVE','DRAFT'],'UOM bind order');
$ok(count($uom['binds'])===substr_count($uom['sql'],'?'),'UOM bind count');
$uomBlocked=S::salesUoms(['1'],[],[]);
$ok(str_contains($uomBlocked['sql'],'1 = 0') && str_contains($uomBlocked['sql'],'IN (NULL)'),'UOM no grant/no conversion fail closed');
$prefixed=S::relationWindows(['1'],['9'],['ACTIVE'],3,'dinktech_');
$ok(str_contains($prefixed['sql'],'FROM dinktech_products'),'Product prefix supported');
$ok(str_contains($prefixed['sql'],'FROM dinktech_product_combo_items'),'Combo prefix supported');
$prefixedUom=S::salesUoms(['1'],['1'],['ACTIVE'],'dinktech_');
$ok(str_contains($prefixedUom['sql'],'JOIN dinktech_product_uoms'),'UOM prefix supported');
$ok(str_contains($prefixedUom['sql'],'FROM dinktech_product_uom_conversions'),'conversion prefix supported');
$invalid(static fn()=>S::productsByIds(['1'],['ACTIVE OR 1=1']),'state injection rejected');
$invalid(static fn()=>S::relationWindows(['1'],[],['ACTIVE'],0),'underflow limit rejected');
$invalid(static fn()=>S::relationWindows(['1'],[],['ACTIVE'],6),'overflow limit rejected');
$invalid(static fn()=>S::salesUoms(['1'],[],['ACTIVE'],'p;DROP'),'prefix injection rejected');
echo 'M7.4 RELATION SQL/PBAC: '.$n.'/'.$n." PASS\n";
