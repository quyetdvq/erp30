<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;

/**
 * Canonical target data supplied by a concrete lifecycle adapter.
 *
 * The snapshot deliberately contains no business table/column knowledge. It
 * is the only target representation LifecycleService uses for orchestration.
 */
final readonly class LifecycleTargetSnapshot
{
    /** @param array<string, mixed> $authorizationTarget */
    public function __construct(
        public string $resourceType,
        public int $targetId,
        public int $currentStateId,
        public int $lifecycleId,
        public int $evaluationOrganizationId,
        public array $authorizationTarget = [],
    ) {
        if (trim($resourceType) === '') {
            throw new DomainException('WF_TARGET_SNAPSHOT_INVALID', ['field' => 'resource_type']);
        }
        PositiveId::normalize($targetId, 'target_id', 'WF_TARGET_SNAPSHOT_INVALID');
        PositiveId::normalize($currentStateId, 'state_id', 'WF_TARGET_SNAPSHOT_INVALID');
        PositiveId::normalize($lifecycleId, 'lifecycle_id', 'WF_TARGET_SNAPSHOT_INVALID');
        PositiveId::normalize($evaluationOrganizationId, 'organization_id', 'WF_TARGET_SNAPSHOT_INVALID');
    }
}
