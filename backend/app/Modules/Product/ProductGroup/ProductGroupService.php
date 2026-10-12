<?php

declare(strict_types=1);

namespace App\Modules\Product\ProductGroup;

use App\Authorization\AuthorizationAuditService;
use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\ConcurrencyException;
use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Lock\MariaDbDomainLockService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/** Authorized Product Group hierarchy mutations and effective-state queries. */
final class ProductGroupService
{
    public const ERROR_NOT_FOUND = 'PRODUCT_GROUP_NOT_FOUND';
    public const ERROR_PARENT_NOT_FOUND = 'PRODUCT_GROUP_PARENT_NOT_FOUND';
    public const ERROR_DUPLICATE_CODE = 'PRODUCT_GROUP_DUPLICATE_CODE';
    public const ERROR_SELF_PARENT = 'PRODUCT_GROUP_SELF_PARENT';
    public const ERROR_HIERARCHY_CYCLE = 'PRODUCT_GROUP_HIERARCHY_CYCLE';
    public const ERROR_CORRUPTED_HIERARCHY = 'PRODUCT_GROUP_CORRUPTED_HIERARCHY';
    public const ERROR_INVALID_STATUS = 'PRODUCT_GROUP_INVALID_STATUS';
    public const ERROR_REQUIRED_FIELD = 'PRODUCT_GROUP_REQUIRED_FIELD';
    public const ERROR_INVALID_FIELD = 'PRODUCT_GROUP_INVALID_FIELD';
    public const ERROR_NOT_SELECTABLE = 'PRODUCT_GROUP_NOT_EFFECTIVELY_ACTIVE';
    public const ERROR_AUTHORIZATION_DENIED = 'PRODUCT_GROUP_AUTHORIZATION_DENIED';
    public const ERROR_STATUS_MUTATION_FORBIDDEN = 'PRODUCT_GROUP_STATUS_REQUIRES_SETSTATE';
    public const ERROR_MUTATION_FAILED = 'PRODUCT_GROUP_MUTATION_FAILED';
    public const ERROR_CONNECTION_INVALID = 'PRODUCT_GROUP_CONNECTION_INVALID';

    /** @var list<string> */
    private const STATUSES = ['ACTIVE', 'INACTIVE'];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?ProductGroupRepository $repository = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?AuthorizationAuditService $audit = null,
        ?ConnectionInterface $db = null,
        private readonly ?DomainLockService $domainLock = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        $operation ??= $this->operation();
        $clientHint = $data['evaluation_organization_id'] ?? null;
        $data = \App\Services\BusinessWorkflowWriteBoundary::create('product_group', $data, $this->db);
        $payload = $this->normalizeCreate($data);
        $this->authorize($operation, 'create', null, $payload, $clientHint);

        if ($payload['parent_id'] === null) {
            return $this->repo()->insert($payload, $operation);
        }

