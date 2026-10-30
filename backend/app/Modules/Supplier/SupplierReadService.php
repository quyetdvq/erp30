<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use App\Modules\Access\Query\AuthorizationSqlScopeCompiler;
use App\Modules\Access\Query\EffectiveRowActionHints;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use UnexpectedValueException;

/**
 * SUP-T03 read-only, SQL-first Supplier boundary (not an HTTP endpoint yet).
 * Shares the canonical transaction-snapshot PBAC pattern with Customer.
 * It never requests or returns bank material; dedicated bank access is T07.
 */
final class SupplierReadService
{
    private readonly BaseConnection $db;
    private readonly ResourceStateRepository $states;
    private readonly DateTimeImmutable $instant;

    public function __construct(private readonly User $actor, ?BaseConnection $connection = null)
    {
        $this->db = $connection ?? db_connect();
        $this->states = new ResourceStateRepository($this->db);
        $this->instant = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** @param array<string,mixed>|null $query @return array<string,mixed> */
    public function list(?array $query): array
    {
        $params = SupplierSql::params($query ?? []);
        return $this->snapshot(function (UnifiedAccessService $access) use ($params): array {
            $page = $params['page'];
            $size = $params['page_size'];
            if (!$access->hasBaseViewPermission('supplier')) {
                return ['data'=>[], 'page'=>$page, 'page_size'=>$size, 'total'=>0];
            }
            $status = $params['filter']['status'] ?? null;
            if ($status !== null && !isset($this->states->definitionFor('supplier')['by_code'][$status])) {
                throw new BadRequestException('Unknown Supplier Lifecycle status.');
            }
            $scope = (new AuthorizationSqlScopeCompiler($access))->compile(new SupplierScopeProvider());
            $groupId = $access->trustedGroupRootId();
            $plan = SupplierSql::collection($params, $scope, $groupId, (string)$this->db->DBPrefix);
            $countQuery = $this->db->query($plan['count_sql'], $plan['count_binds']);
            if ($countQuery === false) throw new UnexpectedValueException('SUP_SCOPED_COUNT_FAILED');
            $total = self::validCount($countQuery->getRowArray()['total'] ?? null);
            if ($total === 0) {
                return ['data'=>[], 'page'=>$page, 'page_size'=>$size, 'total'=>0];
            }
            $rowsQuery = $this->db->query($plan['rows_sql'], $plan['rows_binds']);
            if ($rowsQuery === false) throw new UnexpectedValueException('SUP_SCOPED_PAGE_FAILED');
            $rows = array_values($rowsQuery->getResultArray());
            if (count($rows) > $size) throw new UnexpectedValueException('SUP_SCOPED_PAGE_OVERFLOW');
            $hintsByState = [];
            foreach ($rows as &$row) {
                if (!$access->canView('supplier', $row)) {
                    // A post-LIMIT filter would break COUNT/page parity and leak
                    // information through totals, pagination and empty pages.
                    throw new UnexpectedValueException('SUP_SQL_SCOPE_PBAC_MISMATCH');
                }
                $state = strtoupper(trim((string)$row['status']));
                $hintsByState[$state] ??= EffectiveRowActionHints::forViewedRow($access, 'supplier', $row);
                $row['allowed_actions'] = $hintsByState[$state];
            }
            unset($row);
            return ['data'=>$rows, 'page'=>$page, 'page_size'=>$size, 'total'=>$total];
        });
    }

    /** @return array<string,mixed> */
    public function show(string $id): array
    {
        $id = SupplierSql::positiveId($id);
        return $this->snapshot(function (UnifiedAccessService $access) use ($id): array {
            if (!$access->hasBaseViewPermission('supplier')) {
                throw new PageNotFoundException('Supplier not available.');
            }
            $scope = (new AuthorizationSqlScopeCompiler($access))->compile(new SupplierScopeProvider());
            $groupId = $access->trustedGroupRootId();
            $plan = SupplierSql::detail($id, $scope, $groupId, (string)$this->db->DBPrefix);
            $query = $this->db->query($plan['sql'], $plan['binds']);
            if ($query === false) throw new UnexpectedValueException('SUP_SCOPED_DETAIL_FAILED');
            $row = $query->getRowArray();
            if ($row === null) throw new PageNotFoundException('Supplier not available.');
            if (!$access->canView('supplier', $row)) {
                throw new UnexpectedValueException('SUP_SQL_SCOPE_PBAC_MISMATCH');
            }
            // Read only the target Supplier's usage IDs, never perform per-row
            // queries on the collection endpoint. 513 is a corruption guard
            // beyond the approved 512 maximum, not client-controlled LIMIT.
            $usageQuery = $this->db->table('supplier_company_usages')
                ->select('company_organization_id')->where('supplier_id', $id)
                ->orderBy('company_organization_id', 'ASC')->limit(SupplierRules::MAX_COMPANIES + 1)->get();
            if ($usageQuery === false) throw new UnexpectedValueException('SUP_USAGE_READ_FAILED');
            $usage = array_values($usageQuery->getResultArray());
            if (count($usage) > SupplierRules::MAX_COMPANIES) {
                throw new UnexpectedValueException('SUP_USAGE_LIMIT_BROKEN');
            }
            $mode = (string)($row['all_companies'] ?? '');
            if (!in_array($mode, ['0', '1'], true)
                || ($mode === '1' && $usage !== []) || ($mode === '0' && $usage === [])) {
                throw new UnexpectedValueException('SUP_USAGE_MODE_CORRUPT');
            }
            $row['company_ids'] = array_map('strval', array_column($usage, 'company_organization_id'));
            // Neither the client nor a DB view can use usage as a PBAC grant.
            $row['allowed_actions'] = EffectiveRowActionHints::forViewedRow($access, 'supplier', $row);
            // T08: advisory, VIEW-scoped transition target. A Supplier POST still
            // validates CAS + current PBAC + its canonical Workflow transition.
            // Never route this mutation through generic BusinessWorkflowService.
            $row['setstate_targets'] = [];
            if (($row['allowed_actions']['setstate'] ?? false) === true) {
                try {
                    $row['setstate_targets'][] = $this->states->transitionTarget('supplier', $row, 'setstate');
                } catch (UnexpectedValueException $e) {
                    if ($e->getMessage() !== 'Transition not allowed or ambiguous for current State.') {
                        throw $e;
                    }
                    // No unambiguous configured setstate transition: no UI action.
                }
            }
            return $row;
        });
    }

    /** @template T @param callable(UnifiedAccessService):T $task @return T */
    private function snapshot(callable $task): mixed
    {
        if ($this->db->transDepth !== 0) {
            throw new UnexpectedValueException('SUP_READ_CANNOT_BORROW_WRITE_TRANSACTION');
        }
        if ($this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') === false
            || !$this->db->transBegin()) {
            throw new UnexpectedValueException('SUP_READ_TRANSACTION_FAILED');
        }
        try {
            $access = new UnifiedAccessService($this->actor, $this->db, $this->instant);
            $result = $task($access);
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new UnexpectedValueException('SUP_READ_TRANSACTION_FAILED');
            }
            return $result;
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $e;
        }
    }

    private static function validCount(mixed $value): int
    {
        $raw = is_string($value) || is_int($value) ? (string)$value : '';
        $max = (string)PHP_INT_MAX;
        if (preg_match('/\A[0-9]+\z/D', $raw) !== 1
            || strlen($raw) > strlen($max)
            || (strlen($raw) === strlen($max) && strcmp($raw, $max) > 0)) {
            throw new UnexpectedValueException('SUP_INVALID_SCOPED_COUNT');
        }
        return (int)$raw;
    }
}
