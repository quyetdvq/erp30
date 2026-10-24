<?php

declare(strict_types=1);

require_once __DIR__.'/M62B4Plan.php';
require_once __DIR__.'/../m62b/M62B2DevTransport.php';
require_once __DIR__.'/../m62b/M62B2DevPreflight.php';

/** Explicit DEV-only auto issuance race and post-insert COMBO counter rollback. */
final class M62B4DevAtomicity
{
    /** @var array<string,resource> */
    private array $workers=[];
    /** @var array<string,array<int,resource>> */
    private array $pipes=[];

    public function __construct(private readonly string $root) {}

    /** @return array<string,mixed> */
    public function run(string $group,string $uom,string $user,string $password,bool $preflight): array
    {
        M62B4Plan::id($group); M62B4Plan::id($uom);
        $marker=M62B4Plan::marker(bin2hex(random_bytes(6)));
        $report=['verdict'=>'FAIL_DEV_PRODUCT_ATOMICITY','phase'=>'sql_readiness','marker'=>$marker,
            'group_id'=>$group,'uom_id'=>$uom,'preflight_only'=>$preflight,
            'sql_ready'=>false,'http_preflight'=>false,'two_independent_workers'=>false,
            'auto_codes_distinct'=>false,'counter_advanced'=>false,'combo_http_rejected'=>false,
            'counter_rollback_sql'=>false,'sql_products'=>null,'worker_results'=>null,
            'fixture_retained'=>!$preflight,'baseline_parity'=>'NOT_TESTED',
            'innodb_lock_wait_evidence'=>'NOT_TESTED'];
        $dir=null;
        try {
            // SQL command uses the exact same configured DEV database as CI4 Apache;
            // no SQL mutation ever takes place in this CLI runner.
            $before=$this->snapshot($marker);
            $report['sql_ready']=($before['ready']??false)===true;
            $report['series_code']=$before['series_code']??null;
            $report['numbering_reason']=$before['reason']??null;
            if (!$report['sql_ready']) throw new RuntimeException('No eligible ACTIVE Product AUTO default');
            foreach (['a','b','rollback'] as $kind) {
                if (($before['products'][$kind]??null)!==null) throw new RuntimeException('Marker already used');
            }
            $report['phase']='http_preflight';
            $http=new M62B2DevTransport();
            $http->login($user,$password);
            foreach (['product-groups'=>$group,'product-uoms'=>$uom] as $endpoint=>$id) {
                $check=M62B2DevPreflight::reference($endpoint,$id,
                    $http->request('GET','/api/v1/'.$endpoint.'/'.$id));
                if (!$check['pass']) {
                    $report[$endpoint.'_preflight']=['http_status'=>$check['http_status'],
                        'reason'=>$check['reason'],'error_code'=>$check['error_code']];
                    throw new RuntimeException('DEV Product reference not ACTIVE/visible');
                }
            }
            $report['http_preflight']=true;
            if ($preflight) {
                $report['phase']='complete';
                $report['verdict']='PASS_DEV_PRODUCT_ATOMICITY_PREFLIGHT';
                return $report;
            }
            $report['phase']='launch_writers';
            $dir=$this->makeDir($marker);
            foreach (['a','b'] as $role) $this->launch($dir,$marker,$role,$group,$uom);
            $this->wait($dir,['a.ready','b.ready'],35);
            $report['two_independent_workers']=true;
            $report['phase']='two_auto_create_requests';
            file_put_contents($dir.'/go','GO',LOCK_EX);
            $this->wait($dir,['a.json','b.json'],65);
            $results=[];
            foreach (['a','b'] as $role) {
                $json=json_decode((string)file_get_contents($dir.'/'.$role.'.json'),true,512,JSON_THROW_ON_ERROR);
                if (!is_array($json)) throw new RuntimeException('Unexpected worker response');
                $results[$role]=$json;
            }
            $report['worker_results']=$results;
            $this->closeWorkers();
            $report['phase']='audit_two_writers';
            $after=$this->snapshot($marker);
            $report['sql_products']=$after['products']??null;
            $report['auto_codes_distinct']=M62B4Plan::issued($before,$after,$results['a'],$results['b']);
            $report['counter_advanced']=$report['auto_codes_distinct'];
            if (!$report['auto_codes_distinct']) throw new RuntimeException('Independent Product writers failed SQL verification');
            $report['phase']='combo_auto_rollback';
            $combo=$http->request('POST','/api/v1/products',M62B4Plan::rejectedCombo($marker,$group,$uom));
            $report['combo_http_status']=$combo['status'];
            $error=$combo['json']['error']['code']??null;
            $report['combo_error_code']=is_string($error)&&preg_match('/\A[A-Z][A-Z0-9_]{1,63}\z/D',$error)===1?$error:null;
            $report['combo_http_rejected']=$combo['status']===404 && $report['combo_error_code']==='PRODUCT_NOT_FOUND';
            if (!$report['combo_http_rejected']) throw new RuntimeException('COMBO not rejected at missing component lookup');
            $report['phase']='audit_counter_rollback';
            $final=$this->snapshot($marker);
            $report['counter_rollback_sql']=M62B4Plan::rolledBack($after,$final);
            $report['sql_products']=$final['products']??null;
            if (!$report['counter_rollback_sql']) throw new RuntimeException('SQL counter/Product rollback not demonstrated');
            $report['phase']='complete';
            $report['verdict']='PASS_DEV_PRODUCT_NUMBERING_COMBO_ATOMICITY';
        } catch (Throwable $e) {
            // A failed/aborted two-writer run may have committed one Product.
            // Capture only a bounded SELECT-only inventory for manual follow-up.
            if (!$preflight && $report['sql_ready']) {
                try { $report['sql_products']=$this->snapshot($marker)['products']??null; }
                catch (Throwable) { $report['sql_recovery']='UNAVAILABLE'; }
            }
            // Only class and phase: no raw SQL, cookies, credentials, CSRF or HTTP body.
            $report['failure_class']=get_class($e);
        } finally {
            $this->closeWorkers();
            if ($dir!==null && is_dir($dir)) {
                foreach (['a.ready','b.ready','a.json','b.json','go'] as $name) @unlink($dir.'/'.$name);
                @rmdir($dir);
            }
        }
        return $report;
    }

