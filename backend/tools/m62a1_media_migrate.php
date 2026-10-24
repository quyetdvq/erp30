<?php

declare(strict_types=1);

/**
 * php tools/m62a1_media_migrate.php [--apply] [--baseline]
 * Dry-run by default; never deletes original media or edits database rows.
 * Copies only recognized baseline Product/Avatar images into public/upload.
 */
require_once __DIR__ . '/../app/Shared/Media/ImageUrl.php';
require_once __DIR__ . '/../app/Shared/Media/UploadPaths.php';
require_once __DIR__ . '/../app/Shared/Media/PublicImageMigrator.php';

use App\Shared\Media\PublicImageMigrator;
use App\Shared\Media\UploadPaths;

$options = array_slice($argv, 1);
if (array_diff($options, ['--apply', '--baseline']) !== []) {
    fwrite(STDERR, "Usage: php tools/m62a1_media_migrate.php [--apply] [--baseline]\n");
    exit(2);
}
$project = dirname(__DIR__, 2);
$source = in_array('--baseline', $options, true)
    ? $project . '/backend_baseline/writable/uploads'
    : $project . '/backend/writable/uploads';
$apply = in_array('--apply', $options, true);
try {
    $report = (new PublicImageMigrator($source, UploadPaths::root()))->run($apply);
    echo 'M6.2A.1 PUBLIC MEDIA ' . ($apply ? 'COPY' : 'DRY-RUN') . ': '
        . json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    echo "NOTE: old images are retained; legacy 40-hex files and unrelated attachments are never copied.\n";
    exit($report['conflicts'] > 0 ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'M6.2A.1 MEDIA BLOCKED: ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
