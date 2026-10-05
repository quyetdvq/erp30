<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Lock\MariaDbDomainLockService;
use App\Repositories\OrganizationRepository;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use DateTimeImmutable;

final class OrganizationService
{
    public const ERROR_SECOND_GROUP_ROOT = 'ORG_SECOND_GROUP_ROOT';
    public const ERROR_INVALID_PARENT = 'ORG_INVALID_PARENT';
    public const ERROR_HIERARCHY_CYCLE = 'ORG_HIERARCHY_CYCLE';
    public const ERROR_COMPANY_RESOLUTION_FAILED = 'ORG_COMPANY_RESOLUTION_FAILED';
    public const ERROR_ACTIVE_DATE_INVALID = 'ORG_ACTIVE_DATE_INVALID';
    public const ERROR_WARD_PROVINCE_MISMATCH = 'ORG_WARD_PROVINCE_MISMATCH';
    public const ERROR_REFERENCE_INVALID = 'ORG_REFERENCE_INVALID';
    public const ERROR_MUTATION_FAILED = 'ORG_MUTATION_FAILED';

    /** @var list<string> */
    private const TYPES = ['GROUP', 'COMPANY', 'BRANCH', 'PLANT', 'WAREHOUSE'];

    /** @var array<string, list<string>> */
    private const ALLOWED_CHILDREN = [
        'GROUP' => ['COMPANY'],
        'COMPANY' => ['BRANCH', 'PLANT', 'WAREHOUSE'],
        'BRANCH' => ['PLANT', 'WAREHOUSE'],
        'PLANT' => ['WAREHOUSE'],
        'WAREHOUSE' => [],
    ];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?OrganizationRepository $repository = null,
        ?ConnectionInterface $db = null,
        private readonly ?DomainLockService $domainLock = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $payload = $this->prepareCreate($data);

