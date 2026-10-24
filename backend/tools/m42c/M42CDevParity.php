<?php

declare(strict_types=1);

require_once __DIR__ . '/M42CPlan.php';

/**
 * Development-only HTTP transport: always connects to LOCAL Apache on 127.0.0.1:443.
 * The Host header and TLS SNI select an existing HTTPS virtual host.
 * No arbitrary origins, SQL connections, or authorized write requests.
 */
final class M42CDevHttp
{
    private const HOSTS = ['v2' => 'api.monmin.net', 'baseline' => 'apibase.monmin.net'];
    private array $cookies = [];
    private string $csrfHeader = 'X-CSRF-TOKEN';
    private string $csrf = '';
    private string $hostname;

    public function __construct(string $role)
    {
        $this->hostname = self::hostFor($role);
    }

    public static function hostFor(string $role): string
    {
        return self::HOSTS[$role] ?? throw new M34Failure('Unknown fixed local Apache role');
    }

    /** @return array{status:int,headers:array<string,string>,json:mixed,raw:string} */
    public function request(string $method, string $path, ?array $body = null, string $locale = 'en'): array
    {
        if (! in_array($method, ['GET', 'POST'], true)
            || ! preg_match('#^/api/v1/[a-zA-Z0-9/_.-]+(?:\?context_type=SYSTEM)?$#D', $path)
            || str_contains($path, '..') || ! in_array($locale, ['en', 'vi'], true)) {
            throw new M34Failure('Invalid DEV parity method, path or locale');
        }
        // POST is reserved for the auth/login handshake: no business POST allowed.
        if ($method === 'POST' && $path !== '/api/v1/auth/login') {
            throw new M34Failure('DEV parity deliberately blocks business POST');
        }
        $headers = [
            'Host: ' . $this->hostname,
            'Accept: application/json',
            'Accept-Language: ' . $locale,
            'Connection: close',
        ];
        if ($this->cookies !== []) {
            $pairs = [];
            foreach ($this->cookies as $k => $v) { $pairs[] = $k . '=' . $v; }
            $headers[] = 'Cookie: ' . implode('; ', $pairs);
        }
        if ($body !== null) { $headers[] = 'Content-Type: application/json'; }
        if ($this->csrf !== '') { $headers[] = $this->csrfHeader . ': ' . $this->csrf; }
        $ctx = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR),
                'ignore_errors' => true,
                'follow_location' => 0,
                'timeout' => 10,
            ],
            // Only 127.0.0.1 is contacted. No external DNS or external HTTPS traffic.
            // Local XAMPP may use a self-signed certificate; do not copy this to remote clients.
            'ssl' => [
                'peer_name' => $this->hostname,
                'SNI_enabled' => true,
                'SNI_server_name' => $this->hostname,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);
        $raw = @file_get_contents('https://127.0.0.1' . $path, false, $ctx);
        if (! is_string($raw)) {
            throw new M34Failure('Cannot reach local Apache HTTPS host ' . $this->hostname
                . ' via 127.0.0.1:443. Check Apache, PHP openssl and VirtualHost.');
        }
        $received = $http_response_header ?? [];
        $parsed = []; $status = 0;
        foreach ($received as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $header, $m)) {
                $status = (int) $m[1];
                $parsed = [];
                continue;
            }
            if (! str_contains($header, ':')) { continue; }
            [$key, $value] = explode(':', $header, 2);
            $key = strtolower(trim($key)); $value = trim($value);
            if ($key === 'set-cookie') {
                $piece = trim(explode(';', $value, 2)[0]);
                if (str_contains($piece, '=')) {
                    [$name, $cookie] = explode('=', $piece, 2);
                    if (preg_match('/^[A-Za-z0-9_-]+$/D', $name)) { $this->cookies[$name] = $cookie; }
                }
            } else { $parsed[$key] = $value; }
        }
        if ($status < 100 || $status > 599) { throw new M34Failure('Malformed local HTTPS response'); }
        if (is_string($parsed['x-csrf-token'] ?? null) && $parsed['x-csrf-token'] !== '') {
            $this->csrf = $parsed['x-csrf-token'];
        }
        try { $json = $raw === '' ? null : json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
        catch (JsonException) { $json = null; }
        return ['status' => $status, 'headers' => $parsed, 'json' => $json, 'raw' => $raw];
    }

    public function login(string $user, string $password): void
    {
        $boot = $this->request('GET', '/api/v1/auth/csrf');
        if ($boot['status'] !== 200 || ! is_array($boot['json']['data'] ?? null)) {
            throw new M34Failure('CSRF bootstrap failed on local host ' . $this->hostname);
        }
        $data = $boot['json']['data'];
        if (! is_string($data['csrf_token'] ?? null) || $data['csrf_token'] === '') {
            throw new M34Failure('Missing CSRF bootstrap token');
        }
        $this->csrf = $data['csrf_token'];
        if (is_string($data['header_name'] ?? null)
            && preg_match('/^[A-Za-z0-9_-]+$/D', $data['header_name'])) {
            $this->csrfHeader = $data['header_name'];
        }
        $login = $this->request('POST', '/api/v1/auth/login', [
            str_contains($user, '@') ? 'email' : 'username' => $user,
            'password' => $password,
            'remember' => false,
        ]);
        if ($login['status'] !== 200) {
            throw new M34Failure('DEV login failed on ' . $this->hostname . ' (HTTP ' . $login['status'] . ')');
        }
        $me = $this->request('GET', '/api/v1/auth/me');
        if ($me['status'] !== 200 || ! is_array($me['json']['data']['user'] ?? null)) {
            throw new M34Failure('Authenticated DEV session not established on ' . $this->hostname);
        }
    }
}

