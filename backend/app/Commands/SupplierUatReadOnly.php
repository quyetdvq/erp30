<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Supplier\SupplierSetupDoctor;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;
use Throwable;

/** T09A one-command, GET-only localhost Supplier HTTP smoke. No business mutation. */
final class SupplierUatReadOnly extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'supplier:uat-readonly';
    protected $description = 'DEV only: verify local authenticated Supplier HTTP read contracts (no writes).';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        if ($params !== []) throw new RuntimeException('Usage: php spark supplier:uat-readonly');
        CLI::write('SUP-T09A SUPPLIER HTTP PRECHECK - LOCAL / READ ONLY');
        if (!defined('ENVIRONMENT') || ENVIRONMENT !== 'development') {
            throw new RuntimeException('SUP_T09A_BLOCKED_DEVELOPMENT_ENV_REQUIRED');
        }
        $doctor = (new SupplierSetupDoctor(db_connect()))->inspect();
        if ($doctor['status'] !== 'PASS') {
            throw new RuntimeException('SUP_T09A_BLOCKED_SCHEMA_CATALOG_DOCTOR_FAIL');
        }
        CLI::write('DB: ' . $doctor['connection']['schema'] . ' @ ' . $doctor['connection']['server']
            . ':' . $doctor['connection']['port'] . ' (identity/backup NOT attested)');

        require_once dirname(__DIR__, 2) . '/tools/m87/M87Http.php';
        require_once dirname(__DIR__, 2) . '/tools/sup/SupplierHttpAudit.php';

        $env = \SupplierHttpAudit::processEnvironment();
        $issues = \SupplierHttpAudit::preflight($env);
        if ($issues !== []) {
            CLI::write('BLOCKED PREFLIGHT: ' . implode(', ', $issues), 'red');
            throw new RuntimeException('SUP_T09A_HTTP_PREFLIGHT_FAILED');
        }
        $report = ['milestone' => 'SUP-T09A', 'time_utc' => gmdate('c'),
            'status' => 'BLOCKED_HTTP', 'checks' => [], 'gaps' => []];
        try {
            $audit = new \SupplierHttpAudit();
            $result = $audit->execute(
                static fn (string $role): object => new \M87Http(
                    (string)$env['SUP_UAT_HOST'], (string)$env['SUP_UAT_SCHEME']),
                $env,
            );
            $report = [...$report, ...$result];
        } catch (Throwable $exception) {
            // No raw HTTP bodies, credentials, tokens, exception messages or SQL in reports.
            $report['status'] = 'BLOCKED_HTTP';
            $report['error_type'] = get_class($exception);
        }
        foreach ($report['checks'] as $check) {
            CLI::write(($check['pass'] ? '[PASS] ' : '[FAIL] ') . $check['label']
                . ': ' . $check['detail'], $check['pass'] ? 'green' : 'red');
        }
        foreach ($report['gaps'] as $gap) CLI::write('[PENDING] ' . $gap);
        $path = $this->writeSanitizedReport($report);
        if ($path !== '') CLI::write('Report: ' . $path);
        CLI::write('RESULT: ' . $report['status'] . ' (read-only HTTP precheck ONLY)',
            $report['status'] === 'PASS_READONLY_HTTP_PRECHECK' ? 'green' : 'red');
        CLI::write('No Supplier mutations. CAS, SQL/PBAC parity, real two-actor scenarios, Bank and release NOT accepted.');
        if ($report['status'] !== 'PASS_READONLY_HTTP_PRECHECK') {
            throw new RuntimeException('SUP_T09A_HTTP_PRECHECK_FAILED_OR_BLOCKED');
        }
    }

    /** @param array<string,mixed> $report */
    private function writeSanitizedReport(array $report): string
    {
        $folder = WRITEPATH . 'sup_t09/reports';
        if (!is_dir($folder) && !mkdir($folder, 0700, true) && !is_dir($folder)) return '';
        if (is_link($folder) || !is_writable($folder)) return '';
        $path = $folder . '/sup_t09_readonly_' . gmdate('Ymd_His') . '_'
            . bin2hex(random_bytes(4)) . '.json';
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $json, LOCK_EX) === false) return '';
        @chmod($path, 0600);
        return $path;
    }
}