        return $this->withTreeLock(function () use ($payload): int {
            $this->assertHierarchyCandidate(null, $payload['type'], $payload['parent_id']);
            $this->assertDates($payload);
            $this->assertLocation($payload);

            return $this->repo()->insert($payload);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $current = $this->repo()->find($id);
        if ($current === null) {
            throw new DomainException(self::ERROR_REFERENCE_INVALID, ['organization_id' => $id]);
        }

        $payload = $data;
        if (array_key_exists('type', $payload)) {
            $payload['type'] = $this->normalizeType($payload['type']);
        }
        if (array_key_exists('parent_id', $payload)) {
            $payload['parent_id'] = $this->normalizeParentId($payload['parent_id']);
        }

        $candidate = array_merge($current, $payload);
        $currentType = (string) $current['type'];
        $this->assertDates($candidate);
        $this->assertLocation($candidate);

        if (! array_key_exists('parent_id', $data) && ! array_key_exists('type', $data)) {
            return $this->repo()->update($id, $payload);
        }

        return $this->withTreeLock(function () use ($id, $payload, $candidate, $currentType): bool {
            $this->assertHierarchyCandidate($id, (string) $candidate['type'], $candidate['parent_id']);
            if ((string) $candidate['type'] !== $currentType) {
                $this->assertChildrenCompatible($id, (string) $candidate['type']);
            }

            return $this->repo()->update($id, $payload);
        });
    }

    public function inactivate(int $id): bool
    {
        return $this->update($id, [
            'status' => 'INACTIVE',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function resolveAncestorChain(int $organizationId): array
    {
        $current = $this->repo()->find($organizationId);
        if ($current === null) {
            throw new DomainException(self::ERROR_REFERENCE_INVALID, ['organization_id' => $organizationId]);
        }

        $chain = [];
        $visited = [];
        while (true) {
            $currentId = (int) ($current['id'] ?? 0);
            if ($currentId < 1 || isset($visited[$currentId])) {
                throw new DomainException(self::ERROR_HIERARCHY_CYCLE, ['organization_id' => $organizationId]);
            }
            $visited[$currentId] = true;
            array_unshift($chain, $current);

            $parentId = $current['parent_id'] ?? null;
            if ($parentId === null) {
                return $chain;
            }

            $current = $this->repo()->find((int) $parentId);
            if ($current === null) {
                throw new DomainException(self::ERROR_REFERENCE_INVALID, ['parent_id' => $parentId]);
            }
        }
    }

    /** @return array<string, mixed>|null */
    public function resolveCompanyAncestor(int $organizationId): ?array
    {
        foreach (array_reverse($this->resolveAncestorChain($organizationId)) as $organization) {
            if ((string) $organization['type'] === 'COMPANY') {
                return $organization;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function prepareCreate(array $data): array
    {
        $payload = $data;
        $payload['type'] = $this->normalizeType($data['type'] ?? null);
        $payload['parent_id'] = $this->normalizeParentId($data['parent_id'] ?? null);
        $payload['created_at'] ??= date('Y-m-d H:i:s');

        return $payload;
    }

    private function repo(): OrganizationRepository
    {
        return $this->repository ?? new OrganizationRepository($this->db);
    }

    private function normalizeType(mixed $value): string
    {
        if (! is_string($value)) {
            throw new DomainException(self::ERROR_INVALID_PARENT, ['field' => 'type']);
        }
        $type = strtoupper(trim($value));
        if (! in_array($type, self::TYPES, true)) {
            throw new DomainException(self::ERROR_INVALID_PARENT, ['type' => $type]);
        }

        return $type;
    }

    private function normalizeParentId(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            $parentId = $value;
        } elseif (is_string($value) && ctype_digit($value)) {
            $parentId = (int) $value;
        } else {
            throw new DomainException(self::ERROR_INVALID_PARENT, ['field' => 'parent_id']);
        }
        if ($parentId < 1) {
            throw new DomainException(self::ERROR_INVALID_PARENT, ['field' => 'parent_id']);
        }

        return $parentId;
    }

    private function assertHierarchyCandidate(?int $organizationId, string $type, mixed $parentId): void
    {
        $normalizedParentId = $this->normalizeParentId($parentId);
        if ($type === 'GROUP') {
            if ($normalizedParentId !== null) {
                throw new DomainException(self::ERROR_INVALID_PARENT, ['type' => $type]);
            }
            $this->assertSingleGroupRoot($organizationId);

            return;
        }
        if ($normalizedParentId === null) {
            throw new DomainException(self::ERROR_INVALID_PARENT, ['type' => $type]);
        }

        $parent = $this->repo()->find($normalizedParentId);
        if ($parent === null) {
            throw new DomainException(self::ERROR_INVALID_PARENT, ['parent_id' => $normalizedParentId]);
        }
        $parentType = (string) ($parent['type'] ?? '');
        if (! in_array($type, self::ALLOWED_CHILDREN[$parentType] ?? [], true)) {
            throw new DomainException(self::ERROR_INVALID_PARENT, [
                'parent_id' => $normalizedParentId,
                'parent_type' => $parentType,
                'child_type' => $type,
            ]);
        }
        $this->assertNoCycle($organizationId, $normalizedParentId);
    }

    private function assertSingleGroupRoot(?int $excludeId): void
    {
        foreach ($this->repo()->findAll(['type' => 'GROUP', 'parent_id' => null]) as $root) {
            if ($excludeId === null || (int) $root['id'] !== $excludeId) {
                throw new DomainException(self::ERROR_SECOND_GROUP_ROOT, ['organization_id' => $root['id']]);
            }
        }
    }

    private function assertNoCycle(?int $organizationId, int $parentId): void
    {
        if ($organizationId !== null && $organizationId === $parentId) {
            throw new DomainException(self::ERROR_HIERARCHY_CYCLE, ['organization_id' => $organizationId]);
        }

        $visited = [];
        $currentId = $parentId;
        while (true) {
            if (isset($visited[$currentId])) {
                throw new DomainException(self::ERROR_HIERARCHY_CYCLE, ['organization_id' => $organizationId]);
            }
            $visited[$currentId] = true;
            if ($organizationId !== null && $currentId === $organizationId) {
                throw new DomainException(self::ERROR_HIERARCHY_CYCLE, ['organization_id' => $organizationId]);
            }

            $current = $this->repo()->find($currentId);
            if ($current === null) {
                throw new DomainException(self::ERROR_REFERENCE_INVALID, ['parent_id' => $currentId]);
            }
            if (($current['parent_id'] ?? null) === null) {
                return;
            }
            $currentId = (int) $current['parent_id'];
        }
    }

    private function assertChildrenCompatible(int $organizationId, string $type): void
    {
        foreach ($this->repo()->findAll(['parent_id' => $organizationId]) as $child) {
            if (! in_array((string) $child['type'], self::ALLOWED_CHILDREN[$type] ?? [], true)) {
                throw new DomainException(self::ERROR_INVALID_PARENT, [
                    'organization_id' => $organizationId,
                    'child_type' => $child['type'],
                ]);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function assertDates(array $data): void
    {
        foreach (['active_from', 'active_to'] as $field) {
            if (($data[$field] ?? null) !== null && ! $this->isDate((string) $data[$field])) {
                throw new DomainException(self::ERROR_ACTIVE_DATE_INVALID, ['field' => $field]);
            }
        }
        $from = $data['active_from'] ?? null;
        $to = $data['active_to'] ?? null;
        if ($from !== null && $to !== null && (string) $to < (string) $from) {
            throw new DomainException(self::ERROR_ACTIVE_DATE_INVALID, ['field' => 'active_to']);
        }
    }

    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false
            && $date->format('Y-m-d') === $value
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    /** @param array<string, mixed> $data */
    private function assertLocation(array $data): void
    {
        $provinceId = $this->referenceId($data['province_id'] ?? null, 'province_id');
        $wardId = $this->referenceId($data['ward_id'] ?? null, 'ward_id');
        if ($provinceId !== null && $this->db->table('provinces')->where('id', $provinceId)->countAllResults() !== 1) {
            throw new DomainException(self::ERROR_REFERENCE_INVALID, ['province_id' => $provinceId]);
        }
        if ($wardId !== null && $this->db->table('wards')->where('id', $wardId)->countAllResults() !== 1) {
            throw new DomainException(self::ERROR_REFERENCE_INVALID, ['ward_id' => $wardId]);
        }
        if ($provinceId !== null && $wardId !== null) {
            $matches = $this->db->table('wards')
                ->where('id', $wardId)
                ->where('province_id', $provinceId)
                ->countAllResults();
            if ($matches !== 1) {
                throw new DomainException(self::ERROR_WARD_PROVINCE_MISMATCH, [
                    'province_id' => $provinceId,
                    'ward_id' => $wardId,
                ]);
            }
        }
    }

    private function referenceId(mixed $value, string $field): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            $id = $value;
        } elseif (is_string($value) && ctype_digit($value)) {
            $id = (int) $value;
        } else {
            throw new DomainException(self::ERROR_REFERENCE_INVALID, ['field' => $field]);
        }
        if ($id < 1) {
            throw new DomainException(self::ERROR_REFERENCE_INVALID, ['field' => $field]);
        }

        return $id;
    }

    /** @param callable(): mixed $callback */
    private function withTreeLock(callable $callback): mixed
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => 'organization_mutation']);
        }
        $db = $this->db;
        $lock = $this->domainLock ?? new MariaDbDomainLockService($db);

        try {
            return $lock->synchronized('organization-tree', function () use ($callback, $db): mixed {
                $db->transBegin();
                try {
                    $result = $callback();
                    if (! $db->transStatus()) {
                        throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => 'organization_mutation']);
                    }
                    $db->transCommit();

                    return $result;
                } catch (\Throwable $exception) {
                    $db->transRollback();
                    throw $exception;
                }
            });
        } catch (DomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => 'organization_mutation'], $exception);
        }
    }
}
