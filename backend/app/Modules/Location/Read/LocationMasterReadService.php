<?php

declare(strict_types=1);

namespace App\Modules\Location\Read;

use App\Modules\Access\Query\AuthorizationSqlScopeCompiler;
use App\Modules\Access\Query\EffectiveRowActionHints;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use Throwable;
use UnexpectedValueException;

/** SQL-first Location read, consistent snapshot for PBAC, COUNT and page. */
final class LocationMasterReadService
{
    public function __construct(
        private readonly User $user,
        private readonly BaseConnection $db,
        private readonly DateTimeImmutable $instant,
    ) {}

    /** @param array<string,mixed> $query @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int} */
    public function list(string $resource, array $query): array
    {
        $params = LocationMasterSql::normalize($resource, $query);
        return $this->snapshot(function (UnifiedAccessService $access) use ($resource, $params): array {
            if (!$access->hasBaseViewPermission($resource)) {
                return ['data'=>[],'page'=>$params['page'],'page_size'=>$params['page_size'],'total'=>0];
            }
            $scope = (new AuthorizationSqlScopeCompiler($access))
                ->compile(new LocationMasterScopeProvider($resource));
            $plan = LocationMasterSql::collection($resource, $params, $scope, (string)$this->db->DBPrefix);
            $count = $this->db->query($plan['count_sql'], $plan['count_binds']);
            if ($count === false) throw new UnexpectedValueException('Location SQL COUNT failed.');
            $total = self::count($count->getRowArray()['total'] ?? null);
            if ($total === 0) return ['data'=>[],'page'=>$params['page'],'page_size'=>$params['page_size'],'total'=>0];
            $result = $this->db->query($plan['rows_sql'], $plan['rows_binds']);
            if ($result === false) throw new UnexpectedValueException('Location SQL page query failed.');
            $rows = array_values($result->getResultArray());
            if (count($rows) > $params['page_size']) throw new UnexpectedValueException('Location page limit exceeded.');
            foreach ($rows as &$row) {
                if (!$access->canView($resource, $row)) {
                    throw new UnexpectedValueException('AUTH_SCOPE_PBAC_MISMATCH: Location page.');
                }
                $row['allowed_actions'] = EffectiveRowActionHints::forViewedRow($access, $resource, $row);
            }
            unset($row);
            return ['data'=>$rows,'page'=>$params['page'],'page_size'=>$params['page_size'],'total'=>$total];
        });
    }

    /** @return array<string,mixed>|null */
    public function show(string $resource, string $id): ?array
    {
        return $this->snapshot(function (UnifiedAccessService $access) use ($resource, $id): ?array {
            if (!$access->hasBaseViewPermission($resource)) return null;
            $scope = (new AuthorizationSqlScopeCompiler($access))
                ->compile(new LocationMasterScopeProvider($resource));
            $plan = LocationMasterSql::detail($resource, $id, $scope, (string)$this->db->DBPrefix);
            $result = $this->db->query($plan['sql'], $plan['binds']);
            if ($result === false) throw new UnexpectedValueException('Location SQL detail failed.');
            $row = $result->getRowArray();
            if ($row !== null && !$access->canView($resource, $row)) {
                throw new UnexpectedValueException('AUTH_SCOPE_PBAC_MISMATCH: Location detail.');
            }
            if ($row !== null) $row['allowed_actions'] = EffectiveRowActionHints::forViewedRow($access, $resource, $row);
            return $row;
        });
    }

    private function snapshot(callable $operation): mixed
    {
        if ($this->db->transDepth !== 0) throw new UnexpectedValueException('Location GET cannot borrow transaction.');
        if ($this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') === false
            || !$this->db->transBegin()) throw new UnexpectedValueException('Location snapshot failed.');
        try {
            $access = new UnifiedAccessService($this->user, $this->db, $this->instant);
            $value = $operation($access);
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new UnexpectedValueException('Location snapshot commit failed.');
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
        $value = is_string($raw) || is_int($raw) ? (string)$raw : '';
        $max = (string)PHP_INT_MAX;
        if (preg_match('/\A[0-9]+\z/D', $value) !== 1
            || strlen($value) > strlen($max)
            || (strlen($value) === strlen($max) && strcmp($value, $max) > 0)) {
            throw new UnexpectedValueException('Invalid Location COUNT.');
        }
        return (int)$value;
    }
}
