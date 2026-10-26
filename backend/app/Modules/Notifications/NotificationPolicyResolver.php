<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;

/** Side-effect-free System -> User -> effective policy evaluator. */
final class NotificationPolicyResolver
{
    /** @param array<string,mixed> $row @param array<string,mixed> $definition */
    public static function assertPolicy(array $row, array $definition): void
    {
        if (! in_array((string) ($row['default_value'] ?? ''), ['0', '1'], true)
            || ! in_array((string) ($row['allow_user_override'] ?? ''), ['0', '1'], true)
            || ! NotificationRules::version((string) ($row['revision'] ?? ''))
            || ! NotificationRules::version((string) ($row['override_epoch'] ?? ''))
            || ($definition['mandatory'] && ((string) $row['default_value'] !== '1' || (string) $row['allow_user_override'] !== '0'))) {
            throw new DomainException('NOTIFICATION_CONFIGURATION_INVALID');
        }
    }

    /** @param array<string,mixed> $policy @param array<string,mixed> $definition
     * @return array<string,mixed> */
    public static function policyView(string $key, array $policy, array $definition): array
    {
        self::assertPolicy($policy, $definition);
        return [
            'key' => $key, 'default_value' => (string) $policy['default_value'] === '1',
            'allow_user_override' => (string) $policy['allow_user_override'] === '1',
            'mandatory' => $definition['mandatory'], 'capability' => $definition['capability'],
            'producer_state' => $definition['producer_state'],
            'revision' => (string) $policy['revision'], 'override_epoch' => (string) $policy['override_epoch'],
        ];
    }

    /** @param array<string,mixed> $policy @param array<string,mixed> $definition
     *  @param array<string,mixed>|null $preference
     *  @return array<string,mixed> */
    public static function preferenceView(string $key, array $policy, array $definition, ?array $preference): array
    {
        $view = self::policyView($key, $policy, $definition);
        $available = $definition['capability'] === 'AVAILABLE';
        $default = $view['default_value'];
        $canOverride = $view['allow_user_override'] && ! $definition['mandatory'];
        $fresh = $preference !== null && (string) $preference['override_epoch'] === $view['override_epoch'];
        $userOverride = $fresh && $preference['override_value'] !== null
            && in_array((string) $preference['override_value'], ['0', '1'], true)
            ? (string) $preference['override_value'] === '1' : null;
        if (! $available) {
            $effective = false;
            $source = 'CHANNEL_UNAVAILABLE';
        } elseif ($definition['mandatory']) {
            $effective = true;
            $source = 'MANDATORY';
        } elseif (! $canOverride) {
            $effective = $default;
            $source = 'SYSTEM_LOCKED';
        } elseif ($userOverride !== null) {
            $effective = $userOverride;
            $source = 'USER';
        } else {
            $effective = $default;
            $source = 'SYSTEM_DEFAULT';
        }
        if ($preference !== null && (! NotificationRules::version((string) ($preference['user_revision'] ?? ''))
            || ! NotificationRules::version((string) ($preference['override_epoch'] ?? ''))
            || ($preference['override_value'] !== null && ! in_array((string) $preference['override_value'], ['0', '1'], true)))) {
            throw new DomainException('NOTIFICATION_CONFIGURATION_INVALID');
        }
        return [
            'key' => $key, 'system_default' => $default, 'allow_user_override' => $canOverride,
            'user_override' => $canOverride && $available ? $userOverride : null,
            'effective_value' => $effective, 'effective_source' => $source,
            'override_epoch' => $view['override_epoch'],
            'user_revision' => $preference === null ? '0' : (string) $preference['user_revision'],
            'locked' => ! $canOverride || ! $available, 'capability' => $definition['capability'],
            'producer_state' => $definition['producer_state'],
        ];
    }
}
