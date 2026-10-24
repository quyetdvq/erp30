<?php

declare(strict_types=1);

namespace App\Modules\Workflow\StateResolution\Application\Ports;

/** Resource state resolution contract, independently of repository implementation. */
interface ResourceStateResolutionPort
{
    /** @return array<string,mixed> */
    public function resource(string $type): array;

    public function lifecycleId(string $type): ?int;

    /** @param array<string,mixed> $row */
    public function persistedStateId(string $type, array $row): ?int;

    public function stateIdForCode(string $type, mixed $code): ?int;

    public function codeForStateId(string $type, int $stateId): string;

    /** @return list<string> */
    public function allowedPolicyStateCodes(string $type): array;

    public function assertLifecycleReassignmentSafe(string $type, ?int $nextLifecycleId): void;

    public function initialCode(string $type): ?string;

    public function assertLifecycleStateCompatibility(string $type, int $lifecycleId): void;

    public function assertPolicyState(int $resourceId, int $stateId): void;

    /**
     * @param array<string,mixed>|null $persistedTarget
     * @param array<string,mixed>|null $trustedCatalogRow
     * @param array<string,mixed> $input
     */
    public function stateForAuthorization(
        string $type,
        ?array $persistedTarget,
        ?int $explicitStateId,
        ?array $trustedCatalogRow = null,
        string $action = '',
        array $input = [],
    ): ?int;
}
