<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../app/Shared/Read/PositiveId.php';
require_once __DIR__ . '/../app/Modules/Locations/Mutation/LocationMutationRules.php';

use App\Exceptions\DomainException;
use App\Modules\Locations\Mutation\LocationMutationRules as Rules;

$checks = 0;
$pass = static function (bool $ok, string $label) use (&$checks): void {
    if (! $ok) { throw new RuntimeException('M5.1 rules: ' . $label); }
    ++$checks;
};
$reject = static function (callable $fn, string $expected) use ($pass): void {
    try { $fn(); }
    catch (DomainException $exception) { $pass($exception->getErrorCode() === $expected, 'Reject ' . $expected); return; }
    throw new RuntimeException('Expected ' . $expected);
};

$province = Rules::payload('province', ['code'=>'CT','name'=>'City'], true);
$pass($province === ['code'=>'CT','name'=>'City'], 'Province input preserved');
$pass(Rules::payload('province', ['name'=>'City 2'], false) === ['name'=>'City 2'], 'Province PATCH');
$ward = Rules::payload('ward', ['province_id'=>'00018','code'=>'W','name'=>'Ward','name_full'=>null], true);
$pass($ward['province_id'] === '18' && array_key_exists('name_full', $ward) && $ward['name_full'] === null, 'Ward foreign key and null preserved');
$pass(Rules::payload('ward', ['province_id'=>18], false)['province_id'] === '18', 'Ward reparent ID validated');
$pass(Rules::payload('ward', ['status'=>' active '], false)['status'] === 'ACTIVE', 'Configured status normalized');
$pass(Rules::status('completed_with_errors') === 'COMPLETED_WITH_ERRORS', 'Lifecycle custom State syntax');
$pass(Rules::payload('province', ['code'=>'CT','name'=>'City','created_by'=>88,'updated_by'=>999], true)
    === ['code'=>'CT','name'=>'City'], 'Client audit values ignored');
$pass(Rules::provinceId('18446744073709551615') === '18446744073709551615', 'Unsigned bigint not converted through float');
$pass(Rules::id('00012', 'ward') === '12', 'Target ID canonicalized');

$reject(static fn () => Rules::payload('province', ['name'=>'A'], true), 'LOCATION_FIELD_REQUIRED');
$reject(static fn () => Rules::payload('province', ['code'=>'A'], true), 'LOCATION_FIELD_REQUIRED');
$reject(static fn () => Rules::payload('ward', ['code'=>'X','name'=>'N'], true), 'LOCATION_FIELD_REQUIRED');
$reject(static fn () => Rules::payload('ward', ['province_id'=>0,'code'=>'X','name'=>'N'], true), 'WARD_PROVINCE_NOT_FOUND');
$reject(static fn () => Rules::payload('ward', ['province_id'=>2.0,'code'=>'X','name'=>'N'], true), 'WARD_PROVINCE_NOT_FOUND');
$reject(static fn () => Rules::payload('ward', ['province_id'=>'-8','code'=>'X','name'=>'N'], true), 'WARD_PROVINCE_NOT_FOUND');
$reject(static fn () => Rules::payload('ward', ['province_id'=>'18446744073709551616','code'=>'X','name'=>'N'], true), 'WARD_PROVINCE_NOT_FOUND');
$reject(static fn () => Rules::payload('ward', ['province_id'=>1,'code'=>'X','name'=>'N','organization_id'=>17], true), 'LOCATION_FIELD_UNSUPPORTED');
$reject(static fn () => Rules::payload('province', ['id'=>4], false), 'LOCATION_FIELD_UNSUPPORTED');
$reject(static fn () => Rules::payload('province', ['code'=>'  '], false), 'LOCATION_FIELD_REQUIRED');
$reject(static fn () => Rules::payload('province', ['code'=>1], false), 'LOCATION_FIELD_REQUIRED');
$reject(static fn () => Rules::payload('ward', ['name_full'=>[]], false), 'LOCATION_FIELD_UNSUPPORTED');
$reject(static fn () => Rules::payload('province', [], false), 'LOCATION_UPDATE_EMPTY');
$reject(static fn () => Rules::payload('ward', ['created_at'=>'untrusted'], false), 'LOCATION_UPDATE_EMPTY');
$reject(static fn () => Rules::payload('province', ['status'=>false], false), 'LOCATION_STATUS_INVALID');
$reject(static fn () => Rules::status('INVALID STATUS'), 'LOCATION_STATUS_INVALID');
$reject(static fn () => Rules::id('0', 'province'), 'PROVINCE_NOT_FOUND');
$reject(static fn () => Rules::id('1 OR 1=1', 'ward'), 'WARD_NOT_FOUND');

fwrite(STDOUT, 'M5.1 LOCATION RULES: ' . $checks . '/' . $checks . " PASS\n");
