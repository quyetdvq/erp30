<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;
use Throwable;

/** Shield-owned, device-local opt-in; endpoints never leave the server in API responses. */
final class NotificationPushService
{
    private readonly BaseConnection $db;
    private readonly string $userId;

    public function __construct(User $actor, ?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->userId = (string) $actor->id;
        if (! ctype_digit($this->userId) || $this->userId === '0'
            || ! (bool) $actor->active || $actor->deleted_at !== null
            || ! WorkflowNotificationRules::activeUser($actor->toRawArray())) {
            throw new DomainException('UNAUTHENTICATED');
        }
    }

    /** @return array{available:bool,public_key:?string,queue_enabled:bool,send_enabled:bool} */
    public function config(): array
    {
        $queue = NotificationFlags::pushQueueEnabled($this->db);
        $send = NotificationFlags::pushSendEnabled($this->db);
        $key = null;
        if ($queue) {
            try {
                NotificationPushCrypto::key();
                $key = (new NotificationVapidSender());
            } catch (Throwable) {
                // Unconfigured VAPID is unavailable; no secret or stack trace to the client.
            }
        }
        return ['available' => $queue && $key !== null,
            'public_key' => $key === null ? null : NotificationVapidSender::publicKey(),
            'queue_enabled' => $queue, 'send_enabled' => $send];
    }

