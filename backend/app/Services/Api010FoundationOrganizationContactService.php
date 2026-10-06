<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListResult;
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
    public function __construct(
        private readonly ?CommonListQueryAdapter $queryAdapter = null,
        private readonly ?FoundationLocationRepository $locations = null,
        private readonly ?OrganizationRepository $organizations = null,
        private readonly ?ContactRepository $contacts = null,
        private ?AuthorizationService $authorization = null,
    ) {
    }

    /** @param array<string, mixed> $query */
    public function list(string $resource, array $query): CommonListResult
    {
        $operation = $this->operation();
        $rows = $this->authorizedRows($resource, $operation);
        $allowlist = $this->allowlist($resource);

        return ($this->queryAdapter ?? new CommonListQueryAdapter())->paginate(
            $query,
            $allowlist,
            new AuthorizedArrayListSource($rows),
        );
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
     * @param array<string, mixed> $target
     * @param array<string, mixed> $input
     */
    private function authorize(OperationContext $operation, string $resource, string $action, array $target, array $input): void
    {
        $decision = $this->auth()->authorize(
            $operation,
            $resource,
            $action,
            new EvaluationOrganizationRequest(
                persistedTarget: $target === [] ? null : $target,
                input: $input,
            ),
        );
        if (! $decision->isAllowed()) {
            throw new DomainException(AuthorizationService::REASON_POLICY_DENIED);
        }
    }

    /** @param array<string, mixed> $payload */
    private function createProvince(array $payload, OperationContext $operation): int
    {
        $this->authorize($operation, 'province', 'create', [], $payload);

        return $this->locationService($operation)->createProvince($payload);
    }

    /** @param array<string, mixed> $payload */
    private function createWard(array $payload, OperationContext $operation): int
    {
        $this->authorize($operation, 'ward', 'create', [], $payload);

        return $this->locationService($operation)->createWard($payload);
    }

    /** @param array<string, mixed> $payload */
    private function createOrganization(array $payload, OperationContext $operation): int
    {
        $this->authorize($operation, 'organization', 'create', [], $payload);

        return $this->organizationService($operation)->create($payload);
    }

    /** @param array<string, mixed> $payload */
    private function createContact(array $payload, OperationContext $operation): int
    {
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
