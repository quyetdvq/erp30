<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Modules\Identity\Mutation\SecurityUserMutationRules as Rules;

require __DIR__ . '/../app/Exceptions/DomainException.php';
require __DIR__ . '/../app/Shared/Read/PositiveId.php';
require __DIR__ . '/../app/Modules/Identity/Mutation/SecurityUserMutationRules.php';

$checks = 0;
function okU(string $message, bool $condition): void
{
    global $checks;
    if (! $condition) { throw new RuntimeException('FAIL: ' . $message); }
    $checks++;
    echo 'PASS: ' . $message . PHP_EOL;
}
function rejectsU(string $label, callable $callback, string $code): void
{
    try { $callback(); } catch (DomainException $exception) {
        okU($label, $exception->getErrorCode() === $code);
        return;
    }
    okU($label, false);
}
$hashes = [
 'ShieldUserIdentityService.php' => 'fe10d1dece8308f409c27805952d6c0464436d8ea8642394fbaafd58aa36a069',
 'UserDisplayName.php' => '1433ec808bb6225fb687ba2fb5c9e32d03557e6e7c26ce6b723a1a40d8dbfdac',
 'UserGroupSelection.php' => '712fad1dfbf4b7993ee5a3e9e3bafd35ee82b7ddb3364777671c3e94461b1685',
 'ShieldIdentityAdapter.php' => '40986774476d07acd3f1a6e9937943d7f2b2a29b422345151c474e010ebb4bdc',
 'UserModel.php' => '7886846b595e193b5765515d8b43d6461a003b99682ae9c765429cba4b9a0cad',
];
foreach ($hashes as $file => $sha) {
    $path = __DIR__ . '/fixtures/baseline_v1_2_4/shield_user/' . $file;
    okU('Pinned baseline Shield User source ' . $file, is_file($path) && hash_file('sha256', $path) === $sha);
}
$base = ['username'=>'  ADMIN_1  ', 'email'=>'  A+TEST@Example.COM  ', 'state'=>'  locked ',
    'password'=>'test-password-123', 'name'=>'   Ngọc  ', 'group_ids'=>['15','7']];
