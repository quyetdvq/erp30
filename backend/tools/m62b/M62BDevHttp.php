<?php

declare(strict_types=1);

require_once __DIR__ . '/M62BDevPlan.php';

/**
 * Local-only authenticated HTTP negative-path probe for Product mutations.
 * Business requests have fixed malformed payloads in M62BDevPlan. No CREATE,
 * PATCH or state transition can succeed if the production validator is working.
 * This is NOT a successful-write, rollback, concurrency or baseline parity test.
 */
final class M62BDevHttp
{
    private const HOST = 'api.monmin.net';
    /** @var array<string,string> */
    private array $cookies = [];
    private string $csrfName = 'X-CSRF-TOKEN';
    private string $csrfToken = '';

    /** @param ?array<string,mixed> $payload
     * @return array{status:int,language:string,json:mixed}
     */
    private function request(string $method, string $path, ?array $payload = null, string $locale = 'en'): array
    {
        if (!M62BDevPlan::pathAllowed($method, $path) || !in_array($locale, ['en', 'vi'], true)) {
            throw new RuntimeException('Only exact reviewed Product DEV HTTP probes are permitted.');
        }
        $headers = [
            'Host: '.self::HOST,
            'Accept: application/json',
            'Accept-Language: '.$locale,
            'Connection: close',
        ];
        if ($this->cookies !== []) {
            $parts = [];
            foreach ($this->cookies as $key => $value) $parts[] = $key.'='.$value;
            $headers[] = 'Cookie: '.implode('; ', $parts);
        }
        if ($payload !== null) $headers[] = 'Content-Type: application/json';
        if ($this->csrfToken !== '') $headers[] = $this->csrfName.': '.$this->csrfToken;
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR),
                'ignore_errors' => true,
                'follow_location' => 0,
                'timeout' => 15,
            ],
            // Connect ONLY to loopback. Host/SNI choose existing local XAMPP vhost.
            // Self-signed DEV certificate is accepted; never use this transport remotely.
            'ssl' => [
                'peer_name' => self::HOST,
                'SNI_enabled' => true,
                'SNI_server_name' => self::HOST,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);
        $body = @file_get_contents('https://127.0.0.1'.$path, false, $context);
        if (!is_string($body)) {
            throw new RuntimeException('Cannot reach local Apache HTTPS through 127.0.0.1:443.');
        }
        $responseHeaders = $http_response_header ?? [];
        $status = 0; $resultHeaders = [];
        foreach ($responseHeaders as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $header, $m) === 1) {
                $status = (int)$m[1]; $resultHeaders = [];
                continue;
            }
            if (!str_contains($header, ':')) continue;
            [$key, $value] = explode(':', $header, 2);
            $key = strtolower(trim($key)); $value = trim($value);
            if ($key === 'set-cookie') {
                $pair = explode(';', $value, 2)[0];
                if (str_contains($pair, '=')) {
                    [$cookieName, $cookieValue] = explode('=', $pair, 2);
                    if (preg_match('/\A[a-zA-Z0-9_-]+\z/D', $cookieName) === 1
                        && !strpbrk($cookieValue, "\r\n")) $this->cookies[$cookieName] = $cookieValue;
                }
            } else {
                $resultHeaders[$key] = $value;
            }
        }
        if ($status < 100 || $status > 599) throw new RuntimeException('Malformed local HTTP status.');
        $csrf = $resultHeaders['x-csrf-token'] ?? null;
        if (is_string($csrf) && $csrf !== '') $this->csrfToken = $csrf;
        try { $decoded = $body === '' ? null : json_decode($body, true, 512, JSON_THROW_ON_ERROR); }
        catch (JsonException) { $decoded = null; }
        return ['status' => $status, 'language' => (string)($resultHeaders['content-language'] ?? ''),
            'json' => $decoded];
    }

    /** @return array<string,mixed> */
    public function check(string $productId, string $username, string $password): array
    {
        M62BDevPlan::productId($productId);
        if ($username === '' || $password === '') throw new RuntimeException('DEV actor credentials not provided.');
        $csrf = $this->request('GET', '/api/v1/auth/csrf');
        if ($csrf['status'] !== 200 || !is_array($csrf['json']['data'] ?? null)) {
            throw new RuntimeException('Could not bootstrap CSRF on local Apache.');
        }
        $token = $csrf['json']['data']['csrf_token'] ?? null;
        $header = $csrf['json']['data']['header_name'] ?? null;
        if (!is_string($token) || $token === '') throw new RuntimeException('Missing CSRF token.');
        $this->csrfToken = $token;
        if (is_string($header) && preg_match('/\A[a-zA-Z0-9_-]+\z/D', $header) === 1) {
            $this->csrfName = $header;
        }
        $login = $this->request('POST', '/api/v1/auth/login', [
            str_contains($username, '@') ? 'email' : 'username' => $username,
            'password' => $password,
            'remember' => false,
        ]);
        if ($login['status'] !== 200) throw new RuntimeException('Local DEV authentication rejected.');
        $me = $this->request('GET', '/api/v1/auth/me');
        if ($me['status'] !== 200 || !is_array($me['json']['data']['user'] ?? null)) {
            throw new RuntimeException('DEV actor session was not established.');
        }
        $actor = $me['json']['data']['user'];
        if (str_contains($username, '@')
            ? strcasecmp((string)($actor['email'] ?? ''), $username) !== 0
            : (string)($actor['username'] ?? '') !== $username) {
            throw new RuntimeException('Authenticated actor differs from requested DEV actor.');
        }
        $before = $this->request('GET', '/api/v1/products/'.$productId);
        if ($before['status'] !== 200 || !is_array($before['json']['data'] ?? null)) {
            throw new RuntimeException('Provided Product is not VIEW-accessible in DEV.');
        }
        $results = []; $messages = []; $failed = 0;
        foreach (M62BDevPlan::cases($productId) as $test) {
            foreach (['en', 'vi'] as $locale) {
                $response = $this->request($test['method'], $test['path'], $test['payload'], $locale);
                $error = $response['json']['error'] ?? null;
                $code = is_array($error) ? ($error['code'] ?? null) : null;
                $message = is_array($error) ? ($error['message'] ?? null) : null;
                $passed = $response['status'] === 422 && $code === $test['code']
                    && $response['language'] === $locale
                    && is_string($message) && $message !== '';
                $results[] = ['case' => $test['key'], 'locale' => $locale,
                    'status' => $response['status'], 'code' => is_string($code) ? $code : null,
                    'pass' => $passed];
                if (!$passed) $failed++;
                if (is_string($message)) $messages[$test['key']][$locale] = $message;
            }
        }
        $localized = 0;
        foreach (M62BDevPlan::cases($productId) as $test) {
            $en = $messages[$test['key']]['en'] ?? '';
            $vi = $messages[$test['key']]['vi'] ?? '';
            if ($en !== '' && $vi !== '' && $en !== $vi) $localized++;
            else $failed++;
        }
        $after = $this->request('GET', '/api/v1/products/'.$productId);
        $unchanged = $after['status'] === 200 && $after['json']['data'] === $before['json']['data'];
        if (!$unchanged) $failed++;
        return [
            'verdict' => $failed === 0 ? 'PASS_DEV_PRODUCT_NEGATIVE_HTTP' : 'FAIL_DEV_PRODUCT_NEGATIVE_HTTP',
            'checked' => count($results), 'passed' => count($results) - count(array_filter($results, static fn($r) => !$r['pass'])),
            'localized_error_types' => $localized, 'product_readback_unchanged' => $unchanged,
            'results' => $results,
            'business_mutation_commit' => 'NOT_TESTED',
            'numbering_counter_atomicity' => 'NOT_TESTED',
            'combo_rollback' => 'NOT_TESTED',
            'independent_baseline_parity' => 'NOT_TESTED',
        ];
    }
}
