<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Repositories\EmployeeRepository;
use App\Repositories\OrganizationRepository;
use CodeIgniter\Database\ConnectionInterface;

final class EvaluationOrganizationResolver
{
    public const ERROR_CONTEXT_REQUIRED = 'EVALUATION_ORGANIZATION_CONTEXT_REQUIRED';
    public const ERROR_CONTEXT_INVALID = 'EVALUATION_ORGANIZATION_CONTEXT_INVALID';
    public const ERROR_CONTEXT_NOT_FOUND = 'EVALUATION_ORGANIZATION_NOT_FOUND';
    public const ERROR_HINT_INVALID = 'EVALUATION_ORGANIZATION_HINT_INVALID';
    public const ERROR_HINT_MISMATCH = 'EVALUATION_ORGANIZATION_HINT_MISMATCH';

    /** @var list<string> */
    private const ORGANIZATION_TYPES = ['GROUP', 'COMPANY', 'BRANCH', 'PLANT', 'WAREHOUSE'];

    private OrganizationRepository $organizations;
    private OrganizationScopeResolverRegistry $registry;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?OrganizationRepository $organizations = null,
        ?EmployeeRepository $employees = null,
        ?OrganizationScopeResolverRegistry $registry = null,
        ?ConnectionInterface $db = null,
    ) {
        $connection = $db ?? db_connect();
        $organizations ??= new OrganizationRepository($connection);
        $employees ??= new EmployeeRepository($connection);
        $this->organizations = $organizations;
        $this->registry = $registry ?? $this->defaultRegistry($organizations, $employees);
    }

    /** @param array<string, mixed> $resource */
    public function resolveResource(
        string $resourceType,
        string $action,
        array $resource,
        EvaluationOrganizationRequest $request,
    ): EvaluationOrganizationContext {
        $resourceType = $this->normalizeToken($resourceType, 'resource');
        $action = $this->normalizeToken($action, 'action');
        $domainValue = trim((string) ($resource['domain'] ?? ''));
        if ($domainValue === '') {
            throw new DomainException(self::ERROR_CONTEXT_INVALID, ['field' => 'domain']);
        }
        try {
            $domain = ResourceDomain::from($domainValue);
        } catch (\ValueError) {
            throw new DomainException(self::ERROR_CONTEXT_INVALID, ['domain' => $domainValue]);
        }

        $hint = $this->optionalId($request->clientEvaluationOrganizationId);
        $resolution = $domain->isGroupScoped()
            ? new OrganizationScopeResolution($this->groupId(), 'GROUP_RESOURCE_DOMAIN')
            : $this->registry->resolve($resourceType, $action, $request);
        if ($hint !== null && $hint !== $resolution->organizationId) {
            throw new DomainException(self::ERROR_HINT_MISMATCH, [
                'evaluation_organization_id' => $hint,
                'authoritative_evaluation_organization_id' => $resolution->organizationId,
            ]);
        }

        return $this->validatedContext($resolution->organizationId, $resolution->source);
    }

    private function defaultRegistry(OrganizationRepository $organizations, EmployeeRepository $employees): OrganizationScopeResolverRegistry
    {
        $groupResolver = fn (): int => $this->groupId();

        return new OrganizationScopeResolverRegistry([
            'organization' => new OrganizationResourceScopeResolver(),
            'contact' => new ContactResourceScopeResolver($organizations, $employees, $groupResolver),
            'employee_assignment' => new OrganizationIdResourceScopeResolver(),
            'policy' => new OrganizationIdResourceScopeResolver(),
            'price_list' => new OrganizationIdResourceScopeResolver(),
            'authorization_log' => new AuthorizationLogScopeResolver($groupResolver),
            'import_job' => new ImportJobScopeResolver($groupResolver),
            'display_setting' => new OrganizationIdResourceScopeResolver(),
        ]);
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
            throw new DomainException(self::ERROR_CONTEXT_NOT_FOUND, ['organization_id' => $organizationId]);
        }
        $type = strtoupper((string) ($organization['type'] ?? ''));
        if (! in_array($type, self::ORGANIZATION_TYPES, true)) {
            throw new DomainException(self::ERROR_CONTEXT_INVALID, ['organization_id' => $organizationId]);
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

    private function optionalId(mixed $value): ?int
    {
        return $value === null
            ? null
            : PositiveId::normalize($value, 'evaluation_organization_id', self::ERROR_HINT_INVALID);
    }
}
