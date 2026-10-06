<?php

declare(strict_types=1);

namespace App\Authorization;

interface ResourceOrganizationScopeResolver
{
    public function resolve(string $action, EvaluationOrganizationRequest $request): OrganizationScopeResolution;
}
