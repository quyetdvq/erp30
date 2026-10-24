<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;

// Standard CI4 I/O guard; intentionally no debug toolbar, hot-reload or dynamic routes.
Events::on('pre_system', static function (): void {
    if (ENVIRONMENT === 'testing') {
        return;
    }
    $compression = ini_get('zlib.output_compression');
    if (filter_var($compression, FILTER_VALIDATE_BOOLEAN) || (int) $compression > 0) {
        throw FrameworkException::forEnabledZlibOutputCompression();
    }
});
