<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Exceptions\DomainException;

/**
 * Bounded, read-only search for selecting notification assignees on an
 * authorized Workflow target. Never accepts SQL identifiers or sort clauses.
 */
final class WorkflowAssigneeCandidateQuery
{
    public const MAX_PAGE_SIZE = 50;
    public const MAX_SELECTED = 25;

    /**
     * @param array<string, mixed> $query
     * @return array{q:string,page:int,page_size:int,ids:list<string>}
     */
    public static function parse(array $query): array
    {
        foreach ($query as $key => $_value) {
            if (!in_array($key, ['q', 'page', 'page_size', 'ids'], true)) {
                throw new DomainException('NOTIFICATION_INVALID_REQUEST');
            }
        }
        $rawQuery = $query['q'] ?? '';
        $rawPage = $query['page'] ?? '1';
        $rawSize = $query['page_size'] ?? '25';
        $rawIds = $query['ids'] ?? null;
        if (!is_string($rawQuery) || strlen($rawQuery) > 100
            || !self::positiveNumber($rawPage, 10000)
            || !self::positiveNumber($rawSize, self::MAX_PAGE_SIZE)
            || ($rawIds !== null && !is_string($rawIds))) {
            throw new DomainException('NOTIFICATION_INVALID_REQUEST');
        }
        $q = trim($rawQuery);
        $ids = [];
        if ($rawIds !== null) {
            if ($q !== '' || $rawIds === '' || strlen($rawIds) > 500
                || preg_match('/\A[1-9][0-9]*(?:,[1-9][0-9]*){0,24}\z/D', $rawIds) !== 1) {
                throw new DomainException('NOTIFICATION_INVALID_REQUEST');
            }
            $ids = explode(',', $rawIds);
            if (count(array_unique($ids)) !== count($ids)) {
                throw new DomainException('NOTIFICATION_INVALID_REQUEST');
            }
            foreach ($ids as $id) {
                try {
                    if (NotificationDeliveryRules::shieldUserId($id) !== $id) {
                        throw new DomainException('NOTIFICATION_INVALID_REQUEST');
                    }
                } catch (\InvalidArgumentException) {
                    throw new DomainException('NOTIFICATION_INVALID_REQUEST');
                }
            }
        }
        return [
            'q' => $q,
            'page' => (int) $rawPage,
            'page_size' => (int) $rawSize,
            'ids' => $ids,
        ];
    }

    private static function positiveNumber(mixed $raw, int $max): bool
    {
        if (!(is_string($raw) || is_int($raw))) {
            return false;
        }
        $value = (string) $raw;
        return preg_match('/\A[1-9][0-9]{0,5}\z/D', $value) === 1 && (int) $value <= $max;
    }
}
