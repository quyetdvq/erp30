<?php

declare(strict_types=1);

require_once __DIR__.'/M63B1AvatarPlan.php';
require_once __DIR__.'/M63B1AvatarTransport.php';

/** Self-service DEV-only avatar HTTP, Apache, filesystem and SELECT-only SQL QA. */
final class M63B1DevAvatar
{
    /** @return array<string,mixed> */
    public function run(string $id,string $username,string $password,bool $apply):array
    {
        $id=M63B1AvatarPlan::userId($id);
        $result=['verdict'=>'FAIL_DEV_AVATAR_QA','phase'=>'sql_precheck','target_user_id'=>$id,
            'mode'=>$apply?'EXPLICIT_SELF_AVATAR_WRITE':'READ_ONLY_PREFLIGHT',
            'self_owner_verified'=>false,'sql_verified'=>false,'gd_all_codecs'=>false,
            'negative_cases'=>0,'localized_types'=>0,'upload_http'=>null,'replace_http'=>null,
            'delete_http'=>null,'static_first'=>null,'static_second'=>null,
            'old_gone'=>null,'physical_cleaned'=>false,'final_sql_empty'=>false,
            'http_metadata_cleared'=>false,'image_urls'=>[],
            'last_http'=>null,'admin_delegation'=>'NOT_TESTED',
            'real_http_concurrency'=>'NOT_TESTED','forced_storage_failure'=>'NOT_TESTED',
            'independent_baseline_parity'=>'NOT_TESTED'];
        try {
            $this->audit($id,null);
            $result['sql_verified']=true;
            $result['phase']='login';
            $http=new M63B1AvatarTransport($id);
            $me=$http->login($username,$password);
            $this->trace($result,'auth_me',$me);
            $original=M63B1AvatarPlan::ownEmptyActor($me,$id);
            $result['self_owner_verified']=true;
            $result['phase']='codec_check';
            $result['gd_all_codecs']=M63B1AvatarPlan::codecsReady();
            if (!$result['gd_all_codecs']) throw new RuntimeException('Required GD codecs unavailable.');
            $this->audit($id,null); // avoid stale SQL preflight after login
            if (!$apply) {
                $result['phase']='complete';
                $result['verdict']='PASS_DEV_SELF_AVATAR_PREFLIGHT';
                return $result;
            }
            $result['phase']='negative';
            $gif=base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==',true);
            if (!is_string($gif)) throw new RuntimeException('Cannot create invalid GIF fixture.');
            $messages=[];
            foreach (['en','vi'] as $locale) {
                $missing=$http->multipart(null,'image/png',$locale);
                $this->trace($result,'negative_missing_'.$locale,$missing);
                $messages['missing'][$locale]=M63B1AvatarPlan::negative($missing,'USER_AVATAR_INVALID_CONTENT',$locale);
                $result['negative_cases']++;
                $gifResult=$http->multipart($gif,'image/gif',$locale);
                $this->trace($result,'negative_gif_'.$locale,$gifResult);
                $messages['gif'][$locale]=M63B1AvatarPlan::negative($gifResult,'USER_AVATAR_INVALID_TYPE',$locale);
                $result['negative_cases']++;
            }
            foreach ($messages as $texts) {
                if ($texts['en']===$texts['vi']) throw new RuntimeException('Avatar localization differs from baseline.');
                $result['localized_types']++;
            }
            M63B1AvatarPlan::ownEmptyActor($http->me(),$id);
            $this->audit($id,null);
            $a=M63B1AvatarPlan::sample(1);
            $b=M63B1AvatarPlan::sample(2);
            $result['phase']='upload_first';
            $upload=$http->multipart($a['bytes']);
            $this->trace($result,'upload_first',$upload);
            $result['upload_http']=$upload['status'];
            $urlA=M63B1AvatarPlan::responseUrl($upload,$id);
            $result['image_urls']['first']=$urlA;
            M63B1AvatarPlan::ownAvatar($http->me(),$id,$urlA);
            $this->audit($id,M63B1AvatarPlan::filename($urlA));
            $result['static_first']=$this->staticFile($http,$urlA);
            $result['phase']='replace';
            M63B1AvatarPlan::ownAvatar($http->me(),$id,$urlA);
            $this->audit($id,M63B1AvatarPlan::filename($urlA));
            $replace=$http->multipart($b['bytes']);
            $this->trace($result,'replace',$replace);
            $result['replace_http']=$replace['status'];
            $urlB=M63B1AvatarPlan::responseUrl($replace,$id);
            if ($urlA===$urlB) throw new RuntimeException('Replacement avatar URL reused filename.');
            $result['image_urls']['second']=$urlB;
            M63B1AvatarPlan::ownAvatar($http->me(),$id,$urlB);
            $this->audit($id,M63B1AvatarPlan::filename($urlB));
            $result['static_second']=$this->staticFile($http,$urlB);
            if (is_file(M63B1AvatarPlan::physicalPath($urlA))) {
                throw new RuntimeException('Replaced avatar still exists on filesystem.');
            }
            $gone=$http->staticImage($urlA);
            $result['old_gone']=$gone['status'];
            if ($gone['status']!==404) throw new RuntimeException('Apache still serves replaced avatar.');
            $result['phase']='delete';
            M63B1AvatarPlan::ownAvatar($http->me(),$id,$urlB);
            $this->audit($id,M63B1AvatarPlan::filename($urlB));
            $delete=$http->delete();
            $this->trace($result,'delete',$delete);
            $result['delete_http']=$delete['status'];
            M63B1AvatarPlan::deleted($delete,$id);
            $after=M63B1AvatarPlan::ownEmptyActor($http->me(),$id);
            foreach (['id','username','email','name','active','status','groups','base_permissions'] as $field) {
                if (($original[$field]??null)!==($after[$field]??null)) {
                    throw new RuntimeException('Non-avatar user profile was modified during QA.');
                }
            }
            $result['http_metadata_cleared']=true;
            $this->audit($id,null);
            $result['final_sql_empty']=true;
            if (is_file(M63B1AvatarPlan::physicalPath($urlB))
                || $http->staticImage($urlB)['status']!==404) {
                throw new RuntimeException('Deleted avatar still exists on disk or Apache.');
            }
            $result['physical_cleaned']=true;
            $result['phase']='complete';
            $result['verdict']='PASS_DEV_SELF_AVATAR_HTTP_FS_SQL';
        } catch (Throwable $error) {
            $result['failure_class']=get_class($error);
        }
        return $result;
    }

