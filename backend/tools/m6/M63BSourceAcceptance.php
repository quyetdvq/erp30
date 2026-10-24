<?php

declare(strict_types=1);

require_once __DIR__ . '/M63SourceAcceptance.php';

/** M6.3B: only the frozen User Avatar upload and delete operations. */
final class M63BSourceAcceptance
{
    /** @return array<string,array{id:string,path:string,status:int}> */
    public static function operations(): array
    {
        return [
            'POST users/(:num)/avatar' => ['id' => 'uploadUserAvatar', 'path' => '/api/v1/users/{id}/avatar', 'status' => 200],
            'DELETE users/(:num)/avatar' => ['id' => 'deleteUserAvatar', 'path' => '/api/v1/users/{id}/avatar', 'status' => 200],
        ];
    }

    /** @param array<string,array<string,mixed>> $routes @return array<string,array<string,mixed>> */
    public static function projectedPrevious(array $routes): array
    {
        foreach (self::operations() as $key => $_) {
            [$verb, $path] = explode(' ', $key, 2);
            unset($routes[$verb]['api/v1/' . $path]);
        }
        return $routes;
    }

    /** @param array<string,array<string,mixed>> $routes */
    public static function validateRegistered(array $routes): void
    {
        $owner = 'App\\Modules\\Identity\\Media\\UserAvatarController';
        foreach (self::operations() as $key => $operation) {
            [$verb, $path] = explode(' ', $key, 2);
            if (($routes[$verb]['api/v1/' . $path] ?? null) !== [$owner, $operation['id']]) {
                throw new RuntimeException('M6.3B wrong Identity Avatar mutation route: ' . $key);
            }
        }
        if (isset($routes['GET']['api/v1/users/(:num)/avatar'])
            || isset($routes['PUT']['api/v1/users/(:num)/avatar'])) {
            throw new RuntimeException('Unreviewed User Avatar GET/PUT outside frozen 150 operations');
        }
        $total = 0;
        foreach ($routes as $verb => $entries) if ($verb !== 'OPTIONS') $total += count($entries);
        if ($total !== 129) throw new RuntimeException('M6.3B requires 129 operations, got ' . $total);
        M63SourceAcceptance::validateRegistered(self::projectedPrevious($routes));
    }

    /** @return array{prior:int,added:int,total:int,baseline:int} */
    public static function verify(string $root): array
    {
        $previous = M63SourceAcceptance::verify($root);
        if ($previous['total'] !== 127) throw new RuntimeException('M6.3B requires M6.3A Product Media milestone');
        $main = (string) file_get_contents($root . '/backend/app/Config/Routes.php');
        if (substr_count($main, "require __DIR__ . '/M63BRoutes.php';") !== 1
            || str_contains($main, 'setAutoRoute(true)')) {
            throw new RuntimeException('M6.3B requires a single explicit route include, auto route OFF');
        }
        $source = (string) file_get_contents($root . '/backend/app/Config/M63BRoutes.php');
        $controller = (string) file_get_contents($root . '/backend/app/Modules/Identity/Media/UserAvatarController.php');
        $service = (string) file_get_contents($root . '/backend/app/Modules/Identity/Media/UserAvatarService.php');
        $rules = (string) file_get_contents($root . '/backend/app/Modules/Identity/Media/UserAvatarRules.php');
        $controllerName = preg_quote('\\App\\Modules\\Identity\\Media\\UserAvatarController::class', '~');
        $regex = '~\$routes->(post|delete)\(\x27([^\x27]+)\x27,\s*\[' . $controllerName
            . ',\s*\x27([A-Za-z][A-Za-z0-9_]*)\x27\]\);~';
        preg_match_all($regex, $source, $matches, PREG_SET_ORDER);
        if (count($matches) !== 2 || substr_count($source, '$routes->') !== 2) {
            throw new RuntimeException('Only two User Avatar routes permitted');
        }
        $pins = json_decode((string) file_get_contents($root . '/docs/backend-rewrite/openapi-operations-v1.2.4.json'), true, 512, JSON_THROW_ON_ERROR);
        if (count($pins['operations'] ?? []) !== 150) throw new RuntimeException('Frozen baseline operation count changed');
        $api = [];
        foreach ($pins['operations'] as $op) $api[$op['method'] . ' ' . $op['path']] = $op;
        $seen = [];
        foreach ($matches as $match) {
            $key = strtoupper($match[1]) . ' ' . $match[2];
            $operation = self::operations()[$key] ?? null;
            if ($operation === null || isset($seen[$key]) || $operation['id'] !== $match[3]
                || !str_contains($controller, 'function ' . $operation['id'] . '(')) {
                throw new RuntimeException('Unreviewed Avatar operation: ' . $key);
            }
            $frozen = $api[strtoupper($match[1]) . ' ' . $operation['path']] ?? null;
            if ($frozen === null || ($frozen['operation_id'] ?? null) !== $operation['id']
                || !in_array($operation['status'], $frozen['success_statuses'] ?? [], true)) {
                throw new RuntimeException('Avatar operation differs from frozen OpenAPI: ' . $key);
            }
            $seen[$key] = true;
        }
        foreach (["env('DINKERP_M33B_WRITE_MODE', 'off')", "'UNAUTHENTICATED', 401", "getFile('image')"] as $guard) {
            if (!str_contains($controller, $guard)) throw new RuntimeException('Avatar controller guard missing: ' . $guard);
        }
        foreach (['UserAvatarRules::isOwner(', "canView('user', \$row)", "decideMutation('user', 'edit', \$row)",
            'assertUpload($file)', "->store(\$file->getTempName(), 'avatar')", "->delete(\$old, 'avatar')",
            'where(\'avatar_filename\', $expected)', "where('deleted_at', null)",
            "where('status', (string) \$snapshot['status'])", "where('active', (int) \$snapshot['active'])",
            'affectedRows() !== 1', 'transBegin()', 'transCommit()', 'transRollback()',
            'resetTransStatus()', 'cleanup($new, $id)', 'cleanupOldIfUnreferenced(',
            'countAllResults()', 'PublicImageStorage', "USER_AVATAR_CONCURRENT_UPDATE"] as $guard) {
            if (!str_contains($service, $guard)) throw new RuntimeException('Avatar storage/PBAC/CAS guard missing: ' . $guard);
        }
        if (str_contains($service, 'WRITEPATH') || str_contains($controller, 'readfile(')
            || str_contains($controller, 'function getUserAvatar(')
            || !str_contains($rules, "ImageUrl::for(\$filename, 'avatar')")) {
            throw new RuntimeException('Avatar implementation must never proxy image bytes or expose writable');
        }
        foreach (['en','vi'] as $lang) {
            $catalog = require $root . '/backend/app/Language/' . $lang . '/Api.php';
            foreach (['userAvatarInvalidSize','userAvatarInvalidType','userAvatarInvalidContent',
                'userAvatarConcurrentUpdate','userAvatarStorageFailed'] as $code) {
                if (!is_string($catalog[$code] ?? null) || trim($catalog[$code]) === '') {
                    throw new RuntimeException('Missing frozen Avatar translation: ' . $lang . ' ' . $code);
                }
            }
        }
        return ['prior' => 127, 'added' => 2, 'total' => 129, 'baseline' => 150];
    }
}
