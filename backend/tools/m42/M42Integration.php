<?php

declare(strict_types=1);

require_once __DIR__.'/M42Fixture.php';
require_once __DIR__.'/M42Http.php';
require_once __DIR__.'/../m34b/M34BChildDiagnostics.php';

/**
 * Independent M4 HTTP/SQL probe. Never broadens M3 RUN INTEGRATION and never
 * claims independent baseline parity or two-writer concurrency.
 */
final class M42Integration
{
    private mixed $server=null;
    private array $pipes=[];
    private string $origin='';
    private array $failed=[];
    private array $results=[];
    private int $positive=0;
    private int $unauth=0;
    private int $csrf=0;
    private string $series='';
    private bool $attested=false;

    public function __construct(
        private readonly string $root,
        private readonly M34BSandbox $sandbox,
        private readonly M42Fixture $fixture,
        private readonly array $actor,
        private readonly string $mysqlUser,
        private readonly string $mysqlPassword,
        private readonly int $mysqlPort,
        private readonly string $logDirectory,
    ) {}

    /** @return array<string,mixed> */
    public function run(): array
    {
        $started=microtime(true);
        $phase='allocate_loopback';
        try {
            $port=self::freePort();
            $this->origin='http://127.0.0.1:'.$port;
            M34BSafety::assertUrl($this->origin);
            $phase='prepare_isolated_session';
            $this->ensureSession();
            $phase='attest_disposable_schema';
            $environment = $this->childEnvironment();
            $this->attestChild($environment);
            $phase='start_nonce_guarded_loopback';
            $this->startServer($port, $environment, 'sandbox');
            try {
                $phase='unauthenticated';
                $this->probeUnauthenticated(new M42Http($this->origin));
                $phase='login_disposable_actor';
                $client=new M42Http($this->origin);
                $client->login($this->actor['email'],$this->actor['password']);
                $phase='csrf';
                $this->probeCsrf($client);
                $phase='positive_and_rollback';
                $this->probePositive($client);
            } finally {$this->stopServer();}
        } catch (Throwable $e) {
            $this->failed[]=['phase'=>$phase,'reason'=>M34BSafety::sanitizedException($e)];
        } finally {$this->stopServer();}
        return [
            'milestone'=>'M4.2A', 'runner_revision'=>'M4.2A.1',
            'child_database_attested'=>$this->attested, 'verdict'=>$this->failed===[] && $this->positive===14
                && $this->unauth===28 && $this->csrf===7
                && $this->attested ? 'PASS_LOCAL_M4_SANDBOX' : 'NEEDS_FIX_OR_VERIFY',
            'positive_m4_routes'=>$this->positive,
            'unauthenticated_bilingual_probes'=>$this->unauth,
            'invalid_csrf_probes'=>$this->csrf,
            'legacy_write_gate_probes'=>'RETIRED_NOT_APPLICABLE',
            'sandbox_schema'=>$this->sandbox->name,
            'independent_baseline_live_parity'=>'NOT_TESTED',
            'two_writer_concurrency'=>'NOT_TESTED',
            'production_write'=>'BLOCKED',
            'runtime_seconds'=>round(microtime(true)-$started,2),
            'failed'=>$this->failed, 'results'=>$this->results,
        ];
    }

    private static function freePort(): int
    {
        $socket=@stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
        if ($socket===false) throw new M34BFailure('Cannot reserve M4 loopback HTTP port');
        $address=stream_socket_get_name($socket,false);
        fclose($socket);
        $port=(int)substr(strrchr((string)$address,':'),1);
        if ($port<1024 || $port>65535) throw new M34BFailure('Invalid M4 loopback port');
        return $port;
    }

    /** Fork with an explicit sandbox-only environment; never inherit credentials. */
    private function childEnvironment(): array
    {
        $safe=[];
        foreach (['PATH','Path','SYSTEMROOT','SystemRoot','WINDIR','windir','TEMP','TMP','TMPDIR',
            'HOME','USERPROFILE','HOMEDRIVE','HOMEPATH','COMSPEC','APPDATA','LOCALAPPDATA',
            'PHPRC','PHP_INI_SCAN_DIR'] as $name) {
            $value=getenv($name);
            if (is_string($value)) $safe[$name]=$value;
        }
        return [
            ...$safe,
            'CI_ENVIRONMENT'=>'development',
            'M34B_ONLY_SCHEMA'=>$this->sandbox->name,
            'M34B_ONLY_NONCE'=>$this->sandbox->nonce,
            'app.baseURL'=>$this->origin,
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
            'session.savePath'=>$this->logDirectory.'/session',
            'auth.allowRemembering'=>'false',
        ];
    }

