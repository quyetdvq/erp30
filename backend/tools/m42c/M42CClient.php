<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/m34/M34Core.php';

/** M4 clone-only HTTP client; only the fixed SYSTEM query is additionally permitted. */
final class M42CClient
{
    /** @var array<string,string> */
    private array $cookies = [];
    private string $csrfHeader = 'X-CSRF-TOKEN';
    private string $csrf = '';
    public function __construct(private readonly string $origin) { M34Safety::assertLocalUrl($origin); }

    /** @return array{status:int,headers:array<string,string>,json:mixed,raw:string} */
    public function request(string $method, string $path, ?array $body = null, string $locale = 'en', string $csrfMode = 'auto'): array
    {
        if (! preg_match('#^/api/v1/[a-zA-Z0-9/_.-]+(?:\?context_type=SYSTEM)?$#D', $path) || str_contains($path, '..')) {
            throw new M34Failure('Refusing non-API or unsafe URL path');
        }
        if (! in_array($locale, ['en','vi'], true)) { throw new M34Failure('Only en/vi allowed'); }
        $headers = ['Accept: application/json', 'Accept-Language: ' . $locale];
        if ($this->cookies !== []) {
            $cookie = [];
            foreach ($this->cookies as $name => $value) { $cookie[] = $name . '=' . $value; }
            $headers[] = 'Cookie: ' . implode('; ', $cookie);
        }
        if ($body !== null) { $headers[] = 'Content-Type: application/json'; }
        if ($csrfMode === 'auto' && $this->csrf !== '') { $headers[] = $this->csrfHeader . ': ' . $this->csrf; }
        if ($csrfMode === 'invalid') { $headers[] = $this->csrfHeader . ': INVALID_M34_CSRF_TOKEN'; }
        $content = $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR);
        $ctx = stream_context_create(['http' => [
            'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $content,
            'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 12,
        ]]);
        $url = rtrim($this->origin, '/') . $path;
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) { throw new M34Failure('HTTP request failed for local ' . $method . ' ' . $path); }
        $received = $http_response_header ?? [];
        $status = 0; $parsed = [];
        foreach ($received as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $line, $m)) { $status = (int) $m[1]; continue; }
            if (! str_contains($line, ':')) { continue; }
            [$k,$v] = explode(':', $line, 2);
            $key = strtolower(trim($k)); $value = trim($v);
            if ($key === 'set-cookie') {
                $piece = trim(explode(';', $value, 2)[0]);
                if (str_contains($piece, '=')) {
                    [$n,$c] = explode('=', $piece, 2);
                    if (preg_match('/^[A-Za-z0-9_-]+$/D', $n)) { $this->cookies[$n] = $c; }
                }
                continue;
            }
            $parsed[$key] = $value;
        }
        if ($status < 100 || $status > 599) { throw new M34Failure('Unparseable HTTP response'); }
        if (isset($parsed['x-csrf-token']) && $parsed['x-csrf-token'] !== '') {
            $this->csrf = $parsed['x-csrf-token']; $this->csrfHeader = 'X-CSRF-TOKEN';
        }
        $json = null;
        if (trim($raw) !== '') {
            try { $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
            catch (JsonException) { $json = null; }
        }
        return ['status' => $status, 'headers' => $parsed, 'json' => $json, 'raw' => $raw];
    }

    public function login(string $user, string $password): void
    {
        $csrf = $this->request('GET', '/api/v1/auth/csrf');
        if ($csrf['status'] !== 200) { throw new M34Failure('Could not obtain CSRF token on clone'); }
        $csrfData = $csrf['json']['data'] ?? null;
        if (is_array($csrfData) && is_string($csrfData['csrf_token'] ?? null)) {
            $this->csrf = $csrfData['csrf_token'];
            $this->csrfHeader = is_string($csrfData['header_name'] ?? null) ? $csrfData['header_name'] : 'X-CSRF-TOKEN';
        }
        $response = $this->request('POST', '/api/v1/auth/login', [
            str_contains($user, '@') ? 'email' : 'username' => $user,
            'password' => $password, 'remember' => false,
        ]);
        if ($response['status'] !== 200) { throw new M34Failure('Clone login rejected (HTTP ' . $response['status'] . ')'); }
        $me = $this->request('GET', '/api/v1/auth/me');
        if ($me['status'] !== 200 || ! isset($me['json']['data']['user'])) {
            throw new M34Failure('Clone session auth/me failed');
        }
    }
}
