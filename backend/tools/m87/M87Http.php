<?php

declare(strict_types=1);

require_once __DIR__ . '/M87Plan.php';

/** Localhost-pinned, GET-only ERP API auditor. POST is permitted ONLY for session login. */
final class M87Http
{
    private array $cookies=[];
    private string $csrf='';
    private string $csrfHeader='X-CSRF-TOKEN';
    private readonly string $host;
    private readonly string $scheme;
    private readonly int $port;

    public function __construct(string $host, string $scheme='https')
    {
        if (!in_array($host,['api.monmin.net','localhost'],true)
            || !in_array($scheme,['https','http'],true)) {
            throw new InvalidArgumentException('Only reviewed local Apache virtual hosts are allowed.');
        }
        $this->host=$host; $this->scheme=$scheme;
        $this->port=$scheme==='https'?443:80;
    }

    /** Pure route/method safety check; can be tested without any HTTP traffic. */
    public static function allowed(string $method,string $path,?array $body=null): bool
    {
        return in_array($method,['GET','POST'],true)
            && preg_match('#\A/api/v1/[a-z0-9/_.-]+(?:\?[a-zA-Z0-9_=&%-]+)?\z#D',$path)===1
            && !str_contains($path,'..')
            && ($method!=='POST' || ($path==='/api/v1/auth/login' && $body!==null))
            && ($method!=='GET' || $body===null);
    }

    /** @return array{status:int,json:mixed} */
    public function request(string $method,string $path,?array $body=null): array
    {
        if (!self::allowed($method,$path,$body)) {
            throw new RuntimeException('M8.7 blocks unreviewed HTTP method/path.');
        }
        $headers=['Host: '.$this->host,'Accept: application/json','Accept-Language: en','Connection: close'];
        if ($this->cookies!==[]) {
            $cookie=[];
            foreach ($this->cookies as $name=>$value) $cookie[]=$name.'='.$value;
            $headers[]='Cookie: '.implode('; ',$cookie);
        }
        if ($body!==null) $headers[]='Content-Type: application/json';
        if ($this->csrf!=='') $headers[]=$this->csrfHeader.': '.$this->csrf;
        $context=stream_context_create([
            'http'=>['method'=>$method,'header'=>implode("\r\n",$headers),
                'content'=>$body===null?'':json_encode($body,JSON_THROW_ON_ERROR),
                'ignore_errors'=>true,'follow_location'=>0,'timeout'=>15],
            // TLS bypass is scoped to the hardcoded 127.0.0.1 loopback, NEVER remote internet.
            'ssl'=>['peer_name'=>$this->host,'SNI_enabled'=>true,'SNI_server_name'=>$this->host,
                'verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true],
        ]);
        $raw=@file_get_contents($this->scheme.'://127.0.0.1:'.$this->port.$path,false,$context,0,2_000_001);
        if (!is_string($raw)) throw new RuntimeException('Local HTTP request failed.');
        if (strlen($raw)>2_000_000) throw new RuntimeException('Local HTTP response exceeded 2MB safety bound.');
        $headers=$http_response_header??[]; $status=0;
        foreach ($headers as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i',$line,$matches)) {
                $status=(int)$matches[1];
                continue;
            }
            if (stripos($line,'Set-Cookie:')===0) {
                $piece=explode(';',trim(substr($line,11)),2)[0];
                if (str_contains($piece,'=')) {
                    [$name,$value]=explode('=',$piece,2);
                    if (preg_match('/\A[A-Za-z0-9_-]+\z/D',$name)===1) $this->cookies[$name]=$value;
                }
            }
            if (stripos($line,'X-CSRF-TOKEN:')===0) $this->csrf=trim(substr($line,13));
        }
        if ($status<100 || $status>599) throw new RuntimeException('Malformed local HTTP status.');
        $json=$raw===''?null:json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        return ['status'=>$status,'json'=>$json];
    }

    public function login(string $name,string $password,string $expectedId): void
    {
        if ($name==='' || $password==='') throw new RuntimeException('DEV account credentials missing from environment.');
        $csrf=$this->request('GET','/api/v1/auth/csrf');
        if ($csrf['status']!==200 || !is_array($csrf['json']['data']??null)) {
            throw new RuntimeException('DEV CSRF bootstrap failed.');
        }
        $data=$csrf['json']['data'];
        if (!is_string($data['csrf_token']??null) || $data['csrf_token']==='') {
            throw new RuntimeException('DEV CSRF token missing.');
        }
        $this->csrf=$data['csrf_token'];
        if (is_string($data['header_name']??null)
            && preg_match('/\A[A-Za-z0-9_-]+\z/D',$data['header_name'])===1) {
            $this->csrfHeader=$data['header_name'];
        }
        $login=$this->request('POST','/api/v1/auth/login',[
            str_contains($name,'@')?'email':'username'=>$name,
            'password'=>$password,'remember'=>false,
        ]);
        if ($login['status']!==200) throw new RuntimeException('DEV actor authentication failed.');
        $me=$this->request('GET','/api/v1/auth/me');
        if ($me['status']!==200 || (string)($me['json']['data']['user']['id']??'')!==$expectedId) {
            throw new RuntimeException('Authenticated HTTP actor does not match SQL audit actor ID.');
        }
    }
}
