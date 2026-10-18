<?php

declare(strict_types=1);

namespace App\Modules\Workflow\WriteBoundary\Application\Ports;

/** Read-only, narrow BE010 projection; no mutations or permission checks. */
interface WorkflowStateLookupPort
{
    public function lifecycleId(string $type): ?int;

    /** @return list<string> */
    public function allowedPolicyStateCodes(string $type): array;

    public function initialCode(string $type): ?string;
}
