<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Compatibility;

use App\Authorization\AuthorizationService;
use App\Authorization\PolicyValidator;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Repositories\PolicyRepository;
use App\Modules\Authorization\Administration\Policies\Application\PolicyAdministrationService as CanonicalPolicyAdministrationService;
use CodeIgniter\Database\ConnectionInterface;

/** Legacy Policy administration service; canonical application owns all mutations. */
final class PolicyAdministrationService
{
    public const ERROR_NOT_FOUND = CanonicalPolicyAdministrationService::ERROR_NOT_FOUND;
    public const ERROR_ACTIONS_REQUIRED = CanonicalPolicyAdministrationService::ERROR_ACTIONS_REQUIRED;
    public const ERROR_DENIED = CanonicalPolicyAdministrationService::ERROR_DENIED;

    private readonly CanonicalPolicyAdministrationService $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?PolicyRepository $repository = null,
        ?PolicyValidator $validator = null,
        ?AuthorizationService $authorization = null,
        ?ConnectionInterface $db = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
    ) {
        $this->delegate = new CanonicalPolicyAdministrationService($repository, $validator, $authorization, $db, $mutationContext, $clock);
    }

    /** @param array<string, mixed> $policy
     *  @param list<array<string, mixed>> $actions
     */
    public function create(array $policy, array $actions, ?OperationContext $operation = null): int
    {
        return $this->delegate->create($policy, $actions, $operation);
    }

    /** @param array<string, mixed> $policy
     *  @param list<array<string, mixed>>|null $actions
     */
    public function update(int $id, array $policy, ?array $actions = null, ?OperationContext $operation = null): bool
    {
        return $this->delegate->update($id, $policy, $actions, $operation);
    }

    public function delete(int $id, ?OperationContext $operation = null): bool
    {
        return $this->delegate->delete($id, $operation);
    }
}
