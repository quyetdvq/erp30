<?php

declare(strict_types=1);

require_once __DIR__ . '/M62B2DevPlan.php';

/** Local Apache HTTPS transport, independent of production OAuth/third-party HTTP. */
final class M62B2DevTransport
{
    private const HOST = 'api.monmin.net';
    /** @var array<string,string> */
    private array $cookies = [];
    private string $csrfHeader = 'X-CSRF-TOKEN';
    private string $csrfToken = '';

    /** @param ?array<string,mixed> $payload @return array{status:int,language:string,json:mixed} */
    public function request(string $method, string $path, ?array $payload = null, string $locale = 'en'): array
    {
        if (!M62B2DevPlan::allowed($method, $path) || !in_array($locale, ['en', 'vi'], true)) {
            throw new RuntimeException('DEV Product transport rejected a non-allowlisted method/path.');
        }
        $headers = ['Host: '.self::HOST, 'Accept: application/json', 'Accept-Language: '.$locale, 'Connection: close'];
        if ($this->cookies !== []) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) $pairs[] = $name.'='.$value;
            $headers[] = 'Cookie: '.implode('; ', $pairs);
        }
        if ($payload !== null) $headers[] = 'Content-Type: application/json';
        if ($this->csrfToken !== '') $headers[] = $this->csrfHeader.': '.$this->csrfToken;
        $context = stream_context_create([
            'http' => [
                'method' => $method, 'header' => implode("\r\n", $headers),
                'content' => $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR),
                'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 20,
            ],
            'ssl' => [
                'peer_name' => self::HOST, 'SNI_enabled' => true, 'SNI_server_name' => self::HOST,
                'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true,
            ],
        ]);
        $body = @file_get_contents('https://127.0.0.1'.$path, false, $context);
        if (!is_string($body)) throw new RuntimeException('Local Apache HTTPS was unreachable.');
        $lines = $http_response_header ?? [];
        $status = 0;
        $resultHeaders = [];
        foreach ($lines as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $line, $m) === 1) {
                $status = (int)$m[1]; $resultHeaders = []; continue;
            }
            if (!str_contains($line, ':')) continue;
            [$name, $value] = explode(':', $line, 2);
            $name = strtolower(trim($name)); $value = trim($value);
            if ($name === 'set-cookie') {
                $cookie = explode(';', $value, 2)[0];
                if (str_contains($cookie, '=')) {
                    [$cookieName, $cookieValue] = explode('=', $cookie, 2);
                    if (preg_match('/\A[a-zA-Z0-9_-]+\z/D', $cookieName) === 1
                        && !strpbrk($cookieValue, "\r\n")) $this->cookies[$cookieName] = $cookieValue;
                }
            } else $resultHeaders[$name] = $value;
        }
        if ($status < 100 || $status > 599) throw new RuntimeException('Malformed local HTTP response.');
        if (isset($resultHeaders['x-csrf-token']) && $resultHeaders['x-csrf-token'] !== '') {
            $this->csrfToken = $resultHeaders['x-csrf-token'];
        }
        try { $json = $body === '' ? null : json_decode($body, true, 512, JSON_THROW_ON_ERROR); }
        catch (JsonException) { $json = null; }
        return ['status' => $status, 'language' => (string)($resultHeaders['content-language'] ?? ''), 'json' => $json];
    }

    public function login(string $username, string $password, ?callable $observe = null): void
    {
        if ($username === '' || $password === '') throw new RuntimeException('DEV credentials required.');
        $csrf = $this->request('GET', '/api/v1/auth/csrf');
        if ($observe !== null) $observe('csrf', $csrf);
        $data = $csrf['json']['data'] ?? null;
        if ($csrf['status'] !== 200 || !is_array($data)) throw new RuntimeException('DEV CSRF bootstrap failed.');
        $token = $data['csrf_token'] ?? null;
        $header = $data['header_name'] ?? null;
        if (!is_string($token) || $token === '') throw new RuntimeException('DEV CSRF token missing.');
        $this->csrfToken = $token;
        if (is_string($header) && preg_match('/\A[a-zA-Z0-9_-]+\z/D', $header) === 1) $this->csrfHeader = $header;
        $login = $this->request('POST', '/api/v1/auth/login', [
            str_contains($username, '@') ? 'email' : 'username' => $username,
            'password' => $password, 'remember' => false,
        ]);
        if ($observe !== null) $observe('login', $login);
        if ($login['status'] !== 200) throw new RuntimeException('DEV login rejected.');
        $me = $this->request('GET', '/api/v1/auth/me');
        if ($observe !== null) $observe('me', $me);
        $actor = $me['json']['data']['user'] ?? null;
        if ($me['status'] !== 200 || !is_array($actor)) throw new RuntimeException('DEV actor session not established.');
        $identityMatches = str_contains($username, '@')
            ? strcasecmp((string)($actor['email'] ?? ''), $username) === 0
            : (string)($actor['username'] ?? '') === $username;
        if (!$identityMatches) throw new RuntimeException('DEV actor mismatch.');
    }
}
