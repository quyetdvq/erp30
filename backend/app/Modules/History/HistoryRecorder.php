<?php

declare(strict_types=1);

namespace App\Modules\History;

use App\Shared\Http\RequestMetadata;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * No HTTP-facing write endpoint. Business Services call only inside their
 * existing InnoDB transaction and never hand raw HTTP JSON to this recorder.
 * Failure MUST abort/rollback the business mutation (SQL-12).
 */
final class HistoryRecorder
{
    public function __construct(private readonly BaseConnection $db) {}

    public function created(string $resource, string $id, string $actorId, string $state): void
    {
        $this->write($resource, $id, $actorId, 'CREATE', null, null, $state, null, null, 'CREATE');
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after */
    public function updated(string $resource, string $id, string $actorId, array $before, array $after): void
    {
        $previous = self::state($before['status'] ?? null);
        $current = self::state($after['status'] ?? null);
        if ($previous !== $current) {
            throw new InvalidArgumentException('Status changes must be recorded via Workflow.');
        }
        if (!HistoryPolicy::shouldRecordUpdate($resource, $previous, $current)) return;
        $changes = HistoryPolicy::diff($resource, $before, $after);
        if ($changes === []) return;
        $this->write($resource, $id, $actorId, 'UPDATE', 'edit', $previous, $current, $changes);
    }

    /**
     * `setstate` means STATE_CHANGE; do not invent APPROVE/REJECT semantics.
     * A checkpoint is stored on entry to a reviewed operational state only.
     * @param array<string,mixed> $persistedAfter
     */
    public function transitioned(string $resource, string $id, string $actorId,
        string $before, string $after, string $actionCode, array $persistedAfter): void
    {
        if ($before === $after) throw new InvalidArgumentException('No-op transition.');
        $checkpoint = !HistoryPolicy::isOperational($resource, $before)
            && HistoryPolicy::isOperational($resource, $after)
                ? HistoryPolicy::checkpoint($resource, $persistedAfter) : null;
        $this->write($resource, $id, $actorId, 'STATE_CHANGE', $actionCode,
            $before, $after, ['status'=>[$before,$after]], $checkpoint);
    }

    /**
     * Explicit Workflow decision events for future modules that have semantic
     * submit/approve/reject actions. Generic setstate must NOT call this.
     * @param array<string,mixed> $persistedAfter
     */
    public function decision(string $resource, string $id, string $actorId,
        string $eventType, string $actionCode, string $before, string $after,
        array $persistedAfter): void
    {
        if (!in_array($eventType, ['SUBMIT','APPROVE','REJECT','DELETE','RESTORE'], true)
            || $actionCode !== strtolower($eventType)
            || $before === $after) {
            throw new InvalidArgumentException('Unsupported semantic History decision.');
        }
        $checkpoint = $eventType === 'APPROVE'
            ? HistoryPolicy::checkpoint($resource, $persistedAfter) : null;
        $this->write($resource, $id, $actorId, $eventType, $actionCode,
            $before, $after, ['status'=>[$before,$after]], $checkpoint);
    }

    /** Only trusted command services may call; never log bank values or tokens. */
    public function sensitiveChange(string $resource, string $id, string $actorId,
        string $field, string $actionCode, string $state): void
    {
        if ($resource !== 'supplier' || $field !== 'bank_account'
            || !in_array($actionCode, ['bank_set','bank_replace','bank_remove'], true)) {
            throw new InvalidArgumentException('Unreviewed sensitive History event.');
        }
        $this->write($resource, $id, $actorId, 'SENSITIVE_CHANGE', $actionCode,
            $state, $state, [$field => new \stdClass()]);
    }

    /** @param array<string,mixed>|null $changes @param array<string,mixed>|null $checkpoint */
    private function write(string $resource, string $id, string $actorId, string $type,
        ?string $action, ?string $before, ?string $after, ?array $changes,
        ?array $checkpoint = null, ?string $createMarker = null): void
    {
        if ($this->db->transDepth < 1) {
            throw new RuntimeException('HISTORY_REQUIRES_OWNING_TRANSACTION');
        }
        if (!HistoryPolicy::allowsResource($resource)
            || !self::validId($id) || !self::validId($actorId)) {
            throw new InvalidArgumentException('Unreviewed History identity or actor.');
        }
        if ($type !== 'CREATE' && $createMarker !== null) {
            throw new InvalidArgumentException('Create marker limited to CREATE.');
        }
        $requestId = RequestMetadata::id();
        $event = [
            'resource_type' => $resource,
            'resource_id' => $id,
            'event_type' => $type,
            'action_code' => $action,
            'status_before' => $before,
            'status_after' => $after,
            // Customer and Supplier are approved GROUP MASTER resources.
            'owner_domain' => 'MASTER',
            'owner_organization_id' => null,
            'changes_json' => $changes === null ? null : self::json($changes),
            'checkpoint_json' => $checkpoint === null ? null : self::json($checkpoint),
            'actor_user_id' => $actorId,
            'actor_type' => 'USER',
            'request_id' => $requestId,
            'create_marker' => $createMarker,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];
        try {
            $ok = $this->db->table('business_history_events')->insert($event);
            if (!$ok || !$this->db->transStatus()) {
                throw new RuntimeException('HISTORY_PERSISTENCE_FAILED');
            }
        } catch (Throwable $cause) {
            // Caller rollback must run. Never put full DB diagnostic in the API.
            throw new RuntimeException('HISTORY_PERSISTENCE_FAILED', 0, $cause);
        }
    }

    private static function validId(string $id): bool
    {
        return preg_match('/\A[1-9][0-9]*\z/D', $id) === 1 && strlen($id) <= 20;
    }

    private static function state(mixed $raw): string
    {
        if (!is_string($raw) || preg_match('/\A[A-Z][A-Z0-9_:-]{0,49}\z/D', $raw) !== 1) {
            throw new InvalidArgumentException('Invalid persisted Lifecycle state.');
        }
        return $raw;
    }

    /** @param array<string,mixed> $payload */
    private static function json(array $payload): string
    {
        $serialized = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($serialized) > 32768) throw new RuntimeException('HISTORY_EVENT_TOO_LARGE');
        return $serialized;
    }
}
