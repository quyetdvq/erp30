<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use Config\Email as EmailConfig;
use CodeIgniter\Email\Email;
use RuntimeException;
use Throwable;

/** CodeIgniter SMTP adapter. No network I/O from PHPUnit or domain transactions. */
final class NotificationSmtpSender implements NotificationEmailSender
{
    private readonly EmailConfig $config;
    private readonly string $from;

    public function __construct()
    {
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') {
            throw new RuntimeException('M11_EMAIL_REAL_TRANSPORT_FORBIDDEN_IN_TESTS');
        }
        $config = config(EmailConfig::class);
        $from = (string) env('DINKERP_M11_EMAIL_FROM', '');
        if (! self::configurationReady($config, $from)) {
            throw new RuntimeException('M11_EMAIL_SMTP_CONFIGURATION_INVALID');
        }
        $this->config = $config;
        $this->from = $from;
    }

    /** Safe, read-only configuration check shared by the real sender and the M11 ops audit. */
    public static function configurationReady(mixed $config, mixed $from): bool
    {
        return $config instanceof EmailConfig && NotificationEmailRules::address($from) !== null
            && strtolower($config->protocol) === 'smtp'
            && trim($config->SMTPHost) !== '' && trim($config->SMTPUser) !== ''
            && trim($config->SMTPPass) !== ''
            && $config->SMTPPort >= 1 && $config->SMTPPort <= 65535
            // CI4 port 465 uses implicit TLS; STARTTLS on 465 often fails.
            // Other ports must explicitly upgrade with STARTTLS.
            && ($config->SMTPPort === 465
                ? in_array(strtolower($config->SMTPCrypto), ['', 'ssl'], true)
                : strtolower($config->SMTPCrypto) === 'tls')
            && $config->SMTPTimeout >= 1 && $config->SMTPTimeout <= 30;
    }

    public function send(string $recipientAddress, string $subject, string $body): void
    {
        if (NotificationEmailRules::address($recipientAddress) === null) {
            throw new RuntimeException('M11_EMAIL_ADDRESS_INVALID');
        }
        try {
            $mail = new Email($this->config);
            $mail->setMailType('text');
            $mail->setFrom($this->from, 'DinkERP');
            $mail->setTo($recipientAddress);
            $mail->setSubject($subject);
            $mail->setMessage($body);
            if (! $mail->send(false)) {
                throw new RuntimeException('M11_EMAIL_SMTP_SEND_FAILED');
            }
        } catch (Throwable) {
            // Do not expose server transcripts, SMTP credentials or recipient data,
            // including when a transport throws rather than returning false.
            throw new RuntimeException('M11_EMAIL_SMTP_SEND_FAILED');
        }
    }
}
