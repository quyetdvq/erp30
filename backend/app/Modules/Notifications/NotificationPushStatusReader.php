<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** Bounded, indexed read-only snapshot of one user's recent Import -> Push pipeline. */
final class NotificationPushStatusReader
{
    public function __construct(private readonly BaseConnection $db) {}

    /**
     * @return array{queue_enabled:bool,send_enabled:bool,active_devices:int,
     *   unfinished_outbox:int,unfinished_push:int,jobs:list<array<string,mixed>>,
     *   latest_error_codes:list<array{row_number:int,error_code:string}>}
     */
    public function snapshot(string $userId): array
    {
        foreach (['import_jobs', 'import_job_rows', 'notification_outbox',
            'notification_push_subscriptions', 'notification_push_deliveries', 'settings'] as $table) {
            if (! $this->db->tableExists($table)) {
                throw new RuntimeException('M11_PUSH_STATUS_MISSING_TABLE_' . $table);
            }
        }
        $queueEnabled = NotificationFlags::pushQueueEnabled($this->db);
        $sendEnabled = NotificationFlags::pushSendEnabled($this->db);
        $activeDevices = (int) $this->db->table('notification_push_subscriptions')
            ->where('user_id', $userId)->where('enabled', 1)->countAllResults();
        $unfinishedOutbox = (int) $this->db->table('notification_outbox')
            ->whereIn('status', ['PENDING', 'RETRY', 'LEASED'])->countAllResults();
        $unfinishedPush = (int) $this->db->table('notification_push_deliveries')
            ->whereIn('status', ['PENDING', 'RETRY', 'LEASED'])->countAllResults();
        $jobs = $this->db->table('import_jobs')
            ->select('id,status,success_rows,error_rows,completed_at')
            ->where('created_by', $userId)->orderBy('id', 'DESC')->limit(5)->get()->getResultArray();

        $outboxes = [];
        $receiptsByKey = [];
        if ($jobs !== []) {
            $keys = array_map(static fn (array $job): string =>
                'import-job:' . $job['id'] . ':terminal', $jobs);
            foreach ($this->db->table('notification_outbox')
                ->select('event_key,event_type,status')->whereIn('event_key', $keys)
                ->get()->getResultArray() as $row) {
                $outboxes[(string) $row['event_key']] = $row;
            }
            foreach ($this->db->table('notification_push_deliveries')
                ->select('event_key,status,COUNT(*) AS total', false)
                ->where('recipient_user_id', $userId)->whereIn('event_key', $keys)
                ->groupBy(['event_key', 'status'])->get()->getResultArray() as $row) {
                $receiptsByKey[(string) $row['event_key']][(string) $row['status']] = (int) $row['total'];
            }
        }
        $resultJobs = [];
        foreach ($jobs as $job) {
            $eventKey = 'import-job:' . $job['id'] . ':terminal';
            $outbox = $outboxes[$eventKey] ?? null;
            $resultJobs[] = [
                'id' => (string) $job['id'],
                'status' => (string) $job['status'],
                'success_rows' => (int) $job['success_rows'],
                'error_rows' => (int) $job['error_rows'],
                'outbox_status' => $outbox['status'] ?? 'NOT_FOUND',
                'event_type' => $outbox['event_type'] ?? 'NOT_FOUND',
                'receipts' => $receiptsByKey[$eventKey] ?? [],
            ];
        }
        $latestErrorCodes = [];
        if ($jobs !== [] && (string) $jobs[0]['status'] !== 'COMPLETED') {
            $errors = $this->db->table('import_job_rows')->select('row_number,error_code')
                ->where('import_job_id', (string) $jobs[0]['id'])
                ->where('error_code IS NOT NULL', null, false)
                ->orderBy('row_number', 'ASC')->limit(3)->get()->getResultArray();
            foreach ($errors as $error) {
                $code = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) $error['error_code']);
                $latestErrorCodes[] = [
                    'row_number' => (int) $error['row_number'],
                    'error_code' => substr((string) $code, 0, 80),
                ];
            }
        }
        return [
            'queue_enabled' => $queueEnabled,
            'send_enabled' => $sendEnabled,
            'active_devices' => $activeDevices,
            'unfinished_outbox' => $unfinishedOutbox,
            'unfinished_push' => $unfinishedPush,
            'jobs' => $resultJobs,
            'latest_error_codes' => $latestErrorCodes,
        ];
    }
}
