<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Repositories\AuthorizationLogRepository;
use App\Repositories\ResourceCatalogRepository;
use App\Services\LifecycleAuditPort;
use CodeIgniter\Database\ConnectionInterface;

/** Maps trusted internal decisions into the immutable P1 audit schema. */
final class AuthorizationAuditService implements LifecycleAuditPort
{
    public const ERROR_DECISION_INVALID = 'AUTHORIZATION_AUDIT_DECISION_INVALID';
    public const ERROR_RESOURCE_INVALID = 'AUTHORIZATION_AUDIT_RESOURCE_INVALID';

    private ResourceCatalogRepository $catalog;
    private AuthorizationLogRepository $logs;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?AuthorizationLogRepository $logs = null,
        ?ResourceCatalogRepository $catalog = null,
        ?ConnectionInterface $db = null,
    ) {
        $connection = $db ?? db_connect();
        $this->logs = $logs ?? new AuthorizationLogRepository($connection);
        $this->catalog = $catalog ?? new ResourceCatalogRepository($connection);
    }

    public function recordIfRequired(AuthorizationDecision $decision): ?int
    {
        if (! AuthorizationAuditScope::requiresPersistence($decision->resourceAction)) {
            return null;
        }

        $resource = $this->catalog->findResourceByType($decision->resourceType);
        if ($resource === null) {
            throw new DomainException(self::ERROR_RESOURCE_INVALID, ['resource' => $decision->resourceType]);
        }
        $resourceId = PositiveId::normalize($resource['id'] ?? null, 'resource_id', self::ERROR_RESOURCE_INVALID);
        if (! in_array($decision->decision, ['ALLOW', 'DENY'], true)) {
            throw new DomainException(self::ERROR_DECISION_INVALID, ['decision' => $decision->decision]);
        }

        $matchedPolicyId = count($decision->decisivePolicyIds) === 1
            ? $decision->decisivePolicyIds[0]
            : null;

        return $this->logs->insert([
            'user_id' => $decision->userId,
            'organization_id' => $decision->evaluationOrganizationId,
            'resource_id' => $resourceId,
            'action' => $decision->resourceAction,
            'state_id' => $decision->stateId,
            'decision' => $decision->decision,
            'matched_policy_id' => $matchedPolicyId,
            'reason' => $decision->reasonCode,
            'request_id' => $decision->requestId,
            'created_at' => $decision->evaluationTime->utc()->format('Y-m-d H:i:s'),
        ]);
    }
}
