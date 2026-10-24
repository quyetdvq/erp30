<?php

declare(strict_types=1);

/** Source-only gate: no DB/HTTP, no new API mutations registered. */
require_once __DIR__ . '/m6/M62ASourceAcceptance.php';

try {
    $root = dirname(__DIR__, 2);
    $old = M62ASourceAcceptance::verify($root);
    if ($old['total'] !== 122) throw new RuntimeException('M6.2A 122-route footprint changed');
    $files = [
        'backend/app/Modules/Access/UnifiedAccessService.php' => 'hasBaseViewPermission(',
        'backend/app/Modules/Product/Catalog/ProductCatalogService.php' => 'hasBaseViewPermission(',
        'backend/app/Modules/Product/Master/ProductReadService.php' => 'hasBaseViewPermission(',
        'backend/app/Modules/Product/Master/ProductReadRules.php' => 'ImageUrl::for(',
        'backend/app/Shared/Media/PublicImageStorage.php' => 'UploadPaths::root()',
        'backend/app/Shared/Media/PublicImageMigrator.php' => 'hash_file(',
        'backend/app/Commands/M62ProductAccessAudit.php' => 'decidePersisted(',
        'backend/.htaccess' => 'public/upload/$1/$2',
        'backend/public/.htaccess' => 'upload/$1/$2',
    ];
    foreach ($files as $path => $signal) {
        $body = @file_get_contents($root . '/' . $path);
        if (! is_string($body) || ! str_contains($body, $signal)) {
            throw new RuntimeException('M6.2A.1 missing reviewed control: ' . $path);
        }
    }
    $group = (string)file_get_contents($root . '/backend/app/Modules/Product/Catalog/ProductCatalogService.php');
    $product = (string)file_get_contents($root . '/backend/app/Modules/Product/Master/ProductReadService.php');
    if (str_contains($group, '$this->actor->can(') || str_contains($product, '$this->actor->can(')
        || ! str_contains($group, '$this->access->canView(') || ! str_contains($product, '$this->access->canView(')) {
        throw new RuntimeException('Product authorization was weakened or mixed with Shield-only grants');
    }
    $storage = (string)file_get_contents($root . '/backend/app/Shared/Media/PublicImageStorage.php');
    if (str_contains($storage, "WRITEPATH") || str_contains($storage, "'/writable")
        || ! str_contains($storage, "@fopen($" . "path, 'x+b')")) {
        throw new RuntimeException('Media path/overwrite policy is not public-upload-only');
    }
    echo "M6.2A.1 SOURCE PASS: 122/150 routes unchanged; unified Product PBAC and public-upload media policy pinned.\n";
    echo "M6.2A.1 PENDING: actor HTTP/PBAC, Apache static files, GD uploads and attachment-owning API verification.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'M6.2A.1 SOURCE FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
