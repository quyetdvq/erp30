<?php

declare(strict_types=1);

/** MEDIA-018B: no authenticated binary image reads, no SQL per static-file GET. */
$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);
$check = static function (bool $ok, string $label): void {
    if (! $ok) throw new RuntimeException('MEDIA-018B FAILED: ' . $label);
    echo '  ' . $label . ': PASS' . PHP_EOL;
};
$routes = $read('backend/app/Config/Routes.php');
$check(! str_contains($routes, "\$routes->get('products/(:num)/image'")
    && ! str_contains($routes, "\$routes->get('users/(:num)/avatar'")
    && str_contains($routes, "\$routes->post('products/(:num)/image'")
    && str_contains($routes, "\$routes->post('users/(:num)/avatar'")
    && str_contains($routes, "\$routes->put('users/(:num)/avatar'")
    && str_contains($routes, "\$routes->delete('products/(:num)/image'")
    && str_contains($routes, "\$routes->delete('users/(:num)/avatar'"), 'read GET routes removed; authorized mutation routes preserved');
$check(str_contains($read('backend/app/Config/Routing.php'), 'public bool $autoRoute = false;'),
    'no CI4 autoroute exposes removed read methods');
foreach (['backend/app/Controllers/Api/ProductImages.php', 'backend/app/Controllers/Api/UserAvatars.php'] as $path) {
    $body = $read($path);
    $check(! preg_match('/function\s+show\s*\(/', $body) && str_contains($body, 'function upload(')
        && str_contains($body, 'function delete(') && ! str_contains($body, 'file_get_contents('), $path . ' upload/delete only');
}
foreach (['backend/app/Modules/Product/Media/Application/ProductImageApplicationService.php',
    'backend/app/Modules/Identity/Application/UserAvatarService.php',
    'backend/app/Services/ProductImageService.php'] as $path) {
    $check(! preg_match('/function\s+read\s*\(/', $read($path)), $path . ' has no image-byte read');
}
$openapi = $read('contracts/openapi/openapi.yaml');
$check(! str_contains($openapi, 'getUserAvatar') && ! str_contains($openapi, 'getProductImage')
    && ! str_contains($openapi, 'UserAvatarBinary:')
    && str_contains($openapi, 'operationId: uploadUserAvatar')
    && str_contains($openapi, 'operationId: uploadProductImage'), 'OpenAPI documents only authorized image mutations');
foreach (['frontend/src/components/products/ProductListThumbnail.vue',
    'frontend/src/components/products/ProductImageField.vue',
    'frontend/src/components/users/UserAvatar.vue'] as $path) {
    $body = $read($path);
    $check(str_contains($body, "uploadImageUrl(") && ! str_contains($body, 'fetchProductImage')
        && ! str_contains($body, 'readUserAvatar') && ! str_contains($body, "responseType: 'blob'"),
        $path . ' uses a URL, not binary API fallback');
}
$check(! str_contains($read('frontend/src/services/productImage.ts'), 'fetchProductImage')
    && ! str_contains($read('frontend/src/services/userAvatar.ts'), 'readUserAvatar')
    && ! str_contains($read('frontend/src/api/generated/sdk.gen.ts'), 'getProductImage')
    && ! str_contains($read('frontend/src/api/generated/sdk.gen.ts'), 'getUserAvatar'),
    'service layer and generated SDK expose no binary reads');
$avatarService = $read('frontend/src/services/userAvatar.ts');
$check(str_contains($avatarService, 'apiClient.post')
    && ! str_contains($avatarService, "form.append('_method'")
    && str_contains($read('frontend/src/api/generated/sdk.gen.ts'), ').post<UploadUserAvatarResponses')
    && preg_match('/\bpost:\r?\n\s*operationId: uploadUserAvatar\b/', $openapi) === 1,
    'Avatar upload uses native POST multipart without method spoofing');
$barrel = $read('frontend/src/api/generated/index.ts');
$check(! preg_match('/\bGet(?:ProductImage|UserAvatar)(?:Data|Error|Errors|Response|Responses)\b/', $barrel),
    'generated SDK barrel does not re-export removed image read types');
$check(str_contains($read('backend/app/Infrastructure/Media/YearlyImageStorage.php'),
    "return '/writable/uploads/' . substr(")
    && str_contains($read('frontend/src/components/products/ProductListThumbnail.vue'), 'IntersectionObserver'),
    'validated static URL and lazy loading kept');
echo 'MEDIA-018B STATIC-ONLY SOURCE GATE PASS (Apache/network/runtime not checked).' . PHP_EOL;
