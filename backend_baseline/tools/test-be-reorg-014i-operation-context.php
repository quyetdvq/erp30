<?php

declare(strict_types=1);

/**
 * BE-REORG-014I source-level gate for API-050 command-scoped OperationContext.
 *
 * This intentionally avoids Composer/DB/HTTP. It protects the architectural
 * invariant introduced by 014I while KIR-001 remains frozen for runtime POC.
 */

$backend = dirname(__DIR__);
$path = $backend . '/app/Services/Api050ProductCatalogService.php';
$source = (string) file_get_contents($path);
$methods = be014iMethods($source);

function be014iAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string,string> */
function be014iMethods(string $php): array
{
    $tokens = token_get_all($php, TOKEN_PARSE);
    $methods = [];
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }
        $j = $i + 1;
        while ($j < $count && ($tokens[$j] === '&' || (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE))) {
            $j++;
        }
        if ($j >= $count || ! is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) {
            continue;
        }
        $name = $tokens[$j][1];
        while ($j < $count && $tokens[$j] !== '{' && $tokens[$j] !== ';') {
            $j++;
        }
        if ($j >= $count || $tokens[$j] === ';') {
            continue;
        }
        $depth = 1;
        $body = '';
        for ($j++; $j < $count && $depth > 0; $j++) {
            $token = $tokens[$j];
            if ($token === '{') {
                $depth++;
            } elseif ($token === '}') {
                $depth--;
            }
            if ($depth > 0) {
                $body .= is_array($token) ? $token[1] : $token;
            }
        }
        $methods[$name] = $body;
    }
    return $methods;
}

