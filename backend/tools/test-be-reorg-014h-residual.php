<?php

declare(strict_types=1);

/**
 * BE-REORG-014H source-only residual API-050 coverage and drift gate.
 * No composer, DB, HTTP, SQL or production class execution.
 * Negative fixtures validate that changing the manifest cannot silently pass.
 */

$backend = dirname(__DIR__);
$app = $backend . '/app/';
$record = json_decode((string) file_get_contents(__DIR__ . '/architecture/api050-residual-014h.json'), true, 512, JSON_THROW_ON_ERROR);
$source = json_decode((string) file_get_contents(__DIR__ . '/architecture/api050-orchestration-inventory.json'), true, 512, JSON_THROW_ON_ERROR);
$facade = (string) file_get_contents($app . 'Services/Api050ProductCatalogService.php');
$methods = be014hMethods($facade);

function be014hAssert(bool $condition, string $message): void
{
    if (! $condition) { throw new RuntimeException($message); }
}

/** Extract method bodies without matching comments/string mentions of a method name.
 *  @return array<string,string>
 */
function be014hMethods(string $php): array
{
    $tokens = token_get_all($php, TOKEN_PARSE);
    $methods = [];
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
        $j = $i + 1;
        while ($j < $n && ($tokens[$j] === '&' || (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE))) { $j++; }
        if ($j >= $n || ! is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) { continue; }
        $name = $tokens[$j][1];
        while ($j < $n && $tokens[$j] !== '{' && $tokens[$j] !== ';') { $j++; }
        if ($j >= $n || $tokens[$j] === ';') { continue; }
        $depth = 1;
        $body = '';
        for ($j++; $j < $n && $depth > 0; $j++) {
            $token = $tokens[$j];
            if ($token === '{') { $depth++; }
            if ($token === '}') { $depth--; }
            if ($depth > 0) { $body .= is_array($token) ? $token[1] : $token; }
        }
        $methods[$name] = $body;
    }
    return $methods;
}

/**
 * Full structural validation for the observed residual manifest; also reused
 * with negative mutations of the manifest (not of any application source).
 * @param array<string,mixed> $record
 * @param array<string,mixed> $source
 * @param array<string,string> $bodies
 */
function be014hValidate(array $record, array $source, array $bodies): void
{
    be014hAssert(($record['change_id'] ?? null) === 'BE-REORG-014H', 'Wrong change id');
    be014hAssert(($record['approval_status'] ?? null) === 'SOURCE_OBSERVED_DRAFT_NOT_APPROVED', 'Audit cannot approve a contract');
    be014hAssert(($record['http_operations'] ?? null) === 26 && ($record['residual_http_operations'] ?? null) === 22, 'Incorrect operation totals');
    $all = $source['operations'] ?? [];
    $delegated = $source['already_query_delegated'] ?? [];
    be014hAssert(count($all) === 26 && count($delegated) === 4, 'Upstream 014E inventory changed');
    be014hAssert(($record['already_delegated_list_methods'] ?? null) === $delegated, 'Delegated list method drift');
    $byMethod = [];
    foreach ($all as $op) { $byMethod[$op['facade_method']] = $op; }
    $remaining = array_diff(array_keys($byMethod), $delegated);
    $seen = [];
    $counts = [];
    foreach (($record['operations'] ?? []) as $op) {
        be014hAssert(is_array($op), 'Malformed operation');
        $method = $op['facade_method'] ?? '';
        be014hAssert(is_string($method) && isset($byMethod[$method]) && ! isset($seen[$method]), 'Unknown/duplicate operation: ' . $method);
        $seen[$method] = true;
        $legacy = $byMethod[$method];
        foreach (['module_owner', 'capability', 'operation_id', 'observed_boundary'] as $key) {
            be014hAssert(($op[$key] ?? null) === ($legacy[$key] ?? null), 'Upstream mismatch: ' . $method . '/' . $key);
        }
        be014hAssert(($op['http_method'] ?? null) === ($legacy['method'] ?? null) && ($op['path'] ?? null) === ($legacy['path'] ?? null), 'Route mismatch: ' . $method);
        $role = $op['classification'] ?? null;
        be014hAssert(is_string($role) && isset(($record['classification_counts'] ?? [])[$role]), 'Unknown classification: ' . $method);
        $counts[$role] = ($counts[$role] ?? 0) + 1;
        be014hAssert(isset($bodies[$method]), 'Facade method disappeared: ' . $method);
        $markers = $op['source_body_markers'] ?? null;
        be014hAssert(is_array($markers) && count($markers) > 0, 'Missing method evidence: ' . $method);
        foreach ($markers as $marker) {
            be014hAssert(is_string($marker) && strlen($marker) > 3 && str_contains($bodies[$method], $marker), 'Method evidence changed: ' . $method . '/' . (string) $marker);
        }
        if ($method === 'createProduct') {
            be014hAssert($role === 'KIR001_CREATE' && $op['review_priority'] === 'P0_VERIFY', 'KIR-001 remediation verification priority drift');
        }
    }
    be014hAssert(count($seen) === 22 && count($remaining) === 22 && count(array_diff($remaining, array_keys($seen))) === 0, 'Residual 22-operation mapping drift');
    ksort($counts);
    $expected = $record['classification_counts'];
    ksort($expected);
    be014hAssert($counts === $expected, 'Residual classification count differs from operations');
    $frozen = ['OWNER_DELEGATED' => 5, 'FACADE_PROTECTED_READ' => 4, 'OWNER_MUTATION_READBACK' => 8,
        'NORMALIZE_CREATE' => 3, 'FACADE_AUTH_GATE' => 1, 'KIR001_CREATE' => 1];
    ksort($frozen);
    be014hAssert($counts === $frozen, '14H classification changed; re-audit, do not silently relabel');
    $riskIds = array_column($record['open_risks'] ?? [], 'id');
    be014hAssert($riskIds === ['KIR-001', 'CXT-001', 'PERF-001', 'COMPAT-001'], 'Open risks changed without review');
    be014hAssert(($record['measurement_evidence']['live_latency_p95'] ?? null) === 'NOT_RUN', 'Manifest must not claim runtime data');
}

