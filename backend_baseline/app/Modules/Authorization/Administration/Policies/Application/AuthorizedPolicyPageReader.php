<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Administration\Policies\Application;

use App\Exceptions\DomainException;
use App\Infrastructure\Api\CommonListQuery;
use App\Infrastructure\Api\CommonListResult;
use App\Modules\Authorization\Administration\Policies\Application\Ports\PolicyPageReadRepositoryPort;

/**
 * Read-only Policy page optimization. The API caller keeps sole PBAC authority:
 * it supplies the same bucket authorization/visibility predicates as the
 * row-by-row fallback, within the same read-only snapshot and operation.
 */
final class AuthorizedPolicyPageReader
{
    public function __construct(
        private readonly PolicyPageReadRepositoryPort $policies,
    ) {
    }

    /**
     * @param callable(array{id:int,organization_id:int,status:string}):void $authorizeBucket
     * @param callable(DomainException):bool $isVisibilityDenial
     */
    public function tryList(
        CommonListQuery $parsed,
        callable $authorizeBucket,
        callable $isVisibilityDenial,
    ): ?CommonListResult {
        if (! $this->policies->supportsSqlPolicyList($parsed)) {
            return null;
        }
        $buckets = $this->policies->findPolicyVisibilityBuckets();
        if ($buckets === null) {
            return null;
        }
        $allowed = [];
        foreach ($buckets as $bucket) {
            try {
                $authorizeBucket($bucket);
                $allowed[] = $bucket;
            } catch (DomainException $exception) {
                if (! $isVisibilityDenial($exception)) {
                    throw $exception;
                }
            }
        }
        // The SQL repository uses these same approved buckets for COUNT and page.
        $page = $this->policies->findAuthorizedPolicyPage($parsed, $allowed);
        return new CommonListResult(
            $this->attachActions($page['rows']),
            $parsed,
            (string) $page['total'],
        );
    }

    /**
     * Only hydrate Policy Action relations for rows already authorized and
     * selected for the current page (never scan actions before pagination).
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public function attachActions(array $rows): array
    {
        $ids = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['id'],
            $rows,
        )));
        $byPolicy = [];
        foreach ($this->policies->findAllPolicyActionsForPolicies($ids) as $action) {
            $byPolicy[(int) $action['policy_id']][] = $action;
        }
        foreach ($rows as &$row) {
            $row['actions'] = $byPolicy[(int) $row['id']] ?? [];
        }
        unset($row);

        return $rows;
    }
}
