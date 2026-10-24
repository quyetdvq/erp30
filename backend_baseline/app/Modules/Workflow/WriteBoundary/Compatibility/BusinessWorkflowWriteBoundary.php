<?php

declare(strict_types=1);

namespace App\Modules\Workflow\WriteBoundary\Compatibility;

use App\Bootstrap\WorkflowWriteBoundaryFactory;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility entrypoint for business CRUD/import service state boundaries. */
final class BusinessWorkflowWriteBoundary
{
    /**
     * @param array<string,mixed> $data
     * @param ConnectionInterface<mixed,mixed> $db
     * @return array<string,mixed>
     */
    public static function create(string $resource, array $data, ConnectionInterface $db): array
    {
        return WorkflowWriteBoundaryFactory::create(db: $db)->create($resource, $data);
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $changes
     * @param ConnectionInterface<mixed,mixed> $db
     */
    public static function assertNoDirectStateMutation(
        string $resource,
        array $current,
        array $changes,
        ConnectionInterface $db,
    ): void {
        WorkflowWriteBoundaryFactory::create(db: $db)->assertNoDirectStateMutation($resource, $current, $changes);
    }
}
