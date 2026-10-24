<?php

declare(strict_types=1);

namespace App\Modules\Workflow\BusinessActions\Application\Ports;

/** Fixed business table lookup only; never exposes mutation or a SQL identifier. */
interface BusinessWorkflowRecordPort
{
    public function assertBusinessResource(string $resource): void;

    /** @return array<string,mixed> */
    public function record(string $resource, int $id): array;
}
