<?php

declare(strict_types=1);

use CodeIgniter\Database\BaseConnection;

require_once __DIR__.'/M92Plan.php';
require_once __DIR__.'/M92JournalRules.php';

/** Read-only, bounded consistent-snapshot Product Import journal audit. */
final class M92DbAudit
{
    public function __construct(private readonly BaseConnection $db) {}

    /** @param array{database:string,max_jobs:int,max_rows:int} $options
     *  @return array<string,mixed>
     */
    public function run(array $options): array
    {
        $connected = (string)($this->row('SELECT DATABASE() AS db')['db'] ?? '');
        M92Plan::databaseMatches($connected, $options['database']);
        $prefix = (string)$this->db->DBPrefix;
        if ($prefix !== '' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $prefix) !== 1) {
            throw new RuntimeException('Unsafe DB prefix.');
        }
        $jobs = '`'.$prefix.'import_jobs`';
        $rows = '`'.$prefix.'import_job_rows`';
        $engine = $this->all('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?,?)',
            [$prefix.'import_jobs',$prefix.'import_job_rows']);
        $found = [];
        foreach ($engine as $entry) $found[(string)$entry['TABLE_NAME']] = strtoupper((string)$entry['ENGINE']);
        if (($found[$prefix.'import_jobs'] ?? null) !== 'INNODB'
            || ($found[$prefix.'import_job_rows'] ?? null) !== 'INNODB') {
            throw new RuntimeException('Import journal tables must both exist and use InnoDB.');
        }
        $isolation = strtoupper((string)($this->row('SELECT @@tx_isolation AS isolation')['isolation'] ?? ''));
        if ($isolation !== 'REPEATABLE-READ') {
            throw new RuntimeException('M9.2 snapshot requires REPEATABLE-READ (no session modification).');
        }
        // MariaDB rejects DML inside READ ONLY; a consistent snapshot prevents
        // false count mismatches while another import is writing rows.
        $begun = false;
        try {
            $this->sql('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
            $begun = true;
            $jobCount = $this->count('SELECT COUNT(*) AS n FROM '.$jobs.' WHERE entity_type = ?', ['PRODUCT']);
            if ($jobCount > $options['max_jobs']) {
                return $this->bound('JOBS_LIMIT_EXCEEDED', $jobCount, $options);
            }
            $rowCount = $this->count('SELECT COUNT(*) AS n FROM '.$rows.' r JOIN '.$jobs.' j ON j.id = r.import_job_id WHERE j.entity_type = ?', ['PRODUCT']);
            if ($rowCount > $options['max_rows']) {
                return $this->bound('ROWS_LIMIT_EXCEEDED', $rowCount, $options);
            }
            $orphanRows = $this->count('SELECT COUNT(*) AS n FROM '.$rows.' r LEFT JOIN '.$jobs.' j ON j.id = r.import_job_id WHERE j.id IS NULL');
            $list = $this->all('SELECT id, entity_type, mode, source_format, status, total_rows, success_rows, error_rows FROM '.$jobs
                .' WHERE entity_type = ? ORDER BY id ASC LIMIT '.($options['max_jobs']+1), ['PRODUCT']);
            $aggregate = $this->all('SELECT r.import_job_id, COUNT(*) AS recorded_rows, '
                .'COUNT(DISTINCT r.row_number) AS distinct_rows, MIN(r.row_number) AS min_row_number, '
                .'SUM(CASE WHEN BINARY r.row_status = \'SUCCESS\' THEN 1 ELSE 0 END) AS success_rows, '
                .'SUM(CASE WHEN BINARY r.row_status = \'ERROR\' THEN 1 ELSE 0 END) AS error_rows, '
                .'SUM(CASE WHEN BINARY r.row_status = \'SKIPPED\' THEN 1 ELSE 0 END) AS skipped_rows, '
                .'SUM(CASE WHEN BINARY r.row_status IN (\'SUCCESS\',\'ERROR\',\'SKIPPED\') THEN 0 ELSE 1 END) AS invalid_status_rows, '
                .'SUM(CASE WHEN (BINARY r.row_status = \'SUCCESS\' AND (r.target_ref_id IS NULL OR r.error_code IS NOT NULL)) '
                .'OR (BINARY r.row_status IN (\'ERROR\',\'SKIPPED\') AND (r.target_ref_id IS NOT NULL OR r.error_code IS NULL OR r.error_code = \'\')) '
                .'THEN 1 ELSE 0 END) AS invalid_payload_rows '
                .'FROM '.$rows.' r JOIN '.$jobs.' j ON j.id = r.import_job_id '
                .'WHERE j.entity_type = ? GROUP BY r.import_job_id ORDER BY r.import_job_id ASC', ['PRODUCT']);
            return ['milestone'=>'M9.2', 'mode'=>'DEV_CLONE_READ_ONLY_SNAPSHOT',
                'time_utc'=>gmdate('c'),'database'=>$connected,
                'limits'=>['max_jobs'=>$options['max_jobs'],'max_rows'=>$options['max_rows']],
                ...M92JournalRules::assess($list, $aggregate, $orphanRows, $jobCount, $rowCount)];
        } finally {
            if ($begun) $this->sql('ROLLBACK');
        }
    }

    /** @param array{database:string,max_jobs:int,max_rows:int} $options @return array<string,mixed> */
    private function bound(string $code, int $observed, array $options): array
    {
        return ['milestone'=>'M9.2','mode'=>'DEV_CLONE_READ_ONLY_SNAPSHOT','time_utc'=>gmdate('c'),
            'status'=>'INCONCLUSIVE_AUDIT_BOUND','database'=>$options['database'],
            'observed_count'=>$observed,'coverage_gap_counts'=>[$code=>1],
            'mutation_concurrency'=>'NOT_TESTED','production_acceptance'=>'NOT_ATTESTED'];
    }

    /** @param list<mixed> $binds @return array<string,mixed> */
    private function row(string $sql, array $binds=[]): array
    {
        $result = $this->db->query($sql, $binds);
        if ($result === false) throw new RuntimeException('Snapshot SELECT failed.');
        $row = $result->getRowArray();
        if (!is_array($row)) throw new RuntimeException('Snapshot SELECT returned no scalar row.');
        return $row;
    }

    /** @param list<mixed> $binds @return list<array<string,mixed>> */
    private function all(string $sql, array $binds=[]): array
    {
        $result = $this->db->query($sql, $binds);
        if ($result === false) throw new RuntimeException('Snapshot SELECT failed.');
        return $result->getResultArray();
    }

    /** @param list<mixed> $binds */
    private function count(string $sql, array $binds=[]): int
    {
        $number = $this->row($sql, $binds)['n'] ?? null;
        if (!is_int($number) && !is_string($number)) throw new RuntimeException('Invalid aggregate count.');
        if (preg_match('/\A(?:0|[1-9][0-9]{0,12})\z/D', (string)$number) !== 1) {
            throw new RuntimeException('Unbounded aggregate count.');
        }
        return (int)$number;
    }

    private function sql(string $statement): void
    {
        if ($this->db->query($statement) === false) throw new RuntimeException('M9.2 read-only transaction unavailable.');
    }
}