    private function ensureSession(): void
    {
        $path=$this->logDirectory.'/session';
        if (!is_dir($path) && !mkdir($path,0700,true) && !is_dir($path)) {
            throw new M34BFailure('Could not create M4 isolated session directory');
        }
    }

    private function attestChild(array $env): void
    {
        $command=[PHP_BINARY,$this->root.'/backend/tools/m42/child_spark.php',
            'm34b:attest',$this->sandbox->name,$this->sandbox->nonce];
        $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
            $pipes,$this->root.'/backend',$env);
        if (!is_resource($process)) throw new M34BFailure('M4 forked attestation start failed');
        fclose($pipes[0]);
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        $exit=proc_close($process);
        $marker='M34B_ATTEST_OK '.$this->sandbox->name;
        $ok=$exit===0 && preg_match('/(?:\A|\R)'.preg_quote($marker,'/').'(?:\R|\z)/D',$out)===1;
        $diagnostics=M34BChildDiagnostics::summarize($out,$err,$exit,$ok);
        file_put_contents($this->logDirectory.'/child_attestation.json',
            json_encode($diagnostics,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX);
        if (!$ok) throw new M34BFailure('M4 sandbox child attestation refused: '.$diagnostics['code']);
        $this->attested=true;
    }

    private function startServer(int $port,array $env,string $label): void
    {
        $command=[PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',
            $this->root.'/backend/public',$this->root.'/backend/tools/m42/router.php'];
        $this->server=proc_open($command,[
            0=>['pipe','r'],
            1=>['file',$this->logDirectory.'/http_'.$label.'_out.log','a'],
            2=>['file',$this->logDirectory.'/http_'.$label.'_err.log','a'],
        ],$this->pipes,$this->root.'/backend',$env);
        if (!is_resource($this->server)) throw new M34BFailure('M4 loopback server failed to start');
        for ($attempt=0;$attempt<50;$attempt++) {
            $socket=@fsockopen('127.0.0.1',$port,$err,$msg,0.1);
            if (is_resource($socket)) {fclose($socket);return;}
            usleep(100000);
        }
        throw new M34BFailure('M4 loopback startup failed, check local sanitized logs');
    }

    private function stopServer(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            foreach ($this->pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            proc_close($this->server);
        }
        $this->server=null;
        $this->pipes=[];
    }

    /** @return array<string,array{0:string,1:string,2:mixed,3:int,4:list<string>}> */
    private function cases(): array
    {
        $resource=(string)$this->actor['resource'];
        $org=(string)$this->actor['root'];
        $series=$this->series===''?'18446744073709551615':$this->series;
        $n='/api/v1/resources/'.$resource.'/numbering-series';
        $payload=[
            'series_code'=>'M42_'.strtoupper(substr($this->sandbox->nonce,0,12)),
            'name'=>'M42 protected series',
            'scope_type'=>'GROUP','pattern'=>'M42-LIVE-{YYYY}-{SEQ:4}',
            'reset_policy'=>'YEARLY','generation_mode'=>'AUTO_REQUIRED',
            'start_number'=>1,'increment_by'=>1,'is_default'=>true,'status'=>'ACTIVE',
        ];
        return [
            'getDisplaySettingsRuntime'=>['GET','/api/v1/display-settings/runtime',null,200,[]],
            'listDisplaySettingDefinitions'=>['GET','/api/v1/display-settings/definitions',null,200,[]],
            'listDisplaySettingValues'=>['GET','/api/v1/display-settings/values?context_type=SYSTEM',null,200,[]],
            'updateDisplaySettings'=>['PATCH','/api/v1/display-settings',[
                'context'=>['type'=>'SYSTEM'],
                'changes'=>[['key'=>'Display.quantityDecimals','value'=>5]],
            ],200,['settings','configuration_audit_events']],
            'resetDisplaySettings'=>['POST','/api/v1/display-settings/actions/reset',[
                'context'=>['type'=>'SYSTEM'],'keys'=>['Display.quantityDecimals'],
            ],200,['settings','configuration_audit_events']],
            'listNumberingSeries'=>['GET',$n,null,200,[]],
            'validateNumberingSeriesConfig'=>['POST',$n.'/actions/validate-config',[
                ...$payload,'series_code'=>'M42_DRY_'.strtoupper(substr($this->sandbox->nonce,0,10)),
                'pattern'=>'M42-DRY-{YYYY}-{SEQ:3}',
            ],200,[]],
            'createNumberingSeries'=>['POST',$n,$payload,201,['auto_numbering_series']],
            'getNumberingSeries'=>['GET',$n.'/'.$series,null,200,[]],
            'updateNumberingSeries'=>['PATCH',$n.'/'.$series,['name'=>'M42 protected renamed'],200,['auto_numbering_series']],
            'setNumberingSeriesState'=>['POST',$n.'/'.$series.'/actions/set-state',['status'=>'INACTIVE'],200,['auto_numbering_series']],
            'getBusinessWorkflowStates'=>['GET','/api/v1/business-workflow/organization/states',null,200,[]],
            'getBusinessWorkflowOptions'=>['GET','/api/v1/business-workflow/organization/'.$org.'/options',null,200,[]],
            'executeBusinessWorkflowAction'=>['POST','/api/v1/business-workflow/organization/'.$org.'/actions/archive',[],200,['organizations']],
        ];
    }

