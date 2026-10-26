<?php

declare(strict_types=1);

namespace Tests\M11\Support;

use RuntimeException;

/** Pure safety plan for opt-in test-only Migrations 019-022. */
final class M11TestSchemaPlan
{
    /** @var array<string, list<string>> */
    public const TABLES_BY_MIGRATION = [
        '2026-10-26-000019' => [
            'notification_settings',
            'notification_user_preferences',
            'notification_outbox',
            'user_notifications',
        ],
        '2026-10-26-000020' => [
            'workflow_notification_assignment_sets',
            'workflow_notification_assignees',
        ],
        '2026-10-26-000021' => [
            'notification_email_deliveries',
        ],
        '2026-10-26-000022' => [
            'notification_push_subscriptions',
            'notification_push_deliveries',
        ],
    ];

    /**
     * @param array<string, true> $recorded App migration versions in the tests group
     * @param array<string, string> $engines physical table name => database engine
     * @return list<string> versions that are safe to migrate, in order
     */
    public static function pending(array $recorded, array $engines, string $prefix): array
    {
        if ($prefix !== 'dinktech_') {
            throw new RuntimeException('M11 test schema plan refuses an unexpected table prefix.');
        }
        $prior = null;
        foreach (array_keys(self::TABLES_BY_MIGRATION) as $version) {
            if (isset($recorded[$version]) && $prior !== null && ! isset($recorded[$prior])) {
                throw new RuntimeException('M11 test schema contains migration ' . $version
                    . ' without preceding migration ' . $prior . '. STOP.');
            }
            $prior = $version;
        }

        $pending = [];
        foreach (self::TABLES_BY_MIGRATION as $version => $tables) {
            $recordedVersion = isset($recorded[$version]);
            $found = [];
            foreach ($tables as $logical) {
                $physical = $prefix . $logical;
                if (isset($engines[$physical])) {
                    $found[] = $physical;
                    if (strtoupper($engines[$physical]) !== 'INNODB') {
                        throw new RuntimeException('M11 test schema has non-InnoDB table: ' . $physical);
                    }
                }
            }
            if ($recordedVersion && count($found) !== count($tables)) {
                throw new RuntimeException('M11 test schema ledger says ' . $version
                    . ' is applied, but required tables are missing. STOP; reconcile manually.');
            }
            if (! $recordedVersion && $found !== []) {
                throw new RuntimeException('M11 test schema already contains ' . implode(', ', $found)
                    . ' but Migration ' . $version . ' is absent from the ledger. STOP; do not retry DDL.');
            }
            if (! $recordedVersion) {
                $pending[] = $version;
            }
        }
        return $pending;
    }
}