/** GET-only differential check against the developer's existing Apache vhosts. */
final class M42CDevParity
{
    private array $ops;
    public function __construct(string $root)
    {
        $this->ops = M42CPlan::operations($root);
    }

    /** @return array<string,string> */
    public static function reviewedGetPaths(array $ids): array
    {
        $paths = [
            'getDisplaySettingsRuntime' => '/api/v1/display-settings/runtime',
            'listDisplaySettingDefinitions' => '/api/v1/display-settings/definitions',
            'listDisplaySettingValues' => '/api/v1/display-settings/values?context_type=SYSTEM',
            'getBusinessWorkflowStates' => '/api/v1/business-workflow/organization/states',
        ];
        if (isset($ids['resource-id'])) {
            $resource = $ids['resource-id'];
            $paths['listNumberingSeries'] = '/api/v1/resources/' . $resource . '/numbering-series';
            if (isset($ids['series-id'])) {
                $paths['getNumberingSeries'] = $paths['listNumberingSeries'] . '/' . $ids['series-id'];
            }
        }
        if (isset($ids['organization-id'])) {
            $paths['getBusinessWorkflowOptions'] = '/api/v1/business-workflow/organization/'
                . $ids['organization-id'] . '/options';
        }
        return $paths;
    }

    /** @return array<string,mixed> */
    public function smoke(): array
    {
        $results = [];
        foreach (['baseline', 'v2'] as $role) {
            $client = new M42CDevHttp($role);
            foreach (['/api/v1/health', '/api/v1/auth/csrf'] as $path) {
                $response = $client->request('GET', $path);
                $results[] = ['role' => $role, 'path' => $path, 'status' => $response['status'],
                    'pass' => $response['status'] === 200];
            }
        }
        $pass = count(array_filter($results, static fn ($r) => $r['pass'])) === count($results);
        return ['mode' => 'smoke', 'status' => $pass ? 'PASS_DEV_HTTP_REACHABILITY' : 'FAIL_DEV_HTTP_REACHABILITY',
            'requests' => count($results), 'results' => $results, 'baseline_parity' => 'NOT_TESTED',
            'database_inspection' => 'NOT_PERFORMED'];
    }

    /** @return array<string,mixed> */
    public function read(array $ids, string $user, string $password): array
    {
        if ($user === '' || $password === '') {
            throw new M34Failure('Set M42C_DEV_USER and M42C_DEV_PASSWORD (the same DEV actor on both backends)');
        }
        $clients = ['baseline' => new M42CDevHttp('baseline'), 'v2' => new M42CDevHttp('v2')];
        foreach ($clients as $client) { $client->login($user, $password); }
        $paths = self::reviewedGetPaths($ids);
        $results = []; $failed = 0;
        foreach ($paths as $name => $path) {
            if (! ($this->ops[$name]['read_only'] ?? false)
                || ($this->ops[$name]['method'] ?? '') !== 'GET'
                || ! M42CPlan::pathAllowed($name, $path, $this->ops)) {
                throw new M34Failure('Non-approved GET path in dev runner: ' . $name);
            }
            foreach (['en','vi'] as $locale) {
                $responses = []; $normalized = []; $issues = [];
                foreach ($clients as $role => $client) {
                    $responses[$role] = $client->request('GET', $path, null, $locale);
                    if ($responses[$role]['status'] !== 200) {
                        $issues[] = $role . ': expected 200; got ' . $responses[$role]['status'];
                    }
                    if (($responses[$role]['headers']['content-language'] ?? '') !== $locale) {
                        $issues[] = $role . ': language header mismatch';
                    }
                    try { $normalized[$role] = M34Parity::wire($responses[$role], $locale); }
                    catch (Throwable $e) { $issues[] = $role . ': invalid API JSON envelope'; }
                }
                if (isset($normalized['baseline'], $normalized['v2'])
                    && ! M34Parity::identical($normalized['baseline'], $normalized['v2'])) {
                    $issues[] = 'baseline and rewrite response differ';
                }
                if (! isset($normalized['baseline'], $normalized['v2'])) {
                    $issues[] = 'unable to compare normalized JSON';
                }
                $passed = $issues === [];
                if (! $passed) { $failed++; }
                $results[] = [
                    'operation_id' => $name, 'locale' => $locale, 'pass' => $passed,
                    'baseline_status' => $responses['baseline']['status'],
                    'v2_status' => $responses['v2']['status'], 'issues' => $issues,
                ];
            }
        }
        $skipped = array_values(array_diff(M42CPlan::readNames($this->ops), array_keys($paths)));
        // The POST validate-config endpoint is deliberately not called on live DEV vhosts.
        $status = $failed !== 0 ? 'FAIL_DEV_READ_DIFFERENCE'
            : ($skipped === ['validateNumberingSeriesConfig'] ? 'PASS_DEV_SEVEN_GET_PARITY' : 'PARTIAL_DEV_GET_PARITY');
        return [
            'mode' => 'read', 'status' => $status, 'passed' => count($results) - $failed,
            'paired_cases' => count($results), 'failed' => $failed,
            'operation_get_total' => 7, 'operation_get_tested' => count($paths),
            'not_tested_operations' => $skipped, 'results' => $results,
            'successful_write_parity' => 'NOT_TESTED', 'database_inspection' => 'NOT_PERFORMED',
        ];
    }
}
