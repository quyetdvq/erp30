<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierRules.php';
use App\Modules\Supplier\SupplierRules as R;

$passed = 0;
function good(bool $ok, string $name): void { global $passed; if (!$ok) {throw new RuntimeException($name);} $passed++; }
function rejects(callable $cb, string $name): void {try {$cb();} catch (InvalidArgumentException) {good(true, $name);return;} good(false, $name);}
$base = ['name'=>'Supplier', 'phone'=>'0912345678', 'address'=>'Street', 'supplier_type'=>'COMPANY', 'province_id'=>1, 'ward_id'=>2];
good(R::validateProfile($base)['supplier_group_code'] === 'STANDARD', 'group default');
good(R::validateProfile($base+['payment_term_code'=>'NET30'])['payment_term_code'] === 'NET30', 'term');
good(R::normalizeCode(' a-1 ') === 'A-1', 'code');
rejects(fn()=>R::normalizeCode('a b'), 'invalid code');
rejects(fn()=>R::validateProfile($base+['status'=>'ACTIVE']), 'no status bypass');
rejects(fn()=>R::validateProfile(array_diff_key($base, ['phone'=>1])), 'phone required');
rejects(fn()=>R::validateProfile(array_replace($base,['supplier_type'=>'ORGANIZATION'])), 'type enum');
rejects(fn()=>R::validateProfile(array_replace($base,['payment_term_code'=>'INVALID'])), 'term enum');
good(R::validateUsage(true, []) === [], 'all companies');
good(R::validateUsage(false, ['2',3]) === ['2','3'], 'usage ids');
rejects(fn()=>R::validateUsage(false, []), 'empty allowlist');
rejects(fn()=>R::validateUsage(true, [1]), 'all with allowlist');
rejects(fn()=>R::validateUsage(false, [1,'1']), 'duplicate');
rejects(fn()=>R::validateUsage(false, [0]), 'invalid id');
// CUS post-release regressions to prevent the same bugs in SUP.
$prepared = R::validateProfile($base + ['supplier_code' => '  ', 'email' => '  test@example.com  ']);
good(!array_key_exists('supplier_code', $prepared), 'blank create code delegates to Auto Numbering');
good($prepared['email'] === 'test@example.com', 'email is trimmed and valid');
good(R::validateProfile(array_replace($base, ['phone' => '+84 912 345 678']))['phone'] === '+84 912 345 678', 'formatted international phone accepted');
good(R::validateProfile($base + ['supplier_code' => ' sup-01 '])['supplier_code'] === 'SUP-01', 'code canonicalized on create');
good(R::validateProfile(['name' => ' New Name '], false)['name'] === 'New Name', 'PATCH remains partial without injecting default group');
good(!array_key_exists('supplier_group_code', R::validateProfile(['name' => 'New Name'], false)), 'PATCH cannot overwrite group with default');
good(R::validateProfile(array_replace($base, ['phone' => '0912345678']))['phone'] === '0912345678', 'SUP phone is not unique in validation');
foreach (['status', 'row_version', 'expected_version', 'created_by', 'updated_by', 'created_at',
    'bank_ciphertext', 'bank_last4', 'group_organization_id', 'all_companies',
    'company_ids', 'numbering_series_code', 'unknown_column'] as $field) {
    rejects(fn() => R::validateProfile($base + [$field => 1]), 'reject server-owned/unrecognized profile field: ' . $field);
}
rejects(fn() => R::validateProfile(['supplier_code' => 'BAD'], false), 'PATCH cannot change Supplier Code');
rejects(fn() => R::validateProfile([], false), 'PATCH must not be empty');
rejects(fn() => R::validateProfile(['name' => '   '], false), 'PATCH cannot blank required name');
rejects(fn() => R::validateProfile($base + ['email' => 'invalid-address']), 'reject malformed email');
rejects(fn() => R::validateProfile(array_replace($base, ['phone' => 'xyz'])), 'reject malformed phone');
rejects(fn() => R::validateProfile(array_replace($base, ['name' => str_repeat('x', 256)])), 'reject varchar255 overflow');
rejects(fn() => R::validateProfile(array_replace($base, ['address' => str_repeat('x', 65536)])), 'reject TEXT byte overflow');
rejects(fn() => R::validateProfile(array_replace($base, ['phone' => str_repeat('9', 51)])), 'reject varchar50 phone overflow');
rejects(fn() => R::validateProfile(array_replace($base, ['province_id' => '0001'])), 'reject non-canonical location ID');
rejects(fn() => R::validateProfile(array_replace($base, ['province_id' => '18446744073709551616'])), 'reject BIGINT UNSIGNED overflow');
rejects(fn() => R::validateUsage(false, [2 => '3']), 'reject non-list company array');
rejects(fn() => R::assertRequiredProfile(['name' => 'Supplier', 'phone' => '', 'address' => 'Street',
    'province_id' => 1, 'ward_id' => 2]), 'merged PATCH requires valid phone');
rejects(fn() => R::assertRequiredProfile($base + ['supplier_group_code' => 'NO_SUCH_GROUP']),
    'merged PATCH requires approved Supplier Group');
R::assertRequiredProfile($base + ['supplier_group_code' => 'STANDARD']);
good(true, 'merged persisted Supplier required fields accepted');
echo "SUP-T01 PURE: {$passed}/{$passed} PASS\n";
