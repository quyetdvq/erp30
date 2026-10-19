<?php

declare(strict_types=1);

// This entrypoint is for an ephemeral loopback PHP built-in server only.
// It is never routed by the application or published under public/.
// Capture a redacted health-only stage trace. The PHP built-in server may log
// only "Accepted"/"Closing" when a router exits before normal request logging.
// No request body, cookies, credentials, token values, response body or paths
// are ever written by this callback. It cannot change response behavior.
$pm3HealthStage = 'ROUTER_ENTRY';
$pm3IsHealthRequest = PHP_SAPI === 'cli-server'
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
    && parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) === '/api/v1/health';
if ($pm3IsHealthRequest) {
    register_shutdown_function(static function () use (&$pm3HealthStage): void {
        $log = getenv('ERP_PM3_HTTP_LOG');
        if (! is_string($log)
            || ! str_ends_with(str_replace('\\', '/', $log), '/http-server.log')
            || ! is_dir(dirname($log))) {
            return;
        }
        $write = getenv('ERP_PM_WRITEPATH');
        $token = getenv('ERP_PM_RUN_TOKEN');
        $lastError = error_get_last();
        $fatal = is_array($lastError)
            && in_array($lastError['type'] ?? null, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
        $record = [
            'kind' => 'PM3_HEALTH_STAGE_V1',
            'stage' => $pm3HealthStage,
            'http_status' => http_response_code(),
            'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'UNDEFINED',
            'fatal_php_error_type' => $fatal ? (int) $lastError['type'] : null,
            'supportpath_defined' => defined('SUPPORTPATH'),
            'writepath_defined' => defined('WRITEPATH'),
            'base_guard' => [
                'runner_environment' => getenv('CI_ENVIRONMENT') === 'testing',
                'run_token_format' => is_string($token)
                    && preg_match('/\A[a-f0-9]{32}\z/D', $token) === 1,
                'isolated_write_dir' => is_string($write) && is_dir($write) && ! is_link($write),
                'sandbox_marker_matches' => is_string($write) && is_string($token)
                    && is_file($write . '/.product-media-test-sandbox')
                    && trim((string) file_get_contents($write . '/.product-media-test-sandbox')) === $token,
                'expected_target' => getenv('ERP_FOUNDATION_EXPECTED_TARGET') === '127.0.0.1:3306/erp_test',
                'expected_prefix' => getenv('ERP_FOUNDATION_EXPECTED_PREFIX') === 'dinktech_',
                'remote_loopback' => ($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1',
                'host_loopback' => preg_match('/\A127\.0\.0\.1:[0-9]{2,5}\z/D',
                    (string) ($_SERVER['HTTP_HOST'] ?? '')) === 1,
                'api_route' => str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/v1/'),
            ],
        ];
        $json = json_encode($record, JSON_UNESCAPED_SLASHES);
        if (is_string($json)) {
            // File belongs to the isolated per-run evidence directory.
            @file_put_contents($log . '.health-diagnostic.jsonl', $json . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    });
}

$token = getenv('ERP_PM_RUN_TOKEN');
$write = getenv('ERP_PM_WRITEPATH');
$expected = getenv('ERP_FOUNDATION_EXPECTED_TARGET');
$prefix = getenv('ERP_FOUNDATION_EXPECTED_PREFIX');
if (PHP_SAPI !== 'cli-server'
    || getenv('CI_ENVIRONMENT') !== 'testing'
    || ! is_string($token) || preg_match('/\A[a-f0-9]{32}\z/D', $token) !== 1
    || ! is_string($write) || ! is_dir($write) || is_link($write)
    || ! is_file($write . '/.product-media-test-sandbox')
    || trim((string) file_get_contents($write . '/.product-media-test-sandbox')) !== $token
    || ! is_string($expected) || $expected !== '127.0.0.1:3306/erp_test'
    || ! is_string($prefix) || $prefix !== 'dinktech_'
    || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'
    || ! preg_match('/\A127\.0\.0\.1:[0-9]{2,5}\z/D', (string) ($_SERVER['HTTP_HOST'] ?? ''))
    || ! str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/v1/')) {
    http_response_code(403);
    exit;
}

$pm3HealthStage = 'BASE_GUARD_PASSED';

// CodeIgniter 4.7.4 loads .env before defining ENVIRONMENT, and it
// prioritizes $_ENV / $_SERVER over getenv(). A local .env containing
// CI_ENVIRONMENT=development can therefore defeat the `testing` value
// passed by proc_open() even though the router's getenv() guard passed.
// Synchronize ONLY runner-owned test settings after all loopback/token/DB
// target gates above, and before the normal CodeIgniter web bootstrap.
// No application or .env file is changed; this router is test-only.
$wireOverrides = [
    'CI_ENVIRONMENT' => 'testing',
    'app.forceGlobalSecureRequests' => 'true',
    'security.csrfEnabled' => 'true',
];
foreach ($wireOverrides as $name => $requiredValue) {
    if (getenv($name) !== $requiredValue) {
        $pm3HealthStage = 'OVERRIDE_ENV_REJECTED';
        http_response_code(403);
        exit;
    }
    $_ENV[$name] = $requiredValue;
    $_SERVER[$name] = $requiredValue;
}
$wireBaseURL = 'http://' . $_SERVER['HTTP_HOST'] . '/';
if (getenv('app.baseURL') !== $wireBaseURL) {
    $pm3HealthStage = 'BASEURL_REJECTED';
    http_response_code(403);
    exit;
}
$_ENV['app.baseURL'] = $wireBaseURL;
$_SERVER['app.baseURL'] = $wireBaseURL;

// The HTTP wire server is deliberately plaintext loopback; it cannot exercise
// browser HTTPS cookie transport. The application's .env may enforce production
// Secure/Domain/Path/SameSite cookie attributes, which cannot be relied upon
// at http://127.0.0.1:<ephemeral-port>. Normalize ONLY this guarded test
// subprocess's cookie transport, before CI4 constructs Session/Security.
// Leave CSRF session validation ON and forceGlobalSecureRequests ON; use a
// separately gated Apache + real TLS/browser run for Secure-cookie acceptance.
foreach (['cookie.secure' => 'false', 'cookie.domain' => '',
          'cookie.path' => '/', 'cookie.samesite' => 'Lax'] as $name => $value) {
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

$pm3HealthStage = 'WIRE_SETTINGS_PASSED';

// PM-3-only, redacted forensic record. Not an API endpoint and not shipped to public/.
// The router guard above restricts this to the isolated loopback test process.
// Never log credentials, identity, password hash, tokens, cookie values or body.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) === '/api/v1/auth/login')) {
    register_shutdown_function(static function (): void {
        $log = getenv('ERP_PM3_HTTP_LOG');
        if (! is_string($log) || ! str_ends_with(str_replace('\\', '/', $log), '/http-server.log')
            || ! is_dir(dirname($log))) {
            return;
        }
        $record = [
            'kind' => 'PM3_SHIELD_LOGIN_FORENSIC_V1',
            'http_status' => http_response_code(),
            'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'UNKNOWN',
            'request_method' => 'POST',
        ];
        try {
            if (! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing') {
                $record['stop'] = 'WRONG_ENVIRONMENT';
            } else {
                // Diagnostic booleans only, evaluated at shutdown after CI4's
                // before-filters. This does not prime/rotate CSRF or start a
                // session. Never record the header, session ID, cookie, or hash.
                $csrfConfig = config(\Config\Security::class);
                $sessionConfig = config(\Config\Session::class);
                $cookieConfig = config(\Config\Cookie::class);
                $csrfHeaderKey = 'HTTP_' . strtoupper(str_replace('-', '_', $csrfConfig->headerName));
                $incomingCsrf = $_SERVER[$csrfHeaderKey] ?? null;
                $sessionCsrf = $_SESSION[$csrfConfig->tokenName] ?? null;
                $hasIncomingCsrf = is_string($incomingCsrf) && $incomingCsrf !== '';
                $hasSessionCsrf = is_string($sessionCsrf) && $sessionCsrf !== '';
                $record['csrf_mode_session'] = $csrfConfig->csrfProtection === 'session';
                $record['csrf_enabled'] = $csrfConfig->csrfEnabled;
                $record['csrf_header_received'] = $hasIncomingCsrf;
                $record['csrf_session_hash_present'] = $hasSessionCsrf;
                $record['csrf_header_matches_session_hash'] = $hasIncomingCsrf && $hasSessionCsrf
                    && hash_equals($sessionCsrf, $incomingCsrf);
                $record['session_cookie_received'] = is_string($_COOKIE[$sessionConfig->cookieName] ?? null);
                $record['session_cookie_secure_config'] = $cookieConfig->secure;
                $record['session_active_at_shutdown'] = session_status() === PHP_SESSION_ACTIVE;
                $database = config(\Config\Database::class);
                $db = \Config\Database::connect(null, false);
                $target = $db->query('SELECT DATABASE() AS db, @@port AS port')->getRowArray();
                $record['db_name'] = (string) ($target['db'] ?? '');
                $record['db_port'] = (int) ($target['port'] ?? 0);
                $record['db_group'] = $database->defaultGroup;
                $record['db_prefix'] = (string) $db->DBPrefix;
                if ($record['db_name'] !== 'erp_test' || $record['db_port'] !== 3306
                    || $record['db_prefix'] !== 'dinktech_' || $database->defaultGroup !== 'tests') {
                    $record['stop'] = 'UNSAFE_HTTP_DATABASE_TARGET';
                } else {
                    // Fetch the already-parsed request body AFTER controller dispatch.
                    // Do not consume php://input before CodeIgniter receives it.
                    $payload = json_decode((string) service('request')->getBody(), true);
                    $identifier = is_array($payload) ? ($payload['email'] ?? null) : null;
                    $password = is_array($payload) ? ($payload['password'] ?? null) : null;
                    $record['email_password_payload'] = is_string($identifier)
                        && $identifier !== '' && is_string($password) && $password !== '';
                    if ($record['email_password_payload']) {
                        $row = $db->table('auth_identities')
                            ->select('user_id, secret2')
                            ->where('type', \CodeIgniter\Shield\Authentication\Authenticators\Session::ID_TYPE_EMAIL_PASSWORD)
                            ->where('secret', $identifier)->get()->getRowArray();
                        $record['identity_row_found'] = is_array($row);
                        if (is_array($row)) {
                            $record['raw_hash_verifies'] = is_string($row['secret2'] ?? null)
                                && password_verify($password, $row['secret2']);
                        }
                        $provider = auth('session')->getProvider();
                        $record['provider_class'] = get_class($provider);
                        $user = $provider->findByCredentials(['email' => $identifier]);
                        $record['provider_user_found'] = $user instanceof \CodeIgniter\Shield\Entities\User;
                        if ($user instanceof \CodeIgniter\Shield\Entities\User) {
                            $record['provider_identity_user_matches'] = is_array($row)
                                && (int) $user->id === (int) $row['user_id'];
                            $hash = $user->password_hash;
                            $record['provider_password_hash_present'] = is_string($hash) && $hash !== '';
                            if ($record['provider_password_hash_present']) {
                                $record['shield_password_service_verifies'] = service('passwords')->verify($password, $hash);
                            }
                            $record['user_active_flag'] = (int) $user->active === 1;
                            $record['user_is_banned'] = $user->isBanned();
                            $record['user_is_activated'] = $user->isActivated();
                        }
                    }
                }
            }
        } catch (\Throwable $exception) {
            $record['probe_exception_class'] = get_class($exception);
            // Intentionally omit exception messages: they might contain sensitive input.
        }
        file_put_contents($log . '.auth-diagnostic.jsonl',
            json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
            FILE_APPEND | LOCK_EX);
    });
}

// CodeIgniter 4.7.4 force_https() has a special testing-only bypass:
// HTTPS must be exactly 'test' when ENVIRONMENT === 'testing'. The old 'on'
// unconditionally triggered HTTP 307 in testing even when isSecure() was true.
// Retain the secure-runtime/CSRF filters without redirecting loopback HTTP.
// This simulates a secure request; it is NOT TLS or browser HTTPS acceptance.
$_SERVER['HTTPS'] = 'test';
$_SERVER['REQUEST_SCHEME'] = 'https';
// CI4's web bootstrap does not define SUPPORTPATH, but AutoloadConfig
// references it when the guarded HTTP process runs under `testing`.
// PHPUnit's bootTest() establishes the same constant before autoloading.
// Keep the mapping local to this test-only router, outside public/.
$supportPath = realpath(dirname(__DIR__));
if ($supportPath === false || ! is_dir($supportPath)
    || ! is_dir($supportPath . DIRECTORY_SEPARATOR . 'ProductMedia')) {
    $pm3HealthStage = 'SUPPORTPATH_REJECTED';
    http_response_code(503);
    exit;
}
defined('SUPPORTPATH') || define('SUPPORTPATH', $supportPath . DIRECTORY_SEPARATOR);

defined('WRITEPATH') || define('WRITEPATH', realpath($write) . DIRECTORY_SEPARATOR);
$pm3HealthStage = 'CI_BOOTSTRAP_ENTERED';
// Do not call the production public/index.php here: its Boot::bootWeb()
// leaves no opportunity to register a real, file-backed session before
// Security/CSRF in the HTTP subprocess. Use the *same* CI4 Boot::bootWeb()
// sequence through a test-only subclass whose pre-run hook wires Session.
// Normal public/index.php and all production routes remain untouched.
if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}
if (version_compare(PHP_VERSION, '8.2', '<')) {
    $pm3HealthStage = 'PHP_VERSION_REJECTED';
    http_response_code(503);
    exit;
}
$publicDir = realpath(dirname(__DIR__, 3) . '/public');
if ($publicDir === false || ! is_dir($publicDir)
    || ! is_file($publicDir . '/index.php')) {
    $pm3HealthStage = 'PUBLIC_FRONT_CONTROLLER_REJECTED';
    http_response_code(503);
    exit;
}
defined('FCPATH') || define('FCPATH', $publicDir . DIRECTORY_SEPARATOR);
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}
require_once FCPATH . '../app/Config/Paths.php';
$paths = new \Config\Paths();
require_once $paths->systemDirectory . '/Boot.php';
require_once __DIR__ . '/Pm3WireBoot.php';
exit(\Tests\Support\ProductMedia\Pm3WireBoot::bootWeb($paths));
