<?php

declare(strict_types=1);

namespace App\Modules\Workflow\WriteBoundary\Contracts;

use App\Modules\Workflow\StateResolution\Contracts\ResourceStateResolutionErrors;

/** Preserve legacy machine codes without creating a new error vocabulary. */
final class WorkflowWriteBoundaryErrors
{
    public const ERROR_UNSUPPORTED_INITIAL = 'WF_INITIAL_STATE_UNSUPPORTED_FOR_RESOURCE';
    public const ERROR_INITIAL_STATE_INVALID = ResourceStateResolutionErrors::ERROR_INITIAL_STATE_INVALID;
    public const ERROR_TRANSITION_REQUIRED = ResourceStateResolutionErrors::ERROR_TRANSITION_REQUIRED;

    private function __construct()
    {
    }
}
