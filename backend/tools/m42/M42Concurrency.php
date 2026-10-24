<?php

declare(strict_types=1);

require_once __DIR__.'/M42Fixture.php';
require_once __DIR__.'/M42Http.php';
require_once __DIR__.'/../m34b/M34BChildDiagnostics.php';

/**
 * Opt-in M4.2B: two separate php -S processes, two Shield sessions, one
 * nonce-owned database and an externally held InnoDB resource row-lock.
 * Checks concurrent Numbering *configuration* creation, not number issuance.
 */
final class M42Concurrency
{
    /** @var array<string,resource> */
    private array $servers=[];
    /** @var array<string,resource> */
    private array $workers=[];
    private array $failed=[];
    private array $results=[];
    private bool $attested=false;
    private bool $lockHeld=false;
    private ?PDO $lockDb=null;
    private array $ports=[];
    private string $phase='start';

    public function __construct(
        private readonly string $root,
        private readonly M34BSandbox $sandbox,
        private readonly M42Fixture $fixture,
        private readonly array $actor,
        private readonly string $mysqlUser,
        private readonly string $mysqlPassword,
        private readonly int $mysqlPort,
        private readonly string $dir,
    ) {}

    public function run(): array
    {
        $started=microtime(true);
        try {
            $this->phase='child_nonce_attestation';
            $this->attestChild();
            $this->phase='start_two_independent_servers';
            foreach (['a','b'] as $role) {
                $port=$this->port();
                while (in_array($port,$this->ports,true)) $port=$this->port();
                $this->ports[$role]=$port;
                $this->startServer($role,$port);
            }
            $this->phase='two_independent_shield_logins';
            foreach (['a','b'] as $role) $this->startWorker($role);
            $this->waitFor(['race_a_ready','race_b_ready'],15);

            $this->phase='lock_synthetic_numbering_resource';
            $before=$this->fixture->snapshot();
            $this->lockDb=M34BSandbox::connect('127.0.0.1',$this->mysqlPort,$this->mysqlUser,$this->mysqlPassword);
            $this->lockDb->exec('USE '.M34BSafety::identifier($this->sandbox->name));
            // This independent connection must pass the same nonce DB guard.
            (new M42Fixture($this->sandbox,$this->lockDb))->attest();
            if (!$this->lockDb->beginTransaction()) throw new M34BFailure('M4 race lock transaction start failed');
            $this->lockHeld=true;
            $lock=$this->lockDb->prepare('SELECT id FROM '.$this->fixture->table('resources').' WHERE id=? FOR UPDATE');
            $lock->execute([$this->actor['resource']]);
            if ((string)$lock->fetchColumn()!==(string)$this->actor['resource']) {
                throw new M34BFailure('M4 race resource lock target missing');
            }

            $this->phase='simultaneous_http_writes';
            file_put_contents($this->dir.'/race_go',"GO\n",LOCK_EX);
            // The attested M4-only router creates accepted markers BEFORE CI4
            // enters its FOR UPDATE SQL. Both independent servers must accept.
            $this->waitFor(['race_a_accepted','race_b_accepted'],12);
            $this->waitFor(['race_a_requesting','race_b_requesting'],4);
            $this->phase='verify_two_innodb_lock_waits';
            // Require actual database lock waits rather than merely two HTTP
            // requests in flight. Read system metadata on the already attested
            // local sandbox connection; no template or production data access.
            $locks=$this->waitForTwoSqlLockWaits(7);
            if ($locks!==2 || is_file($this->dir.'/race_a_result.json')
                || is_file($this->dir.'/race_b_result.json')) {
                throw new M34BFailure('M4 race did not show two blocked InnoDB writers');
            }
            $this->results[]=['case'=>'two_http_writers_observed_in_innodb_lock_wait','pass'=>true,'blocked_connections'=>2];
            $this->phase='release_resource_lock';
            $this->unlock();
            $this->phase='assert_two_http_results';
            $this->waitFor(['race_a_result.json','race_b_result.json'],20);
            $replies=[];
            foreach (['a','b'] as $role) {
                $json=json_decode((string) file_get_contents($this->dir.'/race_'.$role.'_result.json'),true,512,JSON_THROW_ON_ERROR);
                if (!is_array($json)) throw new M34BFailure('Malformed M4 worker result');
                $replies[$role]=$json;
            }
            $statuses=array_map(static fn(array $v):int=>(int)($v['status']??0),$replies);
            sort($statuses,SORT_NUMERIC);
            if ($statuses!==[201,409]) {
                throw new M34BFailure('M4 race expected exactly one HTTP 201 and one HTTP 409');
            }
            $losers=array_values(array_filter($replies,static fn(array $r):bool=>($r['status']??null)===409));
            $winners=array_values(array_filter($replies,static fn(array $r):bool=>($r['status']??null)===201));
            if (count($winners)!==1 || count($losers)!==1
                || ($losers[0]['error_code']??null)!=='NUM_SERIES_DUPLICATE'
                || ($winners[0]['series_code']??null)!==('M42_RACE_'.strtoupper(substr($this->sandbox->nonce,0,12)))
                || !is_scalar($winners[0]['series_id']??null)
                || preg_match('/\A[1-9][0-9]*\z/D',(string)$winners[0]['series_id'])!==1) {
                throw new M34BFailure('M4 race response domain semantics mismatch');
            }
            $this->results[]=['case'=>'exactly_one_201_one_409_duplicate','pass'=>true,'statuses'=>$statuses];

            $this->phase='verify_atomic_sandbox_sql';
            $after=$this->fixture->snapshot();
            if (M42Fixture::changed($before,$after)!==['auto_numbering_series']) {
                throw new M34BFailure('M4 race modified unexpected SQL tables');
            }
            $table=$this->fixture->table('auto_numbering_series');
            $count=(int)$this->fixture->scalar('SELECT COUNT(*) FROM '.$table.' WHERE resource_id=? AND series_code=?',
                [(string)$this->actor['resource'],'M42_RACE_'.strtoupper(substr($this->sandbox->nonce,0,12))]);
            $defaults=(int)$this->fixture->scalar('SELECT COUNT(*) FROM '.$table." WHERE resource_id=? AND status='ACTIVE' AND is_default=1",
                [(string)$this->actor['resource']]);
            $counters=(int)$this->fixture->scalar('SELECT COUNT(*) FROM '.$this->fixture->table('auto_numbering_counters'));
            if ($count!==1 || $defaults!==1 || $counters!==0) {
                throw new M34BFailure('M4 race violated unique active/default/zero-issuance invariants');
            }
            $this->results[]=['case'=>'single_series_single_default_no_counter_issuance','pass'=>true];
        } catch (Throwable $e) {
            $this->failed[]=['phase'=>$this->phase,'reason'=>M34BSafety::sanitizedException($e)];
        } finally {
            $this->unlock();
            foreach (['a','b'] as $role) $this->stopProcess($this->workers,$role);
            foreach (['a','b'] as $role) $this->stopProcess($this->servers,$role);
            foreach (['a','b'] as $role) @unlink($this->dir.'/race_'.$role.'_config.json');
        }
        $ok=$this->failed===[] && count($this->results)===3 && $this->attested;
        return [
            'milestone'=>'M4.2B', 'verdict'=>$ok?'PASS_LOCAL_M4_TWO_WRITER':'NEEDS_FIX_OR_VERIFY',
            'child_database_attested'=>$this->attested,
            'separate_http_servers'=>count($this->ports),
            'authenticated_writer_count'=>2,
            'accepted_before_lock_release'=>is_file($this->dir.'/race_a_accepted') && is_file($this->dir.'/race_b_accepted'),
            'numbering_configuration_writers'=>2,
            'number_issuance_concurrency'=>'NOT_IMPLEMENTED_NOT_TESTED',
            'independent_baseline_live_parity'=>'NOT_TESTED', 'production_write'=>'BLOCKED',
            'runtime_seconds'=>round(microtime(true)-$started,2),
            'failed'=>$this->failed, 'results'=>$this->results,
        ];
    }

