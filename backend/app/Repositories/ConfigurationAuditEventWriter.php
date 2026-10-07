<?php

declare(strict_types=1);

namespace App\Repositories;

interface ConfigurationAuditEventWriter
{
    /** @param array<string, mixed> $event */
    public function insert(array $event): int;
}
