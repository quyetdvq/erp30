<?php

declare(strict_types=1);

namespace App\Modules\Contact;

use App\Modules\Access\UnifiedAccessService;

/**
 * A Supplier Contact has TWO independent MASTER VIEW decisions. The Supplier
 * parent must additionally belong to this actor's trusted persisted GROUP.
 * Company Usage is not a grant and cannot replace the GROUP check.
 */
final class SupplierContactParentView
{
    /** @param array<string,mixed>|null $parent */
    public static function allowed(UnifiedAccessService $access, ?array $parent): bool
    {
        return $parent !== null
            && (string) ($parent['group_organization_id'] ?? '') === $access->trustedGroupRootId()
            && $access->canView('supplier', $parent);
    }
}
