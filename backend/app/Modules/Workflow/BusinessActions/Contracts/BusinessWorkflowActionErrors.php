<?php

declare(strict_types=1);

namespace App\Modules\Workflow\BusinessActions\Contracts;

/** Existing machine-readable error contract; no new codes introduced. */
final class BusinessWorkflowActionErrors
{
    public const ERROR_TARGET_ADAPTER_UNAVAILABLE = 'WF_TARGET_ADAPTER_UNAVAILABLE';
    public const ERROR_TARGET_NOT_FOUND = 'WF_TARGET_NOT_FOUND';
    public const ERROR_AUTHORIZATION_DENIED = 'AUTH_POLICY_DENIED';
    public const ERROR_INVALID_TRANSITION = 'WF_INVALID_TRANSITION';
    public const ERROR_TRANSITION_REQUIRED = 'WF_TRANSITION_REQUIRED';

    private function __construct()
    {
    }
}
