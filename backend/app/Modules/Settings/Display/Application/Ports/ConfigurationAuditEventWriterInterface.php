<?php

declare(strict_types=1);

namespace App\Modules\Settings\Display\Application\Ports;

interface ConfigurationAuditEventWriterInterface
{
    /** @param array<string, mixed> $event */
    public function insert(array $event): int;
}
