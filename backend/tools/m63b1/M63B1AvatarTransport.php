<?php

declare(strict_types=1);

/** Narrow local Apache-only authenticated HTTP client; does not contact remote hosts. */
final class M63B1AvatarTransport
{
    private const HOST='api.monmin.net';
    /** @var array<string,string> */
    private array $cookies=[];
    private string $csrfHeader='X-CSRF-TOKEN';
    private string $csrfToken='';
    private readonly string $avatarPath;

    public function __construct(string $targetUserId)
    {
        $this->avatarPath='/api/v1/users/'.M63B1AvatarPlan::userId($targetUserId).'/avatar';
    }

    /** @return array{status:int,language:string,content_type:string,json:mixed,bytes:string} */
    private function send(string $method,string $path,?string $body=null,?string $contentType=null,string $locale='en',bool $static=false):array
    {
        if (!in_array($locale,['en','vi'],true)) throw new InvalidArgumentException('Unsupported QA locale.');
        if ($static) {
            if ($method!=='GET') throw new InvalidArgumentException('Static read must use GET.');
            M63B1AvatarPlan::filename($path);
        } elseif (!in_array([$method,$path],[
            ['GET','/api/v1/auth/csrf'],['POST','/api/v1/auth/login'],['GET','/api/v1/auth/me'],
            ['POST',$this->avatarPath],['DELETE',$this->avatarPath],
        ],true)) {
            throw new InvalidArgumentException('HTTP route outside avatar QA allowlist.');
        }
        $headers=['Host: '.self::HOST,'Accept: '.($static?'image/*':'application/json'),
            'Accept-Language: '.$locale,'Cache-Control: no-cache','Connection: close'];
        if (!$static && $this->cookies!==[]) {
            $cookies=[];
            foreach ($this->cookies as $k=>$v) $cookies[]=$k.'='.$v;
            $headers[]='Cookie: '.implode('; ',$cookies);
        }
        if (!$static && $this->csrfToken!=='') $headers[]=$this->csrfHeader.': '.$this->csrfToken;
        if ($contentType!==null) $headers[]='Content-Type: '.$contentType;
        $context=stream_context_create([
            'http'=>['method'=>$method,'header'=>implode("\r\n",$headers),
                'content'=>$body??'','ignore_errors'=>true,'follow_location'=>0,'timeout'=>25],
            'ssl'=>['peer_name'=>self::HOST,'SNI_enabled'=>true,'SNI_server_name'=>self::HOST,
                'verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true],
        ]);
        $raw=@file_get_contents('https://127.0.0.1'.$path,false,$context);
        if (!is_string($raw)) throw new RuntimeException('Local Apache HTTPS loopback is unavailable.');
        if (strlen($raw)>4*1024*1024) throw new RuntimeException('Unexpected oversized HTTP reply.');
        $status=0;$parsed=[];
        foreach (($http_response_header??[]) as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i',$line,$m)===1) {$status=(int)$m[1];$parsed=[];continue;}
            if (!str_contains($line,':'))continue;
            [$name,$value]=explode(':',$line,2);
            $name=strtolower(trim($name));$value=trim($value);
            if ($name==='set-cookie' && !$static) {
                $pair=explode(';',$value,2)[0];
                if (str_contains($pair,'=')) {
                    [$key,$val]=explode('=',$pair,2);
                    if (preg_match('/\A[A-Za-z0-9_-]+\z/D',$key)===1 && strpbrk($val,"\r\n")===false) {
                        $this->cookies[$key]=$val;
                    }
                }
            } else {$parsed[$name]=$value;}
        }
        if ($status<100 || $status>599) throw new RuntimeException('Invalid loopback HTTP response.');
        if (!$static && is_string($parsed['x-csrf-token']??null) && $parsed['x-csrf-token']!=='') {
            $this->csrfToken=$parsed['x-csrf-token'];
        }
        try {$json=$raw===''?null:json_decode($raw,true,512,JSON_THROW_ON_ERROR);}
        catch (JsonException){$json=null;}
        return ['status'=>$status,'language'=>(string)($parsed['content-language']??''),
            'content_type'=>(string)($parsed['content-type']??''),'json'=>$json,'bytes'=>$static?$raw:''];
    }

    /** @return array<string,mixed> */
    public function login(string $username,string $password):array
    {
        if ($username==='' || $password==='') throw new RuntimeException('DEV login credentials required.');
        $csrf=$this->send('GET','/api/v1/auth/csrf');
        $data=$csrf['json']['data']??null;
        if ($csrf['status']!==200 || !is_array($data) || !is_string($data['csrf_token']??null) || $data['csrf_token']==='') {
            throw new RuntimeException('CSRF initialization failed.');
        }
        $this->csrfToken=$data['csrf_token'];
        if (is_string($data['header_name']??null) && preg_match('/\A[A-Za-z0-9_-]+\z/D',$data['header_name'])===1) {
            $this->csrfHeader=$data['header_name'];
        }
        $key=str_contains($username,'@')?'email':'username';
        $login=$this->json('POST','/api/v1/auth/login',[$key=>$username,'password'=>$password,'remember'=>false]);
        if ($login['status']!==200) throw new RuntimeException('DEV login failed.');
        $me=$this->me();
        $actor=$me['json']['data']['user']??null;
        if ($me['status']!==200 || !is_array($actor)
            || (str_contains($username,'@')
                ? strcasecmp((string)($actor['email']??''),$username)!==0
                : (string)($actor['username']??'')!==$username)) {
            throw new RuntimeException('Authenticated actor identity mismatch.');
        }
        return $me;
    }

    /** @return array<string,mixed> */
    public function me():array {return $this->send('GET','/api/v1/auth/me');}

    /** @param array<string,mixed>|null $payload @return array<string,mixed> */
    public function json(string $method,string $path,?array $payload=null,string $locale='en'):array
    {
        $body=$payload===null?null:json_encode($payload,JSON_THROW_ON_ERROR);
        return $this->send($method,$path,$body,$payload===null?null:'application/json',$locale);
    }

    /** @return array<string,mixed> */
    public function multipart(?string $bytes,string $mime='image/png',string $locale='en'):array
    {
        $boundary='----M63B1'.bin2hex(random_bytes(12));
        if ($bytes===null) {
            $body='--'.$boundary."\r\nContent-Disposition: form-data; name=\"unused\"\r\n\r\n1\r\n--".$boundary."--\r\n";
        } else {
            if (!in_array($mime,['image/png','image/gif'],true) || strlen($bytes)>2*1024*1024+100) {
                throw new InvalidArgumentException('Unsafe QA media content.');
            }
            $ext=$mime==='image/gif'?'gif':'png';
            $body='--'.$boundary."\r\nContent-Disposition: form-data; name=\"image\"; filename=\"m63b1-sample.".$ext."\"\r\n"
                .'Content-Type: '.$mime."\r\n\r\n".$bytes."\r\n--".$boundary."--\r\n";
        }
        return $this->send('POST',$this->avatarPath,$body,'multipart/form-data; boundary='.$boundary,$locale);
    }

    /** @return array<string,mixed> */
    public function delete():array{return $this->json('DELETE',$this->avatarPath);}

    /** @return array<string,mixed> */
    public function staticImage(string $url):array
    {
        M63B1AvatarPlan::filename($url);
        return $this->send('GET',$url,null,null,'en',true);
    }
}
