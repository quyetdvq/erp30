<?php

declare(strict_types=1);

namespace App\Database;

/**
 * An explicit exception only for PHPUnit's destructive migration refresh.
 * A normal migration rollback must keep populated account metadata intact.
 */
final class UserDisplayMetadataResetPolicy
{
    public static function allows(string $environment, string $database, ?string $optIn): bool
    {
        return $environment === 'testing'
            && $database === 'erp_test'
            && $optIn === '1';
    }
}