        return $this->withTreeLock(function () use ($payload, $operation): int {
            $parent = $this->repo()->findParentById($payload['parent_id']);
            if ($parent === null) {
                throw new DomainException(self::ERROR_PARENT_NOT_FOUND, ['parent_id' => $payload['parent_id']]);
            }
            $this->assertNoCycle(null, $parent['id']);

            return $this->repo()->insert($payload, $operation);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        $targetId = PositiveId::normalize($id, 'product_group_id', self::ERROR_NOT_FOUND);
        $current = $this->existing($targetId);
        $clientHint = $data['evaluation_organization_id'] ?? null;
        $payload = $this->normalizeUpdate($data);
        if (array_key_exists('status', $data)) {
            throw new DomainException(self::ERROR_STATUS_MUTATION_FORBIDDEN, ['field' => 'status']);
        }
        $this->authorize($operation, 'edit', $current, $payload, $clientHint);

        $parentChanged = array_key_exists('parent_id', $payload)
            && $this->parentId($current) !== $payload['parent_id'];
        if (! $parentChanged) {
            return $this->repo()->updateMutable($targetId, $payload, $operation);
        }

        return $this->withTreeLock(function () use ($targetId, $payload, $operation): bool {
            $locked = $this->repo()->findForUpdate($targetId);
            if ($locked === null) {
                throw new DomainException(self::ERROR_NOT_FOUND, ['product_group_id' => $targetId]);
            }
            $parentId = $payload['parent_id'];
            if ($parentId !== null) {
                if ($parentId === $targetId) {
                    throw new DomainException(self::ERROR_SELF_PARENT, ['product_group_id' => $targetId]);
                }
                if ($this->repo()->findParentById($parentId) === null) {
                    throw new DomainException(self::ERROR_PARENT_NOT_FOUND, ['parent_id' => $parentId]);
                }
                $this->assertNoCycle($targetId, $parentId);
            }

            return $this->repo()->updateMutable($targetId, $payload, $operation);
        });
    }

    public function reparent(int $id, mixed $parentId, ?OperationContext $operation = null): bool
    {
        return $this->update($id, ['parent_id' => $parentId], $operation);
    }

    public function setState(int $id, string $status, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        if (\App\Services\BusinessLifecycleTransitions::applyIfBound(
            'product_group', $id, $status, $operation, $this->db,
        )) {
            return true;
        }
        $targetId = PositiveId::normalize($id, 'product_group_id', self::ERROR_NOT_FOUND);
        $current = $this->existing($targetId);
        $normalizedStatus = $this->normalizeStatus($status);
        $decision = $this->authorize($operation, 'setstate', $current, []);

        return $this->transaction(function () use ($targetId, $normalizedStatus, $operation, $decision): bool {
            $locked = $this->repo()->findForUpdate($targetId);
            if ($locked === null) {
                throw new DomainException(self::ERROR_NOT_FOUND, ['product_group_id' => $targetId]);
            }
            $updated = $this->repo()->updateStatus($targetId, $normalizedStatus, $operation);
            $this->audit()->recordIfRequired($decision);

            return $updated;
        }, 'product_group_setstate');
    }

    public function activate(int $id, ?OperationContext $operation = null): bool
    {
        return $this->setState($id, 'ACTIVE', $operation);
    }

    public function inactivate(int $id, ?OperationContext $operation = null): bool
    {
        return $this->setState($id, 'INACTIVE', $operation);
    }

    /** @return array<string, mixed>|null */
    public function findById(mixed $id): ?array
    {
        return $this->repo()->findById($id);
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        return $this->repo()->listAll();
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->repo()->findByCode($code);
    }

    /** @return list<array<string, mixed>> */
    public function listChildren(?int $parentId = null): array
    {
        return $this->repo()->listChildren($parentId);
    }

    /** @return list<array<string, mixed>> */
    public function resolveAncestorChain(int $id): array
    {
        $current = $this->existing($id);
        $chain = [];
        $visited = [];
        while (true) {
            $currentId = PositiveId::normalize($current['id'] ?? null, 'product_group_id', self::ERROR_CORRUPTED_HIERARCHY);
            if (isset($visited[$currentId])) {
                throw new DomainException(self::ERROR_CORRUPTED_HIERARCHY, ['product_group_id' => $id]);
            }
            $visited[$currentId] = true;
            array_unshift($chain, $current);
            $parentId = $current['parent_id'] ?? null;
            if ($parentId === null) {
                return $chain;
            }
            $parent = $this->repo()->findParentById($parentId);
            if ($parent === null) {
                throw new DomainException(self::ERROR_PARENT_NOT_FOUND, ['parent_id' => $parentId]);
            }
            $current = $parent;
        }
    }

    public function isEffectivelyActive(int $id): bool
    {
        foreach ($this->resolveAncestorChain($id) as $node) {
            if (($node['status'] ?? null) !== 'ACTIVE') {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function requireSelectableProductGroup(int $id): array
    {
        $target = $this->existing($id);
        if (! $this->isEffectivelyActive($id)) {
            throw new DomainException(self::ERROR_NOT_SELECTABLE, ['product_group_id' => $id]);
        }

        return $target;
    }

    /** @return array<string, mixed> */
    private function existing(int $id): array
    {
        $row = $this->repo()->findById($id);
        if ($row === null) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['product_group_id' => $id]);
        }

        return $row;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalizeCreate(array $data): array
    {
        $this->assertSupportedFields($data, true);
        $payload = $this->withoutInfrastructureFields($data);
        unset($payload['evaluation_organization_id']);
        $payload['code'] = $this->requiredText($payload['code'] ?? null, 'code', 50);
        $payload['name'] = $this->requiredText($payload['name'] ?? null, 'name', 255);
        $payload['parent_id'] = $this->normalizeParentId($payload['parent_id'] ?? null);
        $payload['status'] = array_key_exists('status', $payload)
            ? $this->normalizeStatus($payload['status'])
            : 'ACTIVE';
        if (array_key_exists('description', $payload) && $payload['description'] !== null && ! is_string($payload['description'])) {
            throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => 'description']);
        }

        return $payload;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalizeUpdate(array $data): array
    {
        $this->assertSupportedFields($data, false);
        $payload = $this->withoutInfrastructureFields($data);
        unset($payload['evaluation_organization_id']);
        if (array_key_exists('code', $payload)) {
            $payload['code'] = $this->requiredText($payload['code'], 'code', 50);
        }
        if (array_key_exists('name', $payload)) {
            $payload['name'] = $this->requiredText($payload['name'], 'name', 255);
        }
        if (array_key_exists('parent_id', $payload)) {
            $payload['parent_id'] = $this->normalizeParentId($payload['parent_id']);
        }
        if (array_key_exists('description', $payload) && $payload['description'] !== null && ! is_string($payload['description'])) {
            throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => 'description']);
        }

        return $payload;
    }