    /** @param array<string,mixed> $response */
    private function wire(array $response,string $locale): void
    {
        M34Parity::wire($response,$locale);
        if (!is_array($response['json'])) throw new M34BFailure('M4 API returned no JSON envelope');
    }

    private function assertCase(string $name,M42Http $client, string $method,string $path,?array $body,
        int $status,array $expectedChanges,string $locale='en',string $csrfMode='auto'): array
    {
        $before=$this->fixture->snapshot();
        $reply=$client->request($method,$path,$body,$locale,$csrfMode);
        $this->wire($reply,$locale);
        $after=$this->fixture->snapshot();
        $changes=M42Fixture::changed($before,$after);
        $expected=$expectedChanges;
        sort($changes,SORT_STRING);sort($expected,SORT_STRING);
        if ($reply['status']!==$status || $changes!==$expected) {
            throw new M34BFailure('M4 case '.$name.' expected HTTP '.$status.' changed ['.implode(',',$expected)
                .'], observed HTTP '.$reply['status'].' changed ['.implode(',',$changes).']'
                .' error='.($reply['json']['error']['code']??'none'));
        }
        $this->results[]=['case'=>$name,'status'=>$reply['status'],'changed_tables'=>$changes,'pass'=>true];
        return $reply;
    }

    private function probeUnauthenticated(M42Http $client): void
    {
        $before=$this->fixture->snapshot();
        foreach ($this->cases() as $name=>[$method,$path,$body]) {
            foreach (['en','vi'] as $locale) {
                $reply=$client->request($method,$path,$body,$locale,'omit');
                $this->wire($reply,$locale);
                if (!in_array($reply['status'],[401,403],true)) {
                    throw new M34BFailure('Unauthenticated M4 '.$name.' returned HTTP '.$reply['status']);
                }
                $this->unauth++;
            }
        }
        if ($before!==$this->fixture->snapshot()) {
            throw new M34BFailure('Unauthenticated M4 probes altered protected business tables');
        }
        $this->results[]=['case'=>'unauthenticated_14_routes_bilingual','pass'=>true,'probes'=>$this->unauth];
    }

    private function probeCsrf(M42Http $client): void
    {
        $all=$this->cases();
        foreach (['updateDisplaySettings','resetDisplaySettings','createNumberingSeries',
            'updateNumberingSeries','setNumberingSeriesState','validateNumberingSeriesConfig',
            'executeBusinessWorkflowAction'] as $name) {
            [$method,$path,$body]=$all[$name];
            $this->assertCase('invalid_csrf_'.$name,$client,$method,$path,$body,403,[],'en','invalid');
            $this->csrf++;
        }
    }