$checks = [
    '22 operations and six classes reconcile with 26 API routes' => static function () use ($record, $source, $methods): void {
        be014hValidate($record, $source, $methods);
    },
    'four previously delegated list queries are unchanged' => static function () use ($methods): void {
        foreach (['listProducts' => 'ProductListQueryService', 'listPriceLists' => 'PriceListQueryService',
            'listUoms' => 'ProductUomListQueryService', 'listGroups' => 'ProductGroupListQueryService'] as $method => $service) {
            be014hAssert(str_contains($methods[$method], 'new ' . $service . '('), 'Delegated list changed: ' . $method);
        }
    },
    'eight mutation readbacks preserve authorization via fixed-context show helper' => static function () use ($methods): void {
        foreach (['updateGroup' => 'getGroupWithOperation', 'setGroupState' => 'getGroupWithOperation', 'updateUom' => 'getUomWithOperation',
            'setUomState' => 'getUomWithOperation', 'updateProduct' => 'getProductWithOperation', 'setProductState' => 'getProductWithOperation',
            'updatePriceList' => 'getPriceListWithOperation', 'setPriceListState' => 'getPriceListWithOperation'] as $write => $read) {
            be014hAssert(str_contains($methods[$write], '->' . $read . '('), 'Readback removed: ' . $write);
        }
    },
    'independent conversion read retains product.view gate' => static function () use ($methods): void {
        $body = $methods['getConversions'];
        $permission = strpos($body, "->authorize('product', 'view'");
        $read = strpos($body, '->conversions()->listByProduct(');
        be014hAssert($permission !== false && $read !== false && $permission < $read, 'Conversion read bypasses facade authorization');
    },
    'KIR-001 keeps the Product command boundary while Numbering uses own-or-participate semantics' => static function () use ($methods, $app, $record): void {
        $body = $methods['createProduct'];
        foreach (['->transBegin()', '->products()->create(', '->combos()->replaceItems(', '->transCommit()', '->transRollback()'] as $marker) {
            be014hAssert(str_contains($body, $marker), 'KIR-001 code changed: ' . $marker);
        }
        $allocator = (string) file_get_contents($app . 'Modules/Numbering/Allocation/Application/AutoNumberingCounterAllocator.php');
        be014hAssert(str_contains($allocator, '$ownsTransaction = (int) $this->db->transDepth === 0'), 'Numbering own-or-participate decision missing');
        be014hAssert(str_contains($allocator, '$ownsTransaction && ! $this->db->transCommit()'), 'Numbering caller-owned commit guard missing');
        be014hAssert(! str_contains($allocator, 'if ((int) $this->db->transDepth > 0)'), 'Legacy Numbering nested-transaction rejection returned');
        $risks = array_column($record['open_risks'], null, 'id');
        be014hAssert($risks['KIR-001']['status'] === 'REMEDIATION_SOURCE_READY_RUNTIME_PENDING', 'KIR-001 remediation status drift');
    },
    'CXT-001 source fix is recorded while runtime verification remains open' => static function () use ($methods, $record): void {
        be014hAssert(str_contains($methods['operation'], '$this->mutationContext->begin()'), 'OperationContext seam changed');
        be014hAssert(substr_count($methods['createProduct'], '$this->operation()') === 1, 'CreateProduct must capture one context');
        $risks = array_column($record['open_risks'], null, 'id');
        be014hAssert($risks['CXT-001']['status'] === 'SOURCE_FIXED_RUNTIME_NOT_VERIFIED', 'CXT-001 source fix/runtime status drift');
    },
    'group and UOM retain frozen materialized fallback with PBAC before paging' => static function () use ($app): void {
        foreach (['ProductGroup/ProductGroupListQueryService' => 'ProductGroup', 'Uom/ProductUomListQueryService' => 'Uom'] as $pair => $folder) {
            [$subfolder, $service] = explode('/', $pair);
            $path = $app . 'Modules/Product/' . $subfolder . '/Application/' . $service . '.php';
            $body = be014hMethods((string) file_get_contents($path))['list'] ?? '';
            $load = strpos($body, '->listAll()');
            $state = strpos($body, '->authorization->requiresRowStateAuthorization()');
            $row = strpos($body, '->authorization->rowVisible($row)');
            $page = strpos($body, '->paginate(');
            be014hAssert($load !== false && $state !== false && $row !== false && $page !== false &&
                $load < $state && $state < $row && $row < $page, 'Group/UOM PBAC order changed: ' . $folder);
            be014hAssert(str_contains($body, 'new AuthorizedArrayListSource($rows)'), 'Authorized full-materialization changed');
        }
    },
    'repositories remain SELECT all ordered without SQL pagination in listAll' => static function () use ($app): void {
        foreach (['ProductGroup/ProductGroupRepository.php', 'Uom/ProductUomRepository.php'] as $file) {
            $body = be014hMethods((string) file_get_contents($app . 'Modules/Product/' . $file))['listAll'] ?? '';
            be014hAssert(str_contains($body, "->orderBy('code', 'ASC')") && str_contains($body, '->get()'), 'Repository listAll changed: ' . $file);
            be014hAssert(! str_contains($body, '->limit(') && ! str_contains($body, '->paginate('), '014H cannot silently optimize SQL: ' . $file);
        }
    },
    'active Product Media and Image compatibility callers are retained' => static function () use ($app, $methods): void {
        $media = (string) file_get_contents($app . 'Bootstrap/ProductMediaFactory.php');
        $images = (string) file_get_contents($app . 'Controllers/Api/ProductImages.php');
        be014hAssert(str_contains($media, '$catalog->productReadAccess()') && str_contains($images, '->productForImage('), 'Legacy read callers changed');
        be014hAssert(isset($methods['productReadAccess']) && isset($methods['productForImage']), 'Legacy facade cannot yet be retired');
    },
    'synthetic benchmark explicitly excludes DB/Shield/HTTP' => static function () use ($backend): void {
        $source = (string) file_get_contents($backend . '/tools/bench-be-reorg-014h-php.php');
        be014hAssert(str_contains($source, 'SYNTHETIC_PHP_COMPONENT_ONLY_NOT_HTTP_OR_DB'), 'Missing synthetic-only marker');
        be014hAssert(str_contains($source, 'ProductGroupListQueryService') && str_contains($source, 'ProductUomListQueryService'), 'Synthetic code does not execute canonical query services');
    },
    'HTTP profiler is GET only, localhost only, operator-confirmed erp_test' => static function () use ($backend): void {
        $script = (string) file_get_contents($backend . '/tools/bench-be-reorg-014h-http.ps1');
        foreach (['ConfirmTestDatabase', "'erp_test'", 'GetAsync(', "'localhost'", "'127.0.0.1'", 'AllowAutoRedirect = $false', 'ERP_014H_AUTH_COOKIE'] as $marker) {
            be014hAssert(str_contains($script, $marker), 'HTTP profiler safety marker missing: ' . $marker);
        }
        foreach (['PostAsync(', 'PutAsync(', 'DeleteAsync(', 'PatchAsync('] as $forbidden) {
            be014hAssert(! str_contains($script, $forbidden), 'HTTP profiler must not write: ' . $forbidden);
        }
    },
    'manifest manipulation negative fixtures fail closed' => static function () use ($record, $source, $methods): void {
        $variants = [];
        $v = $record; $v['operations'][0]['facade_method'] = 'madeUpMethod'; $variants[] = $v;
        $v = $record; $v['operations'][0]['source_body_markers'] = ['->totallyMissingMethodCall(']; $variants[] = $v;
        $v = $record; $v['operations'][0]['module_owner'] = 'Pricing'; $variants[] = $v;
        $v = $record; $v['already_delegated_list_methods'] = ['listProducts']; $variants[] = $v;
        $v = $record; $v['approval_status'] = 'APPROVED'; $variants[] = $v;
        $v = $record; $v['operations'][0]['classification'] = 'OWNER_DELEGATED'; $variants[] = $v;
        $v = $record; $v['measurement_evidence']['live_latency_p95'] = 'PASS'; $variants[] = $v;
        $v = $record; foreach ($v['operations'] as &$op) { if ($op['facade_method'] === 'createProduct') { $op['review_priority'] = 'P3_KEEP'; } } unset($op); $variants[] = $v;
        foreach ($variants as $i => $invalid) {
            try { be014hValidate($invalid, $source, $methods); }
            catch (RuntimeException) { continue; }
            throw new RuntimeException('Negative fixture incorrectly accepted: ' . $i);
        }
    },
];

$count = 0;
foreach ($checks as $label => $check) {
    try { $check(); echo '  ' . $label . ": PASS\n"; $count++; }
    catch (Throwable $e) { fwrite(STDERR, '  ' . $label . ': FAIL: ' . $e->getMessage() . "\n"); exit(1); }
}
echo "BE-REORG-014H RESIDUAL SOURCE GATE PASS: {$count} checks + 8 negative manifest fixtures; DB/Shield/HTTP perf NOT_RUN.\n";
