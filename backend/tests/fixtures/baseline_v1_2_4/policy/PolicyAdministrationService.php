<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Administration\Policies\Application;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\PolicyParticipantValidator;
use App\Authorization\PolicyValidator as LegacyPolicyValidator;
use App\Modules\Authorization\Administration\Policies\Validation\PolicyValidator as CanonicalPolicyValidator;
use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Authorization\Administration\Policies\Application\Ports\PolicyAdministrationRepositoryPort;
use App\Modules\Authorization\Administration\Policies\Infrastructure\Persistence\SqlPolicyRepository;
use App\Repositories\ResourceCatalogRepository;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Database\ConnectionInterface;

/** Authorized administration of persisted PBAC Policy rows and Actions. */
final class PolicyAdministrationService
{
    public const ERROR_NOT_FOUND = 'POLICY_NOT_FOUND';
    public const ERROR_ACTIONS_REQUIRED = 'POLICY_ACTIONS_REQUIRED';
    public const ERROR_DENIED = 'POLICY_AUTHORIZATION_DENIED';

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?PolicyAdministrationRepositoryPort $repository = null,
        private readonly ?LegacyPolicyValidator $validator = null,
        private readonly ?AuthorizationService $authorization = null,
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $policy
     *  @param list<array<string, mixed>> $actions
     */
    public function create(array $policy, array $actions, ?OperationContext $operation = null): int
    {
        $operation ??= $this->operation();
        if ($actions === []) {
            throw new DomainException(self::ERROR_ACTIONS_REQUIRED);
        }
        $this->assertPolicyShape($policy);
        $this->authorize($operation, 'create', null, $policy);
        $validatedPolicy = $this->validator()->validatePolicy($policy);
        $validatedActions = $this->validator()->validateActions($actions, (int) $validatedPolicy['resource_id']);
        $this->repo()->assertScopeAvailable($validatedPolicy);

        return $this->repo()->insertWithActions($validatedPolicy, $validatedActions, $operation);
    }

    /** @param array<string, mixed> $policy
     *  @param list<array<string, mixed>>|null $actions
     */
    public function update(int $id, array $policy, ?array $actions = null, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        $current = $this->existing($id);
        $candidate = array_merge($current, $policy);
        unset($candidate['id'], $candidate['status']);
        $this->authorize($operation, 'edit', $current, $candidate);
        $validatedPolicy = $this->validator()->validatePolicy($candidate);
        $validatedActions = $actions === null
            ? null
            : $this->validator()->validateActions($actions, (int) $validatedPolicy['resource_id']);
        if ($validatedActions !== null && $validatedActions === []) {
            throw new DomainException(self::ERROR_ACTIONS_REQUIRED);
        }
        $this->repo()->assertScopeAvailable($validatedPolicy, $id);
        if ($validatedActions === null) {
            return $this->repo()->update($id, $validatedPolicy, $operation);
        }

        return $this->repo()->replaceWithActions($id, $validatedPolicy, $validatedActions, $operation);
    }

    public function delete(int $id, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        $current = $this->existing($id);
        $this->authorize($operation, 'delete', $current, $current);

        return $this->repo()->deleteWithActions($id, $operation);
    }

    /** @param array<string, mixed> $policy
     *  @param array<string, mixed>|null $target
     */
    private function authorize(OperationContext $operation, string $action, ?array $target, array $policy): void
    {
        $request = new EvaluationOrganizationRequest(
            persistedTarget: $target,
            input: $policy,
        );
        $decision = $this->auth()->authorize($operation, 'policy', $action, $request);
        if (! $decision->isAllowed()) {
            throw new DomainException(self::ERROR_DENIED, ['action' => $action]);
        }
    }

    /** @return array<string, mixed> */
    private function existing(int $id): array
    {
        $normalized = PositiveId::normalize($id, 'policy_id', self::ERROR_NOT_FOUND);
        $row = $this->repo()->find($normalized);
        if ($row === null) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['policy_id' => $normalized]);
        }

        return $row;
    }

    private function repo(): PolicyAdministrationRepositoryPort
    {
        return $this->repository ?? new SqlPolicyRepository($this->db);
    }

    private function validator(): LegacyPolicyValidator|CanonicalPolicyValidator
    {
        return $this->validator ?? new CanonicalPolicyValidator($this->repo(), null, new ResourceCatalogRepository($this->db), new ResourceWorkflowStateResolver($this->db));
    }

    private function auth(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService(db: $this->db);
    }

    private function operation(): OperationContext
    {
        return ($this->mutationContext ?? MutationContext::system($this->clock ?? service('clock')))->begin();
    }

    /** @param array<string, mixed> $policy */
    private function assertPolicyShape(array $policy): void
    {
        foreach (['organization_id', 'resource_id', 'participant_type', 'participant_ref_id'] as $field) {
            if (! array_key_exists($field, $policy)) {
                throw new DomainException(PolicyParticipantValidator::ERROR_INVALID_PARTICIPANT, ['field' => $field]);
            }
        }
    }
}
