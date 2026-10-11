<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationService;
use App\Authorization\ContactAuthorizationRegistry;
use App\Authorization\EmployeeContactScopeResolver;
use App\Authorization\EvaluationOrganizationResolver;
use App\Authorization\EffectiveListBucketCompiler;
use App\Authorization\OwnerAwareListBucketCompiler;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListResult;
use App\Infrastructure\Api\SqlAuthorizedBucketListSource;
use App\Infrastructure\Api\SqlOwnerAwareListSource;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\RequestContext;
use App\Repositories\ContactRepository;
use App\Repositories\FoundationLocationRepository;
use App\Repositories\OrganizationRepository;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;

/** Application boundary for the CONTRACT_READY API-010 surfaces. */
final class Api010FoundationOrganizationContactService
{
    public const ERROR_TRANSITION_REQUIRED = 'WF_TRANSITION_REQUIRED';

    public function __construct(
        private readonly ?CommonListQueryAdapter $queryAdapter = null,
        private readonly ?FoundationLocationRepository $locations = null,
        private readonly ?OrganizationRepository $organizations = null,
        private readonly ?ContactRepository $contacts = null,
        private ?AuthorizationService $authorization = null,
        private ?ResourceWorkflowStateResolver $workflowStates = null,
        private readonly ?LifecycleService $lifecycleService = null,
    ) {
    }

    /** @param array<string, mixed> $query */
    public function list(string $resource, array $query): CommonListResult
    {
        $operation = $this->operation();
        return $this->auth()->withReadOnlyBatch($operation, function () use ($resource, $query, $operation) {
            $sql = $this->authorizedSqlPage($resource, $query, $operation);
            if ($sql !== null) {
                return $sql;
            }
            $rows = $this->authorizedRows($resource, $operation);
            $allowlist = $this->allowlist($resource);

            return ($this->queryAdapter ?? new CommonListQueryAdapter())->paginate(
                $query,
                $allowlist,
                new AuthorizedArrayListSource($rows),
            );
        });
    }

