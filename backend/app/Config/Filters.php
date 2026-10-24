<?php

declare(strict_types=1);

namespace Config;

use App\Shared\Http\RequestMetadataFilter;
use App\Shared\Http\CsrfResponseFilter;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\SecureHeaders;

final class Filters extends BaseFilters
{
    public array $aliases = [
        'cors' => Cors::class,
        'csrf' => CSRF::class,
        'forcehttps' => ForceHTTPS::class,
        'secureheaders' => SecureHeaders::class,
        'requestmeta' => RequestMetadataFilter::class,
        'csrfresponse' => CsrfResponseFilter::class,
    ];

    // Keep core force-HTTPS filter active when App config enables it.
    public array $required = [
        'before' => ['forcehttps'],
        'after' => [],
    ];

    public array $globals = [
        // Metadata and CORS must precede CSRF so errors get stable headers.
        'before' => ['cors', 'requestmeta', 'csrf'],
        'after' => ['requestmeta', 'csrfresponse', 'secureheaders'],
    ];

    public array $methods = [];
    public array $filters = [];
}
