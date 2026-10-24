<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Modules/Numbering/Configuration/NumberingRules.php';
use App\Exceptions\DomainException;
use App\Modules\Numbering\Configuration\NumberingRules as Rules;
$count=0;
$check=static function(bool $yes,string $desc) use (&$count): void { if(!$yes) throw new RuntimeException($desc); ++$count; echo "PASS: $desc\n"; };
$reject=static function(callable $fn,string $code) use ($check): void {
    try {$fn();} catch(DomainException $e) {$check($e->getErrorCode()===$code,'Rejected '.$code);return;}
    throw new RuntimeException('Expected '.$code);
};
$payload=['resource_id'=>1,'series_code'=>'p-01','name'=>'Invoice','scope_type'=>'GROUP','pattern'=>'INV-{YYYY}-{SEQ:5}',
 'reset_policy'=>'YEARLY','generation_mode'=>'AUTO_IF_EMPTY','status'=>'ACTIVE'];
$good=Rules::normalize($payload,true);
$check($good['series_code']==='P-01','Series code uppercase');
$check($good['start_number']===1 && $good['increment_by']===1,'Counter defaults');
$check($good['is_default']===0,'Non-default by default');
Rules::validatePattern($good);$check(true,'Valid YEARLY pattern');
$check(Rules::visible(['start_number'=>5,'is_default'=>1])===['start_number'=>'5','is_default'=>true],'Response serialization');
$check(Rules::id('9001')==='9001','Positive identifier');
$check(Rules::normalize(['status'=>'inactive'],false)['status']==='INACTIVE','Status patch normalized');
$reject(static fn()=>Rules::id('1 OR 1=1'),'NUM_INVALID_CONFIGURATION');
$reject(static fn()=>Rules::normalize(['scope_type'=>'INVALID'],false),'NUM_INVALID_CONFIGURATION');
$reject(static fn()=>Rules::normalize(['pattern'=>''],false),'NUM_INVALID_CONFIGURATION');
$reject(static fn()=>Rules::normalize(['is_default'=>'yes'],false),'NUM_INVALID_CONFIGURATION');
$reject(static fn()=>Rules::normalize(['increment_by'=>0],false),'NUM_INVALID_CONFIGURATION');
$reject(static fn()=>Rules::normalize(['created_via'=>'client'],false),'NUM_INVALID_CONFIGURATION');
$reject(static fn()=>Rules::normalize(['name'=>'ok'],true),'NUM_INVALID_CONFIGURATION');
$reject(static fn()=>Rules::validatePattern(array_replace($good,['pattern'=>'INV-{YYYY}'])),'NUM_PATTERN_INVALID');
$reject(static fn()=>Rules::validatePattern(array_replace($good,['pattern'=>'INV-{FOO}-{SEQ:4}'])),'NUM_PATTERN_INVALID');
$reject(static fn()=>Rules::validatePattern(array_replace($good,['pattern'=>'INV-{SEQ:4}-{SEQ:3}'])),'NUM_PATTERN_INVALID');
$reject(static fn()=>Rules::validatePattern(array_replace($good,['pattern'=>'INV-{{SEQ:3}}'])),'NUM_PATTERN_INVALID');
$reject(static fn()=>Rules::validatePattern(array_replace($good,['pattern'=>'INV-{SEQ:4}','reset_policy'=>'MONTHLY'])),'NUM_PATTERN_INVALID');
$reject(static fn()=>Rules::validatePattern(array_replace($good,['pattern'=>'INV-{MM}-{SEQ:4}','reset_policy'=>'MONTHLY'])),'NUM_PATTERN_INVALID');
$reject(static fn()=>Rules::validatePattern(array_replace($good,['pattern'=>'INV-{YYYY}-{MM}-{SEQ:4}','reset_policy'=>'DAILY'])),'NUM_PATTERN_INVALID');
$reject(static fn()=>Rules::validatePattern(array_replace($good,['pattern'=>'INV-{COMPANY_CODE}-{SEQ:4}'])),'NUM_PATTERN_INVALID');
$reject(static fn()=>Rules::validatePattern(array_replace($good,['pattern'=>'<?php {SEQ:3} ?>'])),'NUM_PATTERN_INVALID');
echo 'TOTAL: '.$count." M4 Numbering pure assertions passed.\n";
