<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationDecision;

/** Audit seam used by LifecycleService; production uses AuthorizationAuditService. */
interface LifecycleAuditPort
{
    public function recordIfRequired(AuthorizationDecision $decision): ?int;
}
