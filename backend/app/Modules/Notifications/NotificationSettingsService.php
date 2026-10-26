<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use App\Shared\Http\RequestMetadata;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;
use Throwable;

/** M11 System policy and Shield-owned preference management. */
final class NotificationSettingsService
{
    private readonly BaseConnection $db;
    private readonly string $userId;
    private ?UnifiedAccessService $access = null;

    public function __construct(private readonly User $actor, ?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->userId = (string) $actor->id;
        if (! ctype_digit($this->userId) || $this->userId === '0'
            || ! (bool) $actor->active || $actor->deleted_at !== null) {
            throw new DomainException('UNAUTHENTICATED');
        }
    }

    /** @return list<array<string,mixed>> */
    public function policies(): array
    {
        $all = $this->policyRows();
        $result = [];
        foreach (NotificationCatalog::definitions() as $key => $definition) {
            $row = $all[$key];
            $this->authorize($row, false);
            $result[] = NotificationPolicyResolver::policyView($key, $row, $definition);
        }
        return $result;
    }

    /** @param array<string,mixed> $body @return list<array<string,mixed>> */
    public function updatePolicies(array $body): array
    {
        $changes = NotificationRules::policyChanges($body);
        $updated = $this->transaction(function () use ($changes): array {
            $locked = [];
            // Lock all keys and validate every CAS/PBAC decision before the first mutation.
            foreach ($changes as $change) {
                $key = $change['key'];
                $row = $this->lockedRow('notification_settings', ['setting_key' => $key]);
                if ($row === null) {
                    throw new DomainException('NOTIFICATION_CONFIGURATION_INVALID');
                }
                NotificationPolicyResolver::assertPolicy($row, NotificationCatalog::definitions()[$key]);
                $this->authorize($row, true);
                if ((string) $row['revision'] !== $change['expected_revision']) {
                    throw new DomainException('NOTIFICATION_REVISION_CONFLICT');
                }
                $locked[$key] = $row;
            }
            $output = [];
            foreach ($changes as $change) {
                $key = $change['key'];
                $before = $locked[$key];
                $prior = $this->auditPolicy($before);
                $nextDefault = $change['default_value'] ? '1' : '0';
                $nextOverride = $change['allow_user_override'] ? '1' : '0';
                if ((string) $before['default_value'] !== $nextDefault
                    || (string) $before['allow_user_override'] !== $nextOverride) {
                    $lockOverrides = (string) $before['allow_user_override'] === '1' && $nextOverride === '0';
                    $builder = $this->db->table('notification_settings')
                        ->where('setting_key', $key)
                        ->set('default_value', $nextDefault)
                        ->set('allow_user_override', $nextOverride)
                        ->set('updated_by', $this->userId)
                        ->set('updated_at', 'UTC_TIMESTAMP(6)', false)
                        ->set('revision', 'revision + 1', false);
                    if ($lockOverrides) {
                        $builder->set('override_epoch', 'override_epoch + 1', false);
                    }
                    if (! $builder->update()) {
                        throw new DomainException('NOTIFICATION_STORAGE_FAILED');
                    }
                    $after = $this->lockedRow('notification_settings', ['setting_key' => $key]);
                    if ($after === null) {
                        throw new DomainException('NOTIFICATION_STORAGE_FAILED');
                    }
                    if (! $this->db->table('configuration_audit_events')->insert([
                        'configuration_type' => 'NOTIFICATION_SETTING',
                        'context_type' => 'SYSTEM', 'organization_id' => null,
                        'setting_key' => $key, 'operation' => 'SET',
                        'old_value_json' => json_encode($prior, JSON_THROW_ON_ERROR),
                        'new_value_json' => json_encode($this->auditPolicy($after), JSON_THROW_ON_ERROR),
                        'actor_user_id' => $this->userId,
                        'request_id' => RequestMetadata::id(),
                        'created_at' => gmdate('Y-m-d H:i:s'),
                    ])) {
                        throw new DomainException('NOTIFICATION_STORAGE_FAILED');
                    }
                } else {
                    $after = $before; // No-op: no version change and no audit record.
                }
                $output[] = NotificationPolicyResolver::policyView($key, $after, NotificationCatalog::definitions()[$key]);
            }
            return $output;
        });
        return $updated;
    }

    /** @return list<array<string,mixed>> */
    public function preferences(): array
    {
        // One SQL statement gives a coherent policy+preference view per GET under READ-COMMITTED.
        $s = $this->identifier('notification_settings');
        $p = $this->identifier('notification_user_preferences');
        $query = $this->db->query(
            "SELECT s.*, p.override_value AS preference_value, p.override_epoch AS preference_epoch, "
            . "p.user_revision AS preference_revision "
            . "FROM {$s} s LEFT JOIN {$p} p ON p.setting_key = s.setting_key AND p.user_id = ? "
            . 'ORDER BY s.setting_key',
            [$this->userId],
        );
        if ($query === false) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $rows = [];
        foreach ($query->getResultArray() as $row) {
            $rows[(string) $row['setting_key']] = $row;
        }
        $this->assertComplete($rows);
        $output = [];
        foreach (NotificationCatalog::definitions() as $key => $definition) {
            $row = $rows[$key];
            $preference = $row['preference_revision'] === null ? null : [
                'override_value' => $row['preference_value'],
                'override_epoch' => $row['preference_epoch'],
                'user_revision' => $row['preference_revision'],
            ];
            $output[] = NotificationPolicyResolver::preferenceView($key, $row, $definition, $preference);
        }
        return $output;
    }

