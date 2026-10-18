<?php

declare(strict_types=1);

namespace App\Modules\Workflow\StateResolution\Contracts;

/** Machine-readable errors shared by the canonical engine and legacy façade. */
final class ResourceStateResolutionErrors
{
    public const ERROR_STATE_NOT_MAPPED = 'WF_RESOURCE_STATE_NOT_MAPPED';
    public const ERROR_RESOURCE_LIFECYCLE_INVALID = 'WF_RESOURCE_LIFECYCLE_INVALID';
    public const ERROR_INITIAL_STATE_INVALID = 'WF_INITIAL_STATE_INVALID';
    public const ERROR_POLICY_STATE_INVALID = 'POLICY_STATE_NOT_IN_RESOURCE_LIFECYCLE';
    public const ERROR_STATE_CONTEXT_MISMATCH = 'WF_AUTHORIZATION_STATE_MISMATCH';
    public const ERROR_LIFECYCLE_SELF_REFERENCE = 'WF_LIFECYCLE_SELF_REFERENCE';
    public const ERROR_INITIAL_STATE_REQUIRED = 'WF_INITIAL_STATE_REQUIRED';
    public const ERROR_TRANSITION_REQUIRED = 'WF_TRANSITION_REQUIRED';
    public const ERROR_LIFECYCLE_POLICY_CONFLICT = 'RESOURCE_LIFECYCLE_POLICY_CONFLICT';

    private function __construct()
    {
    }
}
