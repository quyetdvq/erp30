<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Exceptions/DomainException.php';
require dirname(__DIR__, 2) . '/app/Modules/Numbering/Configuration/NumberingRules.php';
require dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierRules.php';
require dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierNumberingPolicy.php';

use App\Exceptions\DomainException;
use App\Modules\Supplier\SupplierNumberingPolicy as P;

$pass = 0;
function check(bool $result, string $message): void {
    global $pass;
    if (!$result) throw new RuntimeException('SUP-T02 FAIL: ' . $message);
    ++$pass;
}
function deny(callable $callback, string $error): void {
    try { $callback(); } catch (DomainException $e) {
        check($e->getErrorCode() === $error, 'reject ' . $error . ' (got ' . $e->getErrorCode() . ')');
        return;
    }
    throw new RuntimeException('SUP-T02 expected ' . $error);
}
$default = [
    'id' => '11', 'resource_id' => '7', 'series_code' => 'SUP_DEFAULT',
    'status' => 'ACTIVE', 'is_default' => 1, 'scope_type' => 'GROUP',
    'pattern' => 'SUP-{RESOURCE}-{SEQ:4}', 'reset_policy' => 'NEVER',
    'generation_mode' => 'AUTO_IF_EMPTY', 'start_number' => '1', 'increment_by' => '1',
];
$alternate = array_replace($default, [
    'id' => '12', 'series_code' => 'SUP_ALT', 'is_default' => 0,
    'pattern' => 'ALT-{RESOURCE}-{SEQ:4}', 'generation_mode' => 'AUTO_REQUIRED',
]);
$chosen = P::choose([$default], null, null, null);
check($chosen['kind'] === 'AUTO' && $chosen['series']['id'] === '11', 'ACTIVE default forces auto even AUTO_IF_EMPTY');
check(P::choose([], null, ' sup-0007 ', null) === ['kind' => 'MANUAL', 'code' => 'SUP-0007'],
    'No default allows canonical manual fallback');
check(P::choose([], 'SUP_ALT', null, $alternate)['series']['id'] === '12', 'Explicit series with no default');
check(P::choose([$default], 'sup_alt', null, $alternate)['series']['id'] === '12',
    'Valid requested series overrides valid ACTIVE default');
check(P::choose([$default], 'SUP_DEFAULT', null, $default)['series']['id'] === '11',
    'Requesting default explicitly works');
check(P::requestedCode(null) === null, 'Omitted requested code');
check(P::requestedCode(' sup_01 ') === 'SUP_01', 'Requested code canonicalized');
check(P::manualCode(' ab-123 ') === 'AB-123', 'Manual code canonicalized');
check(P::maxAttempts() === 1000, 'Bounded collision retries');

$inactive = array_replace($default, ['status' => 'INACTIVE']);
$wrongScope = array_replace($default, ['scope_type' => 'COMPANY']);
$organizationScope = array_replace($default, ['scope_type' => 'ORGANIZATION']);
$wrongPattern = array_replace($default, ['pattern' => 'SUP-{COMPANY_CODE}-{SEQ:3}']);
$wrongMode = array_replace($default, ['generation_mode' => 'MANUAL']);
$wrongReset = array_replace($default, ['reset_policy' => 'WEEKLY']);
$overflow = array_replace($default, ['start_number' => '9223372036854775808']);
$negative = array_replace($default, ['increment_by' => '0']);
$unsetPattern = array_replace($default, ['pattern' => 'STATIC']);
$badStatus = array_replace($default, ['status' => 'DISABLED']);

// A disabled/invalid default cannot be bypassed by manual OR a requested series.
deny(fn() => P::choose([$inactive], null, 'SUP-9', null), 'NUM_IDENTIFIER_SERIES_INACTIVE');
deny(fn() => P::choose([$inactive], 'SUP_ALT', null, $alternate), 'NUM_IDENTIFIER_SERIES_INACTIVE');
deny(fn() => P::choose([$default, $inactive], null, null, null), 'NUM_IDENTIFIER_DEFAULT_AMBIGUOUS');
deny(fn() => P::choose([$default, $inactive], 'SUP_ALT', null, $alternate), 'NUM_IDENTIFIER_DEFAULT_AMBIGUOUS');
deny(fn() => P::choose([$default], null, 'SUP-9', null), 'NUM_IDENTIFIER_MANUAL_NOT_ALLOWED');
deny(fn() => P::choose([$default], 'SUP_ALT', 'SUP-9', $alternate), 'NUM_IDENTIFIER_MANUAL_NOT_ALLOWED');
deny(fn() => P::choose([], 'SUP_ALT', 'SUP-9', $alternate), 'NUM_IDENTIFIER_MANUAL_NOT_ALLOWED');
deny(fn() => P::choose([], null, null, null), 'SUPPLIER_CODE_REQUIRED');
deny(fn() => P::choose([], null, ' ', null), 'SUPPLIER_CODE_REQUIRED');
deny(fn() => P::choose([], null, 'bad code!', null), 'SUPPLIER_CODE_INVALID');
deny(fn() => P::choose([], 'SUP_UNKNOWN', null, null), 'NUM_IDENTIFIER_SERIES_NOT_FOUND');
deny(fn() => P::choose([$default], 'SUP_ALT', null, null), 'NUM_IDENTIFIER_SERIES_NOT_FOUND');
deny(fn() => P::choose([], 'SUP_ALT', null, $default), 'NUM_IDENTIFIER_SERIES_NOT_FOUND');
deny(fn() => P::choose([], 'SUP_ALT', null, array_replace($alternate, ['status' => 'INACTIVE'])),
    'NUM_IDENTIFIER_SERIES_INACTIVE');