    private static function port(): int
    {
        $socket=@stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
        if ($socket===false) throw new M34BFailure('M4 race cannot allocate local loopback port');
        $name=stream_socket_get_name($socket,false);
        fclose($socket);
        $port=(int)substr(strrchr((string)$name,':'),1);
        if ($port<1024 || $port>65535) throw new M34BFailure('Invalid M4 race loopback port');
        return $port;
    }

    private function env(string $role): array
    {
        $safe=[];
        foreach (['PATH','Path','SYSTEMROOT','SystemRoot','WINDIR','windir','TEMP','TMP','TMPDIR',
            'HOME','USERPROFILE','HOMEDRIVE','HOMEPATH','COMSPEC','APPDATA','LOCALAPPDATA',
            'PHPRC','PHP_INI_SCAN_DIR'] as $name) {
            $value=getenv($name);
            if (is_string($value)) $safe[$name]=$value;
        }
        return [...$safe,
            'CI_ENVIRONMENT'=>'development', 'DINKERP_M33B_WRITE_MODE'=>'enabled',
            'M34B_ONLY_SCHEMA'=>$this->sandbox->name, 'M34B_ONLY_NONCE'=>$this->sandbox->nonce,
            'M42_RACE_ROLE'=>$role, 'M42_RACE_DIR'=>$this->dir,
            'app.baseURL'=>'http://127.0.0.1:'.$this->ports[$role],
            'app.forceGlobalSecureRequests'=>'false',
            'database.default.hostname'=>'127.0.0.1',
            'database.default.port'=>(string)$this->mysqlPort,
            'database.default.database'=>$this->sandbox->name,
            'database.default.username'=>$this->mysqlUser,
            'database.default.password'=>$this->mysqlPassword,
            'database.default.DBDriver'=>'MySQLi',
            'database.default.DBPrefix'=>$this->sandbox->tablePrefix(),
            'database.tests.hostname'=>'127.0.0.1',
            'database.tests.port'=>(string)$this->mysqlPort,
            'database.tests.database'=>$this->sandbox->name,
            'database.tests.username'=>$this->mysqlUser,
            'database.tests.password'=>$this->mysqlPassword,
            'database.tests.DBDriver'=>'MySQLi',
            'database.tests.DBPrefix'=>$this->sandbox->tablePrefix(),
            'session.savePath'=>$this->dir.'/session', 'auth.allowRemembering'=>'false',
        ];
    }

