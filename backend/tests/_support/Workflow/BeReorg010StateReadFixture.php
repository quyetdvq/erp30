<?php

declare(strict_types=1);

namespace Tests\Support\Workflow;

use App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateReadPort;
use App\Services\ResourceNativeStateCatalog;

/** Read-only in-memory *port* fixture; never emulates SQL, authorization or mutations. */
final class BeReorg010StateReadFixture implements ResourceStateReadPort
{
    /** @var array<string, array<string,mixed>> */
    public array $resourcesByType = [];
    /** @var array<int, array<string,mixed>> */
    public array $lifecyclesById = [];
    /** @var array<int, list<array<string,mixed>>> */
    public array $membersByLifecycle = [];
    /** @var array<int, array<string,mixed>> */
    public array $statesById = [];
    /** @var array<int, list<array<string,mixed>>> */
    public array $policiesByResourceId = [];
    /** @var list<string> */
    public array $calls = [];

    public static function standard(): self
    {
        $self = new self();
        $types = [...ResourceNativeStateCatalog::INDEPENDENT_RESOURCES, 'province', 'organization'];
        foreach ($types as $i => $type) {
            $self->resourcesByType[$type] = ['id' => $i + 1, 'type' => $type, 'lifecycle_id' => null];
        }
        foreach (['ACTIVE', 'INACTIVE', 'CLOSED', 'DRAFT', 'APPROVED'] as $i => $code) {
            $id = 101 + $i;
            $self->statesById[$id] = ['id' => $id, 'code' => $code, 'status' => 'ACTIVE'];
        }
        $self->lifecyclesById[300] = ['id' => 300, 'status' => 'ACTIVE'];
        $self->lifecyclesById[301] = ['id' => 301, 'status' => 'ACTIVE'];
        $self->membersByLifecycle[300] = [
            ['state_id' => 104, 'is_initial' => 1],
            ['state_id' => 105, 'is_initial' => 0],
        ];
        $self->membersByLifecycle[301] = [['state_id' => 105, 'is_initial' => 1]];
        return $self;
    }

    /** @return array<string,mixed>|null */
    public function findResourceByType(string $type): ?array
    {
        $this->calls[] = 'resourceType:' . $type;
        return $this->resourcesByType[$type] ?? null;
    }

    /** @return array<string,mixed>|null */
    public function findResource(int $id): ?array
    {
        $this->calls[] = 'resource:' . $id;
        foreach ($this->resourcesByType as $resource) {
            if ((int) $resource['id'] === $id) {
                return $resource;
            }
        }
        return null;
    }

    /** @return array<string,mixed>|null */
    public function findLifecycle(int $id): ?array
    {
        $this->calls[] = 'lifecycle:' . $id;
        return $this->lifecyclesById[$id] ?? null;
    }

    /** @return list<array<string,mixed>> */
    public function findLifecycleStates(int $lifecycleId): array
    {
        $this->calls[] = 'members:' . $lifecycleId;
        return $this->membersByLifecycle[$lifecycleId] ?? [];
    }

    /** @return array<string,mixed>|null */
    public function findActiveWorkflowState(int $id): ?array
    {
        $this->calls[] = 'activeState:' . $id;
        $row = $this->statesById[$id] ?? null;
        return $row !== null && $row['status'] === 'ACTIVE' ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function findActiveWorkflowStateByCode(string $code): ?array
    {
        $this->calls[] = 'activeCode:' . $code;
        foreach ($this->statesById as $row) {
            if ($row['code'] === $code && $row['status'] === 'ACTIVE') {
                return $row;
            }
        }
        return null;
    }

    /** @return array<string,mixed>|null */
    public function findWorkflowStateByCode(string $code): ?array
    {
        $this->calls[] = 'anyCode:' . $code;
        foreach ($this->statesById as $row) {
            if ($row['code'] === $code) {
                return $row;
            }
        }
        return null;
    }

    /** @return list<array<string,mixed>> */
    public function findAllPoliciesForResource(int $resourceId): array
    {
        $this->calls[] = 'policies:' . $resourceId;
        return $this->policiesByResourceId[$resourceId] ?? [];
    }
}
