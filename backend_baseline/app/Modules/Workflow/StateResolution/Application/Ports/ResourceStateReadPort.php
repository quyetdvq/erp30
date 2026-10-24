<?php

declare(strict_types=1);

namespace App\Modules\Workflow\StateResolution\Application\Ports;

/** Only persisted reads needed by ResourceStateResolutionService. */
interface ResourceStateReadPort
{
    /** @return array<string,mixed>|null */
    public function findResourceByType(string $type): ?array;

    /** @return array<string,mixed>|null */
    public function findResource(int $id): ?array;

    /** @return array<string,mixed>|null */
    public function findLifecycle(int $id): ?array;

    /** @return list<array<string,mixed>> */
    public function findLifecycleStates(int $lifecycleId): array;

    /** @return array<string,mixed>|null */
    public function findActiveWorkflowState(int $id): ?array;

    /** @return array<string,mixed>|null */
    public function findActiveWorkflowStateByCode(string $code): ?array;

    /** @return array<string,mixed>|null */
    public function findWorkflowStateByCode(string $code): ?array;

    /** @return list<array<string,mixed>> Include inactive policies, without filtering. */
    public function findAllPoliciesForResource(int $resourceId): array;
}