    private function attestChild(): void
    {
        $path=$this->dir.'/session';
        if (!is_dir($path) && !mkdir($path,0700,true) && !is_dir($path)) {
            throw new M34BFailure('M4 race session directory cannot be created');
        }
        $this->ports['a']=self::port();
        $command=[PHP_BINARY,$this->root.'/backend/tools/m42/child_spark.php',
            'm34b:attest',$this->sandbox->name,$this->sandbox->nonce];
        $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
            $pipes,$this->root.'/backend',$this->env('a'));
        if (!is_resource($process)) throw new M34BFailure('M4 race CLI attestation failed to start');
        fclose($pipes[0]);
        $stdout=stream_get_contents($pipes[1]);
        $stderr=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        $exit=proc_close($process);
        $marker='M34B_ATTEST_OK '.$this->sandbox->name;
        $valid=$exit===0 && preg_match('/(?:\A|\R)'.preg_quote($marker,'/').'(?:\R|\z)/D',$stdout)===1;
        $info=M34BChildDiagnostics::summarize($stdout,$stderr,$exit,$valid);
        file_put_contents($this->dir.'/race_attestation.json',
            json_encode($info,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);
        if (!$valid) throw new M34BFailure('M4 race child attestation refused: '.$info['code']);
        $this->attested=true;
        unset($this->ports['a']);
    }

