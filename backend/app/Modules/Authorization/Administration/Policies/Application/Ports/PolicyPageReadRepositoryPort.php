<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Administration\Policies\Application\Ports;

use App\Infrastructure\Api\CommonListQuery;

/** SQL page capabilities, separate from Policy administration mutation port. */
interface PolicyPageReadRepositoryPort
{
    public function supportsSqlPolicyList(CommonListQuery $query): bool;

    /** @return list<array{id:int,organization_id:int,status:string}>|null */
    public function findPolicyVisibilityBuckets(int $maxBuckets = 64): ?array;

    /** @param list<array{id:int,organization_id:int,status:string}> $allowedBuckets
     *  @return array{total:int,rows:list<array<string,mixed>>}
     */
    public function findAuthorizedPolicyPage(CommonListQuery $query, array $allowedBuckets): array;

    /** @param list<int> $policyIds
     *  @return list<array<string,mixed>>
     */
    public function findAllPolicyActionsForPolicies(array $policyIds): array;
}
