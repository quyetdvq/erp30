<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use RuntimeException;

/** Strict, side-effect-free parsing of Spark's CLI option formats. */
final class NotificationPushAcceptanceOptions
{
    /** @param array<array-key, string|null> $params
     *  @return array{stage:string,user_id:?string,import_job_id:?string} */
    public static function parse(array $params): array
    {
        $values = [];
        foreach ($params as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $argument = preg_replace('/\A--/', '', $value);
                $pair = explode('=', (string) $argument, 2);
                if (count($pair) !== 2) {
                    throw new RuntimeException('M11_PUSH_ACCEPTANCE_INVALID_OPTIONS');
                }
                [$name, $input] = $pair;
            } elseif (is_string($key) && $value === null && str_contains($key, '=')) {
                [$name, $input] = explode('=', $key, 2);
            } elseif (is_string($key) && is_string($value)) {
                $name = $key;
                $input = $value;
            } else {
                throw new RuntimeException('M11_PUSH_ACCEPTANCE_INVALID_OPTIONS');
            }
            $name = ltrim($name, '-');
            if (! in_array($name, ['stage', 'user-id', 'import-job-id'], true)
                || array_key_exists($name, $values) || $input === '') {
                throw new RuntimeException('M11_PUSH_ACCEPTANCE_INVALID_OPTIONS');
            }
            $values[$name] = $input;
        }
        $stage = $values['stage'] ?? 'preflight';
        if (! in_array($stage, ['preflight', 'created', 'queued', 'sent'], true)) {
            throw new RuntimeException('M11_PUSH_ACCEPTANCE_INVALID_STAGE');
        }
        $user = $values['user-id'] ?? null;
        $job = $values['import-job-id'] ?? null;
        if (($user === null) !== ($job === null)
            || ($stage !== 'preflight' && ($user === null || $job === null))) {
            throw new RuntimeException('M11_PUSH_ACCEPTANCE_REQUIRES_USER_AND_JOB');
        }
        foreach ([$user, $job] as $id) {
            if ($id !== null && ($id === '0' || preg_match('/\A[1-9][0-9]{0,18}\z/D', $id) !== 1)) {
                throw new RuntimeException('M11_PUSH_ACCEPTANCE_INVALID_ID');
            }
        }
        return ['stage' => $stage, 'user_id' => $user, 'import_job_id' => $job];
    }
}