    private function startServer(string $role, int $port): void
    {
        $command=[PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',
            $this->root.'/backend/public',$this->root.'/backend/tools/m42/router.php'];
        $process=proc_open($command,[0=>['pipe','r'],
            1=>['file',$this->dir.'/race_http_'.$role.'_out.log','a'],
            2=>['file',$this->dir.'/race_http_'.$role.'_err.log','a'],
        ],$pipes,$this->root.'/backend',$this->env($role));
        if (!is_resource($process)) throw new M34BFailure('M4 race loopback HTTP process did not start');
        $this->servers[$role]=$process;
        if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);
        for ($i=0;$i<70;$i++) {
            $socket=@fsockopen('127.0.0.1',$port,$err,$msg,0.1);
            if (is_resource($socket)) {fclose($socket);return;}
            usleep(100000);
        }
        throw new M34BFailure('M4 race HTTP server did not become ready');
    }

    private function startWorker(string $role): void
    {
        $config=$this->dir.'/race_'.$role.'_config.json';
        if (file_exists($config)) throw new M34BFailure('M4 race config already exists');
        $data=[
            'nonce'=>$this->sandbox->nonce, 'role'=>$role,
            'origin'=>'http://127.0.0.1:'.$this->ports[$role],
            'email'=>$this->actor['email'],'password'=>$this->actor['password'],
            'resource'=>(string)$this->actor['resource'],
            'series_code'=>'M42_RACE_'.strtoupper(substr($this->sandbox->nonce,0,12)),
        ];
        file_put_contents($config,json_encode($data,JSON_THROW_ON_ERROR),LOCK_EX);
        @chmod($config,0600);
        $command=[PHP_BINARY,$this->root.'/backend/tools/m42/race_worker.php',$config];
        $process=proc_open($command,[0=>['pipe','r'],
            1=>['file',$this->dir.'/race_worker_'.$role.'_out.log','a'],
            2=>['file',$this->dir.'/race_worker_'.$role.'_err.log','a'],
        ],$pipes,$this->root.'/backend');
        if (!is_resource($process)) throw new M34BFailure('M4 race HTTP worker did not start');
        if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);
        $this->workers[$role]=$process;
    }

    /** @param list<string> $names */
    private function waitFor(array $names,int $seconds): void
    {
        $deadline=microtime(true)+$seconds;
        do {
            $all=true;
            foreach ($names as $name) if (!is_file($this->dir.'/'.$name)) {$all=false;break;}
            if ($all) return;
            foreach (['a','b'] as $role) {
                if (isset($this->workers[$role]) && is_resource($this->workers[$role])) {
                    $state=proc_get_status($this->workers[$role]);
                    if (!$state['running']) throw new M34BFailure('M4 race worker exited before barrier');
                }
            }
            usleep(50000);
        } while (microtime(true)<$deadline);
        throw new M34BFailure('M4 race handshake timed out: '.implode(',',$names));
    }

    /** Require two separate current InnoDB transactions waiting on this sandbox. */
    private function waitForTwoSqlLockWaits(int $seconds): int
    {
        $this->fixture->attest();
        $sql="SELECT COUNT(DISTINCT trx.trx_mysql_thread_id) "
            ."FROM information_schema.INNODB_TRX trx "
            ."JOIN information_schema.PROCESSLIST p ON p.ID=trx.trx_mysql_thread_id "
            ."WHERE p.DB=? AND trx.trx_state='LOCK WAIT' "
            ."AND UPPER(COALESCE(trx.trx_query,'')) LIKE '%FOR UPDATE%'";
        $deadline=microtime(true)+$seconds;
        $max=0;
        do {
            $count=(int)$this->fixture->scalar($sql,[$this->sandbox->name]);
            $max=max($max,$count);
            if ($count===2) return 2;
            if (is_file($this->dir.'/race_a_result.json') || is_file($this->dir.'/race_b_result.json')) break;
            usleep(100000);
        } while (microtime(true)<$deadline);
        throw new M34BFailure('Expected two independently blocked InnoDB writers, observed '.$max);
    }

    private function unlock(): void
    {
        if ($this->lockHeld && $this->lockDb!==null) {
            try {$this->lockDb->commit();}catch (Throwable) {
                try {$this->lockDb->rollBack();}catch (Throwable) {}
            }
            $this->lockHeld=false;
        }
        $this->lockDb=null;
    }

    private function stopProcess(array &$pool,string $role): void
    {
        if (isset($pool[$role]) && is_resource($pool[$role])) {
            $state=proc_get_status($pool[$role]);
            if ($state['running']) proc_terminate($pool[$role]);
            proc_close($pool[$role]);
            unset($pool[$role]);
        }
    }
}
