<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\OperationContext;
use App\Repositories\EmployeeRepository;
use App\Repositories\OrganizationRepository;
use App\Services\EmployeeAssignmentService;
use CodeIgniter\Database\ConnectionInterface;

final class EvaluationOrganizationResolver
{
    public const ERROR_CONTEXT_REQUIRED = 'EVALUATION_ORGANIZATION_CONTEXT_REQUIRED';
    public const ERROR_CONTEXT_INVALID = 'EVALUATION_ORGANIZATION_CONTEXT_INVALID';
    public const ERROR_CONTEXT_NOT_FOUND = 'EVALUATION_ORGANIZATION_NOT_FOUND';
    public const ERROR_HINT_INVALID = 'EVALUATION_ORGANIZATION_HINT_INVALID';
    public const ERROR_HINT_MISMATCH = 'EVALUATION_ORGANIZATION_HINT_MISMATCH';
    public const ERROR_ACTOR_CONTEXT_UNRESOLVED = 'EVALUATION_ORGANIZATION_ACTOR_CONTEXT_UNRESOLVED';

    /** @var list<string> */
    private const GROUP_RESOURCES = [
        'product',
        'product_group',
        'product_uom',
        'province',
        'ward',
        'user',
        'authorization_group',
        'resource',
    ];

    /** @var list<string> */
    private const ORGANIZATION_TYPES = ['GROUP', 'COMPANY', 'BRANCH', 'PLANT', 'WAREHOUSE'];

