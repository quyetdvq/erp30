<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Execution;

use App\Exceptions\DomainException;
use App\Modules\Contact\ContactScope;
use CodeIgniter\Database\BaseConnection;

/**
 * Supply the authoritative ORGANIZATION scope for Workflow Contact PBAC.
 *
 * contacts has no organization_id column. Organization Contacts inherit their
 * persisted owner organization; Employee Contacts inherit the unique current
 * ACTIVE primary assignment. Reuse the canonical P1 ContactScope instead of
 * inventing a GROUP/current-Company scope or accepting a client hint.
 *
 * The returned organization_id is an authorization-only projection: it is
 * NEVER persisted back to contacts. Customer Contacts remain MASTER-scoped.
 */
final class BusinessWorkflowContactScope
{
    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function fromPersisted(string $resource, array $row, BaseConnection $db): array
    {
        if (!in_array($resource, ['organization_contact', 'employee_contact'], true)) {
            return $row;
        }
        if (!BusinessWorkflowRules::matchesTargetOwner($resource, $row)) {
            throw new DomainException('WF_TARGET_NOT_FOUND');
        }
        try {
            $scope = (new ContactScope($db))->resolve($row);
        } catch (DomainException $cause) {
            // A missing owner, an invalid persisted reference or a missing or
            // ambiguous primary assignment must NOT fall back to GROUP. Match
            // ContactService's fail-closed visibility behavior without leaking
            // its parent/assignment state through a Workflow GET endpoint.
            if (in_array($cause->getErrorCode(), [
                'CONTACT_OWNER_NOT_FOUND', 'CONTACT_PRIMARY_SCOPE_UNAVAILABLE',
                'CONTACT_TYPE_UNSUPPORTED', 'CONTACT_TYPE_REQUIRED',
                'CONTACT_OWNER_REFERENCE_REQUIRED',
            ], true)) {
                throw new DomainException('WF_TARGET_NOT_FOUND', [], $cause);
            }
            throw $cause;
        }
        if ($scope['resource'] !== $resource || !isset($scope['target']['organization_id'])) {
            throw new DomainException('WF_TARGET_NOT_FOUND');
        }
        return $scope['target'];
    }
}
