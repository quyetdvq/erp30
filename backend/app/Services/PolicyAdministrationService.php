<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\PolicyParticipantValidator;
use App\Authorization\PolicyValidator;
use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Repositories\PolicyRepository;
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
        private readonly ?PolicyRepository $repository = null,
        private readonly ?PolicyValidator $validator = null,
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
        unset($candidate['id']);
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
        $decision = $this->auth()->authorize($operation, 'policy', $action, $request, $policy['state_id'] ?? null);
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

    private function repo(): PolicyRepository
    {
        return $this->repository ?? new PolicyRepository($this->db);
    }

    private function validator(): PolicyValidator
    {
        return $this->validator ?? new PolicyValidator($this->repo());
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
