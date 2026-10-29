<?php

declare(strict_types=1);

/**
 * M3.4 clone-only HTTP integration harness. This file deliberately has no
 * CodeIgniter, Composer or production-DB dependencies.
 */
final class M34Failure extends RuntimeException {}

final class M34Contract
{
    // Byte-for-byte pins from the user-provided erp_v1.2.4.zip.
    // NEVER accept a hash merely because it is declared by the local manifest.
    public const OPENAPI_SHA256 = 'f1736a006c2f4e1f529b22b8434be6995bcfbec8e7252d0af8ddc9d739de1728';
    public const MANIFEST_SHA256 = '5afe430a5189706b573cb8c1edd15fcbcf891ace0bb3fe9501a49154817a16e9';

    /**
     * Read a pinned reference. If a repository document is missing, use the
     * separately shipped, SHA-256-verified snapshot. Never use the snapshot
     * to conceal a present-but-modified document.
     */
    private static function pinnedFile(string $repoPath, string $snapshotPath, string $expectedSha, string $label): string
    {
        if (file_exists($repoPath) || is_link($repoPath)) {
            if (! is_file($repoPath)) {
                throw new M34Failure('Pinned baseline ' . $label . ' path is not a file: ' . $repoPath);
            }
            $path = $repoPath;
        } elseif (is_file($snapshotPath)) {
            $path = $snapshotPath;
            fwrite(STDERR, 'NOTE: repository ' . $label . ' missing; using verified v1.2.4 snapshot: ' . $path . PHP_EOL);
        } else {
            throw new M34Failure('Missing pinned baseline ' . $label . ': ' . $repoPath
                . ' (snapshot also missing: ' . $snapshotPath . ')');
        }
        if (! is_readable($path)) {
            throw new M34Failure('Unreadable pinned baseline ' . $label . ': ' . $path);
        }
        $actualSha = hash_file('sha256', $path);
        if ($actualSha === false || ! hash_equals($expectedSha, $actualSha)) {
            throw new M34Failure('Pinned v1.2.4 ' . $label . ' SHA-256 mismatch: expected='
                . $expectedSha . ', actual=' . ($actualSha === false ? 'unavailable' : $actualSha)
                . ', path=' . $path . '. Restore the original reference from M3.4A.1; do not disable SHA verification.');
        }
        return $path;
    }