    private function probePositive(M42Http $client): void
    {
        $order=[
            'getDisplaySettingsRuntime','listDisplaySettingDefinitions','listDisplaySettingValues',
            'updateDisplaySettings','resetDisplaySettings',
            'listNumberingSeries','validateNumberingSeriesConfig','createNumberingSeries',
            'getNumberingSeries','updateNumberingSeries','setNumberingSeriesState',
            'getBusinessWorkflowStates','getBusinessWorkflowOptions','executeBusinessWorkflowAction',
        ];
        foreach ($order as $name) {
            [$method,$path,$body,$status,$changes]=$this->cases()[$name];
            $reply=$this->assertCase('positive_'.$name,$client,$method,$path,$body,$status,$changes);
            $this->assertPositivePayload($name, $reply['json']['data'] ?? null);
            if ($name==='createNumberingSeries') {
                $id=$reply['json']['data']['id']??null;
                if ((!is_int($id) && !is_string($id)) || preg_match('/\A[1-9][0-9]*\z/D',(string)$id)!==1) {
                    throw new M34BFailure('M4 Numbering create did not return positive series id');
                }
                $this->series=(string)$id;
                $this->probeNumberingNegatives($client);
            }
            if ($name==='updateDisplaySettings') $this->probeSettingsRollback($client);
            if ($name==='getBusinessWorkflowOptions') $this->probeWorkflowNegatives($client);
            $this->positive++;
        }
        $this->assertCase('stale_workflow_action',$client,'POST',
            '/api/v1/business-workflow/organization/'.$this->actor['root'].'/actions/archive',[],422,[]);
    }

    /** HTTP success alone must not pass if M4 response data is wrong. */
    private function assertPositivePayload(string $name, mixed $data): void
    {
        $ok = match ($name) {
            'getDisplaySettingsRuntime' => is_array($data) && isset($data['timezone'], $data['number'], $data['money']),
            'listDisplaySettingDefinitions' => is_array($data) && count($data) === 15,
            'listDisplaySettingValues' => is_array($data) && count($data) === 15,
            'updateDisplaySettings' => is_array($data) && count($data) === 1
                && ($data[0]['key'] ?? null) === 'Display.quantityDecimals'
                && ($data[0]['effective_value'] ?? null) === 5,
            'resetDisplaySettings' => is_array($data) && count($data) === 1
                && ($data[0]['key'] ?? null) === 'Display.quantityDecimals'
                && ($data[0]['effective_value'] ?? null) === 3,
            'listNumberingSeries' => is_array($data) && $data === [],
            'validateNumberingSeriesConfig' => is_array($data) && ($data['valid'] ?? null) === true,
            'createNumberingSeries' => is_array($data) && ($data['status'] ?? null) === 'ACTIVE'
                && ($data['series_code'] ?? null) === 'M42_'.strtoupper(substr($this->sandbox->nonce,0,12)),
            'getNumberingSeries' => is_array($data) && (string)($data['id'] ?? '') === $this->series,
            'updateNumberingSeries' => is_array($data) && ($data['name'] ?? null) === 'M42 protected renamed',
            'setNumberingSeriesState' => is_array($data) && ($data['status'] ?? null) === 'INACTIVE',
            'getBusinessWorkflowStates' => is_array($data) && ($data['mode'] ?? null) === 'LIFECYCLE'
                && ($data['initial'] ?? null) === 'ACTIVE'
                && in_array('INACTIVE',$data['states'] ?? [],true),
            'getBusinessWorkflowOptions' => is_array($data) && ($data['current'] ?? null) === 'ACTIVE'
                && in_array(['action_code'=>'archive','status'=>'INACTIVE'],$data['options'] ?? [],true),
            'executeBusinessWorkflowAction' => is_array($data) && ($data['status'] ?? null) === 'INACTIVE'
                && ($data['action_code'] ?? null) === 'archive',
            default => false,
        };
        if (!$ok) throw new M34BFailure('M4 success JSON data shape/semantics mismatch for '.$name);
    }

    private function probeSettingsRollback(M42Http $client): void
    {
        $trigger='m42_audit_forced_rollback';
        $this->fixture->exec('CREATE TRIGGER '.M34BSafety::identifier($trigger).' BEFORE INSERT ON '
            .$this->fixture->table('configuration_audit_events')
            ." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='M42_AUDIT_FORCED_FAILURE'");
        try {
            $body=['context'=>['type'=>'SYSTEM'],
                'changes'=>[['key'=>'Display.quantityDecimals','value'=>6]]];
            $before=$this->fixture->snapshot();
            $reply=$client->request('PATCH','/api/v1/display-settings',$body,'en');
            $this->wire($reply,'en');
            if ($reply['status']<400 || $before!==$this->fixture->snapshot()) {
                throw new M34BFailure('M4 Settings audit failure was not rolled back atomically');
            }
            $this->results[]=['case'=>'settings_forced_audit_sql_rollback','pass'=>true,'status'=>$reply['status']];
        } finally {$this->fixture->exec('DROP TRIGGER IF EXISTS '.M34BSafety::identifier($trigger));}
    }

