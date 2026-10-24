<?php

declare(strict_types=1);

/** Dedicated, bounded XAMPP loopback transport. Never contacts remote API servers. */
final class M63A1MediaTransport
{
    private const HOST = 'api.monmin.net';
    /** @var array<string,string> */
    private array $cookies = [];
    private string $csrfHeader = 'X-CSRF-TOKEN';
    private string $csrfToken = '';

    /** @return array{status:int,language:string,content_type:string,json:mixed,bytes:string} */
    private function send(string $method, string $path, ?string $body = null, ?string $contentType = null, string $locale = 'en', bool $static = false): array
    {
        if (!in_array($locale, ['en','vi'], true)) throw new InvalidArgumentException('Invalid locale.');
        if ($static) {
            if ($method !== 'GET' || preg_match('~\A/uploads/20[0-9]{2}/[0-9]{8}product[0-9]{7}\.(?:png|jpg|webp)\z~D', $path) !== 1) {
                throw new InvalidArgumentException('Static image path refused.');
            }
        } else {
            $auth = in_array([$method, $path], [
                ['GET','/api/v1/auth/csrf'], ['POST','/api/v1/auth/login'], ['GET','/api/v1/auth/me'],
            ], true);
            $product = preg_match('~\A/api/v1/products/[1-9][0-9]{0,17}(?:/image)?\z~D', $path) === 1;
            if (!$auth && (!$product || !in_array($method, ['GET','POST','DELETE'], true)
                || ($method !== 'GET' && !str_ends_with($path, '/image'))
                || ($method === 'GET' && str_ends_with($path, '/image')))) {
                throw new InvalidArgumentException('Non-allowlisted media QA request refused.');
            }
        }
        $headers = ['Host: '.self::HOST, 'Accept: '.($static?'image/*':'application/json'),
            'Accept-Language: '.$locale, 'Cache-Control: no-cache', 'Connection: close'];
        if ($this->cookies !== [] && !$static) {
            $cookies = [];
            foreach ($this->cookies as $key => $value) $cookies[] = $key.'='.$value;
            $headers[] = 'Cookie: '.implode('; ', $cookies);
        }
        if ($this->csrfToken !== '' && !$static) $headers[] = $this->csrfHeader.': '.$this->csrfToken;
        if ($contentType !== null) $headers[] = 'Content-Type: '.$contentType;
        $context = stream_context_create([
            'http'=>['method'=>$method, 'header'=>implode("\r\n", $headers),
                'content'=>$body ?? '', 'ignore_errors'=>true, 'follow_location'=>0, 'timeout'=>25],
            'ssl'=>['peer_name'=>self::HOST, 'SNI_enabled'=>true, 'SNI_server_name'=>self::HOST,
                'verify_peer'=>false, 'verify_peer_name'=>false, 'allow_self_signed'=>true],
        ]);
        $raw = @file_get_contents('https://127.0.0.1'.$path, false, $context);
        if (!is_string($raw)) throw new RuntimeException('Local Apache HTTPS loopback unavailable.');
        if (strlen($raw)> 4*1024*1024) throw new RuntimeException('Unexpectedly large HTTP response.');
        $lines = $http_response_header ?? [];
        $status=0; $parsed=[];
        foreach ($lines as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $line, $match) === 1) {
                $status=(int)$match[1]; $parsed=[]; continue;
            }
            if (!str_contains($line, ':')) continue;
            [$name,$value] = explode(':',$line,2);
            $name=strtolower(trim($name)); $value=trim($value);
            if ($name === 'set-cookie' && !$static) {
                $pair=explode(';',$value,2)[0];
                if (str_contains($pair,'=')) {
                    [$key,$val]=explode('=',$pair,2);
                    if (preg_match('/\A[a-zA-Z0-9_-]+\z/D',$key)===1 && !strpbrk($val,"\r\n")) {
                        $this->cookies[$key]=$val;
                    }
                }
            } else { $parsed[$name]=$value; }
        }
        if ($status<100 || $status>599) throw new RuntimeException('Invalid HTTP status.');
        if (!$static && isset($parsed['x-csrf-token']) && $parsed['x-csrf-token'] !== '') {
            $this->csrfToken = $parsed['x-csrf-token'];
        }
        try { $json=$raw==='' ? null : json_decode($raw,true,512,JSON_THROW_ON_ERROR); }
        catch (JsonException) { $json=null; }
        return ['status'=>$status, 'language'=>(string)($parsed['content-language']??''),
            'content_type'=>(string)($parsed['content-type']??''), 'json'=>$json, 'bytes'=>$static?$raw:''];
    }

    public function login(string $username, string $password): void
    {
        if ($username === '' || $password === '') throw new RuntimeException('DEV credentials required.');
        $csrf=$this->send('GET','/api/v1/auth/csrf');
        $data=$csrf['json']['data']??null;
        if ($csrf['status']!==200 || !is_array($data)
            || !is_string($data['csrf_token']??null) || $data['csrf_token']==='') {
            throw new RuntimeException('Local CSRF initialization failed.');
        }
        $this->csrfToken=$data['csrf_token'];
        if (is_string($data['header_name']??null)
            && preg_match('/\A[A-Za-z0-9_-]+\z/D',$data['header_name'])===1) {
            $this->csrfHeader=$data['header_name'];
        }
        $identity = str_contains($username,'@')?'email':'username';
        $login=$this->json('POST','/api/v1/auth/login',[$identity=>$username,'password'=>$password,'remember'=>false]);
        if ($login['status']!==200) throw new RuntimeException('DEV login failed.');
        $me=$this->send('GET','/api/v1/auth/me');
        $actor=$me['json']['data']['user']??null;
        if ($me['status']!==200 || !is_array($actor)) throw new RuntimeException('DEV actor session invalid.');
        if (str_contains($username,'@')
            ? strcasecmp((string)($actor['email']??''),$username)!==0
            : (string)($actor['username']??'')!==$username) {
            throw new RuntimeException('DEV actor mismatch.');
        }
    }

    /** @return array<string,mixed> */
    public function json(string $method, string $path, ?array $payload=null, string $locale='en'): array
    {
        $body=$payload===null?null:json_encode($payload,JSON_THROW_ON_ERROR);
        return $this->send($method,$path,$body,$payload===null?null:'application/json',$locale);
    }

    /** @return array<string,mixed> */
    public function multipart(string $path, ?string $bytes, string $mime='image/png', string $locale='en'): array
    {
        $boundary='----M63A1'.bin2hex(random_bytes(12));
        if ($bytes === null) {
            $body='--'.$boundary."\r\nContent-Disposition: form-data; name=\"unused\"\r\n\r\n1\r\n--".$boundary."--\r\n";
        } else {
            if (!in_array($mime,['image/png','image/gif'],true) || strlen($bytes)>3*1024*1024+100) {
                throw new InvalidArgumentException('Unreviewed multipart media content.');
            }
            $ext=$mime==='image/gif'?'gif':'png';
            $body='--'.$boundary."\r\nContent-Disposition: form-data; name=\"image\"; filename=\"m63a1-fixture.".$ext."\"\r\n"
                .'Content-Type: '.$mime."\r\n\r\n".$bytes."\r\n--".$boundary."--\r\n";
        }
        return $this->send('POST',$path,$body,'multipart/form-data; boundary='.$boundary,$locale);
    }

    /** @return array<string,mixed> */
    public function staticImage(string $url): array
    {
        M63A1MediaPlan::filename($url);
        return $this->send('GET',$url,null,null,'en',true);
    }
}