$checks = [
    'nine mutation-readback commands capture exactly one context' => static function () use ($methods): void {
        $cases = [
            'updateGroup' => ['->groups()->update(', 'getGroupWithOperation'],
            'setGroupState' => ['->groups()->setState(', 'getGroupWithOperation'],
            'updateUom' => ['->uoms()->update(', 'getUomWithOperation'],
            'setUomState' => ['->uoms()->setState(', 'getUomWithOperation'],
            'updateProduct' => ['->products()->update(', 'getProductWithOperation'],
            'setProductState' => ['->products()->setState(', 'getProductWithOperation'],
            'updatePriceList' => ['->priceLists()->update(', 'getPriceListWithOperation'],
            'setPriceListState' => ['->priceLists()->setState(', 'getPriceListWithOperation'],
            'createPriceList' => ['->priceLists()->create(', 'getPriceListWithOperation'],
        ];
        foreach ($cases as $method => [$write, $readback]) {
            $body = $methods[$method] ?? '';
            be014iAssert(substr_count($body, '$this->operation()') === 1, $method . ' must capture one context');
            be014iAssert(str_contains($body, '$operation = $this->operation();'), $method . ' does not name its command context');
            be014iAssert(str_contains($body, $write) && str_contains($body, ', $operation)'), $method . ' mutation does not receive context');
            be014iAssert(str_contains($body, '->' . $readback . '($id, $operation)'), $method . ' readback does not reuse context');
        }
    },
    'readback helpers authorize with the supplied context' => static function () use ($methods): void {
        foreach (['getGroupWithOperation', 'getUomWithOperation', 'getPriceListWithOperation'] as $method) {
            $body = $methods[$method] ?? '';
            be014iAssert(str_contains($body, 'operation: $operation'), $method . ' recaptures authorization context');
            be014iAssert(! str_contains($body, '$this->operation()'), $method . ' must not recapture context');
        }
        $product = $methods['getProductWithOperation'] ?? '';
        be014iAssert(str_contains($product, 'static fn (): OperationContext => $operation'), 'Product readback does not pin supplied context');
        be014iAssert(str_contains($product, "->authorizedRow(\$id, 'view')"), 'Product readback lost persisted-row view authorization');
        be014iAssert(! str_contains($product, '$this->operation()'), 'Product readback must not recapture context');
    },
    'createProduct shares one context across create combo and response read' => static function () use ($methods): void {
        $body = $methods['createProduct'] ?? '';
        be014iAssert(substr_count($body, '$this->operation()') === 1, 'createProduct must capture exactly one context');
        be014iAssert(substr_count($body, '->products()->create($payload, $operation)') === 2, 'Both Product create branches must reuse context');
        be014iAssert(str_contains($body, '->combos()->replaceItems($id, $items, $operation)'), 'COMBO composition does not reuse context');
        be014iAssert(substr_count($body, '->getProductWithOperation($id, $operation)') === 2, 'Product response readback does not reuse context');
    },
    'KIR-001 transaction structure remains frozen' => static function () use ($methods): void {
        $body = $methods['createProduct'] ?? '';
        foreach (['->transBegin()', '->transStatus()', '->transCommit()', '->transRollback()', '->resetTransStatus()'] as $marker) {
            be014iAssert(str_contains($body, $marker), 'KIR-001 transaction marker changed: ' . $marker);
        }
    },
    'standalone reads still capture per invocation and preserve public compatibility' => static function () use ($methods): void {
        foreach (['getGroup' => 'getGroupWithOperation', 'getUom' => 'getUomWithOperation', 'getPriceList' => 'getPriceListWithOperation'] as $method => $helper) {
            $body = $methods[$method] ?? '';
            be014iAssert(substr_count($body, '$this->operation()') === 1, $method . ' must capture one invocation context');
            be014iAssert(str_contains($body, '->' . $helper . '($id, $this->operation())'), $method . ' no longer delegates through fixed-context helper');
        }
        // Product GET now adds advisory PBAC action flags to its authorized
        // result. Both the readback and flags must share its one operation.
        $product = $methods['getProduct'] ?? '';
        be014iAssert(substr_count($product, '$this->operation()') === 1, 'getProduct must capture one invocation context');
        be014iAssert(str_contains($product, '$operation = $this->operation();'), 'Product GET does not pin its context');
        be014iAssert(str_contains($product, '->getProductWithOperation($id, $operation)'), 'Product GET readback uses another context');
        be014iAssert(str_contains($product, '->withAllowedActions($operation, [$row])'), 'Product GET action flags use another context');
        $compat = $methods['productReadAccess'] ?? '';
        be014iAssert(str_contains($compat, 'fn (): OperationContext => $this->operation()'), 'Product Media compatibility seam changed');
    },
    'operation factory remains non-cached' => static function () use ($methods, $source): void {
        $body = $methods['operation'] ?? '';
        be014iAssert(str_contains($body, '$this->mutationContext->begin()'), 'Injected MutationContext behavior changed');
        be014iAssert(str_contains($body, 'new MutationContext(') && str_contains($body, ')->begin()'), 'Runtime authenticated operation capture changed');
        be014iAssert(! preg_match('/private\s+\??OperationContext\s+\$[A-Za-z_][A-Za-z0-9_]*\s*=/', $source), 'OperationContext must not be cached as facade state');
    },
    'authorization helper supports explicit context without changing default callers' => static function () use ($methods): void {
        $authorize = $methods['authorize'] ?? '';
        $decision = $methods['decision'] ?? '';
        be014iAssert(str_contains($authorize, '$operation'), 'authorize lacks explicit operation seam');
        be014iAssert(str_contains($decision, '$operation ?? $this->operation()'), 'decision does not preserve lazy default behavior');
    },
];

$passed = 0;
foreach ($checks as $label => $check) {
    try {
        $check();
        echo '  ' . $label . ": PASS\n";
        $passed++;
    } catch (Throwable $e) {
        fwrite(STDERR, '  ' . $label . ': FAIL: ' . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "BE-REORG-014I OPERATION CONTEXT SOURCE GATE PASS: {$passed} checks; DB/Shield/HTTP runtime NOT_RUN.\n";
