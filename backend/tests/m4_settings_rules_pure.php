<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Modules/Settings/Display/DisplaySettingRules.php';

use App\Exceptions\DomainException;
use App\Modules\Settings\Display\DisplaySettingRules as Rules;

$count = 0;
$check = static function (bool $ok,string $message) use (&$count): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$message);
    $count++; echo 'PASS: '.$message."\n";
};
$reject = static function (callable $run,string $code) use ($check): void {
    try { $run(); } catch (DomainException $e) { $check($e->getErrorCode()===$code, 'Rejected '.$code); return; }
    throw new RuntimeException('Expected error: '.$code);
};
$check(count(Rules::definitions())===15,'15 canonical Display Setting keys');
$check(Rules::storageKey('Display.number.decimalSeparator')==='number_decimalSeparator','Canonical storage key retains baseline underscore');
$check(Rules::category('Display.money.symbolMode')==='MONEY','Money category');
$check(Rules::category('Display.quantityDecimals')==='NUMBER','Number category');
$check(Rules::category('Display.timezone')==='DATE_TIME','Date category');
$check(Rules::context(['type'=>'system'],true)['context']==='system','SYSTEM scope no org');
$check(Rules::context(['context_type'=>'ORGANIZATION','organization_id'=>'42'],false)['context']==='organization:42','Organization scope');
$check(Rules::changes([['key'=>'Display.trailingZeros','value'=>true]])['Display.trailingZeros']===true,'Boolean validated strictly');
$check(Rules::keys(['Display.trailingZeros','Display.timezone'])===['Display.trailingZeros','Display.timezone'],'Ordered reset keys');
$check(Rules::runtime(array_map(static fn ($x) => $x['default'],Rules::definitions()))['timezone']==='Asia/Ho_Chi_Minh','Baseline runtime projection');
$reject(static fn()=>Rules::context(['type'=>'SYSTEM','organization_id'=>1],true),'DISPLAY_SETTING_CONTEXT_INVALID');
$reject(static fn()=>Rules::context(['type'=>'ORGANIZATION','organization_id'=>'0'],true),'DISPLAY_SETTING_CONTEXT_INVALID');
$reject(static fn()=>Rules::context(['type'=>'ORGANIZATION','organization_id'=>'9223372036854775808'],true),'DISPLAY_SETTING_CONTEXT_INVALID');
$reject(static fn()=>Rules::changes([['key'=>'Display.timezone','value'=>'Not_A_Timezone']]),'DISPLAY_SETTING_INVALID');
$reject(static fn()=>Rules::changes([['key'=>'Display.quantityDecimals','value'=>'3']]),'DISPLAY_SETTING_INVALID');
$reject(static fn()=>Rules::changes([['key'=>'Display.trailingZeros','value'=>1]]),'DISPLAY_SETTING_INVALID');
$reject(static fn()=>Rules::changes([['key'=>'Display.timezone','value'=>'UTC'],['key'=>'Display.timezone','value'=>'UTC']]),'DISPLAY_SETTING_DUPLICATE_KEY');
$reject(static fn()=>Rules::changes([['key'=>'UNKNOWN','value'=>true]]),'DISPLAY_SETTING_NOT_FOUND');
$reject(static fn()=>Rules::keys(['Display.timezone','Display.timezone']),'DISPLAY_SETTING_DUPLICATE_KEY');
$reject(static fn()=>Rules::keys(['unknown']),'DISPLAY_SETTING_NOT_FOUND');
$reject(static fn()=>Rules::changes(['Display.timezone'=>'UTC']),'DISPLAY_SETTING_INVALID');
echo 'TOTAL: '.$count." M4 Settings pure assertions passed.\n";