    /** @param array<string,mixed> $body @return list<array<string,mixed>> */
    public function updatePreferences(array $body): array
    {
        $changes = NotificationRules::preferenceChanges($body);
        $this->transaction(function () use ($changes): void {
            // Lock policy first, then preferences, in lexicographic key order.
            foreach ($changes as $change) {
                $key = $change['key'];
                $policy = $this->lockedRow('notification_settings', ['setting_key' => $key]);
                if ($policy === null) {
                    throw new DomainException('NOTIFICATION_CONFIGURATION_INVALID');
                }
                NotificationPolicyResolver::assertPolicy($policy, NotificationCatalog::definitions()[$key]);
                if ((string) $policy['override_epoch'] !== $change['expected_override_epoch']) {
                    throw new DomainException('NOTIFICATION_REVISION_CONFLICT');
                }
                if ((string) $policy['allow_user_override'] !== '1') {
                    throw new DomainException('NOTIFICATION_OVERRIDE_LOCKED');
                }
                $prior = $this->lockedRow('notification_user_preferences', [
                    'user_id' => $this->userId, 'setting_key' => $key,
                ]);
                $revision = $prior === null ? '0' : (string) $prior['user_revision'];
                if ($revision !== $change['expected_user_revision']) {
                    throw new DomainException('NOTIFICATION_REVISION_CONFLICT');
                }
                $value = $change['value'] === null ? null : ($change['value'] ? 1 : 0);
                if ($prior === null) {
                    try {
                        $ok = $this->db->table('notification_user_preferences')
                            ->set('updated_at', 'UTC_TIMESTAMP(6)', false)->insert([
                            'user_id' => $this->userId, 'setting_key' => $key,
                            'override_value' => $value, 'override_epoch' => (string) $policy['override_epoch'],
                            'user_revision' => '1',
                        ]);
                    } catch (Throwable $exception) {
                        if ((int) $exception->getCode() === 1062) {
                            throw new DomainException('NOTIFICATION_REVISION_CONFLICT', [], $exception);
                        }
                        throw $exception;
                    }
                    if (! $ok) {
                        $code = (int) ($this->db->error()['code'] ?? 0);
                        throw new DomainException($code === 1062 ? 'NOTIFICATION_REVISION_CONFLICT' : 'NOTIFICATION_STORAGE_FAILED');
                    }
                } else {
                    $ok = $this->db->table('notification_user_preferences')
                        ->where('id', (string) $prior['id'])
                        ->set('override_value', $value)
                        ->set('override_epoch', (string) $policy['override_epoch'])
                        ->set('user_revision', 'user_revision + 1', false)
                        ->set('updated_at', 'UTC_TIMESTAMP(6)', false)
                        ->update();
                    if (! $ok) {
                        throw new DomainException('NOTIFICATION_STORAGE_FAILED');
                    }
                }
            }
        });
        $keys = array_column($changes, 'key');
        return array_values(array_filter($this->preferences(), static fn (array $row): bool => in_array($row['key'], $keys, true)));
    }

    /** @return array<string,array<string,mixed>> */
    private function policyRows(): array
    {
        $query = $this->db->table('notification_settings')->get();
        if ($query === false) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        $rows = [];
        foreach ($query->getResultArray() as $row) {
            $rows[(string) $row['setting_key']] = $row;
        }
        $this->assertComplete($rows);
        return $rows;
    }

    /** @param array<string,array<string,mixed>> $rows */
    private function assertComplete(array $rows): void
    {
        foreach (NotificationCatalog::definitions() as $key => $definition) {
            if (! isset($rows[$key])) {
                throw new DomainException('NOTIFICATION_CONFIGURATION_INVALID');
            }
            NotificationPolicyResolver::assertPolicy($rows[$key], $definition);
        }
    }

    /** @param array<string,mixed> $row */
    private function authorize(array $row, bool $mutation): void
    {
        $this->access ??= new UnifiedAccessService($this->actor, $this->db);
        $decision = $mutation
            ? $this->access->decideMutation('notification_setting', 'edit', $row)
            : $this->access->decidePersisted('notification_setting', 'view', $row);
        if (! $decision['allowed']) {
            throw new DomainException('NOTIFICATION_ACCESS_DENIED');
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function auditPolicy(array $row): array
    {
        return [
            'default_value' => (string) $row['default_value'] === '1',
            'allow_user_override' => (string) $row['allow_user_override'] === '1',
            'revision' => (string) $row['revision'],
            'override_epoch' => (string) $row['override_epoch'],
        ];
    }

    /** @param array<string,string> $where @return array<string,mixed>|null */
    private function lockedRow(string $table, array $where): ?array
    {
        $compiled = $this->db->table($table)->where($where)->getCompiledSelect();
        $query = $this->db->query($compiled . ' FOR UPDATE');
        if ($query === false) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        return $query->getRowArray();
    }

    private function identifier(string $logical): string
    {
        $name = $this->db->prefixTable($logical);
        if (! preg_match('/\A[a-zA-Z0-9_]+\z/D', $name)) {
            throw new RuntimeException('Invalid notification DB identifier');
        }
        return '`' . $name . '`';
    }

    /** @template T @param callable():T $operation @return T */
    private function transaction(callable $operation): mixed
    {
        if (! $this->db->transBegin()) {
            throw new DomainException('NOTIFICATION_STORAGE_FAILED');
        }
        try {
            $result = $operation();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new DomainException('NOTIFICATION_STORAGE_FAILED');
            }
            return $result;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $exception;
        }
    }
}