    /** @return array<string, mixed> */
    public function get(string $resource, int $id): array
    {
        $operation = $this->operation();
        $row = $this->row($resource, $id);
        $this->authorizeVisible($resource, $row, $operation);

        return $row;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(string $resource, array $payload): array
    {
        $operation = $this->operation();
        $id = match ($resource) {
            'province' => $this->createProvince($payload, $operation),
            'ward' => $this->createWard($payload, $operation),
            'organization' => $this->createOrganization($payload, $operation),
            'contact' => $this->createContact($payload, $operation),
            default => throw new \InvalidArgumentException('Unsupported API-010 resource.'),
        };

        return $this->get($resource, $id);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(string $resource, int $id, array $payload): array
    {
        $operation = $this->operation();
        $row = $this->row($resource, $id);
        $this->authorizeVisible($resource, $row, $operation);
        // State changes must go through a validated Transition rather than
        // smuggling a status field through an ordinary edit operation.
        if (array_key_exists('status', $payload) && $this->stateResolver()->lifecycleId($this->authorizationResource($resource, $row, [])) !== null) {
            throw new DomainException(self::ERROR_TRANSITION_REQUIRED, ['resource' => $resource]);
        }
        if ($resource === 'contact' && array_key_exists('status', $payload)) {
            throw new DomainException('CONTACT_STATE_REQUIRES_SETSTATE');
        }
        $this->authorizeAction($resource, 'edit', $row, $payload, $operation);

        match ($resource) {
            'province' => $this->locationService($operation)->updateProvince($id, $payload),
            'ward' => $this->locationService($operation)->updateWard($id, $payload),
            'organization' => $this->organizationService($operation)->update($id, $payload),
            'contact' => $this->contactService($operation)->update($id, $payload),
            default => throw new \InvalidArgumentException('Unsupported API-010 resource.'),
        };

        return $this->get($resource, $id);
    }

    /** @return array<string, mixed> */
    public function setState(string $resource, int $id, string $status): array
    {
        $operation = $this->operation();
        $row = $this->row($resource, $id);
        $this->authorizeVisible($resource, $row, $operation);
        $authorizationResource = $this->authorizationResource($resource, $row, []);
        if ($this->stateResolver()->lifecycleId($authorizationResource) !== null) {
            if (! in_array($authorizationResource, ['province', 'ward', 'organization_contact', 'employee_contact'], true)) {
                throw new DomainException(LifecycleService::ERROR_TARGET_ADAPTER_UNAVAILABLE, ['resource' => $authorizationResource]);
            }
            $destinationStateId = $this->stateResolver()->stateIdForCode($authorizationResource, $status);
            $this->lifecycle()->execute($authorizationResource, $id, 'setstate', $operation, $destinationStateId);

            return $this->get($resource, $id);
        }
        // Legacy resources without a configured Lifecycle retain their API.
        $this->authorizeAction($resource, 'setstate', $row, ['status' => $status], $operation);

        match ($resource) {
            'province' => $this->locationService($operation)->setProvinceState($id, $status),
            'ward' => $this->locationService($operation)->setWardState($id, $status),
            'organization' => $this->organizationService($operation)->update($id, ['status' => $status]),
            'contact' => $this->setContactState($id, $status, $operation),
            default => throw new \InvalidArgumentException('Unsupported API-010 resource.'),
        };

        return $this->get($resource, $id);
    }

    /** @param array<string, mixed> $query */
    public function tree(array $query): CommonListResult
    {
        $result = $this->list('organization', $query);
        $rows = iterator_to_array($result->data);
        $visibleIds = [];
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $visibleIds[(string) $row['id']] = true;
            }
        }
        foreach ($rows as &$row) {
            if (($row['parent_id'] ?? null) !== null && ! isset($visibleIds[(string) $row['parent_id']])) {
                $row['parent_id'] = null;
            }
        }
        unset($row);

        return new CommonListResult($rows, $result->query, $result->total);
    }

    /**
     * MASTER location rows share GROUP scope. The authoritative State is
     * persisted status; compile each State once, then SQL count/page.
     * Organization/Contact now have an additional owner-aware SQL fast-path,
     * with the original authorization-before-pagination path as fallback.
     *
     * @param array<string,mixed> $query
     */
    private function authorizedSqlPage(string $resource, array $query, OperationContext $operation): ?CommonListResult
    {
        if (in_array($resource, ['organization', 'contact'], true)) {
            return $this->ownerAwareSqlPage($resource, $query, $operation);
        }
        if (! in_array($resource, ['province', 'ward'], true)
            || array_key_exists('q', $query)
            || (isset($query['sort']) && ! in_array($query['sort'], ['id', '-id'], true))) {
            return null;
        }
        $adapter = $this->queryAdapter ?? new CommonListQueryAdapter();
        $parsed = $adapter->parse($query, $this->allowlist($resource));
        if (! SqlAuthorizedBucketListSource::supports($resource, $parsed)) {
            return null;
        }
        $db = db_connect();
        $buckets = (new EffectiveListBucketCompiler($db, $this->auth()))->compile(
            $resource,
            $operation,
            static fn (DomainException $exception): bool => in_array($exception->getErrorCode(), [
                AuthorizationService::REASON_POLICY_DENIED,
                AuthorizationService::REASON_NO_BASE_PERMISSION,
            ], true),
        );
        if ($buckets === null) {
            return null;
        }
        $result = $adapter->paginate($query, $this->allowlist($resource),
            new SqlAuthorizedBucketListSource($db, $resource, $buckets));
        $data = [];
        foreach ($result->data as $row) {
            if (is_array($row)) {
                $this->authorize($operation, $resource, 'view', $row, []);
                $data[] = $row;
            }
        }
        return new CommonListResult($data, $result->query, $result->total);
    }

