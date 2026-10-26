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

/** Persist operational flags in the existing Settings table, with SYSTEM PBAC, CAS and audit. */
final class NotificationRuntimeConfigService
{
    private const SETTING_CLASS = 'NotificationRuntime';
    private const SETTING_CONTEXT = 'system';
    private const REVISION_KEY = '__revision';
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;

    public function __construct(private readonly User $actor, ?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->access = new UnifiedAccessService($actor, $this->db);
    }

    /** @return array{revision:string,items:list<array{key:string,section:string,enabled:bool,source:string}>} */
    public function listing(): array
    {
        $this->authorize(false);
        return $this->snapshot();
    }

    /** @param array<string,mixed> $payload
     *  @return array{revision:string,items:list<array{key:string,section:string,enabled:bool,source:string}>} */
    public function update(array $payload): array
    {
        $input = NotificationRuntimeConfigRules::changes($payload);
        $this->authorize(true);
        if (! $this->db->transBegin()) {
            throw new RuntimeException('M11_RUNTIME_CONFIG_TRANSACTION_FAILED');
        }
        try {
            // Serializes initial insert + concurrent updates, including absent revision row.
            $roots = $this->db->table('organizations')->select('id')
                ->where('type', 'GROUP')->where('parent_id', null)->limit(2)->get()->getResultArray();
            if (count($roots) !== 1) {
                throw new DomainException('NOTIFICATION_CONFIGURATION_INVALID');
            }
            $sql = $this->db->table('organizations')->select('id')
                ->where('id', $roots[0]['id'])->getCompiledSelect();
            if ($this->db->query($sql . ' FOR UPDATE')->getRowArray() === null) {
                throw new DomainException('NOTIFICATION_CONFIGURATION_INVALID');
            }
            $revision = $this->revision();
            if ($revision !== $input['expected_revision']) {
                throw new DomainException('NOTIFICATION_REVISION_CONFLICT');
            }
            $changed = false;
            foreach ($input['changes'] as $item) {
                $key = $item['key'];
                $row = $this->stored($this->db, $key);
                $old = $row === null
                    ? NotificationRuntimeConfigRules::legacyEnabled($key)
                    : (string) $row['value'] === '1';
                // Persist even if equal to legacy environment: the operator is migrating
                // the control from deployment configuration into the database.
                if ($row !== null && $old === $item['enabled']) {
                    continue;
                }
                $newValue = $item['enabled'] ? '1' : '0';
                $coordinates = self::coordinates($key);
                if ($row === null) {
                    $ok = $this->db->table('settings')->insert($coordinates + [
                        'value' => $newValue, 'type' => 'boolean',
                        'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                } else {
                    $ok = $this->db->table('settings')->where($coordinates)->update([
                        'value' => $newValue, 'type' => 'boolean', 'updated_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                }
                if (! $ok || ! $this->db->table('configuration_audit_events')->insert([
                    'configuration_type' => 'NOTIFICATION_RUNTIME',
                    'context_type' => 'SYSTEM', 'organization_id' => null,
                    'setting_key' => $key, 'operation' => 'SET',
                    'old_value_json' => json_encode(['enabled' => $old, 'source' => $row === null ? 'legacy_env_or_default' : 'database'], JSON_THROW_ON_ERROR),
                    'new_value_json' => json_encode(['enabled' => $item['enabled']], JSON_THROW_ON_ERROR),
                    'actor_user_id' => (string) $this->actor->id,
                    'request_id' => RequestMetadata::id(), 'created_at' => gmdate('Y-m-d H:i:s'),
                ])) {
                    throw new DomainException('NOTIFICATION_STORAGE_FAILED');
                }
                $changed = true;
            }
            if ($changed) {
                if ($revision === (string) PHP_INT_MAX) {
                    throw new DomainException('NOTIFICATION_CONFIGURATION_INVALID');
                }
                $next = (string) ((int) $revision + 1);
                $coordinates = self::coordinates(self::REVISION_KEY);
                $prior = $this->stored($this->db, self::REVISION_KEY);
                $ok = $prior === null
                    ? $this->db->table('settings')->insert($coordinates + [
                        'value' => $next, 'type' => 'integer',
                        'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
                    ])
                    : $this->db->table('settings')->where($coordinates)->update([
                        'value' => $next, 'type' => 'integer', 'updated_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                if (! $ok) {
                    throw new DomainException('NOTIFICATION_STORAGE_FAILED');
                }
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new DomainException('NOTIFICATION_STORAGE_FAILED');
            }
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $e;
        }
        return $this->snapshot();
    }

    /** @return array{revision:string,items:list<array{key:string,section:string,enabled:bool,source:string}>} */
    private function snapshot(): array
    {
        $items = [];
        foreach (NotificationRuntimeConfigRules::definitions() as $key => $section) {
            $row = self::stored($this->db, $key);
            $items[] = ['key' => $key, 'section' => $section,
                'enabled' => $row === null ? NotificationRuntimeConfigRules::legacyEnabled($key) : (string) $row['value'] === '1',
                'source' => $row === null ? 'legacy_env_or_default' : 'database'];
        }
        return ['revision' => $this->revision(), 'items' => $items];
    }

    private function revision(): string
    {
        $row = self::stored($this->db, self::REVISION_KEY);
        if ($row === null) {
            return '0';
        }
        if ($row['type'] !== 'integer' || ! NotificationRuntimeConfigRules::revision((string) $row['value'])) {
            throw new DomainException('NOTIFICATION_CONFIGURATION_INVALID');
        }
        return (string) $row['value'];
    }

    private function authorize(bool $write): void
    {
        $row = $this->db->table('notification_settings')
            ->where('setting_key', 'import.completed.in_app')->get()->getRowArray();
        if ($row === null) {
            throw new DomainException('NOTIFICATION_CONFIGURATION_INVALID');
        }
        $decision = $write
            ? $this->access->decideMutation('notification_setting', 'edit', $row)
            : $this->access->decidePersisted('notification_setting', 'view', $row);
        if (! $decision['allowed']) {
            throw new DomainException('NOTIFICATION_ACCESS_DENIED');
        }
    }

    /** @return array{class:string,key:string,context:string} */
    private static function coordinates(string $key): array
    {
        return ['class' => self::SETTING_CLASS, 'key' => $key, 'context' => self::SETTING_CONTEXT];
    }

    /** @return array<string,mixed>|null */
    public static function stored(BaseConnection $db, string $key): ?array
    {
        $query = $db->table('settings')->select('value,type')->where(self::coordinates($key))->get();
        if ($query === false) {
            throw new RuntimeException('M11_RUNTIME_CONFIG_READ_FAILED');
        }
        $row = $query->getRowArray();
        if ($row !== null && $key !== self::REVISION_KEY
            && ! NotificationRuntimeConfigRules::storedValue($row['value'] ?? null, $row['type'] ?? null)) {
            throw new DomainException('NOTIFICATION_CONFIGURATION_INVALID');
        }
        return $row;
    }
}
