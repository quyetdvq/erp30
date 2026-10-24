<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

final class Security extends BaseConfig
{
    public string $csrfProtection = 'session';
    public bool $tokenRandomize = false;
    public string $tokenName = 'csrf_test_name';
    public string $headerName = 'X-CSRF-TOKEN';
    public string $cookieName = 'csrf_cookie_name';
    public int $expires = 7200;
    public bool $regenerate = true;
    // API failures must be JSON, never a 302 HTML redirect.
    public bool $redirect = false;
}