    /** @return array<string,mixed> */
    private function snapshot(string $marker): array
    {
        $p=proc_open([PHP_BINARY,$this->root.'/backend/spark','m62b4:snapshot',$marker],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$this->root.'/backend');
        if (!is_resource($p)) throw new RuntimeException('Could not start SELECT-only snapshot');
        fclose($pipes[0]);
        $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($p)!==0 || !is_string($output)) throw new RuntimeException('Read-only snapshot failed');
        return M62B4Plan::parseSnapshot($output,$marker);
    }

    private function makeDir(string $marker): string
    {
        $base=$this->root.'/backend/writable/m62b4/runs';
        if (!is_dir($base) && !mkdir($base,0700,true) && !is_dir($base)) throw new RuntimeException('No secure QA directory');
        if (is_link($base)) throw new RuntimeException('Symlink run directory rejected');
        $dir=$base.'/run_'.$marker;
        if (!mkdir($dir,0700) || is_link($dir)) throw new RuntimeException('Nonce run directory not exclusive');
        return $dir;
    }

    private function launch(string $dir,string $marker,string $role,string $group,string $uom): void
    {
        $cmd=[PHP_BINARY,__DIR__.'/worker.php',$marker,$role,$group,$uom,$dir];
        $p=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,
            $this->root.'/backend');
        if (!is_resource($p)) throw new RuntimeException('Could not launch independent DEV writer');
        fclose($pipes[0]);
        $this->workers[$role]=$p;
        $this->pipes[$role]=[$pipes[1],$pipes[2]];
    }

    /** @param list<string> $files */
    private function wait(string $dir,array $files,int $seconds): void
    {
        $until=microtime(true)+$seconds;
        do {
            $pending=array_values(array_filter($files,static fn(string $file):bool=>!is_file($dir.'/'.$file)));
            if ($pending===[]) return;
            foreach ($this->workers as $role=>$p) {
                $state=proc_get_status($p);
                $expected=str_ends_with($files[0],'.ready') ? $role.'.ready' : $role.'.json';
                if (!$state['running'] && !is_file($dir.'/'.$expected)) {
                    throw new RuntimeException('DEV worker exited before barrier/result');
                }
            }
            usleep(50000);
        } while (microtime(true)<$until);
        throw new RuntimeException('DEV writer deadline reached; partial committed fixtures may remain');
    }

    private function closeWorkers(): void
    {
        foreach ($this->workers as $role=>$p) {
            $state=proc_get_status($p);
            if ($state['running']) @proc_terminate($p);
            foreach ($this->pipes[$role]??[] as $pipe) if (is_resource($pipe)) fclose($pipe);
            @proc_close($p);
        }
        $this->workers=[]; $this->pipes=[];
    }
}
