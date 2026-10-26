<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Notifications\NotificationSmtpProbeOptions;
use App\Modules\Notifications\NotificationSmtpSender;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Email as EmailConfig;
use RuntimeException;

/**
 * One-purpose SMTP provisioning check. No database access or queue claims.
 * The default never makes a network connection or sends a message.
 */
final class NotificationsSmtpCheck extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'notifications:smtp-check';
    protected $description = 'Read-only SMTP readiness; optional explicitly confirmed one-address live SMTP test.';

    /** @param array<array-key,string|null> $params */
    public function run(array $params): void
    {
        $options = NotificationSmtpProbeOptions::parse($params);
        $from = (string) env('DINKERP_M11_EMAIL_FROM', '');
        $ready = NotificationSmtpSender::configurationReady(config(EmailConfig::class), $from);

        CLI::write('M11 SMTP preflight (no secrets, recipient details, or SMTP settings printed).');
        CLI::write('smtp_configuration_ready=' . ($ready ? 'yes' : 'no'));

        if (! $ready) {
            CLI::write('BLOCKED: complete the private backend/.env SMTP settings before any Email sends.', 'red');
            throw new RuntimeException('M11_SMTP_CONFIGURATION_NOT_READY');
        }
        if ($options['test_recipient'] === null) {
            CLI::write('PASS: configuration shape only. No network I/O, no sends, no database changes.', 'green');
            CLI::write('SMTP credentials have not been authenticated against a live provider.');
            return;
        }
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') {
            throw new RuntimeException('M11_SMTP_LIVE_TEST_FORBIDDEN_IN_TESTING');
        }

        // Explicitly scoped *single* operator test; never dispatch queued ERP receipts.
        (new NotificationSmtpSender())->send(
            $options['test_recipient'],
            'DinkERP - SMTP configuration test',
            "DinkERP SMTP setup test.\nThis is a one-time operator-initiated test, not a business notification.\n"
                . "No M11 queue rows were claimed or changed.\n",
        );
        CLI::write('SMTP provider accepted one test send. Verify delivery in the target mailbox.', 'green');
        CLI::write('No M11 queue, consent, or runtime switch was changed.');
    }
}
