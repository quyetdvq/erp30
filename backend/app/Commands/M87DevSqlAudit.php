<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;
use Throwable;

/** Explicit DEV-only READ ONLY SQL/PBAC parity and EXPLAIN verifier. */
final class M87DevSqlAudit extends BaseCommand
{
    protected $group='DinkERP';
    protected $name='m87:db-audit';
    protected $description='DEV-only SQL/PBAC parity, scoped pagination and EXPLAIN; never mutates business data.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        require_once ROOTPATH . 'tools/m87/M87SqlVerifier.php';
        $options = \M87Plan::args($params);
        \M87Plan::devEnvironment(ENVIRONMENT);
        $report=['milestone'=>'M8.7','status'=>'BLOCKED_ENV_OR_RUNTIME',
            'time_utc'=>gmdate('c'),'production_acceptance'=>'BLOCKED'];
        try {
            $db=db_connect();
            $report=(new \M87SqlVerifier($db))->verify($options);
        } catch (Throwable $error) {
            $report['exception']=get_class($error);
            $report['reason']=match (true) {
                str_contains($error->getMessage(),'AUTH_SCOPE_COMPILATION_UNSUPPORTED')=>'SCOPE_CAP_REACHED',
                str_contains($error->getMessage(),'Connected database')=>'DEV_DATABASE_MISMATCH',
                default=>'REVIEW_LOCAL_ERROR_LOG',
            };
            CLI::write('M8.7 BLOCKED: '.$report['reason']);
        }
        $folder=WRITEPATH.'m87/reports';
        if (!is_dir($folder) && !mkdir($folder,0700,true) && !is_dir($folder)) {
            throw new RuntimeException('Cannot create M8.7 local report directory.');
        }
        $file=$folder.'/m87_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
        file_put_contents($file,json_encode($report,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);
        @chmod($file,0600);
        CLI::write('M8.7 DEV SQL audit: '.$report['status']);
        CLI::write('M8.7 report: '.$file);
        CLI::write('HTTP actor authentication, mutation/race and baseline parity remain NOT_TESTED. Production BLOCKED.');
        if (!in_array($report['status'], ['PASS_DEV_SQL_PARITY', 'INCONCLUSIVE_DB_FIXTURE_COVERAGE'], true)) {
            throw new RuntimeException('M8.7 DEV SQL audit incomplete or failed; review the local report.');
        }
    }
}
