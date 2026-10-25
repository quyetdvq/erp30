<?php

declare(strict_types=1);

namespace App\Modules\Organization\Read;

use App\Modules\Access\Query\AuthorizationSqlScopeCompiler;
use App\Modules\Access\Query\EffectiveRowActionHints;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use Throwable;
use UnexpectedValueException;

/**
 * Opt-in SQL-first, read-only Organization/Employee Assignment list/detail.
 * Activation is guarded by ReadCatalogController (M3.2 enforce mode required).
 * Every read uses a single repeatable-read snapshot across policy, COUNT, page.
 */
final class OrganizationPeopleReadService
{
    public function __construct(
        private readonly User $user,
        private readonly BaseConnection $db,
        private readonly DateTimeImmutable $instant,
    ) {}

    /** @param array<string,mixed> $query @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int} */
    public function list(string $resource, array $query): array
    {
        $params = OrganizationPeopleSql::normalize($resource, $query);
        return $this->snapshot(function(UnifiedAccessService $access) use($resource,$params): array {
            if (!$access->hasBaseViewPermission($resource)) {
                return ['data'=>[], 'page'=>$params['page'], 'page_size'=>$params['page_size'], 'total'=>0];
            }
            $scope = (new AuthorizationSqlScopeCompiler($access))
                ->compile(new OrganizationPeopleScopeProvider($resource, $this->db));
            $plan = OrganizationPeopleSql::collection($resource, $params, $scope, (string)$this->db->DBPrefix);
            $countQuery = $this->db->query($plan['count_sql'], $plan['count_binds']);
            if ($countQuery === false) throw new UnexpectedValueException('Scoped Organization/People COUNT query failed.');
            $total = self::validCount($countQuery->getRowArray()['total'] ?? null);
            if ($total === 0) return ['data'=>[], 'page'=>$params['page'], 'page_size'=>$params['page_size'], 'total'=>0];
            $pageQuery = $this->db->query($plan['rows_sql'], $plan['rows_binds']);
            if ($pageQuery === false) throw new UnexpectedValueException('Scoped Organization/People page query failed.');
            $data = array_values($pageQuery->getResultArray());
            if (count($data) > $params['page_size']) {
                throw new UnexpectedValueException('Organization/People query exceeded page size.');
            }
            foreach ($data as &$row) {
                if (!$access->canView($resource, $row)) {
                    // Do not post-filter: doing so corrupts authorized totals.
                    throw new UnexpectedValueException('AUTH_SCOPE_PBAC_MISMATCH: Organization/People page.');
                }
                $row['allowed_actions'] = EffectiveRowActionHints::forViewedRow($access, $resource, $row);
                if ($resource === 'employee_assignment') {
                    $row['is_primary'] = (bool)((int)($row['is_primary'] ?? 0));
                }
            }
            unset($row);
            return ['data'=>$data, 'page'=>$params['page'], 'page_size'=>$params['page_size'], 'total'=>$total];
        });
    }

    /** @return array<string,mixed>|null */
    public function show(string $resource, string $id): ?array
    {
        return $this->snapshot(function(UnifiedAccessService $access) use($resource,$id): ?array {
            if (!$access->hasBaseViewPermission($resource)) return null;
            $scope = (new AuthorizationSqlScopeCompiler($access))
                ->compile(new OrganizationPeopleScopeProvider($resource, $this->db));
            $plan = OrganizationPeopleSql::detail($resource, $id, $scope, (string)$this->db->DBPrefix);
            $result = $this->db->query($plan['sql'], $plan['binds']);
            if ($result === false) throw new UnexpectedValueException('Scoped Organization/People detail query failed.');
            $row = $result->getRowArray();
            if ($row === null) return null;
            if (!$access->canView($resource, $row)) {
                throw new UnexpectedValueException('AUTH_SCOPE_PBAC_MISMATCH: Organization/People detail.');
            }
            $row['allowed_actions'] = EffectiveRowActionHints::forViewedRow($access, $resource, $row);
            if ($resource === 'employee_assignment') $row['is_primary'] = (bool)((int)($row['is_primary'] ?? 0));
            return $row;
        });
    }

    /** @param list<string> $ids @return list<array{id:string,code:string,name:string,type:string}> */
    public function organizationLabels(array $ids): array
    {
        if ($ids === []) return [];
        return $this->snapshot(function(UnifiedAccessService $access) use($ids): array {
            if (!$access->hasBaseViewPermission('organization')) return [];
            $scope = (new AuthorizationSqlScopeCompiler($access))
                ->compile(new OrganizationPeopleScopeProvider('organization', $this->db));
            $plan = OrganizationPeopleSql::labels($ids, $scope, (string)$this->db->DBPrefix);
            $result = $this->db->query($plan['sql'], $plan['binds']);
            if ($result === false) throw new UnexpectedValueException('Scoped organization labels query failed.');
            $found = [];
            foreach ($result->getResultArray() as $row) {
                if (!$access->canView('organization', $row)) {
                    throw new UnexpectedValueException('AUTH_SCOPE_PBAC_MISMATCH: Organization labels.');
                }
                $found[(string)$row['id']] = [
                    'id'=>(string)$row['id'], 'code'=>(string)$row['code'],
                    'name'=>(string)$row['name'], 'type'=>(string)$row['type'],
                ];
            }
            $out = [];
            foreach ($ids as $id) if (isset($found[$id])) $out[] = $found[$id];
            return $out;
        });
    }

    private function snapshot(callable $work): mixed
    {
        if ($this->db->transDepth !== 0) {
            throw new UnexpectedValueException('Organization/People read must not borrow a caller transaction.');
        }
        if ($this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') === false
            || !$this->db->transBegin()) {
            throw new UnexpectedValueException('Unable to start Organization/People read snapshot.');
        }
        try {
            $access = new UnifiedAccessService($this->user, $this->db, $this->instant);
            $data = $work($access);
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new UnexpectedValueException('Organization/People read snapshot not committed.');
            }
            return $data;
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $e;
        }
    }

    private static function validCount(mixed $raw): int
    {
        $text = is_string($raw) || is_int($raw) ? (string)$raw : '';
        $limit = (string)PHP_INT_MAX;
        if (preg_match('/\A[0-9]+\z/D', $text) !== 1 || strlen($text) > strlen($limit)
            || (strlen($text) === strlen($limit) && strcmp($text,$limit)>0)) {
            throw new UnexpectedValueException('Invalid Organization/People SQL total.');
        }
        return (int)$text;
    }
}