    /**
     * SQL filters only PBAC-approved, persisted Owner/State groups. Never use
     * a raw Policy ALLOW/DENY JOIN or paginate before authorization. Only ID
     * sort + exactly reproducible filters may take this fast-path.
     *
     * @param array<string,mixed> $query
     */
    private function ownerAwareSqlPage(string $resource, array $query, OperationContext $operation): ?CommonListResult
    {
        $adapter = $this->queryAdapter ?? new CommonListQueryAdapter();
        $allowlist = $this->allowlist($resource);
        $parsed = $adapter->parse($query, $allowlist);
        if (! SqlOwnerAwareListSource::supports($resource, $parsed)) {
            return null;
        }
        $db = db_connect();
        $compiler = new OwnerAwareListBucketCompiler($db, $this->auth());
        $approved = $resource === 'organization'
            ? $compiler->organizations($operation)
            : $compiler->contacts($operation);
        if ($approved === null) {
            return null;
        }
        $result = $adapter->paginate($query, $allowlist,
            new SqlOwnerAwareListSource($db, $resource, $approved));

        // The compiler only authorizes representative targets. Verify every
        // returned row again before exposing it, including after concurrent
        // owner/State changes. If a bucket went stale, rerun the legacy path.
        $rows = [];
        foreach ($result->data as $row) {
            if (! is_array($row)) {
                return null;
            }
            try {
                $this->authorize($operation, $resource, 'view', $row, []);
            } catch (DomainException $exception) {
                if (in_array($exception->getErrorCode(), [
                    AuthorizationService::REASON_POLICY_DENIED,
                    AuthorizationService::REASON_NO_BASE_PERMISSION,
                    ContactAuthorizationRegistry::ERROR_TYPE_UNSUPPORTED,
                    EmployeeContactScopeResolver::ERROR_PRIMARY_SCOPE_UNAVAILABLE,
                    EvaluationOrganizationResolver::ERROR_CONTEXT_NOT_FOUND,
                ], true)) {
                    return null;
                }
                throw $exception;
            }
            $rows[] = $row;
        }
        return new CommonListResult($rows, $result->query, $result->total);
    }

    /** @return list<array<string, mixed>> */
    private function authorizedRows(string $resource, OperationContext $operation): array
    {
        $rows = match ($resource) {
            'province' => $this->locationRepo()->findAll('provinces'),
            'ward' => $this->locationRepo()->findAll('wards'),
            'organization' => $this->organizationRepo()->findAll(),
            'contact' => $this->contactRepo()->search(),
            default => throw new \InvalidArgumentException('Unsupported API-010 resource.'),
        };
        $visible = [];
        foreach ($rows as $row) {
            try {
                $this->authorize($operation, $resource, 'view', $row, []);
                $visible[] = $row;
            } catch (DomainException $exception) {
                if (! in_array($exception->getErrorCode(), [
                    AuthorizationService::REASON_POLICY_DENIED,
                    AuthorizationService::REASON_NO_BASE_PERMISSION,
                    ContactAuthorizationRegistry::ERROR_TYPE_UNSUPPORTED,
                    EmployeeContactScopeResolver::ERROR_PRIMARY_SCOPE_UNAVAILABLE,
                    EvaluationOrganizationResolver::ERROR_CONTEXT_NOT_FOUND,
                ], true)) {
                    throw $exception;
                }
            }
        }

        return $visible;
    }

