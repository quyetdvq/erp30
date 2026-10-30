<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;
use App\Modules\Workflow\Execution\BusinessWorkflowRules;
use InvalidArgumentException;

/** Pure contract, snapshot and fanout guard for M11.2. */
final class WorkflowNotificationRules
{
    public const MAX_ASSIGNEES = 25;

    /** @param array<string,mixed> $body @return array{expected_revision:string,user_ids:list<string>} */
    public static function replacement(array $body): array
    {
        $keys = array_keys($body);
        sort($keys);
        if ($keys !== ['expected_revision','user_ids']
            || ! NotificationRules::version($body['expected_revision'] ?? null, true)
            || ! is_array($body['user_ids']) || ! array_is_list($body['user_ids'])
            || count($body['user_ids']) > self::MAX_ASSIGNEES) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        $ids = [];
        foreach ($body['user_ids'] as $raw) {
            if (! is_string($raw) || preg_match('/\A[1-9][0-9]*\z/D', $raw) !== 1) {
                throw new DomainException('NOTIFICATION_INVALID_REQUEST', ['field' => 'user_ids']);
            }
            try { $id = NotificationDeliveryRules::shieldUserId($raw); }
            catch (InvalidArgumentException) { throw new DomainException('NOTIFICATION_INVALID_REQUEST', ['field' => 'user_ids']); }
            if ($id === null || isset($ids[$id])) {
                throw new DomainException('NOTIFICATION_INVALID_REQUEST', ['field' => 'user_ids']);
            }
            $ids[$id] = true;
        }
        // PHP casts decimal-string associative keys to int; restore API strings.
        $ordered = array_map('strval', array_keys($ids));
        usort($ordered, static fn (string $a, string $b): int => strlen($a) <=> strlen($b) ?: strcmp($a, $b));
        return ['expected_revision' => $body['expected_revision'], 'user_ids' => $ordered];
    }

    /** @param list<string> $userIds */
    public static function eventPayload(string $resource, string $from, string $to, string $action, array $userIds): string
    {
        if ($resource !== 'supplier') {
            BusinessWorkflowRules::table($resource);
        }
        if ($from === '' || $to === '' || $from === $to || strlen($from) > 50 || strlen($to) > 50
            || preg_match('/\A[A-Z][A-Z0-9_:-]{0,49}\z/D', $from) !== 1
            || preg_match('/\A[A-Z][A-Z0-9_:-]{0,49}\z/D', $to) !== 1
            || count($userIds) < 1 || count($userIds) > self::MAX_ASSIGNEES) {
            throw new InvalidArgumentException('Invalid Workflow event');
        }
        BusinessWorkflowRules::action($action);
        if ($resource === 'supplier' && $action !== 'setstate') {
            throw new InvalidArgumentException('Supplier notification must describe the CAS setstate action');
        }
        foreach ($userIds as $id) {
            if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1
                || NotificationDeliveryRules::shieldUserId($id) !== $id) {
                throw new InvalidArgumentException('Invalid recipient snapshot');
            }
        }
        if (count(array_unique($userIds)) !== count($userIds)) {
            throw new InvalidArgumentException('Duplicate snapshot recipients');
        }
        return json_encode(['schema' => 2, 'resource_type' => $resource, 'from_state' => $from,
            'to_state' => $to, 'action' => $action, 'recipient_user_ids' => $userIds], JSON_THROW_ON_ERROR);
    }

    /** @return array{schema:int,resource_type:string,from_state:string,to_state:string,action:string,recipient_user_ids:list<string>} */
    public static function decode(string $json): array
    {
        try { $parsed = json_decode($json, true, 16, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new InvalidArgumentException('Invalid Workflow payload'); }
        if (! is_array($parsed) || array_keys($parsed) !== [
            'schema','resource_type','from_state','to_state','action','recipient_user_ids',
        ] || $parsed['schema'] !== 2 || ! is_string($parsed['resource_type'])
            || ! is_string($parsed['from_state']) || ! is_string($parsed['to_state'])
            || ! is_string($parsed['action']) || ! is_array($parsed['recipient_user_ids'])
            || ! array_is_list($parsed['recipient_user_ids'])) {
            throw new InvalidArgumentException('Invalid Workflow payload');
        }
        // Canonical roundtrip enforces the same strict bounds on producer and worker.
        try {
            $canonical = self::eventPayload($parsed['resource_type'], $parsed['from_state'],
                $parsed['to_state'], $parsed['action'], $parsed['recipient_user_ids']);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Invalid Workflow payload', 0, $e);
        }
        if (! hash_equals($canonical, $json)) {
            throw new InvalidArgumentException('Noncanonical Workflow payload');
        }
        return $parsed;
    }

    /** @param array<string,mixed> $row */
    public static function activeUser(array $row): bool
    {
        return (string) ($row['active'] ?? '0') === '1' && ($row['deleted_at'] ?? null) === null
            && ! in_array(strtolower(trim((string) ($row['status'] ?? ''))),
                ['inactive','locked','suspended','banned','disabled'], true);
    }
}
