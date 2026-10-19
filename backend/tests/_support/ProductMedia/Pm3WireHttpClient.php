<?php

declare(strict_types=1);

namespace Tests\Support\ProductMedia;

use RuntimeException;

/** True HTTP/cURL client; no CI4 feature-test request or mocked UploadedFile. */
final class Pm3WireHttpClient
{
    /** @var resource|\CurlHandle */
    private $handle;
    private string $csrfHeader = '';
    private string $csrfToken = '';

    public function __construct(private readonly string $baseUrl, string $cookieFile)
    {
        $handle = curl_init();
        if ($handle === false) {
            throw new RuntimeException('Cannot initialize cURL HTTP client.');
        }
        $this->handle = $handle;
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT => 'DinkERP-PM3-loopback-test/1',
        ]);
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    public function call(string $method, string $path, ?array $multipart = null, ?array $json = null, ?string $csrf = null): array
    {
        $headers = ['Accept: application/json', 'Accept-Language: en', 'X-Request-ID: pm3-' . bin2hex(random_bytes(5))];
        if ($csrf !== null && $this->csrfHeader !== '') {
            $headers[] = $this->csrfHeader . ': ' . $csrf;
        }
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        $options = [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $json !== null ? json_encode($json, JSON_THROW_ON_ERROR) : $multipart,
        ];
        // A subsequent GET must not retain POST multipart flags from prior calls.
        if ($json === null && $multipart === null) {
            $options[CURLOPT_POSTFIELDS] = null;
        }
        curl_setopt_array($this->handle, $options);
        $raw = curl_exec($this->handle);
        if (! is_string($raw)) {
            throw new RuntimeException('PM3 loopback HTTP request failed: ' . curl_error($this->handle));
        }
        $headerSize = curl_getinfo($this->handle, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE);
        if (! is_int($headerSize) || ! is_int($status)) {
            throw new RuntimeException('PM3 loopback HTTP response has invalid status/headers.');
        }
        $responseHeaders = [];
        foreach (explode("\r\n", substr($raw, 0, $headerSize)) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }
        }
        return ['status' => $status, 'headers' => $responseHeaders, 'body' => substr($raw, $headerSize)];
    }

    public function primeCsrf(): void
    {
        $result = $this->call('GET', 'auth/csrf');
        $value = json_decode($result['body'], true, 512, JSON_THROW_ON_ERROR);
        if ($result['status'] !== 200 || ! is_array($value)
            || ! is_string($value['data']['csrf_token'] ?? null)
            || ! is_string($value['data']['header_name'] ?? null)) {
            throw new RuntimeException('Cannot obtain real session CSRF token from loopback API.');
        }
        // Session-mode CSRF requires the next HTTP request to reuse the
        // exact session that issued this token. Fail at the real boundary
        // rather than later misdiagnosing a 403 as Shield credentials.
        // curl's cookie-list entries include secret values: only examine
        // the name field; do not persist/print the entries or session ID.
        $cookies = curl_getinfo($this->handle, CURLINFO_COOKIELIST);
        $sessionCookiePresent = false;
        if (is_array($cookies)) {
            foreach ($cookies as $entry) {
                if (! is_string($entry)) { continue; }
                $fields = explode("\t", $entry);
                if (count($fields) === 7 && $fields[5] === 'ci_session') {
                    $sessionCookiePresent = true;
                    break;
                }
            }
        }
        if (! $sessionCookiePresent) {
            // No secret values or raw Set-Cookie headers in this exception.
            $cookieHeaderSeen = isset($result['headers']['set-cookie']);
            throw new RuntimeException('PM3 loopback CSRF GET did not establish a cURL ci_session cookie'
                . ' (Set-Cookie header seen=' . ($cookieHeaderSeen ? 'yes' : 'no')
                . '). Check the test-only Cookie transport configuration and session initialization.');
        }
        $this->csrfToken = $value['data']['csrf_token'];
        $this->csrfHeader = $value['data']['header_name'];
    }

    public function csrfToken(): string { return $this->csrfToken; }

    /** @param array{headers:array<string,string>} $response */
    public function rotateCsrf(array $response): void
    {
        $token = $response['headers'][strtolower($this->csrfHeader)] ?? null;
        if (! is_string($token) || $token === '') {
            // An HTTP response can lack this header after a CSRF failure; a
            // fresh GET will recover the same browser session's new token.
            $this->primeCsrf();
            return;
        }
        $this->csrfToken = $token;
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    public function authorized(string $method, string $path, ?array $multipart = null, ?array $json = null): array
    {
        if ($this->csrfToken === '') { $this->primeCsrf(); }
        $result = $this->call($method, $path, $multipart, $json, $this->csrfToken);
        $this->rotateCsrf($result);
        return $result;
    }

    public function login(string $email, string $password): void
    {
        $this->primeCsrf();
        // Use Shield's canonical email/password identity query. PM-0 fixture
        // usernames are synthetic and not the identity stored with the hash.
        $response = $this->authorized('POST', 'auth/login', null, ['email' => $email, 'password' => $password]);
        if ($response['status'] !== 200) {
            $payload = json_decode($response['body'], true);
            $code = is_array($payload) && is_string($payload['error']['code'] ?? null)
                ? $payload['error']['code'] : 'UNKNOWN';
            // API error code only; never include body, email, password, session or CSRF token.
            if (preg_match('/\A[A-Z_]{1,64}\z/D', $code) !== 1) { $code = 'UNKNOWN'; }
            // cURL's Netscape cookie-list metadata contains cookie values.
            // Parse only name/secure flags and discard all values immediately.
            // The real request is not modified, and no cookie value is logged.
            $sessionCookieInJar = false;
            $sessionCookieSecureInJar = false;
            $jar = curl_getinfo($this->handle, CURLINFO_COOKIELIST);
            if (is_array($jar)) {
                foreach ($jar as $entry) {
                    if (! is_string($entry)) { continue; }
                    $fields = explode("\t", $entry);
                    if (count($fields) === 7 && $fields[5] === 'ci_session') {
                        $sessionCookieInJar = true;
                        $sessionCookieSecureInJar = strtoupper($fields[3]) === 'TRUE';
                    }
                }
            }
            throw new RuntimeException('PM3 synthetic Shield session login rejected (HTTP '
                . $response['status'] . ', API ' . $code
                . ', cURL session cookie present=' . ($sessionCookieInJar ? 'yes' : 'no')
                . ', secure=' . ($sessionCookieSecureInJar ? 'yes' : 'no')
                . '). Inspect http-server.log.auth-diagnostic.jsonl for redacted stage evidence.');
        }
    }

    public function close(): void { curl_close($this->handle); }
}