deny(fn() => P::choose([$wrongScope], 'SUP_ALT', null, $alternate), 'NUM_SCOPE_CONTEXT_REQUIRED');
deny(fn() => P::choose([$wrongPattern], 'SUP_ALT', null, $alternate), 'NUM_PATTERN_INVALID');
deny(fn() => P::choose([$badStatus], null, 'MANUAL1', null), 'NUM_COUNTER_CONFIGURATION_INVALID');
foreach ([$wrongScope, $organizationScope] as $row) {
    deny(fn() => P::validateSeries($row), 'NUM_SCOPE_CONTEXT_REQUIRED');
}
foreach ([$wrongMode, $wrongReset, $overflow, $negative] as $row) {
    deny(fn() => P::validateSeries($row), 'NUM_COUNTER_CONFIGURATION_INVALID');
}
deny(fn() => P::validateSeries($unsetPattern), 'NUM_PATTERN_INVALID');
deny(fn() => P::requestedCode('abc unsafe'), 'NUM_INVALID_CONFIGURATION');
deny(fn() => P::requestedCode(''), 'NUM_INVALID_CONFIGURATION');
deny(fn() => P::manualCode(str_repeat('A', 51)), 'SUPPLIER_CODE_INVALID');
deny(fn() => P::number('9223372036854775808', 0), 'NUM_COUNTER_CONFIGURATION_INVALID');
deny(fn() => P::number('01', 0), 'NUM_COUNTER_CONFIGURATION_INVALID');
deny(fn() => P::number(-1, 0), 'NUM_COUNTER_CONFIGURATION_INVALID');
deny(fn() => P::number(0, 1), 'NUM_COUNTER_CONFIGURATION_INVALID');
deny(fn() => P::next(PHP_INT_MAX, 1), 'NUM_COUNTER_OVERFLOW');
deny(fn() => P::next(1, 0), 'NUM_COUNTER_OVERFLOW');
deny(fn() => P::render('SUP-{COMPANY_CODE}-{SEQ:3}', 1, new DateTimeImmutable('2026-10-30'), 'G1'),
    'NUM_COUNTER_RUNTIME_CONTEXT_REQUIRED');

check(P::number('10', 0) === 10, 'Strict counter bigint parser');
check(P::next(10, 3) === 13, 'Counter increment');
$date = new DateTimeImmutable('2026-10-30 09:17:00+07:00');
check(P::periodKey($default, $date) === 'ALL', 'Never reset period');
check(P::periodKey(['reset_policy' => 'YEARLY'], $date) === '2026', 'Yearly period');
check(P::periodKey(['reset_policy' => 'MONTHLY'], $date) === '202610', 'Monthly period');
check(P::periodKey(['reset_policy' => 'DAILY'], $date) === '20261030', 'Daily period');
deny(fn() => P::periodKey(['reset_policy' => 'NONE'], $date), 'NUM_COUNTER_CONFIGURATION_INVALID');
check(P::render($default['pattern'], 7, $date, 'G1') === 'SUP-SUPPLIER-0007',
    '{RESOURCE} identifies supplier, never customer');
check(P::render('S-{ORG_CODE}-{YYYY}-{MM}-{DD}-{SEQ:3}', 5, $date, 'G1') === 'S-G1-2026-10-30-005',
    'Group-scoped business date and Group code');
check(P::render('SUP-{SEQ:3}', 12345, $date, 'G1') === 'SUP-12345', 'No sequence truncation');
deny(fn() => P::render(str_repeat('A', 50) . '{SEQ:2}', 1, $date, 'G1'), 'SUPPLIER_CODE_INVALID');

$issuerSource = file_get_contents(dirname(__DIR__, 2) . '/app/Modules/Supplier/SupplierNumberIssuer.php');
check(is_string($issuerSource) && str_contains($issuerSource, 'transDepth < 1'), 'Parent transaction required');
check(substr_count($issuerSource, "' FOR UPDATE'") >= 4, 'Resource, series, group and counter locking reads');
check(str_contains($issuerSource, "->where('is_default', 1)") && !str_contains($issuerSource, "->where('status', 'ACTIVE')->where('is_default'"),
    'Inactive defaults are not filtered out');
check(str_contains($issuerSource, "->where('group_organization_id', $" . "groupId)"), 'Group scoped uniqueness lookup');
check(str_contains($issuerSource, 'return $persist($choice[\'code\'], $groupId);'), 'Manual branch passes trusted GROUP id');
check(str_contains($issuerSource, '$id = $persist($issuedCode, $groupId);'), 'Auto persist in owner transaction');
check(str_contains($issuerSource, "table('auto_numbering_counters')->insert"), 'P1 counter table reused');
check(!str_contains($issuerSource, 'transBegin(') && !str_contains($issuerSource, 'transCommit('),
    'Issuer does not start/commit nested transaction');
check(!str_contains($issuerSource, 'GET_LOCK(') && !str_contains($issuerSource, "table('auto_numbering_series')->insert"),
    'No lock/seeding in issuer; parent owns mutex, Numbering configuration stays explicit');
check(!str_contains($issuerSource, "table('customers')"), 'No Customer table dependency');

echo "SUP-T02 NUMBERING PURE: {$pass}/{$pass} PASS\n";
