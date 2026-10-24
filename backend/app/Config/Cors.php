<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

final class Cors extends BaseConfig
{
    /** @var array<string, mixed> */
    public array $default = [
        'allowedOrigins' => [],
        'allowedOriginsPatterns' => [],
        'supportsCredentials' => true,
        'allowedHeaders' => ['Accept', 'Content-Type', 'Accept-Language', 'X-Request-ID', 'X-CSRF-TOKEN', 'Authorization', 'If-Match', 'If-None-Match'],
        'exposedHeaders' => ['X-Request-ID', 'Content-Language', 'X-CSRF-TOKEN', 'ETag'],
        'allowedMethods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'maxAge' => 7200,
    ];

    public function __construct()
    {
        parent::__construct();
        $configured = trim((string) env('cors.allowedOrigins', ''));
        $origins = $configured === '' && ENVIRONMENT !== 'production'
            ? ['http://erp.dinktech.vn', 'https://erp.monmin.net', 'http://erp.monmin.net',
                'http://localhost:5173', 'http://127.0.0.1:5173',
                'http://localhost:5174', 'http://127.0.0.1:5174']
            : array_map('trim', explode(',', $configured));
        $valid = [];
        foreach ($origins as $origin) {
            $origin = rtrim($origin, '/');
            if ($origin === '') {
                continue;
            }
            $pieces = parse_url($origin);
            if (! is_array($pieces) || ! in_array($pieces['scheme'] ?? '', ['http', 'https'], true)
                || ! isset($pieces['host']) || isset($pieces['path']) || isset($pieces['query']) || isset($pieces['fragment'])
                || str_contains($origin, '*') || isset($pieces['user']) || isset($pieces['pass'])) {
                throw new \InvalidArgumentException('cors.allowedOrigins must contain exact HTTP(S) origins.');
            }
            $valid[$origin] = true;
        }
        $valid = array_keys($valid);
        // One CI4 literal origin can be emitted unconditionally. Use an anchored regex for a single origin.
        if (count($valid) === 1) {
            $this->default['allowedOriginsPatterns'] = [preg_quote($valid[0], '#')];
        } else {
            $this->default['allowedOrigins'] = $valid;
        }
    }
}
