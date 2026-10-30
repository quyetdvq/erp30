<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;

/**
 * SUP-T05 integration port, implemented ONLY by the future Purchase module.
 *
 * A successful return certifies that the Purchase document was loaded from
 * persistent storage and locked in the caller-owned transaction, and the
 * actor passed the Purchase-specific mutation PBAC and mutable/new-document
 * checks for that document (never a historical POSTED document).
 * Return the document's PERSISTED company_organization_id; never a value from
 * the request body, a user-selected Company, or a Supplier usage list.
 *
 * The Purchase module is not implemented in SUP-G1, so no concrete adapter or
 * permissive fallback is provided. This interface grants NO Purchase rights.
 */
interface AuthorizedPurchaseCompanyPort
{
    public function resolveAuthorizedLockedCompanyId(
        string $persistedPurchaseId,
        User $actor,
        BaseConnection $db,
    ): string;
}
