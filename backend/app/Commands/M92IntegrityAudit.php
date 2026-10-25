<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;
use Throwable;

/** M9.2 DEV-only Product Import journal audit; no business writes. */
final class M92IntegrityAudit extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'm92:integrity-audit';
    protected $description = 'READ ONLY bounded Product Import job/row consistency on a confirmed DEV clone.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        require_once ROOTPATH.'tools/m92/M92DbAudit.php';
        $options = \M92Plan::args($params);
        \M92Plan::devEnvironment(ENVIRONMENT);
        $report = ['milestone'=>'M9.2', 'time_utc'=>gmdate('c'), 'status'=>'BLOCKED_DEV_AUDIT',
            'production_acceptance'=>'NOT_ATTESTED','mutation_concurrency'=>'NOT_TESTED'];
        try {
            $report = (new \M92DbAudit(db_connect()))->run($options);
        } catch (Throwable $error) {
            // Never save DB errors, stack traces, SQL, secrets, row IDs or names.
            $report['failure_class'] = get_class($error);
            $report['failure_code'] = 'PRECONDITION_OR_SNAPSHOT_FAILED';
            CLI::write('M9.2 BLOCKED: inspect environment, schema, database match and InnoDB.');
        }
        $folder = WRITEPATH.'m92/reports';
        if (!is_dir($folder) && !mkdir($folder,0700,true) && !is_dir($folder)) {
            throw new RuntimeException('Cannot create protected local M9.2 report directory.');
        }
        if (is_link($folder) || !is_writable($folder)) throw new RuntimeException('Unsafe M9.2 report directory.');
        $file = $folder.'/m92_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
        $encoded = json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($file,$encoded,LOCK_EX)===false) throw new RuntimeException('M9.2 report write failed.');
        @chmod($file,0600);
        CLI::write('M9.2 Product Import journal: '.$report['status']);
        CLI::write('M9.2 report: '.$file);
        CLI::write('Mutation concurrency, HTTP auth, policy and production acceptance NOT ATTESTED by this read-only audit.');
        if ($report['status'] !== 'PASS_DEV_IMPORT_JOURNAL_SNAPSHOT') {
            throw new RuntimeException('M9.2 journal is inconclusive/failed. Review sanitized report; never auto-retry uncertain import jobs.');
        }
    }
}