$create = Rules::create($base);
okU('Create username lowercases/trimmed', $create['username'] === 'admin_1');
okU('Create email normalized like baseline', $create['email'] === 'a+test@example.com');
okU('Six-state Shield status canonicalized', $create['state'] === 'LOCKED');
okU('Display name trimmed Unicode', $create['name'] === 'Ngọc');
okU('Group selection deterministically ordered', $create['group_ids'] === ['7','15']);
okU('Password preserved byte-for-byte until Shield hashing', $create['password'] === 'test-password-123');
okU('Omitted group list creates no memberships', Rules::create(array_diff_key($base, ['group_ids'=>1]))['group_ids'] === []);
okU('Missing name is null', Rules::create(array_diff_key($base, ['name'=>1]))['name'] === null);
foreach (['ACTIVE','INACTIVE','LOCKED','SUSPENDED','BANNED','DISABLED'] as $state) {
    okU('Shield native operation ' . $state, Rules::state(strtolower($state)) === $state);
}
rejectsU('Missing state rejected', fn () => Rules::create(array_diff_key($base, ['state'=>1])), 'USER_STATE_INVALID');
rejectsU('Unknown state rejected', fn () => Rules::state('DELETED'), 'USER_STATE_INVALID');
rejectsU('Create cannot inject audit', fn () => Rules::create([...$base, 'created_by'=>999]), 'VALIDATION_ERROR');
rejectsU('Create cannot inject password hash', fn () => Rules::create([...$base, 'password_hash'=>'fake']), 'VALIDATION_ERROR');
rejectsU('Create cannot inject direct permissions', fn () => Rules::create([...$base, 'permissions'=>['user.delete']]), 'VALIDATION_ERROR');
rejectsU('Missing username rejected', fn () => Rules::create(array_diff_key($base,['username'=>1])), 'VALIDATION_ERROR');
rejectsU('Username cannot exceed 30 bytes', fn () => Rules::username(str_repeat('x',31)), 'VALIDATION_ERROR');
rejectsU('Missing email rejected', fn () => Rules::create(array_diff_key($base,['email'=>1])), 'VALIDATION_ERROR');
rejectsU('Invalid email rejected', fn () => Rules::email('wrong@'), 'VALIDATION_ERROR');
rejectsU('Blank password rejected', fn () => Rules::password('   '), 'USER_PASSWORD_POLICY_INVALID');
rejectsU('Password non-string rejected', fn () => Rules::password(999), 'USER_PASSWORD_POLICY_INVALID');
rejectsU('Name null rejected', fn () => Rules::name(null), 'VALIDATION_ERROR');
rejectsU('Name empty rejected', fn () => Rules::name('  '), 'VALIDATION_ERROR');
rejectsU('Name exceeds 255 characters', fn () => Rules::name(str_repeat('ơ',256)), 'VALIDATION_ERROR');
rejectsU('Group IDs must be a list', fn () => Rules::groupIds(['a'=>'3']), 'VALIDATION_ERROR');
rejectsU('Group IDs only strings', fn () => Rules::groupIds([3]), 'VALIDATION_ERROR');
rejectsU('Group IDs cannot have leading zero', fn () => Rules::groupIds(['03']), 'VALIDATION_ERROR');
rejectsU('Group IDs cannot duplicate', fn () => Rules::groupIds(['3','3']), 'VALIDATION_ERROR');
rejectsU('Group IDs reject float', fn () => Rules::groupIds([3.0]), 'VALIDATION_ERROR');
rejectsU('Group IDs reject overflow', fn () => Rules::groupIds(['99999999999999999999']), 'VALIDATION_ERROR');
$patch = Rules::patch(['username'=>' NEW ','email'=>' NEW@EXAMPLE.COM ','name'=>'  X  ','row_version'=>'42']);
okU('PATCH username normalized', $patch['username'] === 'new');
okU('PATCH email normalized', $patch['email'] === 'new@example.com');
okU('PATCH name normalized', $patch['full_name'] === 'X');
okU('PATCH row_version accepted without mutation', ! array_key_exists('row_version', $patch));
rejectsU('PATCH row_version alone invalid', fn () => Rules::patch(['row_version'=>'1']), 'VALIDATION_ERROR');
rejectsU('PATCH cannot edit native State', fn () => Rules::patch(['state'=>'ACTIVE']), 'VALIDATION_ERROR');
rejectsU('PATCH cannot supply password', fn () => Rules::patch(['password'=>'pass']), 'VALIDATION_ERROR');
okU('Set-state accepts row_version', Rules::setState(['state'=>'  disabled ','row_version'=>'1']) === 'DISABLED');
rejectsU('Set-state refuses status field', fn () => Rules::setState(['status'=>'ACTIVE']), 'VALIDATION_ERROR');
okU('Reset password accepts row_version', Rules::resetPassword(['new_password'=>'pass123456','row_version'=>'1']) === 'pass123456');
rejectsU('Reset password rejects unrelated fields', fn () => Rules::resetPassword(['new_password'=>'pw','state'=>'LOCKED']), 'VALIDATION_ERROR');
okU('Direct permission casing/duplicates normalized', Rules::permissions(['permissions'=>['  USER.View ', 'user.view', 'user.edit']]) === ['user.view', 'user.edit']);
okU('Empty permissions removes all direct grants', Rules::permissions(['permissions'=>[]]) === []);
rejectsU('Permission must be dot-qualified', fn () => Rules::permissions(['permissions'=>['user']]), 'VALIDATION_ERROR');
rejectsU('Permission token format strict', fn () => Rules::permissions(['permissions'=>['user.delete.any']]), 'VALIDATION_ERROR');
rejectsU('Permission list must be sequential', fn () => Rules::permissions(['permissions'=>['bad'=>'user.view']]), 'VALIDATION_ERROR');
rejectsU('Missing permission list rejected', fn () => Rules::permissions([]), 'VALIDATION_ERROR');
rejectsU('Unknown permission request field rejected', fn () => Rules::permissions(['permissions'=>[],'actor_id'=>42]), 'VALIDATION_ERROR');
okU('User ID canonicalized without float', Rules::id('00000000009') === '9');
rejectsU('User ID overflow rejected', fn () => Rules::id('18446744073709551615'), 'USER_NOT_FOUND');
rejectsU('User ID zero rejected', fn () => Rules::id('0'), 'USER_NOT_FOUND');
echo 'SOURCE: SHA-256-verified v1.2.4 snapshots; no live baseline execution.' . PHP_EOL;
echo 'TOTAL: ' . $checks . ' M3.3B3 Shield User rules assertions passed.' . PHP_EOL;
