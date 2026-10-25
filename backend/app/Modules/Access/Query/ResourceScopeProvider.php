<?php

declare(strict_types=1);

namespace App\Modules\Access\Query;

use App\Modules\Access\UnifiedAccessService;

/** Each business module owns its trusted scope resolver, not a generic policy JOIN. */
interface ResourceScopeProvider
{
    public function resource(): string;
    public function domain(): string;
    public function compile(UnifiedAccessService $access): AuthorizedSqlScope;
}
