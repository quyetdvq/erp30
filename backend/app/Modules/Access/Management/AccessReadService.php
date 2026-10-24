<?php

declare(strict_types=1);

namespace App\Modules\Access\Management;

use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\UnifiedAccessService;
use App\Shared\Http\AuthorizedListPage;
use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;

/**
 * Protected P1 API-030 read use cases. No writes and no implicit permission
 * granted when a catalog entry/action/Lifecycle is missing.
 */
final class AccessReadService
{
    private AccessReadRepository $repository;
    private UnifiedAccessService $access;
    private ResourceStateRepository $states;

    public function __construct(User $actor, ?BaseConnection $db = null)
    {
        $connection = $db ?? db_connect();
        $this->repository = new AccessReadRepository($connection);
        $this->access = new UnifiedAccessService($actor, $connection);
        $this->states = new ResourceStateRepository($connection);
    }

    /**
     * @param array<string,mixed> $query
     * @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int}
     */
    public function listing(string $type, array $query): array
    {
        AccessReadContract::table($type);
        $withActions = AccessReadContract::consumeInclude($type, $query);
        // Validate all fields/filters/sorts BEFORE fetching rows or revealing counts.
        AuthorizedListPage::paginate($type, [], $this->withoutLogDates($type, $query));
        $visible = [];
        foreach ($this->repository->all($type) as $row) {
            // PBAC uses persisted state and authoritative Organization scope.
            if ($this->access->canView($type, $row)) {
                $visible[] = $row;
            }
        }
        $query = AccessReadContract::consumeLogDateFilters($type, $query, $visible);
        $page = AuthorizedListPage::paginate($type, $visible, $query);
        if ($type === 'policy' || $withActions) {
            $page['data'] = AccessReadContract::attachPageActions(
                $type, $page['data'], $this->repository->pageActions($type, $page['data']),
            );
        }
        return $page;
    }

    /** @return array<string,mixed>|null Returns null both for missing and invisible rows. */
    public function detail(string $type, int|string $id): ?array
    {
        AccessReadContract::table($type);
        try {
            $key = PositiveId::value($id);
        } catch (InvalidArgumentException) {
            return null;
        }
        $row = $this->repository->one($type, $key);
        if ($row === null || ! $this->access->canView($type, $row)) {
            return null;
        }
        if ($type === 'resource') {
            // Detail's state codes + actions match ResourceAdministrationService.
            $row['policy_state_codes'] = array_values(array_map(
                static fn (array $option): string => $option['code'],
                array_filter($this->states->policyStateOptions((string) $row['type']),
                    static fn (array $option): bool => $option['state_id'] !== null),
            ));
            $row['actions'] = $this->repository->related('actions', $row);
        } elseif ($type === 'policy') {
            $row['actions'] = $this->repository->pageActions('policy', [$row]);
        }
        if ($type === 'policy') {
            $row['actions'] = AccessReadContract::attachPageActions('policy', [$row], $row['actions'])[0]['actions'];
        }
        return $row;
    }

    /** @return list<array<string,mixed>>|null */
    public function relation(string $type, string $relation, int|string $parentId): ?array
    {
        $allowed = [
            'authorization_group' => ['members','permissions'],
            'resource' => ['actions'],
        ];
        if (! in_array($relation, $allowed[$type] ?? [], true)) {
            throw new InvalidArgumentException('Invalid Access relation.');
        }
        $parent = $this->detail($type, $parentId);
        return $parent === null ? null : $this->repository->related($relation, $parent);
    }

    /**
     * Drop only the two baseline log date-range extensions for preflight
     * validation. All other arbitrary keys must still be rejected.
     * @param array<string,mixed> $query @return array<string,mixed>
     */
    private function withoutLogDates(string $type, array $query): array
    {
        if ($type === 'authorization_log' && isset($query['filter']) && is_array($query['filter'])) {
            unset($query['filter']['created_from'], $query['filter']['created_to']);
            if ($query['filter'] === []) {
                unset($query['filter']);
            }
        }
        return $query;
    }
}
