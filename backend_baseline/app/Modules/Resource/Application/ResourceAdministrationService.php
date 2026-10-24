<?php

declare(strict_types=1);

namespace App\Modules\Resource\Application;

use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Resource\Application\Ports\ResourceLifecycleGuardPort;
use App\Modules\Resource\Domain\ResourceDomain;
use App\Modules\Resource\Domain\ResourceNativeStateCatalog;
use App\Modules\Resource\Infrastructure\Persistence\SqlResourceCatalogRepository;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Exceptions\BadRequestException;

/**
 * Resource-specific application operations extracted from API-030.
 *
 * These methods are called AFTER API-030 base-permission/PBAC authorization.
 * This service does not make authorization decisions or expose HTTP endpoints;
 * callers must keep the authorization checks ahead of writes and reads.
 */
final class ResourceAdministrationService
{
    private ?ResourceLifecycleGuardPort $runtimeStateResolver = null;

    /** @param \Closure():ResourceLifecycleGuardPort $guardFactory */
    public function __construct(
        private readonly SqlResourceCatalogRepository $repository,
        private readonly \Closure $guardFactory,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function actions(int $resourceId): array
    {
        return $this->repository->findResourceActions($resourceId);
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function createAction(int $resourceId, array $payload, OperationContext $operation): array
    {
        $code = $this->token($payload['code'] ?? null, 'code');
        $row = ['resource_id' => $resourceId, 'code' => $code, 'description' => $payload['description'] ?? null, ...$operation->createdAudit()];
        $actionId = $this->repository->insertResourceAction($row);

        return $this->repository->findResourceActionById($actionId) ?? [];
    }

    /** @return array<string, mixed>|null */
    public function findAction(int $resourceId, int $actionId): ?array
    {
        return $this->repository->findResourceAction($resourceId, $actionId);
    }

    /** @param array<string, mixed> $payload
     *  @param array<string, mixed> $action
     *  @return array<string, mixed>
     */
    public function updateAction(int $actionId, array $payload, array $action, OperationContext $operation): array
    {
        $changes = [];
        if (array_key_exists('description', $payload)) {
            $changes['description'] = $payload['description'];
        }
        if (array_key_exists('code', $payload) && $this->token($payload['code'], 'code') !== (string) $action['code']) {
            throw new DomainException('RESOURCE_ACTION_CODE_IMMUTABLE');
        }
        if ($changes === []) {
            throw new DomainException('VALIDATION_ERROR');
        }
        $changes = [...$changes, ...$operation->updatedAudit()];
        $this->repository->updateResourceAction($actionId, $changes);

        return $this->repository->findResourceActionById($actionId) ?? [];
    }

    public function deleteAction(int $actionId): void
    {
        $this->repository->deleteResourceAction($actionId);
    }

    public function delete(int $resourceId): void
    {
        $this->repository->deleteResource($resourceId);
    }

    /** @param array<string, mixed> $current */
    public function assertContactRecoveryAllowed(array $current, string $status): void
    {
        if (($current['type'] ?? null) === 'contact' && strtoupper(trim($status)) === 'ACTIVE') {
            throw new DomainException('CONTACT_LEGACY_RESOURCE_RETIRED');
        }
    }

    public function setState(int $id, string $status, string $expected, OperationContext $operation): bool
    {
        return $this->repository->setResourceStatus($id, $status, $expected, $operation);
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    public function decorate(array $row, bool $includeActions = true): array
    {
        $states = $this->runtimeStateResolver ??= ($this->guardFactory)();
        $row['policy_state_codes'] = $states->allowedPolicyStateCodes((string) $row['type']);
        if ($includeActions) {
            $row['actions'] = $this->repository->findResourceActions((int) $row['id']);
        }
        return $row;
    }

    /** @param array<string, mixed> $query */
    public function consumeIncludeActions(array &$query): bool
    {
        if (! array_key_exists('include', $query)) {
            return false;
        }
        if (! is_string($query['include']) || $query['include'] !== 'actions') {
            throw new BadRequestException('Malformed list query: include.');
        }
        unset($query['include']);

        return true;
    }

    /** @param list<array<string, mixed>> $rows
     *  @return list<array<string, mixed>>
     */
    public function attachActions(array $rows): array
    {
        $resourceIds = [];
        foreach ($rows as $row) {
            $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) {
                $resourceIds[] = (int) $id;
            }
        }

        $actionsByResource = [];
        foreach ($this->repository->findResourceActionsForResources(array_values(array_unique($resourceIds))) as $action) {
            $resourceId = (int) ($action['resource_id'] ?? 0);
            $actionsByResource[$resourceId][] = [
                'id' => (int) ($action['id'] ?? 0),
                'code' => (string) ($action['code'] ?? ''),
                'description' => array_key_exists('description', $action) && $action['description'] !== null
                    ? (string) $action['description']
                    : null,
            ];
        }

        foreach ($rows as &$row) {
            $row['actions'] = $actionsByResource[(int) ($row['id'] ?? 0)] ?? [];
        }
        unset($row);

        return $rows;
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload, OperationContext $operation): int
    {
        $type = $this->token($payload['type'] ?? null, 'type');
        if (ResourceNativeStateCatalog::isIndependent($type) && ($payload['lifecycle_id'] ?? null) !== null) {
            throw new DomainException(ResourceLifecycleGuardPort::ERROR_LIFECYCLE_SELF_REFERENCE, ['resource' => $type]);
        }
        $domain = strtoupper(trim((string) ($payload['domain'] ?? '')));
        if ($domain === '' || $domain === 'COMPANY' || ! in_array($domain, array_map(static fn (ResourceDomain $d): string => $d->value, ResourceDomain::cases()), true)) {
            throw new DomainException('RESOURCE_DOMAIN_INVALID', ['field' => 'domain']);
        }
        if (array_key_exists('status', $payload) && $payload['status'] !== 'ACTIVE') {
            throw new DomainException('NATIVE_STATE_REQUIRES_SETSTATE', ['resource' => 'resource']);
        }
        if (isset($payload['lifecycle_id'])) {
            $lifecycleId = \App\Infrastructure\Id\PositiveId::normalize(
                $payload['lifecycle_id'], 'lifecycle_id', ResourceLifecycleGuardPort::ERROR_RESOURCE_LIFECYCLE_INVALID,
            );
            (($this->guardFactory)())->assertLifecycleStateCompatibility($type, $lifecycleId);
        }
        $row = [
            'status' => 'ACTIVE',
            'type' => $type,
            'name' => $this->token($payload['name'] ?? null, 'name'),
            'domain' => $domain,
            'lifecycle_id' => $payload['lifecycle_id'] ?? null,
            ...$operation->createdAudit(),
        ];
        return $this->repository->insertResource($row);
    }

    /** @param array<string, mixed> $payload */
    public function update(int $id, array $payload, OperationContext $operation): bool
    {
        if (array_key_exists('domain', $payload)) {
            throw new DomainException('RESOURCE_DOMAIN_IMMUTABLE', ['field' => 'domain']);
        }
        $current = $this->repository->findResource($id);
        if ($current === null) {
            throw new PageNotFoundException('Resource not found.');
        }
        if (array_key_exists('lifecycle_id', $payload)
            && ResourceNativeStateCatalog::isIndependent((string) $current['type'])
            && $payload['lifecycle_id'] !== null) {
            throw new DomainException(ResourceLifecycleGuardPort::ERROR_LIFECYCLE_SELF_REFERENCE);
        }
        if (array_key_exists('lifecycle_id', $payload)) {
            $next = $payload['lifecycle_id'];
            $nextId = $next === null ? null : \App\Infrastructure\Id\PositiveId::normalize(
                $next, 'lifecycle_id', ResourceLifecycleGuardPort::ERROR_RESOURCE_LIFECYCLE_INVALID,
            );
            (($this->guardFactory)())->assertLifecycleReassignmentSafe(
                (string) $current['type'], $nextId,
            );
        }
        $changes = [];
        foreach (['type', 'name', 'lifecycle_id'] as $field) {
            if (array_key_exists($field, $payload)) {
                if ($field === 'type') {
                    throw new DomainException('RESOURCE_TYPE_IMMUTABLE', ['field' => 'type']);
                }
                $changes[$field] = $field === 'name' ? $this->token($payload[$field], $field) : $payload[$field];
            }
        }
        if ($changes === []) {
            throw new DomainException('VALIDATION_ERROR');
        }

        return $this->repository->updateResource($id, [...$changes, ...$operation->updatedAudit()]);
    }

    private function token(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException('VALIDATION_ERROR', ['field' => $field]);
        }
        return strtolower(trim($value));
    }}
