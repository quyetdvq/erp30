<?php

declare(strict_types=1);

namespace App\Modules\History;

use App\Modules\Access\UnifiedAccessService;
use App\Modules\History\Ports\HistoryParentViewPort;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use UnexpectedValueException;

/**
 * Both GROUP MASTER parent resources have independently owned VIEW adapters.
 * Same snapshot covers parent PBAC and history COUNT/ROWS (no TOCTOU leak).
 */
final class HistoryReadService
{
    private readonly BaseConnection $db;

    public function __construct(private readonly User $actor,
        private readonly HistoryParentViewPort $parent, ?BaseConnection $connection = null)
    {
        $this->db = $connection ?? db_connect();
    }

    /** @param array<string,mixed>|null $query @return array<string,mixed> */
    public function customer(string $id, ?array $query): array
    {
        return $this->page('customer', $id, $query);
    }

    /** @param array<string,mixed>|null $query @return array<string,mixed> */
    public function supplier(string $id, ?array $query): array
    {
        return $this->page('supplier', $id, $query);
    }

    /** @param array<string,mixed>|null $query @return array<string,mixed> */
    private function page(string $resource, string $id, ?array $query): array
    {
        if (!in_array($resource, ['customer', 'supplier'], true)) {
            throw new UnexpectedValueException('HISTORY_PARENT_RESOURCE_UNSUPPORTED');
        }
        if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1 || strlen($id) > 20) {
            throw new \CodeIgniter\HTTP\Exceptions\BadRequestException('Invalid history parent ID.');
        }
        $params = HistorySql::params($query ?? []);
        if ($this->db->transDepth !== 0) {
            throw new UnexpectedValueException('HISTORY_READ_CANNOT_BORROW_WRITE');
        }
        if ($this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') === false
            || !$this->db->transBegin()) {
            throw new UnexpectedValueException('HISTORY_READ_TRANSACTION_FAILED');
        }
        try {
            $access = new UnifiedAccessService(
                $this->actor, $this->db, new DateTimeImmutable('now', new DateTimeZone('UTC'))
            );
            $this->parent->assertVisible($resource, $id, $access, $this->db);
            $plan = HistorySql::forVisibleParent($resource, $id, $params, (string)$this->db->DBPrefix, (string) config('Auth')->tables['users']);
            $countResult = $this->db->query($plan['count_sql'], $plan['count_binds']);
            if ($countResult === false) throw new UnexpectedValueException('HISTORY_COUNT_SQL_FAILED');
            $totalRaw = $countResult->getRowArray()['total'] ?? null;
            if ((!is_string($totalRaw) && !is_int($totalRaw))
                || preg_match('/\A[0-9]+\z/D', (string)$totalRaw) !== 1) {
                throw new UnexpectedValueException('HISTORY_COUNT_INVALID');
            }
            $total = (int)$totalRaw;
            $rows = [];
            if ($total > 0) {
                $pageResult = $this->db->query($plan['rows_sql'], $plan['rows_binds']);
                if ($pageResult === false) throw new UnexpectedValueException('HISTORY_ROWS_SQL_FAILED');
                foreach ($pageResult->getResultArray() as $row) {
                    $rows[] = HistoryEventPresenter::present($row);
                }
            }
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new UnexpectedValueException('HISTORY_READ_TRANSACTION_FAILED');
            }
            return ['data'=>$rows,'total'=>$total,'page'=>$params['page'], 'page_size'=>$params['page_size']];
        } catch (Throwable $cause) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $cause;
        }
    }
}
