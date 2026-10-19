<?php

declare(strict_types=1);

namespace Tests\Support\ProductMedia;

use CodeIgniter\Boot;
use CodeIgniter\CodeIgniter;
use CodeIgniter\Session\Handlers\FileHandler;
use Config\Cookie as CookieConfig;
use Config\Security as SecurityConfig;
use Config\Services;
use Config\Session as SessionConfig;
use RuntimeException;

/**
 * CI4's real boot sequence with one pre-request, test-only service seam.
 *
 * Boot::bootWeb() calls runCodeIgniter() only AFTER loading environment,
 * common functions, the autoloader and app services; it has not yet called
 * CodeIgniter::run(), so Security/CSRF before-filters cannot have run.
 */
final class Pm3WireBoot extends Boot
{
    protected static function runCodeIgniter(CodeIgniter $app): void
    {
        self::installWireSession();
        parent::runCodeIgniter($app);
    }

    private static function installWireSession(): void
    {
        $write = getenv('ERP_PM_WRITEPATH');
        $runToken = getenv('ERP_PM_RUN_TOKEN');
        if (PHP_SAPI !== 'cli-server' || ! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing'
            || getenv('ERP_PM3_WIRE_ENABLE') !== '1'
            || ! is_string($write) || ! is_dir($write) || is_link($write)
            || ! is_string($runToken) || preg_match('/\A[a-f0-9]{32}\z/D', $runToken) !== 1
            || ! is_file($write . '/.product-media-test-sandbox')
            || trim((string) file_get_contents($write . '/.product-media-test-sandbox')) !== $runToken
            || realpath($write) !== realpath(WRITEPATH)
            || getenv('ERP_FOUNDATION_EXPECTED_TARGET') !== '127.0.0.1:3306/erp_test'
            || getenv('ERP_FOUNDATION_EXPECTED_PREFIX') !== 'dinktech_'
            || session_status() !== PHP_SESSION_NONE || headers_sent()) {
            throw new RuntimeException('PM-3 wire session guard failed before Security/CSRF.');
        }

        $sessionDirectory = WRITEPATH . 'session';
        if (! is_dir($sessionDirectory) || is_link($sessionDirectory) || ! is_writable($sessionDirectory)
            || realpath($sessionDirectory) !== realpath($write . '/session')) {
            throw new RuntimeException('PM-3 wire session directory is not isolated and writable.');
        }
        // Fail closed if another bootstrap step constructed Session or Security
        // before this test-only seam; replacing them would hide a real ordering bug.
        if (Services::has('session') || Services::has('security')) {
            throw new RuntimeException('PM-3 session/security already initialized before wire boot hook.');
        }
        $security = config(SecurityConfig::class);
        $cookie = config(CookieConfig::class);
        if (! $security->csrfEnabled || $security->csrfProtection !== 'session'
            || $cookie->secure || $cookie->domain !== '' || $cookie->path !== '/'
            || $cookie->samesite !== 'Lax') {
            throw new RuntimeException('PM-3 wire CSRF/cookie contract is not enforced.');
        }

        // Preserve the app's session cookie identity, but never inherit its
        // storage driver or save path: the HTTP process must use only files in
        // the runner-owned disposable WRITEPATH, not a production DB/session.
        $sessionConfig = config(SessionConfig::class);
        if ($sessionConfig->cookieName !== 'ci_session') {
            throw new RuntimeException('PM-3 wire session cookie name unexpectedly changed.');
        }
        $sessionConfig->driver = FileHandler::class;
        $sessionConfig->savePath = $sessionDirectory;

        // Same wiring as CI4 Services::session(false), but with an adapter that
        // honors PHP start/close/destroy in `testing` and a strict FileHandler.
        $logger = Services::logger();
        $driver = new FileHandler($sessionConfig, '127.0.0.1');
        $driver->setLogger($logger);
        require_once __DIR__ . '/Pm3RealHttpSession.php';
        $session = new Pm3RealHttpSession($driver, $sessionConfig);
        $session->setLogger($logger);
        Services::response()->removeHeader('Cache-Control');
        if ($session->start() !== $session || session_status() !== PHP_SESSION_ACTIVE
            || realpath((string) ini_get('session.save_path')) !== realpath($sessionDirectory)) {
            throw new RuntimeException('PM-3 wire session did not start in sandbox.');
        }
        Services::injectMock('session', $session);
        if (service('session') !== $session) {
            throw new RuntimeException('PM-3 wire session service was not installed.');
        }
        // Health-only diagnostic stage, without a session ID or cookie value.
        // The guarded router owns the shutdown callback reading this variable.
        if (isset($GLOBALS['pm3HealthStage'])) {
            $GLOBALS['pm3HealthStage'] = 'WIRE_SESSION_INSTALLED';
        }
    }
}
