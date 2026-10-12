<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Guard domain-service entry points, not only HTTP controllers: background
 * imports and internal callers must never bypass a bound Resource Lifecycle.
 */
final class BusinessWorkflowWriteBoundary
{
    /** @param array<string,mixed> $data @return array<string,mixed> */
    public static function create(string $resource, array $data, ConnectionInterface $db): array
    {
        if (! self::isLifecycleBound($resource, $db)) {
            return $data;
        }
        return (new InitialCreateStateNormalizer(new ResourceWorkflowStateResolver($db)))
            ->normalize($resource, $data);
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $changes */
    public static function assertNoDirectStateMutation(
        string $resource,
        array $current,
        array $changes,
        ConnectionInterface $db,
    ): void {
        if (! array_key_exists('status', $changes)) {
            return;
        }
        if (is_string($changes['status'])
            && strtoupper(trim($changes['status'])) === strtoupper((string) ($current['status'] ?? ''))) {
            return;
        }
        if (self::isLifecycleBound($resource, $db)) {
            throw new DomainException(ResourceWorkflowStateResolver::ERROR_TRANSITION_REQUIRED, [
                'resource' => $resource,
            ]);
        }
    }

    private static function isLifecycleBound(string $resource, ConnectionInterface $db): bool
    {
        if (ResourceNativeStateCatalog::isIndependent($resource)) {
            return false;
        }
        $row = $db->table('resources')->select('lifecycle_id')
            ->where('type', $resource)->get()->getRowArray();
        return $row !== null && ($row['lifecycle_id'] ?? null) !== null;
    }
}
