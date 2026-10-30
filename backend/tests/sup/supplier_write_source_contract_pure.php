<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2) . '/app/Modules/';
$read = static function (string $path) use ($root): string {
    $data = file_get_contents($root . $path);
    if (!is_string($data) || $data === '') throw new RuntimeException('Missing source: ' . $path);
    return $data;
};
$write = $read('Supplier/SupplierWriteService.php');
$payload = $read('Supplier/SupplierWritePayload.php');
$facade = $read('Supplier/SupplierService.php');
$issuer = $read('Supplier/SupplierNumberIssuer.php');
$history = $read('History/HistoryRecorder.php');
$oldRead = $read('Supplier/SupplierReadService.php');
$n = 0;
$ok = static function (bool $value, string $label) use (&$n): void {
    if (!$value) throw new RuntimeException('SUP-T04 SOURCE FAIL: ' . $label);
    ++$n;
};
$ok(str_contains($write, 'final class SupplierWriteService'), 'isolated transactional service');
$ok(str_contains($facade, 'final class SupplierService'), 'public facade for future T07');
foreach (['list','show','create','update','replaceCompanyUsage','setState'] as $method) {
    $ok(str_contains($facade, 'function ' . $method . '('), 'facade includes ' . $method);
}
foreach (['create','update','replaceCompanyUsage','setState'] as $method) {
    $ok(str_contains($write, 'function ' . $method . '('), 'write service includes ' . $method);
}
foreach (['create','patch','replaceUsage','setState'] as $method) {
    $ok(str_contains($payload, 'function ' . $method . '('), 'write parser includes ' . $method);
}
$ok(str_contains($write, ":supplier-master"), 'same named lock for all writes');
$ok(str_contains($write, 'GET_LOCK(') && str_contains($write, 'RELEASE_LOCK('), 'advisory lock cleaned up');
$ok(str_contains($write, 'transDepth !== 0'), 'no nested write');
$ok(str_contains($write, 'transBegin()') && str_contains($write, 'transCommit()') && str_contains($write, 'transRollback()'), 'physical transaction owned');
$ok(str_contains($write, 'new SupplierNumberIssuer(') && str_contains($issuer, 'transDepth < 1'), 'numbering participant in same transaction');
$ok(str_contains($write, 'supplier_company_usages'), 'shared transaction for usage');
$ok(str_contains($write, "->where('row_version', " . '$expected)'), 'SQL conditional version CAS');
$ok(str_contains($write, 'affectedRows() !== 1'), 'detect zero-row CAS');
$ok(str_contains($payload, 'SUPPLIER_EXPECTED_VERSION_INVALID'), 'strict expected version DTO');
$ok(!str_contains($payload, "'status','row_version'"), 'no generic field forwarding');
$ok(str_contains($write, 'assertRequiredProfile($merged)'), 'validate merged profile on PATCH');
$ok(str_contains($write, 'SUPPLIER_WARD_PROVINCE_MISMATCH'), 'province/ward relation guard');
$ok(str_contains($write, "->whereIn('id', $" . "ids)"), 'bulk Company validation avoids N+1');
$ok(str_contains($write, "'COMPANY'") && str_contains($write, "'ACTIVE'") && str_contains($write, "parent_id'"), 'Company type/state/Group check');
$ok(str_contains($write, "initialState('supplier')") && !str_contains($write, "'status'=>'ACTIVE'"), 'dynamic initial status');
$ok(str_contains($write, "transitionTarget('supplier'") && str_contains($write, "'setstate', " . '$old, true'), 'Workflow transition rechecks PBAC');
$ok(str_contains($write, "'SUPPLIER_VERSION_CONFLICT'"), 'stale version fail closed');
$ok(str_contains($write, "'SUPPLIER_CODE_DUPLICATE'"), 'duplicate SQL mapped');
$ok(str_contains($write, "created('supplier'") && str_contains($write, "updated('supplier'")
    && str_contains($write, "transitioned('supplier'"), 'atomic Shared History integration');
$ok(str_contains($history, "'supplier'"), 'shared Recorder allows Supplier');
$ok(!str_contains($write, 'bank_ciphertext') && !str_contains($write, 'bank_nonce')
    && !str_contains($write, 'bank_last4'), 'bank data absent from generic write service');
$ok(!str_contains($payload, "'bank_"), 'bank data not part of DTO whitelist');
$ok(!str_contains($write, 'SELECT *') && !str_contains($write, 'select()'), 'explicit safe projection');
$ok(!str_contains($write, 'INSERT INTO') && !str_contains($write, 'UPDATE suppliers'), 'DBPrefix-aware query builder');
$ok(!str_contains($write, 'canViewBank') && !str_contains($facade, 'bankAccount'), 'no bank access in T04 facade');
$ok(!str_contains($oldRead, 'SupplierWriteService('), 'T03 reader has no write dependency');
$ok(!str_contains($facade, 'Controller') && !str_contains($facade, 'Routes'), 'no API route introduced');

echo "SUP-T04 WRITE SOURCE: {$n}/{$n} PASS\n";