    /** @return array<string,array{method:string,path:string,status:int}> */
    public static function operations(string $root): array
    {
        $docs = $root . '/docs/backend-rewrite/';
        $fixtures = $root . '/backend/tests/fixtures/baseline_v1_2_4/contract/';
        $manifest = self::pinnedFile(
            $docs . 'openapi-operations-v1.2.4.json',
            $fixtures . 'openapi-operations-v1.2.4.json',
            self::MANIFEST_SHA256, 'OpenAPI operation manifest'
        );
        self::pinnedFile(
            $docs . 'openapi-frozen-v1.2.4.yaml',
            $fixtures . 'openapi-frozen-v1.2.4.yaml',
            self::OPENAPI_SHA256, 'OpenAPI YAML'
        );
        $source = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($source) || ($source['source'] ?? null) !== 'erp_v1.2.4.zip'
            || ($source['openapi_sha256'] ?? null) !== self::OPENAPI_SHA256
            || ! isset($source['operations']) || ! is_array($source['operations'])) {
            throw new M34Failure('Pinned v1.2.4 manifest identity/structure mismatch: ' . $manifest);
        }
        $result = [];
        foreach ($source['operations'] ?? [] as $op) {
            if (! in_array($op['method'] ?? '', ['POST','PATCH','PUT','DELETE'], true)) { continue; }
            if (! preg_match('#^/api/v1/(users|authorization-groups|resources|policies|workflow-states|lifecycles)(?:/|$)#', $op['path'] ?? '')) { continue; }
            if (str_contains($op['path'], '/numbering-series') || str_contains($op['path'], '/avatar')) { continue; }
            $key = (string) ($op['operation_id'] ?? '');
            if ($key === '' || isset($result[$key])) { throw new M34Failure('Duplicate or missing OpenAPI operation_id'); }
            $statuses = $op['success_statuses'] ?? [];
            if (count($statuses) !== 1) { throw new M34Failure('Unexpected success status for ' . $key); }
            $result[$key] = ['method' => $op['method'], 'path' => $op['path'], 'status' => (int) $statuses[0]];
        }
        if (count($result) !== 29) { throw new M34Failure('Expected 29 reviewed mutation contracts, got ' . count($result)); }
        self::assertRoutes($result, $root . '/backend/app/Config/Routes.php');
        return $result;
    }

    /** @param array<string,array{method:string,path:string,status:int}> $ops */
    public static function assertRoutes(array $ops, string $routesPath): void
    {
        $domainRoot = dirname($routesPath) . '/Routes/ApiV1/IdentityRoutes.php';
        if (is_file($domainRoot)) {
            require_once dirname(__DIR__) . '/routes/LegacyRouteSource.php';
            $routes = \DinkERP\Tools\Routes\LegacyRouteSource::core70(dirname($routesPath, 4));
        } else {
            // Synthetic M3.4 reference copies contain just one Routes.php.
            $routes = (string) file_get_contents($routesPath);
        }
        if (! str_contains($routes, '$routes->setAutoRoute(false)')) {
            throw new M34Failure('Security blocker: CI4 auto-routing must remain OFF');
        }
        foreach ($ops as $name => $op) {
            $method = strtolower($op['method']);
            $route = substr($op['path'], strlen('/api/v1/'));
            $route = preg_replace('/\{(?:id|action_id)\}/', '(:num)', $route);
            // A static route check is not an HTTP parity assertion.
            if (! str_contains($routes, '$routes->' . $method . "('" . $route . "'")) {
                throw new M34Failure('Missing explicit v2 route for ' . $name . ' (' . $op['method'] . ' ' . $route . ')');
            }
        }
    }

    /** @param array{method:string,path:string,status:int} $op */
    public static function missingTargetPath(array $op): string
    {
        return str_replace(['{id}','{action_id}'], '18446744073709551615', $op['path']);
    }
}

final class M34Safety
{
    public const CONFIRMATION = 'M34_CLONE_ONLY';
    public const CLONE_SUFFIX = ['v2' => '_m34_v2_clone', 'baseline' => '_m34_baseline_clone'];