    /** @return array<string, mixed> */
    private function row(string $resource, int $id): array
    {
        $row = match ($resource) {
            'province' => $this->locationRepo()->find('provinces', $id),
            'ward' => $this->locationRepo()->find('wards', $id),
            'organization' => $this->organizationRepo()->find($id),
            'contact' => $this->contactRepo()->find($id),
            default => throw new \InvalidArgumentException('Unsupported API-010 resource.'),
        };
        if ($row === null) {
            throw new PageNotFoundException('API-010 target not found.');
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    private function authorizeVisible(string $resource, array $row, OperationContext $operation): void
    {
        try {
            $this->authorize($operation, $resource, 'view', $row, []);
        } catch (DomainException $exception) {
            if ($exception->getErrorCode() === AuthorizationService::REASON_POLICY_DENIED
                || $exception->getErrorCode() === AuthorizationService::REASON_NO_BASE_PERMISSION
                || $exception->getErrorCode() === ContactAuthorizationRegistry::ERROR_TYPE_UNSUPPORTED
                || $exception->getErrorCode() === EmployeeContactScopeResolver::ERROR_PRIMARY_SCOPE_UNAVAILABLE
                || $exception->getErrorCode() === EvaluationOrganizationResolver::ERROR_CONTEXT_NOT_FOUND
            ) {
                throw new PageNotFoundException('API-010 target is not visible.');
            }
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $input
     */
    private function authorizeAction(string $resource, string $action, array $target, array $input, OperationContext $operation): void
    {
        $this->authorize($operation, $resource, $action, $target, $input);
    }

    /**
     * Route by trusted persisted owner for reads/mutations, and by validated
     * input only for create. Never accept a client-supplied resource hint.
     *
     * @param array<string,mixed> $target
     * @param array<string,mixed> $input
     */
    private function authorizationResource(string $resource, array $target, array $input): string
    {
        return $resource === 'contact'
            ? ContactAuthorizationRegistry::resourceFor($target !== [] ? $target : $input)
            : $resource;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $input
     */
    private function authorize(OperationContext $operation, string $resource, string $action, array $target, array $input): void
    {
        $authorizationResource = $this->authorizationResource($resource, $target, $input);
        $decision = $this->auth()->authorize(
            $operation,
            $authorizationResource,
            $action,
            new EvaluationOrganizationRequest(
                persistedTarget: $target === [] ? null : $target,
                input: $input,
            ),
            $target === [] ? null : $this->stateResolver()->persistedStateId($authorizationResource, $target),
        );
        if (! $decision->isAllowed()) {
            throw new DomainException(AuthorizationService::REASON_POLICY_DENIED);
        }
    }

    /** @param array<string, mixed> $payload */
    private function createProvince(array $payload, OperationContext $operation): int
    {
        $this->assertInitialStatus('province', $payload);
        $this->authorize($operation, 'province', 'create', [], $payload);

        return $this->locationService($operation)->createProvince($payload);
    }

    /** @param array<string, mixed> $payload */
    private function createWard(array $payload, OperationContext $operation): int
    {
        $this->assertInitialStatus('ward', $payload);
        $this->authorize($operation, 'ward', 'create', [], $payload);

        return $this->locationService($operation)->createWard($payload);
    }

    /** @param array<string, mixed> $payload */
    private function createOrganization(array $payload, OperationContext $operation): int
    {
        $this->assertInitialStatus('organization', $payload);
        $this->authorize($operation, 'organization', 'create', [], $payload);

        return $this->organizationService($operation)->create($payload);
    }

    /** @param array<string, mixed> $payload */
    private function createContact(array $payload, OperationContext $operation): int
    {
        $resource = $this->authorizationResource('contact', [], $payload);
        // The server selects the initial State. A client hint may corroborate
        // it for legacy clients, but must never override the configured State.
        $initial = $this->stateResolver()->initialCode($resource) ?? 'ACTIVE';
        if (array_key_exists('status', $payload)
            && (! is_string($payload['status']) || strtoupper(trim($payload['status'])) !== $initial)) {
            throw new DomainException(ResourceWorkflowStateResolver::ERROR_INITIAL_STATE_INVALID, [
                'resource' => $resource, 'initial_status' => $initial,
            ]);
        }
        $payload['status'] = $initial;
        $this->assertInitialStatus($resource, $payload);
        $this->authorize($operation, 'contact', 'create', [], $payload);

        return $this->contactService($operation)->create($payload);
    }

    private function setContactState(int $id, string $status, OperationContext $operation): bool
    {
        if (! in_array($status, ['ACTIVE', 'INACTIVE', 'CLOSED'], true)) {
            throw new DomainException(ContactService::ERROR_STATUS_INVALID, ['status' => $status]);
        }
        if ($status === 'ACTIVE') {
            return $this->contactService($operation)->update($id, ['status' => $status]);
        }

        return $this->contactService($operation)->inactivate($id, $status);
    }

    private function operation(): OperationContext
    {
        $user = auth()->user();
        if (! $user instanceof User || ! is_numeric($user->id) || (int) $user->id < 1) {
            throw new DomainException('UNAUTHENTICATED');
        }
        $context = new MutationContext(service('clock'), (int) $user->id, RequestContext::requestId(), false);

        return $context->begin();
    }

    private function locationService(OperationContext $operation): FoundationLocationService
    {
        return new FoundationLocationService($this->locationRepo(), null, new MutationContext(service('clock'), $operation->actorUserId, $operation->requestId, false));
    }

    private function lifecycle(): LifecycleService
    {
        if ($this->lifecycleService !== null) {
            return $this->lifecycleService;
        }
        $adapterProvince = new FoundationLocationLifecycleAdapter('province');
        $adapterWard = new FoundationLocationLifecycleAdapter('ward');
        $organizationContact = new ContactLifecycleAdapter('organization_contact');
        $employeeContact = new ContactLifecycleAdapter('employee_contact');

        return new LifecycleService(adapters: [
            $adapterProvince, $adapterWard, $organizationContact, $employeeContact,
        ]);
    }

    private function organizationService(OperationContext $operation): OrganizationService
    {
        return new OrganizationService($this->organizationRepo(), null, null, new MutationContext(service('clock'), $operation->actorUserId, $operation->requestId, false));
    }

    private function contactService(OperationContext $operation): ContactService
    {
        return new ContactService($this->contactRepo(), null, new MutationContext(service('clock'), $operation->actorUserId, $operation->requestId, false));
    }

    private function auth(): AuthorizationService
    {
        return $this->authorization ??= new AuthorizationService();
    }

    /** @param array<string,mixed> $payload */
    private function assertInitialStatus(string $resource, array $payload): void
    {
        $initial = $this->stateResolver()->initialCode($resource);
        if ($initial !== null && (! is_string($payload['status'] ?? null)
            || strtoupper(trim($payload['status'])) !== $initial)) {
            throw new DomainException(ResourceWorkflowStateResolver::ERROR_INITIAL_STATE_INVALID, [
                'resource' => $resource,
                'initial_status' => $initial,
            ]);
        }
    }

    private function stateResolver(): ResourceWorkflowStateResolver
    {
        return $this->workflowStates ??= new ResourceWorkflowStateResolver();
    }

    private function locationRepo(): FoundationLocationRepository
    {
        return $this->locations ?? new FoundationLocationRepository();
    }

    private function organizationRepo(): OrganizationRepository
    {
        return $this->organizations ?? new OrganizationRepository();
    }

    private function contactRepo(): ContactRepository
    {
        return $this->contacts ?? new ContactRepository();
    }

    private function allowlist(string $resource): CommonListQueryAllowlist
    {
        return match ($resource) {
            'province' => new CommonListQueryAllowlist(['code', 'name'], ['status', 'code', 'name'], ['code', 'name', 'status']),
            'ward' => new CommonListQueryAllowlist(['code', 'name', 'name_full'], ['province_id', 'code', 'name', 'status'], ['code', 'name', 'name_full', 'province_id', 'status']),
            'organization' => new CommonListQueryAllowlist(['code', 'name', 'legal_name'], ['parent_id', 'type', 'status'], ['code', 'name', 'legal_name', 'parent_id', 'type', 'status']),
            'contact' => new CommonListQueryAllowlist(['name', 'email', 'phone', 'mobile'], ['contact_type', 'contact_ref_id', 'status'], ['name', 'email', 'phone', 'mobile', 'contact_type', 'contact_ref_id', 'status']),
            default => throw new \InvalidArgumentException('Unsupported API-010 resource.'),
        };
    }
}
