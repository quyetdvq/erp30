<?php

declare(strict_types=1);

namespace App\Modules\Workflow\WriteBoundary\Infrastructure\Adapters;

use App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateResolutionPort;
use App\Modules\Workflow\WriteBoundary\Application\Ports\WorkflowStateLookupPort;
use App\Services\ResourceWorkflowStateResolver;

/** Narrow BE010/legacy read-only bridge; preserves the supplied resolver instance. */
final class ExistingStateResolutionLookupAdapter implements WorkflowStateLookupPort
{
    public function __construct(private readonly ResourceStateResolutionPort|ResourceWorkflowStateResolver $states)
    {
    }

    public function lifecycleId(string $type): ?int
    {
        return $this->states->lifecycleId($type);
    }

    /** @return list<string> */
    public function allowedPolicyStateCodes(string $type): array
    {
        return $this->states->allowedPolicyStateCodes($type);
    }

    public function initialCode(string $type): ?string
    {
        return $this->states->initialCode($type);
    }
}