    /** @return array<string,mixed> */
    public static function config(string $path, bool $confirmed): array
    {
        if (! $confirmed) { throw new M34Failure('Clone test not explicitly confirmed; pass --confirm=M34_CLONE_ONLY'); }
        if (! is_file($path)) { throw new M34Failure('Missing local clone config (see config example)'); }
        $raw = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($raw) || ($raw['purpose'] ?? null) !== 'M34_DISPOSABLE_CLONES') {
            throw new M34Failure('Clone purpose marker missing from configuration');
        }
        $nonce = (string) ($raw['run_id'] ?? '');
        if (! preg_match('/^[A-Za-z0-9_-]{16,80}$/D', $nonce)) {
            throw new M34Failure('Use a separate unpredictable run_id (16..80 alphanumeric chars)');
        }
        foreach (['v2','baseline'] as $role) {
            if (! is_array($raw[$role] ?? null)) { throw new M34Failure('Missing ' . $role . ' clone configuration'); }
            $record = $raw[$role];
            self::assertLocalUrl((string) ($record['base_url'] ?? ''));
            $dsn = (string) ($record['dsn'] ?? '');
            if (! preg_match('/^mysql:(?:[^;]*;)*[^;]*$/', $dsn)) { throw new M34Failure('MySQL DSN required for ' . $role); }
            $options = [];
            foreach (explode(';', substr($dsn, 6)) as $fragment) {
                if ($fragment === '') { continue; }
                if (substr_count($fragment, '=') !== 1) { throw new M34Failure('Invalid DSN option'); }
                [$k, $v] = explode('=', $fragment, 2);
                $k = strtolower($k);
                if (isset($options[$k]) || ! in_array($k, ['host','port','dbname','charset'], true)) {
                    throw new M34Failure('Duplicate or unsupported DSN option: ' . $k);
                }
                $options[$k] = $v;
            }
            if (! in_array($options['host'] ?? '', ['127.0.0.1','localhost'], true)
                || ! str_ends_with($options['dbname'] ?? '', self::CLONE_SUFFIX[$role])) {
                throw new M34Failure('Dangerous DSN: ' . $role . ' must use local DB ending in ' . self::CLONE_SUFFIX[$role]);
            }
            if (isset($options['unix_socket']) || isset($options['socket'])) {
                throw new M34Failure('Socket DB connections not supported by clone guard');
            }
            foreach (['db_user','db_password_env','login_user_env','login_password_env'] as $key) {
                if (! is_string($record[$key] ?? null) || trim($record[$key]) === '') {
                    throw new M34Failure('Missing ' . $role . '.' . $key);
                }
            }
            if (isset($record['db_password']) || isset($record['login_password'])) {
                throw new M34Failure('Never embed DB or Shield passwords in config JSON');
            }
            $raw[$role]['verified_db_name'] = $options['dbname'];
        }
        if (rtrim($raw['v2']['base_url'], '/') === rtrim($raw['baseline']['base_url'], '/')
            || $raw['v2']['verified_db_name'] === $raw['baseline']['verified_db_name']) {
            throw new M34Failure('v2/baseline must use separate HTTP origins AND databases');
        }
        return $raw;
    }

    public static function assertLocalUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts)
            || ! in_array($parts['scheme'] ?? '', ['http','https'], true)
            || ! in_array(strtolower($parts['host'] ?? ''), ['localhost','127.0.0.1','::1'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['','/'], true)) {
            throw new M34Failure('HTTP origin MUST be bare localhost/127.0.0.1 (never api.monmin.net or public URL): ' . $url);
        }
    }

    public static function environment(string $name): string
    {
        if (! preg_match('/^[A-Z][A-Z0-9_]*$/D', $name)) { throw new M34Failure('Invalid environment variable name'); }
        $value = getenv($name);
        if (! is_string($value) || $value === '') { throw new M34Failure('Environment variable not set: ' . $name); }
        return $value;
    }
}

final class M34CloneDb
{
    private PDO $pdo;
    /** @var list<string> */
    public const TRACKED = [
        'users','auth_identities','auth_groups_users','auth_permissions_users',
        'authorization_groups','group_permissions','resources','resource_actions',
        'policies','policy_actions','workflow_states','lifecycles',
        'lifecycle_states','lifecycle_transitions',
    ];

    /** @param array<string,mixed> $server */
    public function __construct(array $server, string $role, string $runId)
    {
        if (! extension_loaded('pdo_mysql')) { throw new M34Failure('pdo_mysql PHP extension required for clone verification'); }
        $this->pdo = new PDO($server['dsn'], $server['db_user'], M34Safety::environment($server['db_password_env']), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $actual = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($actual !== $server['verified_db_name']) { throw new M34Failure('DSN database does not match SELECT DATABASE()'); }
        // Deliberate, manually-created guard table; NO CREATE/ALTER/DROP/INSERT in runner.
        $query = $this->pdo->prepare('SELECT run_id FROM m34_clone_guard WHERE clone_role = ? LIMIT 1');
        $query->execute([$role]);
        if ($query->fetchColumn() !== $runId) {
            throw new M34Failure('DB clone marker missing or run_id does not match for ' . $role);
        }
        // Read-only guard checks: two database schemas must be independently marked.
        $this->pdo->query('SELECT id FROM users LIMIT 0');
    }

    /** @return array<string,string> hashes of all rows of tracked business tables */
    public function snapshot(): array
    {
        $tables = $this->pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN);
        $existing = array_fill_keys($tables, true);
        $snapshot = [];
        foreach (self::TRACKED as $table) {
            if (! isset($existing[$table])) { continue; }
            $rows = $this->pdo->query('SELECT * FROM `' . $table . '`')->fetchAll();
            $canonical = [];
            foreach ($rows as $row) {
                ksort($row, SORT_STRING);
                // Shield session activity is not mutation of Access policy. All
                // other rows/columns remain covered, including audit fields.
                unset($row['last_active']);
                $canonical[] = json_encode($row, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            }
            sort($canonical, SORT_STRING);
            $snapshot[$table] = hash('sha256', implode("\n", $canonical));
        }
        foreach (['users','policies','resources','workflow_states','lifecycles'] as $essential) {
            if (! isset($snapshot[$essential])) { throw new M34Failure('Missing required tracked table: ' . $essential); }
        }
        return $snapshot;
    }

    /** @return list<string> */
    public static function changed(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $name) {
            if (($before[$name] ?? null) !== ($after[$name] ?? null)) { $changed[] = $name; }
        }
        sort($changed);
        return $changed;
    }
}

