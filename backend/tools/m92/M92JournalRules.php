<?php

declare(strict_types=1);

/** Pure journal invariants; evaluated on ONE consistent SQL snapshot. */
final class M92JournalRules
{
    /**
     * @param list<array<string,mixed>> $jobs Safe job fields only; no source filename or PII.
     * @param list<array<string,mixed>> $summaries Per-job SQL aggregates, no raw job rows.
     * @return array<string,mixed> Privacy-preserving status and aggregate evidence.
     */
    public static function assess(array $jobs, array $summaries, int $orphanRows, int $jobCount, int $rowCount): array
    {
        if ($orphanRows < 0 || $jobCount < 0 || $rowCount < 0) {
            throw new InvalidArgumentException('Invalid negative snapshot count.');
        }
        $failures = [];
        $gaps = [];
        $add = static function (array &$store, string $code): void {
            $store[$code] = ($store[$code] ?? 0) + 1;
        };
        $stats = [];
        foreach ($summaries as $summary) {
            $id = (string)($summary['import_job_id'] ?? '');
            if (!self::id($id) || isset($stats[$id])) {
                $add($failures, 'INVALID_OR_DUPLICATE_ROW_AGGREGATE');
                continue;
            }
            $stats[$id] = $summary;
        }
        $seen = [];
        $sumRows = 0;
        $states = [];
        foreach ($jobs as $job) {
            $id = (string)($job['id'] ?? '');
            if (!self::id($id) || isset($seen[$id])) {
                $add($failures, 'INVALID_OR_DUPLICATE_JOB_ID');
                continue;
            }
            $seen[$id] = true;
            $status = (string)($job['status'] ?? '');
            $safeStatus = in_array($status, ['PENDING','PROCESSING','COMPLETED','COMPLETED_WITH_ERRORS','FAILED'], true) ? $status : 'INVALID_ENUM';
            $states[$safeStatus] = ($states[$safeStatus] ?? 0) + 1;
            if (($job['entity_type'] ?? null) !== 'PRODUCT'
                || !in_array($status, ['PENDING','PROCESSING','COMPLETED','COMPLETED_WITH_ERRORS','FAILED'], true)
                || !in_array($job['mode'] ?? null, ['CREATE_ONLY','UPDATE_ONLY','UPSERT'], true)
                || !in_array($job['source_format'] ?? null, ['CSV','XLSX'], true)) {
                $add($failures, 'INVALID_JOB_ENUM');
                continue;
            }
            $total = self::number($job['total_rows'] ?? null);
            $success = self::number($job['success_rows'] ?? null);
            $errors = self::number($job['error_rows'] ?? null);
            if ($total === null || $total < 1 || $total > 1000 || $success === null || $errors === null
                || $success > $total || $errors > $total || $success + $errors > $total) {
                $add($failures, 'INVALID_JOB_COUNTERS');
                continue;
            }
            $s = $stats[$id] ?? [
                'recorded_rows'=>0, 'distinct_rows'=>0, 'min_row_number'=>null,
                'success_rows'=>0, 'error_rows'=>0, 'skipped_rows'=>0,
                'invalid_status_rows'=>0, 'invalid_payload_rows'=>0,
            ];
            $values = [];
            foreach (['recorded_rows','distinct_rows','success_rows','error_rows','skipped_rows',
                'invalid_status_rows','invalid_payload_rows'] as $key) {
                $values[$key] = self::number($s[$key] ?? null);
            }
            if (in_array(null, $values, true)) {
                $add($failures, 'INVALID_JOURNAL_AGGREGATE');
                continue;
            }
            $sumRows += $values['recorded_rows'];
            if ($values['recorded_rows'] > $total || $values['distinct_rows'] !== $values['recorded_rows']
                || ($values['recorded_rows'] > 0 && (self::number($s['min_row_number'] ?? null) ?? 0) < 2)
                || $values['invalid_status_rows'] !== 0 || $values['invalid_payload_rows'] !== 0) {
                $add($failures, 'JOURNAL_ROW_INVARIANT');
            }
            if ($status === 'PENDING' && ($values['recorded_rows'] > 0 || $success > 0 || $errors > 0)) {
                $add($failures, 'PENDING_JOB_HAS_ROWS');
            }
            if (in_array($status, ['PENDING','PROCESSING'], true)) {
                $add($gaps, 'OPEN_JOB_SNAPSHOT');
                continue;
            }
            if ($status === 'FAILED') {
                // A business command may commit before a row journal write fails.
                // A partial FAILED job needs manual reconciliation, not automatic retry.
                $add($gaps, 'FAILED_JOB_MANUAL_REVIEW');
                if ($values['recorded_rows'] !== $total || $success !== $values['success_rows']
                    || $errors !== $values['error_rows']) {
                    $add($gaps, 'PARTIAL_OR_UNCERTAIN_FAILED_JOURNAL');
                }
                continue;
            }
            if ($values['recorded_rows'] !== $total || $success !== $values['success_rows']
                || $errors !== $values['error_rows'] || $values['skipped_rows'] !== 0
                || $values['success_rows'] + $values['error_rows'] !== $total) {
                $add($failures, 'TERMINAL_JOB_JOURNAL_MISMATCH');
            }
            if (($status === 'COMPLETED' && ($errors !== 0 || $success !== $total))
                || ($status === 'COMPLETED_WITH_ERRORS' && $errors === 0)) {
                $add($failures, 'TERMINAL_JOB_STATUS_MISMATCH');
            }
        }
        if (count($jobs) !== $jobCount || count($seen) !== count($jobs)) {
            $add($failures, 'JOB_SNAPSHOT_COUNT_MISMATCH');
        }
        if (count($stats) !== count($seen) && count(array_diff(array_keys($stats), array_keys($seen))) > 0) {
            $add($failures, 'ORPHAN_PRODUCT_AGGREGATE');
        }
        if ($sumRows !== $rowCount) {
            $add($failures, 'ROW_SNAPSHOT_COUNT_MISMATCH');
        }
        if ($orphanRows > 0) {
            $failures['ORPHAN_JOURNAL_ROWS'] = $orphanRows;
        }
        if ($jobCount === 0 || $rowCount === 0) {
            $add($gaps, 'NO_COMPLETED_IMPORT_FIXTURE');
        }
        $terminal = ($states['COMPLETED'] ?? 0) + ($states['COMPLETED_WITH_ERRORS'] ?? 0);
        if ($terminal === 0) $add($gaps, 'NO_TERMINAL_IMPORT_FIXTURE');
        ksort($failures, SORT_STRING);
        ksort($gaps, SORT_STRING);
        ksort($states, SORT_STRING);
        return [
            'status' => $failures !== [] ? 'FAIL_DEV_IMPORT_JOURNAL_INTEGRITY'
                : ($gaps !== [] ? 'INCONCLUSIVE_IMPORT_RECOVERY_OR_FIXTURE' : 'PASS_DEV_IMPORT_JOURNAL_SNAPSHOT'),
            'job_count' => $jobCount, 'journal_row_count' => $rowCount,
            'orphan_row_count' => $orphanRows,
            'status_counts' => $states,
            'issue_counts' => $failures, 'coverage_gap_counts' => $gaps,
            'scope' => 'PRODUCT jobs; journal integrity only',
            'mutation_concurrency' => 'NOT_TESTED',
            'http_auth' => 'NOT_TESTED', 'baseline_parity' => 'NOT_TESTED',
            'production_acceptance' => 'NOT_ATTESTED',
        ];
    }

    private static function id(string $id): bool
    {
        return preg_match('/\A[1-9][0-9]{0,19}\z/D', $id) === 1;
    }

    private static function number(mixed $raw): ?int
    {
        if (!is_int($raw) && !is_string($raw)) return null;
        $text = (string)$raw;
        if (preg_match('/\A(?:0|[1-9][0-9]{0,12})\z/D', $text) !== 1
            || (float)$text > PHP_INT_MAX) return null;
        return (int)$text;
    }
}
