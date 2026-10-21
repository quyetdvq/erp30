<?php

declare(strict_types=1);

namespace App\Modules\Workflow\WriteBoundary\Compatibility;

use App\Bootstrap\WorkflowWriteBoundaryFactory;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility status validator; BE010 remains the Lifecycle membership authority. */
final class WorkflowBusinessStatus
{
    /**
     * @param list<string> $nativeCodes
     * @param ConnectionInterface<mixed,mixed>|null $db
     */
    public static function normalize(
        string $type,
        mixed $value,
        array $nativeCodes,
        string $errorCode,
        ?ConnectionInterface $db = null,
    ): string {
        return WorkflowWriteBoundaryFactory::create(db: $db)
            ->normalizeStatus($type, $value, $nativeCodes, $errorCode);
    }
}