final class M34Http
{
    /** @var array<string,string> */
    private array $cookies = [];
    private string $csrfHeader = 'X-CSRF-TOKEN';
    private string $csrf = '';
    public function __construct(private readonly string $origin) { M34Safety::assertLocalUrl($origin); }

    /** @return array{status:int,headers:array<string,string>,json:mixed,raw:string} */
    public function request(string $method, string $path, ?array $body = null, string $locale = 'en', string $csrfMode = 'auto'): array
    {
        if (! preg_match('#^/api/v1/[a-zA-Z0-9/{}_.-]+$#D', $path) || str_contains($path, '..')) {
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

final class M34Parity
{
    /** @param array<string,mixed> $response */
    public static function wire(array $response, string $locale): array
    {
        $status = $response['status']; $headers = $response['headers']; $json = $response['json'];
        if ($status >= 400) {
            if (! is_array($json) || ! is_array($json['error'] ?? null)
                || ! is_string($json['error']['code'] ?? null)
                || ! is_string($json['error']['message'] ?? null)
                || ! is_array($json['error']['details'] ?? null)) {
                throw new M34Failure('Non-JSON API error envelope for HTTP ' . $status);
            }
            if (($headers['content-language'] ?? '') !== $locale) {
                throw new M34Failure('Incorrect Content-Language for ' . $locale);
            }
            if (empty($headers['x-request-id']) || ! is_string($json['meta']['request_id'] ?? null)) {
                throw new M34Failure('Missing X-Request-ID / meta.request_id');
            }
            if ($headers['x-request-id'] !== $json['meta']['request_id']) {
                throw new M34Failure('X-Request-ID does not match JSON meta.request_id');
            }
            if (! str_contains(strtolower($headers['content-type'] ?? ''), 'application/json')) {
                throw new M34Failure('Error response Content-Type must be JSON');
            }
        } elseif ($status === 204) {
            if (trim($response['raw']) !== '') { throw new M34Failure('HTTP 204 must have empty body'); }
            return ['status' => 204, 'body' => null];
        } elseif ($status >= 200 && $status < 300 && ! is_array($json)) {
            throw new M34Failure('Expected JSON response for HTTP ' . $status);
        }
        if (! is_array($json)) { return ['status' => $status, 'body' => null]; }
        // Remove ONLY per-request random request_id and do not conceal
        // language, validation field details, error messages or resource data.
        if (isset($json['meta']) && is_array($json['meta'])) { unset($json['meta']['request_id']); }
        return ['status' => $status, 'body' => self::canonical($json)];
    }

    public static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) { return $value; }
        if (! array_is_list($value)) { ksort($value, SORT_STRING); }
        foreach ($value as $k => $v) { $value[$k] = self::canonical($v); }
        return $value;
    }

    public static function identical(array $left, array $right): bool
    {
        return self::canonical($left) === self::canonical($right);
    }
}
