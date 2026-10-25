<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
require_once __DIR__.'/../app/Modules/Pricing/PriceList/PriceListRules.php';

use App\Exceptions\DomainException;
use App\Modules\Pricing\PriceList\PriceListRules as R;

$n=0;
$ok=static function(bool $condition,string $name)use(&$n):void{
    if (!$condition) throw new RuntimeException('M7.3 Pricing rules: '.$name);
    ++$n;
};
$reject=static function(callable $fn,string $code,string $name)use($ok):void{
    try {$fn();$ok(false,$name);} catch(DomainException $e){$ok($e->getErrorCode()===$code,$name);}
};
$ok(R::id('000004')==='4','Price List normalized BIGINT');
$ok(R::id('18446744073709551615')==='18446744073709551615','Price List max BIGINT');
$reject(static fn()=>R::id('0'),'PRICE_LIST_NOT_FOUND','invalid row zero');
$reject(static fn()=>R::id('18446744073709551616'),'PRICE_LIST_NOT_FOUND','row ID overflow');
$create=['code'=>' pl-1 ','name'=>' Biểu giá ','organization_id'=>'0003',
    'effective_from'=>'2026-10-24T12:00:00+07:00','effective_to'=>'2026-10-25T01:00:00+07:00',
    'priority'=>'-2147483648'];
$row=R::master($create,true);
$ok($row['code']==='PL-1','normalized code');
$ok($row['name']==='Biểu giá','trim name');
$ok($row['organization_id']==='3','organization BIGINT canonical');
$ok($row['effective_from']==='2026-10-24 05:00:00','UTC effective from');
$ok($row['effective_to']==='2026-10-24 18:00:00','UTC effective to');
$ok(R::apiDateTime($row['effective_from'])==='2026-10-24T05:00:00Z','RFC3339 UTC response');
$ok(R::apiDateTime(null)===null,'nullable UTC response');
try { R::apiDateTime('2026-02-30 00:00:00'); $ok(false,'invalid persisted datetime'); }
catch (InvalidArgumentException) { $ok(true,'invalid persisted datetime fails closed'); }

$ok($row['priority']===-2147483648,'signed min priority');
$ok($row['description']===null,'optional description');
$ok(R::master(['code'=>'A','name'=>'B','organization_id'=>1,
        'effective_from'=>'2026-10-24 00:00:00'],true)['priority']===0,'default priority');
$ok(R::master(['name'=>'New','priority'=>0],false)===['name'=>'New','priority'=>0],'PATCH subset');
$ok(R::master(['description'=>null],false)['description']===null,'nullable description patch');
$ok(R::master(['name'=>'A','id'=>123,'updated_by'=>6],false)===['name'=>'A'],'server audit ignored');
$reject(static fn()=>R::master([],true),'PRICE_LIST_REQUIRED_FIELD','create requires fields');
$reject(static fn()=>R::master([],false),'PRICE_LIST_INVALID_FIELD','empty patch rejected');
$reject(static fn()=>R::master(['status'=>'ACTIVE'],false),'PRICE_LIST_STATUS_REQUIRES_SETSTATE','PATCH cannot set State');
$reject(static fn()=>R::master([...$create,'status'=>'ACTIVE'],true),'PRICE_LIST_STATUS_REQUIRES_SETSTATE','CREATE ignores deprecated State');
$reject(static fn()=>R::master(['foo'=>1],false),'PRICE_LIST_INVALID_FIELD','unknown field');
$reject(static fn()=>R::master(['code'=>' '],false),'PRICE_LIST_REQUIRED_FIELD','empty code');
$reject(static fn()=>R::master(['name'=>str_repeat('x',256)],false),'PRICE_LIST_INVALID_FIELD','name length');
$reject(static fn()=>R::master(['organization_id'=>0],false),'PRICE_LIST_INVALID_REFERENCE','zero organization');
$reject(static fn()=>R::master(['organization_id'=>true],false),'PRICE_LIST_INVALID_REFERENCE','boolean organization');
foreach (['2147483648','-2147483649','1.2','1e4',true,4.1] as $bad) {
    $reject(static fn()=>R::master(['priority'=>$bad],false),'PRICE_LIST_INVALID_FIELD','invalid priority');
}
foreach (['2026-02-30 12:00:00','2026-01-01T99:00:00Z','2026-01-01T12:00:00',true] as $bad) {
    $reject(static fn()=>R::master(['effective_from'=>$bad],false),'PRICE_LIST_INVALID_EFFECTIVE_INTERVAL','bad datetime');
}
$reject(static fn()=>R::master(['effective_from'=>'2026-10-24 02:00:00',
    'effective_to'=>'2026-10-24 01:00:00'],false),'PRICE_LIST_INVALID_EFFECTIVE_INTERVAL','inverted time');