    /** Bounded read-only SQL verification against the same XAMPP backend .env. */
    private function audit(string $id,?string $filename):void
    {
        $expected=$filename??'EMPTY';
        if ($filename!==null && \App\Shared\Media\ImageUrl::for($filename,'avatar')===null) {
            throw new RuntimeException('Invalid public Avatar filename.');
        }
        $backend=dirname(__DIR__,2);
        $command=[PHP_BINARY,$backend.'/spark','m63b1:db-audit',$id,$expected];
        $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$backend);
        if (!is_resource($process)) throw new RuntimeException('Unable to start read-only SQL audit.');
        fclose($pipes[0]);
        $stdout=stream_get_contents($pipes[1]);
        $stderr=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        $exit=proc_close($process);
        if ($exit!==0 || !is_string($stdout)
            || preg_match('/^M63B1_DB_JSON=(\{[^\r\n]+\})\s*$/m',$stdout,$m)!==1) {
            throw new RuntimeException('Read-only SQL avatar fixture verification did not pass.');
        }
        $result=json_decode($m[1],true,32,JSON_THROW_ON_ERROR);
        if (!is_array($result)) throw new RuntimeException('Invalid SQL snapshot.');
        M63B1AvatarPlan::sqlAudit($result,$id,$filename);
    }

    /** @return int */
    private function staticFile(M63B1AvatarTransport $http,string $url):int
    {
        $path=M63B1AvatarPlan::physicalPath($url);
        $bytes=@file_get_contents($path);
        if (!is_string($bytes) || strlen($bytes)<32 || strlen($bytes)>2*1024*1024
            || (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes)!=='image/png') {
            throw new RuntimeException('Avatar physical file missing or invalid.');
        }
        $static=$http->staticImage($url);
        if ($static['status']!==200
            || strtolower(trim(explode(';',$static['content_type'],2)[0]))!=='image/png'
            || hash('sha256',$static['bytes'])!==hash('sha256',$bytes)) {
            throw new RuntimeException('Apache static avatar content differs from public/upload file.');
        }
        return 200;
    }

    /** @param array<string,mixed> $response */
    private function trace(array &$result,string $phase,array $response):void
    {
        $code=$response['json']['error']['code']??null;
        $result['last_http']=['phase'=>$phase,'status'=>(int)($response['status']??0),
            'error_code'=>is_string($code)&&preg_match('/\A[A-Z][A-Z0-9_]{0,95}\z/D',$code)===1?$code:null];
    }
}
