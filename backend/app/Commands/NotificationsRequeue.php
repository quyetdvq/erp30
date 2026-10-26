<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;
use Throwable;

/** Explicit, audited DEV-clone-only repair after diagnosing a DEAD outbox row. */
final class NotificationsRequeue extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'notifications:requeue';
    protected $description = 'Requeue one DEAD M11 event on an explicitly confirmed DEV clone with audit.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        $event = CLI::getOption('event-id');
        $actor = CLI::getOption('actor-user-id');
        $confirm = CLI::getOption('confirm');
        if (ENVIRONMENT === 'production'
            || (string) env('DINKERP_M11_OPERATIONS_WRITE_MODE', 'off') !== 'enabled'
            || $confirm !== 'DEV_CLONE_ONLY'
            || ! is_scalar($event) || ! is_scalar($actor)
            || preg_match('/\A[1-9][0-9]{0,19}\z/D', (string) $event) !== 1
            || preg_match('/\A[1-9][0-9]{0,9}\z/D', (string) $actor) !== 1) {
            throw new RuntimeException('Requeue disabled. Require DEV clone, DINKERP_M11_OPERATIONS_WRITE_MODE=enabled, --confirm=DEV_CLONE_ONLY, --event-id=N, --actor-user-id=N.');
        }
        $db = db_connect();
        $physical = static function (string $name) use ($db): string {
            $table = $db->prefixTable($name);
            if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $table) !== 1) {
                throw new RuntimeException('Invalid DB prefix.');
            }
            return '`' . $table . '`';
        };
        $outbox = $physical('notification_outbox');
        $users = $physical('users');
        $audit = $physical('configuration_audit_events');
        $db->transBegin();
        try {
            $operator = $db->query("SELECT id FROM {$users} WHERE id=? AND active=1 AND deleted_at IS NULL FOR UPDATE", [(string) $actor])->getRowArray();
            if ($operator === null) {
                throw new RuntimeException('Operator Shield User is not active.');
            }
            $old = $db->query("SELECT id,event_key,status,attempts,last_error_code FROM {$outbox} WHERE id=? AND status='DEAD' FOR UPDATE", [(string) $event])->getRowArray();
            if ($old === null) {
                throw new RuntimeException('Only existing DEAD events can be explicitly requeued.');
            }
            $updated = $db->query("UPDATE {$outbox} SET status='PENDING',attempts=0,lease_token=NULL,"
                . "leased_until=NULL,available_at=UTC_TIMESTAMP(6),last_error_code=NULL,processed_at=NULL"
                . " WHERE id=? AND status='DEAD'", [(string) $event]);
            if ($updated === false || $db->affectedRows() !== 1) {
                throw new RuntimeException('Requeue update failed.');
            }
            $ok = $db->table('configuration_audit_events')->insert([
                'configuration_type' => 'NOTIFICATION_OPERATOR',
                'context_type' => 'SYSTEM', 'organization_id' => null,
                'setting_key' => 'outbox:' . (string) $event, 'operation' => 'REQUEUE',
                'old_value_json' => json_encode(['status' => $old['status'], 'attempts' => (int) $old['attempts'],
                    'last_error_code' => $old['last_error_code']], JSON_THROW_ON_ERROR),
                'new_value_json' => json_encode(['status' => 'PENDING', 'attempts' => 0], JSON_THROW_ON_ERROR),
                'actor_user_id' => (string) $actor,
                'request_id' => 'm11-cli-' . bin2hex(random_bytes(12)),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if (! $ok || $db->transStatus() === false || ! $db->transCommit()) {
                throw new RuntimeException('Audited requeue transaction failed.');
            }
            CLI::write('Requeued one DEAD event. Operator and action recorded in configuration_audit_events.', 'green');
        } catch (Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
