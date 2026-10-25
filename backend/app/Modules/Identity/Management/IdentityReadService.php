<?php

declare(strict_types=1);

namespace App\Modules\Identity\Management;

use App\Modules\Access\UnifiedAccessService;
use App\Modules\Access\Query\EffectiveRowActionHints;
use App\Shared\Http\AuthorizedListPage;
use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;

/** Shield User reads; target existence and counts remain PBAC-filtered. */
final class IdentityReadService
{
    private readonly IdentityReadRepository $repository;
    private readonly UnifiedAccessService $access;

    public function __construct(User $actor, ?BaseConnection $db = null)
    {
        $connection = $db ?? db_connect();
        $this->repository = new IdentityReadRepository($connection);
        $this->access = new UnifiedAccessService($actor, $connection);
    }

    /** @param array<string,mixed> $query
     *  @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int}
     */
    public function securityUsers(array $query): array
    {
        AuthorizedListPage::paginate('security_user', [], $query);
        $visible = [];
        foreach ($this->repository->securityUsers() as $row) {
            if ($this->access->canView('user', $row)) {
                $visible[] = $row;
            }
        }
        $page = AuthorizedListPage::paginate('security_user', $visible, $query);
        foreach ($page['data'] as &$row) {
            $row['allowed_actions'] = EffectiveRowActionHints::forViewedRow($this->access, 'user', $row);
        }
        unset($row);
        return $page;
    }

    /** Direct grants are NOT inherited group permissions.
     *  @return array{permissions:list<string>}|null
     */
    public function userPermissions(int|string $id): ?array
    {
        try {
            $key = PositiveId::value($id);
        } catch (InvalidArgumentException) {
            return null;
        }
        $target = $this->repository->userPermissionTarget($key);
        if ($target === null || ! $this->access->canView('user', $target)) {
            return null;
        }
        return ['permissions' => $this->repository->directPermissionTokens($key)];
    }
}
