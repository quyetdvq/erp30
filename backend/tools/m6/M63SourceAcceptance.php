<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/routes/LegacyRouteSource.php';

require_once __DIR__ . '/M62BSourceAcceptance.php';

/** M6.3A: two exact Product representative image mutation endpoints. */
final class M63SourceAcceptance
{
    /** @return array<string,array{id:string,path:string,status:int}> */
    public static function operations(): array
    {
        return [
            'POST products/(:num)/image' => ['id'=>'uploadProductImage', 'path'=>'/api/v1/products/{id}/image', 'status'=>200],
            'DELETE products/(:num)/image' => ['id'=>'deleteProductImage', 'path'=>'/api/v1/products/{id}/image', 'status'=>200],
        ];
    }

    /** @param array<string,array<string,mixed>> $routes @return array<string,array<string,mixed>> */
    public static function projectedPrevious(array $routes): array
    {
        foreach (self::operations() as $key => $_) {
            [$verb,$path] = explode(' ', $key, 2);
            unset($routes[$verb]['api/v1/'.$path]);
        }
        return $routes;
    }

    /** @param array<string,array<string,mixed>> $routes */
    public static function validateRegistered(array $routes): void
    {
        $owner = 'App\\Modules\\Product\\Media\\ProductImageController';
        foreach (self::operations() as $key => $op) {
            [$verb,$path] = explode(' ', $key, 2);
            if (($routes[$verb]['api/v1/'.$path] ?? null) !== [$owner, $op['id']]) {
                throw new RuntimeException('M6.3A wrong Product image mutation route: '.$key);
            }
        }
        $total = 0;
        foreach ($routes as $method => $entries) if ($method !== 'OPTIONS') $total += count($entries);
        if ($total !== 127) throw new RuntimeException('M6.3A requires 127 exact operations, got '.$total);
        if (isset($routes['GET']['api/v1/products/(:num)/image'])) {
            throw new RuntimeException('Product image bytes may never be served by a GET controller');
        }
        M62BSourceAcceptance::validateRegistered(self::projectedPrevious($routes));
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root): array
    {
        $previous = M62BSourceAcceptance::verify($root);
        if ($previous['total'] !== 125) throw new RuntimeException('M6.3A requires 125-route Product Master milestone');
        $main = (string) file_get_contents($root.'/backend/app/Config/Routes.php');
        if ((\DinkERP\Tools\Routes\LegacyRouteSource::mounted($root, 'M63Routes.php') ? 1 : 0) !== 1
            || str_contains($main,'setAutoRoute(true)')) {
            throw new RuntimeException('M6.3A must use one explicit route include and no autoroute');
        }
        $routes = (string) \DinkERP\Tools\Routes\LegacyRouteSource::source($root, 'M63Routes.php');
        $controller = (string) file_get_contents($root.'/backend/app/Modules/Product/Media/ProductImageController.php');
        $regex = '~\$routes->(post|delete)\(\x27([^\x27]+)\x27,\s*\[\\\\App\\\\Modules\\\\Product\\\\Media\\\\ProductImageController::class,\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\]\);~';
        preg_match_all($regex, $routes, $matches, PREG_SET_ORDER);
        if (count($matches) !== 2 || substr_count($routes, '$routes->') !== 2) {
            throw new RuntimeException('Only the two frozen Product Media mutations are approved');
        }
        $pins = json_decode((string) file_get_contents($root.'/docs/backend-rewrite/openapi-operations-v1.2.4.json'), true, 512, JSON_THROW_ON_ERROR);
        if (count($pins['operations'] ?? []) !== 150) throw new RuntimeException('Frozen API count changed');
        $api = [];
        foreach ($pins['operations'] as $op) $api[$op['method'].' '.$op['path']] = $op;
        $seen = [];
        foreach ($matches as $m) {
            $key = strtoupper($m[1]).' '.$m[2];
            $operation = self::operations()[$key] ?? null;
            if ($operation === null || isset($seen[$key]) || $operation['id'] !== $m[3]
                || !str_contains($controller, 'function '.$m[3].'(')) {
                throw new RuntimeException('Unapproved Product Media mutation: '.$key);
            }
            $frozen = $api[strtoupper($m[1]).' '.$operation['path']] ?? null;
            if ($frozen === null || ($frozen['operation_id'] ?? null) !== $operation['id']
                || !in_array($operation['status'], $frozen['success_statuses'] ?? [], true)) {
                throw new RuntimeException('Product Media operation drifted from frozen contract: '.$key);
            }
            $seen[$key] = true;
        }
        $service = (string) file_get_contents($root.'/backend/app/Modules/Product/Media/ProductImageService.php');
        foreach (['decideMutation(', 'canView(', 'FOR UPDATE', 'GET_LOCK(', 'RELEASE_LOCK(',
            'transBegin()', 'transCommit()', 'transRollback()', "'image_filename'",
            'PublicImageStorage', "':product-master'", 'ImageUrl::for(', 'cleanup(', 'ProductReadService'] as $guard) {
            if (!str_contains($service, $guard)) throw new RuntimeException('Product Media security/atomicity guard missing: '.$guard);
        }
        foreach (['UNAUTHENTICATED', "'UNAUTHENTICATED', 401", "getFile('image')"] as $guard) {
            if (!str_contains($controller, $guard)) throw new RuntimeException('Product Media request boundary missing: '.$guard);
        }
        foreach (['en','vi'] as $lang) {
            $values = require $root.'/backend/app/Language/'.$lang.'/ProductRuntime.php';
            foreach (['PRODUCT_IMAGE_INVALID_SIZE','PRODUCT_IMAGE_INVALID_TYPE',
                'PRODUCT_IMAGE_INVALID_CONTENT','PRODUCT_IMAGE_STORAGE_FAILED'] as $code) {
                if (!is_string($values[$code] ?? null) || trim($values[$code]) === '') {
                    throw new RuntimeException('Missing bilingual image error: '.$lang.' '.$code);
                }
            }
        }
        $storage = (string) file_get_contents($root.'/backend/app/Shared/Media/PublicImageStorage.php');
        if (str_contains($storage, 'WRITEPATH') || !str_contains($storage, 'UploadPaths::root()')) {
            throw new RuntimeException('Product image storage cannot expose writable');
        }
        if (str_contains($controller, 'function get') || is_dir($root.'/backend/app/Modules/ProductMedia')) {
            throw new RuntimeException('Product Media GET/second module owner is forbidden');
        }
        return ['prior'=>125, 'added'=>2, 'total'=>127, 'baseline'=>150];
    }
}