    private function probeNumberingNegatives(M42Http $client): void
    {
        $base='/api/v1/resources/'.$this->actor['resource'].'/numbering-series';
        $payload=$this->cases()['createNumberingSeries'][2];
        $this->assertRejected($client,'numbering_duplicate_code','POST',$base,$payload);
        $this->assertRejected($client,'numbering_pattern_collision','POST',$base,[
            ...$payload,'series_code'=>'M42_COLLISION_'.substr($this->sandbox->nonce,0,7),
            'is_default'=>false,'pattern'=>'M42-LIVE-{YYYY}-{SEQ:5}',
        ]);
        $this->assertRejected($client,'numbering_active_default','POST',$base,[
            ...$payload,'series_code'=>'M42_DEFAULT_'.substr($this->sandbox->nonce,0,7),
            'pattern'=>'M42-NEW-{YYYY}-{SEQ:4}',
        ]);
        $this->fixture->insert('auto_numbering_counters',[
            'numbering_series_id'=>$this->series,
            'scope_organization_id'=>$this->actor['root'],
            'period_key'=>'2026','last_value'=>1,'created_at'=>gmdate('Y-m-d H:i:s'),
        ]);
        $this->assertRejected($client,'numbering_issued_code_immutable','PATCH',$base.'/'.$this->series,[
            'series_code'=>'M42_NEVER_REASSIGN',
        ]);
    }

    private function assertRejected(M42Http $client,string $case,string $method,string $path,array $body): void
    {
        $before=$this->fixture->snapshot();
        $reply=$client->request($method,$path,$body,'en');
        $this->wire($reply,'en');
        if ($reply['status']<400 || $reply['status']>=500 || $before!==$this->fixture->snapshot()) {
            throw new M34BFailure('M4 negative case '.$case.' did not reject atomically (HTTP '.$reply['status'].')');
        }
        $this->results[]=['case'=>$case,'status'=>$reply['status'],'pass'=>true,'changed_tables'=>[]];
    }

    private function probeWorkflowNegatives(M42Http $client): void
    {
        $path='/api/v1/business-workflow/organization/'.$this->actor['root'].'/actions/archive';
        $now=gmdate('Y-m-d H:i:s');
        $policy=$this->fixture->insert('policies',[
            'organization_id'=>$this->actor['root'],
            'resource_id'=>$this->actor['organization_resource'],
            'state_id'=>null,'participant_type'=>'GROUP',
            'participant_ref_id'=>$this->actor['group'],'status'=>'ACTIVE','created_at'=>$now,
        ]);
        $this->fixture->insert('policy_actions',[
            'policy_id'=>$policy,'resource_action_id'=>$this->actor['organization_action_archive'],
            'effect'=>'ABSOLUTE_DENY','created_at'=>$now,
        ]);
        try {
            $reply=$this->assertCase('workflow_absolute_deny',$client,'POST',$path,[],403,[]);
            if (($reply['json']['error']['code']??null)!=='AUTH_POLICY_DENIED') {
                throw new M34BFailure('M4 workflow ABSOLUTE_DENY has wrong error code');
            }
        } finally {
            $this->fixture->exec('DELETE FROM '.$this->fixture->table('policy_actions').' WHERE policy_id=?',[$policy]);
            $this->fixture->exec('DELETE FROM '.$this->fixture->table('policies').' WHERE id=?',[$policy]);
        }
        $trigger='m42_workflow_forced_rollback';
        $this->fixture->exec('CREATE TRIGGER '.M34BSafety::identifier($trigger).' BEFORE UPDATE ON '
            .$this->fixture->table('organizations')
            ." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='M42_WF_FORCED_FAILURE'");
        try {
            $before=$this->fixture->snapshot();
            $reply=$client->request('POST',$path,[],'en');
            $this->wire($reply,'en');
            if ($reply['status']<400 || $before!==$this->fixture->snapshot()) {
                throw new M34BFailure('M4 Workflow forced SQL error was not rolled back atomically');
            }
            $this->results[]=['case'=>'workflow_forced_sql_rollback','pass'=>true,'status'=>$reply['status']];
        } finally {$this->fixture->exec('DROP TRIGGER IF EXISTS '.M34BSafety::identifier($trigger));}
    }
}
