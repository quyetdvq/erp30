<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__.'/../m6/M64ASourceAcceptance.php';

/** M7.1 two frozen Product UOM conversion operations; source-only gate. */
final class M71SourceAcceptance
{
    /** @return array<string,array{id:string,path:string}> */
    public static function operations(): array
    {
        return [
            'GET products/(:num)/uom-conversions' => [
                'id'=>'listProductUomConversions', 'path'=>'/api/v1/products/{id}/uom-conversions',
            ],
            'PUT products/(:num)/uom-conversions' => [
                'id'=>'replaceProductUomConversions', 'path'=>'/api/v1/products/{id}/uom-conversions',
            ],
        ];
    }

    /** @param array<string,array<string,mixed>> $registered */
    public static function validateRegistered(array $registered): void
    {
        $earlier = $registered;
        foreach (self::operations() as $key=>$op) {
            [$verb,$path] = explode(' ', $key, 2);
            $route = 'api/v1/'.$path;
            if (($earlier[$verb][$route] ?? null) !== [
                'App\\Modules\\Product\\Conversion\\ProductConversionController', $op['id'],
            ]) {
                throw new RuntimeException('Missing or wrong M7.1 route: '.$key);
            }
            unset($earlier[$verb][$route]);
        }
        $total = 0;
        foreach ($registered as $verb=>$paths) if ($verb !== 'OPTIONS') $total += count($paths);
        if ($total !== 134) throw new RuntimeException('M7.1 requires exactly 134 routes.');
        M64ASourceAcceptance::validateRegistered($earlier);
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root): array
    {
        $prior = M64ASourceAcceptance::verify($root);
        if ($prior['total'] !== 132) throw new RuntimeException('Missing M6.4A.1 accepted source.');
        $config = (string)file_get_contents($root.'/backend/app/Config/Routes.php');
        if ((\DinkERP\Tools\Routes\LegacyRouteSource::mounted($root, 'M71Routes.php') ? 1 : 0) !== 1
            || !str_contains($config, '$routes->setAutoRoute(false)')) {
            throw new RuntimeException('M7.1 route include/autoroute guard invalid.');
        }
        $source = (string)\DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M71Routes.php');
        $regexp = '~\\$routes->(get|put)\(\x27([^\x27]+)\x27,\s*\[\\\\App\\\\Modules\\\\Product\\\\Conversion\\\\ProductConversionController::class,\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\]\);~';
        preg_match_all($regexp, $source, $matches, PREG_SET_ORDER);
        if (count($matches) !== 2 || substr_count($source, '$routes->') !== 2) {
            throw new RuntimeException('Exactly two M7.1 Product UOM routes required.');
        }
        $frozen = json_decode((string)file_get_contents(
            $root.'/docs/backend-rewrite/openapi-operations-v1.2.4.json'), true, 512, JSON_THROW_ON_ERROR);
        $pins = [];
        foreach ($frozen['operations'] as $op) $pins[$op['method'].' '.$op['path']] = $op;
        if (count($pins) !== 150) throw new RuntimeException('Frozen OpenAPI contract modified.');
        $controller = (string)file_get_contents($root.'/backend/app/Modules/Product/Conversion/ProductConversionController.php');
        $service = (string)file_get_contents($root.'/backend/app/Modules/Product/Conversion/ProductConversionService.php');
        $rules = (string)file_get_contents($root.'/backend/app/Modules/Product/Conversion/ProductConversionRules.php');
        $sql = (string)file_get_contents($root.'/backend/app/Modules/Product/Conversion/ProductConversionSql.php');
        $seen = [];
        foreach ($matches as $match) {
            $key = strtoupper($match[1]).' '.$match[2];
            $expected = self::operations()[$key] ?? null;
            if ($expected === null || isset($seen[$key]) || $match[3] !== $expected['id']
                || !str_contains($controller, 'public function '.$match[3].'(')) {
                throw new RuntimeException('M7.1 unexpected route or handler: '.$key);
            }
            $pin = $pins[strtoupper($match[1]).' '.$expected['path']] ?? null;
            if (($pin['operation_id'] ?? null) !== $expected['id']
                || !in_array(200, $pin['success_statuses'] ?? [], true)) {
                throw new RuntimeException('M7.1 OpenAPI mismatch: '.$key);
            }
            $seen[$key] = true;
        }
        foreach (["'UNAUTHENTICATED', 401", "'ACCESS_MUTATION_NOT_ENABLED', 403",
            "env('DINKERP_M33B_WRITE_MODE', 'off')", 'JsonRequest::readObject(', 'replacementBody(', 'BadRequestException'] as $gate) {
            if (!str_contains($controller, $gate)) throw new RuntimeException('M7.1 HTTP gate missing: '.$gate);
        }
        foreach (["viewableMasterLifecycleStatuses('product')", 'hasBaseViewPermission(',
            "canView('product'", "decideMutation('product', 'edit'", 'ProductConversionSql::authorizedProduct(',
            'ProductConversionPlan::build(', 'assertTransactionEngines(', 'GET_LOCK(', ' FOR UPDATE',
            'transBegin(', 'transRollback(', 'transCommit(', 'resetTransStatus(',
            'where(\'product_id\'', 'perform(\'delete\'', 'perform(\'update\'', 'perform(\'insert\''] as $gate) {
            if (!str_contains($service, $gate)) throw new RuntimeException('M7.1 transaction/PBAC safeguard missing: '.$gate);
        }
        foreach (['MAX_ITEMS = 250', "'conversion_factor'", 'decimal(', 'assertPrice(',
            'assertEligible(', 'PRODUCT_CONVERSION_DUPLICATE', 'array_is_list(', 'PositiveId::value('] as $gate) {
            if (!str_contains($rules, $gate)) throw new RuntimeException('M7.1 decimal/request guard missing: '.$gate);
        }
        foreach (['p.id = ?', 'UPPER(TRIM(p.status))', ' COLLATE utf8mb4_bin IN (', 'LIMIT 1'] as $gate) {
            if (!str_contains($sql, $gate)) throw new RuntimeException('M7.1 SQL VIEW scope missing: '.$gate);
        }
        if (str_contains($service, '->getResultArray() as $product')
            || str_contains($config, 'setAutoRoute(true)')) {
            throw new RuntimeException('M7.1 unbounded table read or autoroute introduced.');
        }
        return ['prior'=>132, 'added'=>2, 'total'=>134, 'baseline'=>150];
    }
}