    private OrganizationRepository $organizations;
    private EmployeeRepository $employees;
    private EmployeeAssignmentService $assignments;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?OrganizationRepository $organizations = null,
        ?EmployeeRepository $employees = null,
        ?EmployeeAssignmentService $assignments = null,
        ?ConnectionInterface $db = null,
    ) {
        $connection = $db ?? db_connect();
        $this->organizations = $organizations ?? new OrganizationRepository($connection);
        $this->employees = $employees ?? new EmployeeRepository($connection);
        $this->assignments = $assignments ?? new EmployeeAssignmentService(null, $connection);
    }

    public function resolve(
        EvaluationOrganizationRequest $request,
        OperationContext $operation,
    ): EvaluationOrganizationContext {
        $resource = $this->normalizeToken($request->resource, 'resource');
        $operationName = $this->normalizeToken($request->operation, 'operation');
        $hint = $this->optionalId(
            $request->clientEvaluationOrganizationId,
            'evaluation_organization_id',
            self::ERROR_HINT_INVALID,
        );

        [$organizationId, $source] = $this->authoritativeOrganization(
            $resource,
            $operationName,
            $request,
            $operation,
        );

        if ($hint !== null && $hint !== $organizationId) {
            throw new DomainException(self::ERROR_HINT_MISMATCH, [
                'evaluation_organization_id' => $hint,
                'authoritative_evaluation_organization_id' => $organizationId,
            ]);
        }

        return $this->validatedContext($organizationId, $source);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function authoritativeOrganization(
        string $resource,
        string $operationName,
        EvaluationOrganizationRequest $request,
        OperationContext $operation,
    ): array {
        if (in_array($resource, self::GROUP_RESOURCES, true)) {
            return [$this->groupId(), 'GROUP_RESOURCE'];
        }

        $isCreate = $operationName === 'create';
        $target = $request->persistedTarget;
        if (! $isCreate) {
            if ($target === null) {
                throw new DomainException(self::ERROR_CONTEXT_REQUIRED, ['resource' => $resource]);
            }

            $source = $resource === 'employee_assignment' ? 'ASSIGNMENT_ORGANIZATION' : 'PERSISTED_TARGET';

            return [$this->existingTargetOrganization($resource, $target), $source];
        }

        if ($resource === 'organization') {
            return [$this->requiredInputId($request->input, 'parent_id'), 'CANONICAL_PARENT'];
        }
        if ($resource === 'employee_assignment') {
            return [$this->requiredInputId($request->input, 'organization_id'), 'ASSIGNMENT_ORGANIZATION'];
        }

        $canonical = $this->canonicalCreateOrganization($request->input);
        if ($canonical !== null) {
            return [$canonical, 'CANONICAL_OWNER_SCOPE'];
        }
        if (! $request->allowPrimaryAssignmentFallback) {
            throw new DomainException(self::ERROR_CONTEXT_REQUIRED, ['resource' => $resource]);
        }

        return [$this->primaryAssignmentOrganization($operation), 'PRIMARY_ASSIGNMENT'];
    }

    /** @param array<string, mixed> $target */
    private function existingTargetOrganization(string $resource, array $target): int
    {
        if ($resource === 'organization') {
            return $this->requiredTargetId($target, 'id');
        }
        if (in_array($resource, ['employee_assignment', 'policy', 'price_list'], true)) {
            return $this->requiredTargetId($target, 'organization_id');
        }

        foreach (['organization_id', 'owner_organization_id'] as $field) {
            if (array_key_exists($field, $target)) {
                return $this->requiredTargetId($target, $field);
            }
        }

        throw new DomainException(self::ERROR_CONTEXT_REQUIRED, ['resource' => $resource]);
    }

    /** @param array<string, mixed> $input */
    private function canonicalCreateOrganization(array $input): ?int
    {
        foreach (['organization_id', 'owner_organization_id', 'parent_organization_id', 'scope_organization_id'] as $field) {
            if (array_key_exists($field, $input)) {
                return $this->requiredInputId($input, $field);
            }
        }

        return null;
    }

    /** @param array<string, mixed> $input */
    private function requiredInputId(array $input, string $field): int
    {
        if (! array_key_exists($field, $input)) {
            throw new DomainException(self::ERROR_CONTEXT_REQUIRED, ['field' => $field]);
        }

        return PositiveId::normalize($input[$field], $field, self::ERROR_CONTEXT_INVALID);
    }

    /** @param array<string, mixed> $target */
    private function requiredTargetId(array $target, string $field): int
    {
        if (! array_key_exists($field, $target)) {
            throw new DomainException(self::ERROR_CONTEXT_REQUIRED, ['field' => $field]);
        }

        return PositiveId::normalize($target[$field], $field, self::ERROR_CONTEXT_INVALID);
    }

    private function primaryAssignmentOrganization(OperationContext $operation): int
    {
        if ($operation->actorUserId === null) {
            throw new DomainException(self::ERROR_ACTOR_CONTEXT_UNRESOLVED);
        }

        $employee = $this->employees->findByUserId($operation->actorUserId);
        if ($employee === null || ($employee['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException(self::ERROR_ACTOR_CONTEXT_UNRESOLVED, [
                'user_id' => $operation->actorUserId,
            ]);
        }

        $default = $this->assignments->resolveDefaultActorContext(
            PositiveId::normalize($employee['id'] ?? null, 'employee_id', self::ERROR_ACTOR_CONTEXT_UNRESOLVED),
            $operation->evaluationTime,
        );
        if ($default === null || ! isset($default['organization']['id'])) {
            throw new DomainException(self::ERROR_ACTOR_CONTEXT_UNRESOLVED, [
                'user_id' => $operation->actorUserId,
            ]);
        }

        return PositiveId::normalize(
            $default['organization']['id'],
            'organization_id',
            self::ERROR_ACTOR_CONTEXT_UNRESOLVED,
        );
    }

    private function groupId(): int
    {
        $roots = $this->organizations->findAll(['type' => 'GROUP', 'parent_id' => null]);
        if (count($roots) !== 1) {
            throw new DomainException(self::ERROR_CONTEXT_REQUIRED, ['resource' => 'GROUP']);
        }

        return PositiveId::normalize($roots[0]['id'] ?? null, 'organization_id', self::ERROR_CONTEXT_INVALID);
    }

    private function validatedContext(int $organizationId, string $source): EvaluationOrganizationContext
    {
        $organization = $this->organizations->find($organizationId);
        if ($organization === null) {
            throw new DomainException(self::ERROR_CONTEXT_NOT_FOUND, [
                'organization_id' => $organizationId,
            ]);
        }
        if (($organization['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException(self::ERROR_CONTEXT_INVALID, [
                'organization_id' => $organizationId,
            ]);
        }

        $type = strtoupper((string) ($organization['type'] ?? ''));
        if (! in_array($type, self::ORGANIZATION_TYPES, true)) {
            throw new DomainException(self::ERROR_CONTEXT_INVALID, [
                'organization_id' => $organizationId,
            ]);
        }

        return new EvaluationOrganizationContext($organizationId, $type, $source);
    }

    private function normalizeToken(string $value, string $field): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            throw new DomainException(self::ERROR_CONTEXT_INVALID, ['field' => $field]);
        }

        return $normalized;
    }

    private function optionalId(mixed $value, string $field, string $errorCode): ?int
    {
        if ($value === null) {
            return null;
        }

        return PositiveId::normalize($value, $field, $errorCode);
    }
}
