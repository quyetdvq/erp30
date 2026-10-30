<?php

declare(strict_types=1);

namespace App\Modules\History;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use UnexpectedValueException;

/** Deliberate privileged cross-resource audit; never infer access from Customer VIEW. */
final class HistoryMonitorReadService
{
    private readonly BaseConnection $db;

    public function __construct(private readonly User $actor, ?BaseConnection $connection = null)
    {
        $this->db = $connection ?? db_connect();
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function listing(array $query): array
    {
        // Fail closed before any History SELECT or COUNT.
        $this->assertAuthorized();
        $params = HistorySql::monitorParams($query);
        if ($this->db->transDepth !== 0) {
            throw new UnexpectedValueException('HISTORY_MONITOR_READ_CANNOT_BORROW_WRITE');
        }
        if ($this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') === false
            || !$this->db->transBegin()) {
            throw new UnexpectedValueException('HISTORY_MONITOR_TRANSACTION_FAILED');
        }
        try {
            // Repeat the canonical guard within the same consistent read snapshot.
            $this->assertAuthorized();
            $plan = HistorySql::forMonitor($params, (string) $this->db->DBPrefix,
                (string) config('Auth')->tables['users']);
            $countResult = $this->db->query($plan['count_sql'], $plan['count_binds']);
            if ($countResult === false) throw new UnexpectedValueException('HISTORY_MONITOR_COUNT_FAILED');
            $raw = $countResult->getRowArray()['total'] ?? null;
            if ((!is_string($raw) && !is_int($raw)) || preg_match('/\A[0-9]+\z/D', (string)$raw) !== 1) {
                throw new UnexpectedValueException('HISTORY_MONITOR_COUNT_INVALID');
            }
            $total = (int) $raw;
            $rows = [];
            if ($total > 0) {
                $result = $this->db->query($plan['rows_sql'], $plan['rows_binds']);
                if ($result === false) throw new UnexpectedValueException('HISTORY_MONITOR_PAGE_FAILED');
                foreach ($result->getResultArray() as $row) {
                    $rows[] = HistoryEventPresenter::present($row);
                }
            }
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new UnexpectedValueException('HISTORY_MONITOR_TRANSACTION_FAILED');
            }
            return ['data'=>$rows,'total'=>$total,'page'=>$params['page'],
                'page_size'=>$params['page_size'],'from'=>$params['from'],'to'=>$params['to']];
        } catch (Throwable $error) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $error;
        }
    }

    private function assertAuthorized(): void
    {
        $anchor = $this->db->table('resources')
            ->select('id,type,domain,status,lifecycle_id')
            ->where('type','business_history_monitor')->get()->getRowArray();
        if ($anchor === null || ($anchor['domain'] ?? null) !== 'SYSTEM'
            || ($anchor['status'] ?? null) !== 'ACTIVE' || ($anchor['lifecycle_id'] ?? null) !== null) {
            throw new DomainException('HISTORY_MONITOR_ACCESS_DENIED');
        }
        if ($this->db->table('resource_actions')->where('resource_id',$anchor['id'])
            ->where('code','view')->countAllResults() !== 1) {
            throw new DomainException('HISTORY_MONITOR_ACCESS_DENIED');
        }
        $access = new UnifiedAccessService($this->actor,$this->db,
            new DateTimeImmutable('now',new DateTimeZone('UTC')));
        if (!$access->decidePersisted('business_history_monitor','view',$anchor)['allowed']) {
            throw new DomainException('HISTORY_MONITOR_ACCESS_DENIED');
        }
    }
}
