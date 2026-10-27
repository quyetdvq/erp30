<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use InvalidArgumentException;

/** Email is optional and requires *affirmative current User consent*, never a System default. */
final class NotificationEmailRules
{
    /** Only Import terminal events with explicitly supported SMTP templates. */
    public static function policyKeyForEvent(string $eventType): ?string
    {
        return match ($eventType) {
            'import.completed' => 'import.completed.email',
            'import.completed_with_errors' => 'import.completed_with_errors.email',
            'import.failed' => 'import.failed.email',
            default => null,
        };
    }

    /** Backward-compatible entry for the original Import completed Email consent. */
    public static function consented(array $policy, ?array $preference, bool $requireProducerActive = true): bool
    {
        return self::consentedForPolicyKey('import.completed.email', $policy, $preference, $requireProducerActive);
    }

    /** @param array<string,mixed> $policy @param array<string,mixed>|null $preference */
    public static function consentedForPolicyKey(string $key, array $policy, ?array $preference, bool $requireProducerActive = true): bool
    {
        $definitions = NotificationCatalog::definitions();
        $definition = $definitions[$key] ?? null;
        // Explicitly reject a wrong row, unsupported event, or channel substitution.
        if ($definition === null || ! in_array($key, [
            'import.completed.email', 'import.completed_with_errors.email', 'import.failed.email',
        ], true) || ($policy['setting_key'] ?? null) !== $key
            || ($definition['requires_explicit_consent'] ?? false) !== true) {
            return false;
        }
        NotificationPolicyResolver::assertPolicy($policy, $definition);
        return $definition['capability'] === 'AVAILABLE'
            && (! $requireProducerActive || $definition['producer_state'] === 'ACTIVE')
            && (string) $policy['allow_user_override'] === '1'
            && $preference !== null
            && (string) ($preference['override_value'] ?? '') === '1'
            && (string) ($preference['override_epoch'] ?? '') === (string) $policy['override_epoch']
            && NotificationRules::version((string) ($preference['user_revision'] ?? ''));
    }

    public static function address(mixed $value): ?string
    {
        if (! is_string($value) || $value !== trim($value) || strlen($value) > 254
            || preg_match('/[\r\n\x00-\x1f\x7f]/', $value) === 1) {
            return null;
        }
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : null;
    }

    /** @return array{subject:string,body:string} */
    public static function content(string $eventType, string $json): array
    {
        if (self::policyKeyForEvent($eventType) === null || strlen($json) > 512) {
            throw new InvalidArgumentException('M11_EMAIL_UNSUPPORTED_EVENT');
        }
        try {
            $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('M11_EMAIL_INVALID_PAYLOAD', 0, $e);
        }
        if (! is_array($data) || array_keys($data) !== ['success_rows', 'error_rows']
            || ! is_int($data['success_rows']) || ! is_int($data['error_rows'])
            || $data['success_rows'] < 0 || $data['error_rows'] < 0) {
            throw new InvalidArgumentException('M11_EMAIL_INVALID_PAYLOAD');
        }
        // All text is fixed copy plus strictly validated integer counters; no raw
        // importer data, CSV cells, stack traces, HTML, URL or recipient identity.
        return match ($eventType) {
            'import.completed' => [
                'subject' => 'DinkERP - Nhập dữ liệu hoàn tất',
                'body' => "Công việc nhập dữ liệu đã hoàn tất.\n"
                    . 'Số dòng thành công: ' . $data['success_rows'] . "\n"
                    . 'Số dòng lỗi: ' . $data['error_rows'] . "\n"
                    . "Đăng nhập DinkERP để xem chi tiết.\n",
            ],
            'import.completed_with_errors' => [
                'subject' => 'DinkERP - Nhập dữ liệu có dòng lỗi',
                'body' => "Nhập dữ liệu đã hoàn tất nhưng có dòng lỗi.\n"
                    . 'Số dòng thành công: ' . $data['success_rows'] . "\n"
                    . 'Số dòng lỗi: ' . $data['error_rows'] . "\n"
                    . "Đăng nhập DinkERP để kiểm tra kết quả.\n",
            ],
            'import.failed' => [
                'subject' => 'DinkERP - Nhập dữ liệu thất bại',
                'body' => "Công việc nhập dữ liệu không hoàn tất.\n"
                    . "Hãy đăng nhập DinkERP để kiểm tra trạng thái và nhật ký.\n",
            ],
        };
    }
}