    /** @return list<array{id:string,created_at:string,detail_ready:bool}> */
    public function listing(): array
    {
        $rows = $this->db->table('notification_push_subscriptions')
            ->select('id,created_at,key_material_cipher')->where('user_id', $this->userId)->where('enabled', 1)
            ->orderBy('id', 'DESC')->get()->getResultArray();
        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'], 'created_at' => (string) $row['created_at'],
            'detail_ready' => is_string($row['key_material_cipher']) && $row['key_material_cipher'] !== '',
        ], $rows);
    }

    /** @param array<string,mixed> $body @return array{id:string,created_at:string,detail_ready:bool} */
    public function subscribe(array $body): array
    {
        $validated = NotificationPushRules::subscription($body);
        $endpoint = $validated['endpoint'];
        if (! $this->config()['available']) {
            throw new DomainException('NOTIFICATION_PUSH_DISABLED');
        }
        $key = NotificationPushCrypto::key();
        $hash = NotificationPushCrypto::fingerprint($endpoint, $key);
        $cipher = NotificationPushCrypto::encrypt($endpoint, $key);
        $keysCipher = NotificationPushCrypto::encryptKeys($validated['p256dh'], $validated['auth'], $key);
        if (! $this->db->transBegin()) {
            throw new RuntimeException('M11_PUSH_TRANSACTION_FAILED');
        }
        try {
            // All new subscriptions by one User are serialized (max devices=5).
            $user = $this->db->query('SELECT id,active,status,deleted_at FROM '
                . $this->table('users') . ' WHERE id=? FOR UPDATE', [$this->userId])->getRowArray();
            if ($user === null || ! WorkflowNotificationRules::activeUser($user)) {
                throw new DomainException('UNAUTHENTICATED');
            }
            if (! $this->consented()) {
                throw new DomainException('NOTIFICATION_PUSH_CONSENT_REQUIRED');
            }
            $row = $this->db->query('SELECT * FROM ' . $this->table('notification_push_subscriptions')
                . ' WHERE endpoint_hash=? FOR UPDATE', [$hash])->getRowArray();
            $now = gmdate('Y-m-d H:i:s');
            if ($row === null) {
                $total = $this->db->table('notification_push_subscriptions')
                    ->where('user_id', $this->userId)->where('enabled', 1)->countAllResults();
                if ($total >= 5) {
                    throw new DomainException('NOTIFICATION_PUSH_DEVICE_LIMIT');
                }
                $ok = $this->db->table('notification_push_subscriptions')->insert([
                    'user_id' => $this->userId, 'endpoint_hash' => $hash,
                    'endpoint_cipher' => $cipher, 'key_material_cipher' => $keysCipher, 'enabled' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $id = (string) $this->db->insertID();
            } else {
                if ((string) $row['user_id'] !== $this->userId || (string) $row['enabled'] !== '1') {
                    $total = $this->db->table('notification_push_subscriptions')
                        ->where('user_id', $this->userId)->where('enabled', 1)->countAllResults();
                    if ($total >= 5) {
                        throw new DomainException('NOTIFICATION_PUSH_DEVICE_LIMIT');
                    }
                }
                // Re-registering the same active device is idempotent: never discard queued work.
                // When changing owners or reactivating a revoked endpoint, fence older receipts.
                if ((string) $row['user_id'] !== $this->userId || (string) $row['enabled'] !== '1') {
                    $cancelled = $this->db->table('notification_push_deliveries')->where('subscription_id', $row['id'])
                        ->whereIn('status', ['PENDING','RETRY','LEASED'])
                        ->update(['status' => 'CANCELLED', 'lease_token' => null, 'leased_until' => null,
                            'last_error_code' => 'M11_PUSH_SUBSCRIPTION_REASSIGNED']);
                    if (! $cancelled) {
                        throw new DomainException('NOTIFICATION_STORAGE_FAILED');
                    }
                }
                $ok = $this->db->table('notification_push_subscriptions')->where('id', $row['id'])->update([
                    'user_id' => $this->userId, 'endpoint_cipher' => $cipher,
                    'key_material_cipher' => $keysCipher,
                    'enabled' => 1, 'revoked_at' => null, 'updated_at' => $now,
                ]);
                $id = (string) $row['id'];
            }
            if (! $ok || ! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new DomainException('NOTIFICATION_STORAGE_FAILED');
            }
            return ['id' => $id, 'created_at' => $row === null ? $now : (string) $row['created_at'],
                'detail_ready' => true];
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $e;
        }
    }

    /** @return array{removed:bool} */
    public function unsubscribe(string $id): array
    {
        $id = NotificationPushRules::id($id);
        if (! $this->db->transBegin()) {
            throw new RuntimeException('M11_PUSH_TRANSACTION_FAILED');
        }
        try {
            $row = $this->db->query('SELECT id FROM ' . $this->table('notification_push_subscriptions')
                . ' WHERE id=? AND user_id=? FOR UPDATE', [$id, $this->userId])->getRowArray();
            if ($row === null) {
                throw new DomainException('NOTIFICATION_NOT_FOUND');
            }
            $ok = $this->db->table('notification_push_subscriptions')->where('id', $id)
                ->where('user_id', $this->userId)->update([
                    'enabled' => 0, 'revoked_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
            $other = $this->db->table('notification_push_deliveries')->where('subscription_id', $id)
                ->whereIn('status', ['PENDING','RETRY','LEASED'])->update([
                    'status' => 'CANCELLED', 'lease_token' => null, 'leased_until' => null,
                    'last_error_code' => 'M11_PUSH_USER_REVOKED',
                ]);
            if (! $ok || ! $other || ! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new DomainException('NOTIFICATION_STORAGE_FAILED');
            }
            return ['removed' => true];
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $e;
        }
    }

    /** A user may subscribe after explicitly opting into ANY supported Push category. */
    private function consented(): bool
    {
        $keys = array_keys(array_filter(NotificationCatalog::definitions(),
            static fn (array $definition, string $key): bool => str_ends_with($key, '.push'), ARRAY_FILTER_USE_BOTH));
        $rows = $this->db->table('notification_settings s')
            ->select('s.*,p.override_value AS preference_value,p.override_epoch AS preference_epoch,'
                . 'p.user_revision AS preference_revision')
            ->join('notification_user_preferences p',
                'p.setting_key=s.setting_key AND p.user_id=' . (int) $this->userId, 'left')
            ->whereIn('s.setting_key', $keys)->get()->getResultArray();
        foreach ($rows as $policy) {
            $pref = $policy['preference_revision'] === null ? null : [
                'override_value' => $policy['preference_value'],
                'override_epoch' => $policy['preference_epoch'], 'user_revision' => $policy['preference_revision'],
            ];
            if (NotificationPushRules::consented($policy, $pref, (string) $policy['setting_key'])) {
                return true;
            }
        }
        return false;
    }

    private function table(string $logical): string
    {
        $name = $this->db->prefixTable($logical);
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $name) !== 1) {
            throw new RuntimeException('M11_PUSH_INVALID_TABLE');
        }
        return '`' . $name . '`';
    }
}
