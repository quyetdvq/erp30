<?php

declare(strict_types=1);

namespace App\Modules\People\Read;

use App\Modules\Access\Query\AuthorizationSqlScopeCompiler;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use Throwable;
use UnexpectedValueException;

/**
 * M8.6C opt-in SQL-first MASTER People reader. Never uses in-memory paging.
 * Snapshot binds actor/policy decisions, COUNT and page to the same database
 * view. No business writes, and no borrowing caller-owned transactions.
 */
final class PeopleMasterReadService
{
    public function __construct(
        private readonly User $user,
        private readonly BaseConnection $db,
        private readonly DateTimeImmutable $instant,
    ) {}

    /**
     * @param array<string,mixed> $query
     * @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int}
     */
    public function list(string $resource, array $query): array
    {
        $params = PeopleMasterSql::normalize($resource, $query);
        return $this->snapshot(function (UnifiedAccessService $access) use ($resource, $params): array {
            if (!$access->hasBaseViewPermission($resource)) {
                return ['data'=>[], 'page'=>$params['page'], 'page_size'=>$params['page_size'], 'total'=>0];
            }
            $scope = (new AuthorizationSqlScopeCompiler($access))
                ->compile(new PeopleMasterScopeProvider($resource));
            $plan = PeopleMasterSql::collection($resource, $params, $scope, $this->instant,
                (string)$this->db->DBPrefix);
            $result = $this->db->query($plan['count_sql'], $plan['count_binds']);
            if ($result === false) throw new UnexpectedValueException('Scoped People COUNT query failed.');
            $total = self::count($result->getRowArray()['total'] ?? null);
            if ($total === 0) {
                return ['data'=>[], 'page'=>$params['page'], 'page_size'=>$params['page_size'], 'total'=>0];
            }
            $page = $this->db->query($plan['rows_sql'], $plan['rows_binds']);
            if ($page === false) throw new UnexpectedValueException('Scoped People page query failed.');
            $rows = array_values($page->getResultArray());
            if (count($rows) > $params['page_size']) {
                throw new UnexpectedValueException('People SQL response exceeded page size.');
            }
            foreach ($rows as $row) {
                if (!$access->canView($resource, $row)) {
                    // Never post-filter: it would corrupt the authorized COUNT.
                    throw new UnexpectedValueException('AUTH_SCOPE_PBAC_MISMATCH: People page.');
                }
            }
            return ['data'=>$rows, 'page'=>$params['page'], 'page_size'=>$params['page_size'], 'total'=>$total];
        });
    }

    /** @return array<string,mixed>|null */
    public function show(string $resource, string $id): ?array
    {
        return $this->snapshot(function (UnifiedAccessService $access) use ($resource, $id): ?array {
            if (!$access->hasBaseViewPermission($resource)) return null;
            $scope = (new AuthorizationSqlScopeCompiler($access))
                ->compile(new PeopleMasterScopeProvider($resource));
            $plan = PeopleMasterSql::detail($resource, $id, $scope, (string)$this->db->DBPrefix);
            $result = $this->db->query($plan['sql'], $plan['binds']);
            if ($result === false) throw new UnexpectedValueException('Scoped People detail query failed.');
            $row = $result->getRowArray();
            if ($row !== null && !$access->canView($resource, $row)) {
                throw new UnexpectedValueException('AUTH_SCOPE_PBAC_MISMATCH: People detail.');
            }
            return $row;
        });
    }

    private function snapshot(callable $work): mixed
    {
        if ($this->db->transDepth !== 0) {
            throw new UnexpectedValueException('People read cannot borrow caller transaction.');
        }
        if ($this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') === false
            || !$this->db->transBegin()) {
            throw new UnexpectedValueException('Unable to start People read snapshot.');
        }
        try {
            $access = new UnifiedAccessService($this->user, $this->db, $this->instant);
            $value = $work($access);
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new UnexpectedValueException('People read snapshot did not commit.');
            }
            return $value;
        } catch (Throwable $error) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $error;
        }
    }

    private static function count(mixed $raw): int
    {
        $string = is_string($raw) || is_int($raw) ? (string)$raw : '';
        $max = (string)PHP_INT_MAX;
        if (preg_match('/\A[0-9]+\z/D', $string) !== 1
            || strlen($string) > strlen($max)
            || (strlen($string) === strlen($max) && strcmp($string, $max) > 0)) {
            throw new UnexpectedValueException('Invalid People SQL COUNT.');
        }
        return (int)$string;
    }
}
