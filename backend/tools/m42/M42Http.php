<?php

declare(strict_types=1);

require_once __DIR__.'/../m34b/M34BSafety.php';
require_once __DIR__.'/../m34/M34Core.php';

/** Loopback-only authenticated HTTP client with one allowlisted Settings query. */
final class M42Http
{
    private array $cookies=[];
    private string $csrf='';
    private string $csrfHeader='X-CSRF-TOKEN';

    public function __construct(private readonly string $origin) { M34BSafety::assertUrl($origin); }

    /** @return array{status:int,headers:array<string,string>,json:mixed,raw:string} */
    public function request(string $method, string $path, ?array $body=null, string $locale='en', string $csrfMode='auto'): array
    {
        if (!in_array($method,['GET','POST','PATCH'],true)
            || !in_array($locale,['en','vi'],true)
            || !in_array($csrfMode,['auto','invalid','omit'],true)
            || preg_match('#\A/api/v1/[a-zA-Z0-9/_.-]+(?:\?context_type=(?:SYSTEM|ORGANIZATION)(?:&organization_id=[1-9][0-9]*)?)?\z#D',$path)!==1
            || str_contains($path,'..')
            || (str_contains($path,'?') && !str_starts_with($path,'/api/v1/display-settings/values?'))) {
            throw new M34BFailure('Refusing unsafe M4 HTTP request');
        }
        $headers=['Accept: application/json','Accept-Language: '.$locale];
        if ($this->cookies!==[]) {
            $cookies=[];
            foreach ($this->cookies as $key=>$value) $cookies[]=$key.'='.$value;
            $headers[]='Cookie: '.implode('; ',$cookies);
        }
        if ($body!==null) $headers[]='Content-Type: application/json';
        if ($csrfMode==='auto' && $this->csrf!=='') $headers[]=$this->csrfHeader.': '.$this->csrf;
        if ($csrfMode==='invalid') $headers[]='X-CSRF-TOKEN: INVALID_M42_CSRF_TOKEN';
        $context=stream_context_create(['http'=>[
            'method'=>$method,'header'=>implode("\r\n",$headers),
            'content'=>$body===null?'':json_encode($body,JSON_THROW_ON_ERROR),
            'ignore_errors'=>true,'follow_location'=>0,'timeout'=>12,
        ]]);
        $raw=@file_get_contents($this->origin.$path,false,$context);
        if ($raw===false) throw new M34BFailure('M4 loopback HTTP transport failed');
        $status=0;$parsed=[];
        foreach ($http_response_header??[] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i',$line,$match)) {$status=(int)$match[1];continue;}
            if (!str_contains($line,':')) continue;
            [$key,$value]=explode(':',$line,2);
            $key=strtolower(trim($key));$value=trim($value);
            if ($key==='set-cookie') {
                $first=explode(';',$value,2)[0];
                if (str_contains($first,'=')) {
                    [$name,$cookie]=explode('=',$first,2);
                    if (preg_match('/^[A-Za-z0-9_-]+$/D',$name)===1 && !preg_match('/[\r\n]/',$cookie)) {
                        $this->cookies[$name]=$cookie;
                    }
                }
                continue;
            }
            $parsed[$key]=$value;
        }
        if ($status<100 || $status>599) throw new M34BFailure('M4 HTTP response status invalid');
        if (($parsed['x-csrf-token']??'')!=='') {
            $this->csrf=$parsed['x-csrf-token'];$this->csrfHeader='X-CSRF-TOKEN';
        }
        $json=null;
        if (trim($raw)!=='') {
            try {$json=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch (JsonException) {$json=null;}
        }
        return ['status'=>$status,'headers'=>$parsed,'json'=>$json,'raw'=>$raw];
    }

    public function login(string $email,string $password): void
    {
        $csrf=$this->request('GET','/api/v1/auth/csrf');
        if ($csrf['status']!==200) throw new M34BFailure('M4 test CSRF bootstrap failed');
        $info=$csrf['json']['data']??null;
        if (!is_array($info) || !is_string($info['csrf_token']??null)) {
            throw new M34BFailure('M4 CSRF bootstrap token absent');
        }
        $this->csrf=$info['csrf_token'];
        $this->csrfHeader=is_string($info['header_name']??null)?$info['header_name']:'X-CSRF-TOKEN';
        $login=$this->request('POST','/api/v1/auth/login',[
            'email'=>$email,'password'=>$password,'remember'=>false,
        ]);
        if ($login['status']!==200) throw new M34BFailure('M4 disposable actor login failed (HTTP '.$login['status'].')');
        if ($this->request('GET','/api/v1/auth/me')['status']!==200) {
            throw new M34BFailure('M4 session verification failed');
        }
    }
}
