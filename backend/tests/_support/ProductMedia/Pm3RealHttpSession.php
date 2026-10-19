<?php

declare(strict_types=1);

namespace Tests\Support\ProductMedia;

use CodeIgniter\Session\Session;
use RuntimeException;

/**
 * PM-3 wire-HTTP-only session adapter.
 *
 * CI4 4.7.4 normally avoids PHP's native session lifecycle in the `testing`
 * environment. That behavior is appropriate for PHPUnit feature tests but
 * cannot carry a cookie-backed session between two independent HTTP requests.
 * Only Pm3WireBoot loads this class inside the guarded loopback subprocess.
 * No production service, vendor class or PHPUnit bootstrap is replaced.
 */
final class Pm3RealHttpSession extends Session
{
    protected function startSession(): void
    {
        $this->assertWireContext();
        if (session_status() !== PHP_SESSION_NONE || ! session_start()) {
            throw new RuntimeException('PM-3 wire session could not start.');
        }
    }

    public function close()
    {
        $this->assertWireContext();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public function destroy()
    {
        $this->assertWireContext();
        if (session_status() === PHP_SESSION_ACTIVE && ! session_destroy()) {
            throw new RuntimeException('PM-3 wire session could not be destroyed.');
        }
    }

    private function assertWireContext(): void
    {
        if (! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing'
            || PHP_SAPI !== 'cli-server'
            || getenv('ERP_PM3_WIRE_ENABLE') !== '1') {
            throw new RuntimeException('PM-3 real session is restricted to the test HTTP subprocess.');
        }
    }
}
