<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\SystemAdminPermissionReconciler;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** Guarded maintenance command for the reviewed SYSTEM_ADMIN correction. */
final class SystemAdminPermissionReconcile extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'systemadmin:reconcile';
    protected $description = 'Review or apply the exact SYSTEM_ADMIN 93-action correction.';
    protected $usage = 'systemadmin:reconcile --backup=<path> --sha256=<checksum> [--approved] [--apply]';
    protected $options = [
        'backup:' => 'Encrypted, auditable pre-state backup path.',
        'sha256:' => 'Expected SHA-256 checksum for the backup artifact.',
        'approved' => 'Explicit Security/Operations/Architecture approval is recorded.',
        'apply' => 'Apply the correction; without this flag the command is read-only.',
    ];

    /** @param array<string,mixed> $params */
    public function run(array $params): void
    {
        // CLI commands do not always inherit Config\Database::$defaultGroup;
        // force the isolated test connection unless an explicitly named
        // staging target was supplied.
        $target = strtolower((string) env('DINKERP_SYSTEM_ADMIN_TARGET', ''));
        $db = $target === 'staging' ? db_connect() : db_connect('tests');
        $this->assertGuard($db);
        $backup = (string) (CLI::getOption('backup') ?? '');
        $checksum = strtolower((string) (CLI::getOption('sha256') ?? ''));
        if (! is_file($backup) || ! hash_equals($checksum, strtolower((string) hash_file('sha256', $backup)))) {
            throw new RuntimeException('A readable backup and matching SHA-256 checksum are required.');
        }

        $reconciler = new SystemAdminPermissionReconciler();
        $state = $reconciler->inspect($db);
        CLI::write(sprintf('SYSTEM_ADMIN pre-state: %d permissions; extras=%s', count($state['current']), implode(',', $state['extras'])), 'yellow');
        if (! CLI::getOption('apply')) {
            CLI::write('Dry-run only; no permission rows changed.', 'green');
            return;
        }

        $result = $reconciler->reconcile($db);
        CLI::write(sprintf('Removed %s; exact remaining permissions=%d.', implode(',', $result['removed']), $result['remaining']), 'green');
    }

    /** @param BaseConnection<mixed,mixed> $db */
    private function assertGuard(BaseConnection $db): void
    {
        $database = (string) $db->getDatabase();
        $target = strtolower((string) env('DINKERP_SYSTEM_ADMIN_TARGET', ''));
        if ($database !== 'erp_test' && $target !== 'staging') {
            throw new RuntimeException('SYSTEM_ADMIN reconciliation is limited to erp_test or an explicitly named staging target.');
        }
        if (! CLI::getOption('approved') || ! filter_var(env('DINKERP_SYSTEM_ADMIN_APPROVED', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('Explicit Security/Operations/Architecture approval is required.');
        }
    }
}
