<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Management;

use App\Modules\Access\UnifiedAccessService;
use App\Modules\Access\Query\EffectiveRowActionHints;
use App\Shared\Http\AuthorizedListPage;
use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;

/** Workflow-owned list/detail/relations with the central persisted PBAC gate. */
final class WorkflowReadService
{
    private readonly WorkflowReadRepository $repository;
    private readonly UnifiedAccessService $access;

    public function __construct(User $actor, ?BaseConnection $db = null)
    {
        $connection = $db ?? db_connect();
        $this->repository = new WorkflowReadRepository($connection);
        $this->access = new UnifiedAccessService($actor, $connection);
    }

    /** @param array<string,mixed> $query
     *  @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int}
     */
    public function listing(string $type, array $query): array
    {
        WorkflowReadContract::table($type);
        // M3.3 AccessReadContract rejected include=... on Workflow lists with
        // this exact exception; keep the pre-refactor error mapping stable.
        if (array_key_exists('include', $query)) {
            throw new BadRequestException('Malformed list query: include.');
        }
        // Validate query BEFORE scanning, filtering or leaking existence/counts.
        AuthorizedListPage::paginate($type, [], $query);
        $visible = [];
        foreach ($this->repository->all($type) as $row) {
            if ($this->access->canView($type, $row)) {
                $visible[] = $row;
            }
        }
        $page = AuthorizedListPage::paginate($type, $visible, $query);
        foreach ($page['data'] as &$row) {
            $row['allowed_actions'] = EffectiveRowActionHints::forViewedRow($this->access, $type, $row);
        }
        unset($row);
        return $page;
    }

    /** @return array<string,mixed>|null */
    public function detail(string $type, int|string $id): ?array
    {
        WorkflowReadContract::table($type);
        try {
            $key = PositiveId::value($id);
        } catch (InvalidArgumentException) {
            return null;
        }
        $row = $this->repository->one($type, $key);
        if ($row === null || !$this->access->canView($type, $row)) return null;
        $row['allowed_actions'] = EffectiveRowActionHints::forViewedRow($this->access, $type, $row);
        return $row;
    }

    /** @return list<array<string,mixed>>|null */
    public function relation(string $type, string $relation, int|string $id): ?array
    {
        if ($type !== 'lifecycle' || ! in_array($relation, ['states','transitions'], true)) {
            throw new InvalidArgumentException('Invalid Workflow relation.');
        }
        $parent = $this->detail($type, $id);
        return $parent === null ? null : $this->repository->related($relation, $parent);
    }
}