    /** @param array<string, mixed> $data */
    private function assertSupportedFields(array $data, bool $creating): void
    {
        $supported = ['parent_id', 'code', 'name', 'description', 'status', 'evaluation_organization_id', 'id', 'created_by', 'updated_by', 'created_at', 'updated_at'];
        foreach ($data as $field => $_value) {
            if (! in_array($field, $supported, true)) {
                throw new DomainException(ProductGroupRepository::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
        if (! $creating && array_key_exists('id', $data)) {
            unset($data['id']);
        }
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function withoutInfrastructureFields(array $data): array
    {
        unset($data['id'], $data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);

        return $data;
    }

    private function requiredText(mixed $value, string $field, int $maxLength): string
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $maxLength) {
            throw new DomainException(self::ERROR_REQUIRED_FIELD, ['field' => $field]);
        }

        return $field === 'code' ? strtoupper(trim($value)) : trim($value);
    }

    private function normalizeParentId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return PositiveId::normalize($value, 'parent_id', self::ERROR_PARENT_NOT_FOUND);
    }

    private function normalizeStatus(mixed $value): string
    {
        return \App\Services\WorkflowBusinessStatus::normalize(
            'product_group', $value, self::STATUSES, self::ERROR_INVALID_STATUS, $this->db,
        );
    }

    /** @param array<string, mixed> $row */
    private function parentId(array $row): ?int
    {
        return $row['parent_id'] === null ? null : PositiveId::normalize($row['parent_id'], 'parent_id', self::ERROR_CORRUPTED_HIERARCHY);
    }

    private function assertNoCycle(?int $targetId, mixed $prospectiveParentId): void
    {
        $currentId = PositiveId::normalize($prospectiveParentId, 'parent_id', self::ERROR_PARENT_NOT_FOUND);
        $visited = [];
        while (true) {
            if (isset($visited[$currentId])) {
                throw new DomainException(self::ERROR_CORRUPTED_HIERARCHY, ['parent_id' => $currentId]);
            }
            $visited[$currentId] = true;
            if ($targetId !== null && $currentId === $targetId) {
                throw new DomainException(self::ERROR_HIERARCHY_CYCLE, ['product_group_id' => $targetId]);
            }
            $current = $this->repo()->findById($currentId);
            if ($current === null) {
                throw new DomainException(self::ERROR_PARENT_NOT_FOUND, ['parent_id' => $currentId]);
            }
            $parentId = $current['parent_id'] ?? null;
            if ($parentId === null) {
                return;
            }
            $currentId = PositiveId::normalize($parentId, 'parent_id', self::ERROR_CORRUPTED_HIERARCHY);
        }
    }

    /** @param array<string, mixed>|null $target
     *  @param array<string, mixed> $input
     */
    private function authorize(
        OperationContext $operation,
        string $action,
        ?array $target,
        array $input,
        mixed $clientHint = null,
    ): \App\Authorization\AuthorizationDecision {
        $request = new EvaluationOrganizationRequest(
            persistedTarget: $target,
            input: $input,
            clientEvaluationOrganizationId: $clientHint,
        );
        try {
            $decision = $this->auth()->authorize($operation, 'product_group', $action, $request);
        } catch (DomainException $exception) {
            throw $exception;
        }
        if (! $decision->isAllowed()) {
            if ($action === 'setstate') {
                $this->audit()->recordIfRequired($decision);
            }
            throw new DomainException(self::ERROR_AUTHORIZATION_DENIED, ['action' => $action]);
        }

        return $decision;
    }

    private function repo(): ProductGroupRepository
    {
        return $this->repository ?? new ProductGroupRepository($this->db);
    }

    private function auth(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService(db: $this->db);
    }

    private function audit(): AuthorizationAuditService
    {
        return $this->audit ?? new AuthorizationAuditService(db: $this->db);
    }

    private function operation(): OperationContext
    {
        return ($this->mutationContext ?? MutationContext::system($this->clock ?? service('clock')))->begin();
    }

    /** @param callable(): mixed $callback */
    private function withTreeLock(callable $callback): mixed
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'product_group_tree']);
        }
        $db = $this->db;
        $lock = $this->domainLock ?? new MariaDbDomainLockService($db);

        try {
            return $lock->synchronized('product-group-tree', function () use ($callback, $db): mixed {
                $db->transBegin();
                try {
                    $result = $callback();
                    if (! $db->transStatus()) {
                        throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => 'product_group_tree']);
                    }
                    $db->transCommit();

                    return $result;
                } catch (\Throwable $exception) {
                    $db->transRollback();
                    $db->resetTransStatus();
                    throw $exception;
                }
            });
        } catch (ConcurrencyException $exception) {
            throw $exception;
        } catch (DomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => 'product_group_tree'], $exception);
        }
    }

    /** @param callable(): mixed $callback */
    private function transaction(callable $callback, string $operation): mixed
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => $operation]);
        }
        $this->db->transBegin();
        try {
            $result = $callback();
            if (! $this->db->transStatus()) {
                throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => $operation]);
            }
            $this->db->transCommit();

            return $result;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => $operation], $exception);
        }
    }
}