$reject(static fn()=>R::interval(['effective_from'=>'2026-10-24 02:00:00',
    'effective_to'=>'2026-10-24 02:00:00']),'PRICE_LIST_INVALID_EFFECTIVE_INTERVAL','equal endpoints');
$ok(R::requestedState(['status'=>'active'])==='ACTIVE','state requested normalization');
$reject(static fn()=>R::requestedState(['status'=>'BAD','id'=>'1']),'PRICE_LIST_INVALID_STATUS','extra status fields');
$reject(static fn()=>R::requestedState(['status'=>null]),'PRICE_LIST_INVALID_STATUS','no state status');
$ok(R::items([])===[],'explicit bare empty clears items');
$ok(R::items(['items'=>[]])===[],'explicit object empty clears items');
$payload=['product_id'=>'0002','uom_id'=>3,'price'=>'0.10'];
$ok(R::items([$payload])[0]===['product_id'=>'2','uom_id'=>'3','price'=>'0.10'],'item normalized');
$ok(R::items([['product_id'=>3,'uom_id'=>4,'price'=>0]])[0]['price']==='0','zero price allowed');
$ok(R::price('9999999999999999.99')==='9999999999999999.99','DECIMAL 18,2 max');
$ok(R::samePrice('1.0','1.00'),'decimal trailing zeros');
$ok(!R::samePrice('1.00','1.01'),'exact cent comparison');
foreach (['1.001','-1','1e6','0.001','10000000000000000',true,1.2,null] as $bad) {
    $reject(static fn()=>R::price($bad),'PRICE_LIST_INVALID_PRICE','invalid price');
}
$reject(static fn()=>R::items(['items'=>null]),'PRICE_LIST_INVALID_ITEM','null items rejected');
$reject(static fn()=>R::items(['foo'=>[]]),'PRICE_LIST_INVALID_ITEM','wrong envelope rejected');
$reject(static fn()=>R::items(['items'=>[...[$payload]],'organization_id'=>6]),'PRICE_LIST_INVALID_ITEM','owner hint rejected');
$reject(static fn()=>R::items([['product_id'=>2,'uom_id'=>3,'price'=>1,'id'=>8]]),'PRICE_LIST_INVALID_ITEM','client id rejected');
$reject(static fn()=>R::items([['product_id'=>2,'uom_id'=>3,'price'=>1,'updated_by'=>8]]),'PRICE_LIST_INVALID_ITEM','client audit rejected');
$reject(static fn()=>R::items([$payload,['product_id'=>2,'uom_id'=>'0003','price'=>'1']]),'PRICE_LIST_ITEM_DUPLICATE','canonical duplicate pair');
$reject(static fn()=>R::items([['product_id'=>true,'uom_id'=>3,'price'=>'1']]),'PRICE_LIST_INVALID_REFERENCE','boolean product');
$reject(static fn()=>R::items(array_fill(0,R::MAX_ITEMS+1,$payload)),'PRICE_LIST_INVALID_ITEM','item set bounded');
$q=R::query(['page'=>'2','page_size'=>'20','sort'=>'-priority,name','filter'=>['status'=>['ACTIVE','DRAFT'],'organization_id'=>'003'], 'q'=>' Test ']);
$ok($q['page']===2 && $q['page_size']===20 && $q['q']==='Test','list query pagination/search');
$ok($q['sort']===[['field'=>'priority','direction'=>'DESC'],['field'=>'name','direction'=>'ASC'],['field'=>'id','direction'=>'ASC']],'stable SQL sort');
$ok($q['filter']['organization_id']===['3'],'normalized organization filter');
foreach ([['page'=>0],['page_size'=>200],['sort'=>'id;DROP TABLE'],['sort'=>'-priority,-priority'],['filter'=>['xxx'=>'value']],['q'=>[]],['unknown'=>'bad']] as $bad) {
    $reject(static fn()=>R::query($bad),'PRICE_LIST_INVALID_FIELD','invalid list query');
}
echo 'M7.3 PRICING RULES: '.$n.'/'.$n." PASS\n";
