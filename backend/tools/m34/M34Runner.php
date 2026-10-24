<?php

declare(strict_types=1);

require_once __DIR__ . '/M34Core.php';

final class M34Runner
{
    /** @var array<string,M34CloneDb> */
    private array $databases = [];
    /** @var array<string,M34Http> */
    private array $http = [];
    /** @var array<string,array<string,mixed>> */
    private array $responses = ['baseline' => [], 'v2' => []];
    /** @var list<array<string,mixed>> */
    private array $results = [];
    private int $failures = 0;

    /** @param array<string,mixed> $config @param array<string,array{method:string,path:string,status:int}> $ops */
    public function __construct(private readonly array $config, private readonly array $ops)
    {
        foreach (['baseline','v2'] as $role) {
            $this->databases[$role] = new M34CloneDb($config[$role], $role, $config['run_id']);
            $this->http[$role] = new M34Http($config[$role]['base_url']);
        }
    }

    /** @return array<string,mixed> */
    public function preflight(): array
    {
        $snapshots = [];
        foreach ($this->databases as $role => $db) {
            $snapshots[$role] = $db->snapshot();
        }
        if ($snapshots['baseline'] !== $snapshots['v2']) {
            $differences = [];
            foreach (array_unique(array_merge(array_keys($snapshots['baseline']), array_keys($snapshots['v2']))) as $table) {
                if (($snapshots['baseline'][$table] ?? null) !== ($snapshots['v2'][$table] ?? null)) { $differences[] = $table; }
            }
            // Not fatal for negative probes, but strictly BLOCK positive
            // side-effect parity from comparing non-identical starting data.
            return ['clones_marked' => true, 'identical_starting_snapshots' => false, 'differing_tables' => $differences,
                'operation_count' => count($this->ops), 'writes_performed' => 0];
        }
        return ['clones_marked' => true, 'identical_starting_snapshots' => true, 'differing_tables' => [],
            'operation_count' => count($this->ops), 'writes_performed' => 0];
    }

    private function authenticate(): void
    {
        foreach (['baseline','v2'] as $role) {
            $server = $this->config[$role];
            $this->http[$role]->login(
                M34Safety::environment($server['login_user_env']),
                M34Safety::environment($server['login_password_env'])
            );
        }
    }

    /** @return array<string,mixed> */
    public function negative(): array
    {
        $this->authenticate();
        foreach ($this->ops as $operation => $definition) {
            $path = M34Contract::missingTargetPath($definition);
            // Missing resource ID and {} for create both force a negative
            // validation/404 path. No production data is ever addressed.
            foreach (['en', 'vi'] as $locale) {
                $this->pair($operation, $definition, $path, [], $locale, 'invalid_payload', 'auto');
            }
            // Independent CSRF and unauthenticated gates with the same route.
            $this->pair($operation, $definition, $path, [], 'en', 'bad_csrf', 'invalid');
            $this->pair($operation, $definition, $path, [], 'vi', 'unauthenticated', 'omit');
        }
        return $this->report('negative');
    }

    /**
     * @param array{method:string,path:string,status:int} $definition
     * @param array<string,mixed> $body
     */
    private function pair(string $operation, array $definition, string $path, array $body, string $locale, string $case, string $csrfMode): void
    {
        $responses = [];
        foreach (['baseline', 'v2'] as $role) {
            $before = $this->databases[$role]->snapshot();
            $client = $case === 'unauthenticated'
                ? new M34Http($this->config[$role]['base_url']) : $this->http[$role];
            $responses[$role] = $client->request($definition['method'], $path, $body, $locale, $csrfMode);
            $after = $this->databases[$role]->snapshot();
            $changed = M34CloneDb::changed($before, $after);
            if ($changed !== []) {
                throw new M34Failure('STOP: Unexpected DB mutation on negative probe ' . $operation . ' ' . $role . ' [' . implode(', ', $changed) . ']. Restore BOTH clones.');
            }
            if ($responses[$role]['status'] < 400 || $responses[$role]['status'] >= 500) {
                throw new M34Failure('STOP: negative probe expected 4xx on ' . $role . ' ' . $operation . ', got HTTP ' . $responses[$role]['status']);
            }
            if (($responses[$role]['json']['error']['code'] ?? '') === 'ACCESS_MUTATION_NOT_ENABLED') {
                throw new M34Failure('Write feature flag is still OFF in ' . $role . ' clone: cannot claim mutation parity');
            }
        }
        $same = false; $error = ''; $a = null; $b = null;
        try {
            $a = M34Parity::wire($responses['baseline'], $locale);
            $b = M34Parity::wire($responses['v2'], $locale);
            $same = M34Parity::identical($a, $b);
            if (! $same) { $error = 'HTTP status, error.code/message/details or error envelope differs'; }
        } catch (M34Failure $exception) {
            $error = $exception->getMessage();
        }
        if (! $same) { $this->failures++; }
        $this->results[] = [
            'operation' => $operation, 'case' => $case, 'language' => $locale,
            'pass' => $same, 'baseline_status' => $responses['baseline']['status'],
            'v2_status' => $responses['v2']['status'],
            'baseline_error' => $responses['baseline']['json']['error']['code'] ?? null,
            'v2_error' => $responses['v2']['json']['error']['code'] ?? null,
            'reason' => $error,
            'baseline_body' => $same ? null : ($a['body'] ?? self::safeError($responses['baseline'])),
            'v2_body' => $same ? null : ($b['body'] ?? self::safeError($responses['v2'])),
        ];
    }

    /** @param array<string,mixed> $response @return array<string,mixed> */
    private static function safeError(array $response): array
    {
        return ['status' => $response['status'],
            'code' => $response['json']['error']['code'] ?? null,
            'message' => $response['json']['error']['message'] ?? null];
    }

    /**
     * Full 29-operation replay ONLY on manually prepared disposable clones.
     * Every case must be explicitly authored with a positive body and ID
     * references. No placeholder gets silently substituted with real data.
     * @return array<string,mixed>
     */
    public function positive(string $casesPath): array
    {
        $pre = $this->preflight();
        if (! $pre['identical_starting_snapshots']) {
            throw new M34Failure('Positive replay blocked: clones do not start from identical tracked Access data. Re-copy BOTH from one clean snapshot.');
        }
        $cases = json_decode((string) file_get_contents($casesPath), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($cases) || ! is_array($cases['scenarios'] ?? null) || count($cases['scenarios']) !== 29) {
            throw new M34Failure('Positive replay requires exactly 29 explicitly filled scenarios');
        }
        $covered = []; $keys = [];
        foreach ($cases['scenarios'] as $case) {
            if (! is_array($case)) { throw new M34Failure('Invalid scenario'); }
            $operation = (string) ($case['operation_id'] ?? '');
            $key = (string) ($case['key'] ?? '');
            if (! isset($this->ops[$operation]) || isset($covered[$operation]) || ! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $key) || isset($keys[$key])) {
                throw new M34Failure('Unknown, duplicated or malformed positive scenario: ' . $operation);
            }
            if (! isset($case['path_ids']) || ! is_array($case['path_ids']) || ! array_key_exists('body', $case)
                || ! is_array($case['body']) || ($case['ready'] ?? false) !== true) {
                throw new M34Failure('Scenario not explicitly marked ready, or has no IDs/body: ' . $key);
            }
            $this->assertFilled($case['path_ids']);
            $this->assertFilled($case['body']);
            $covered[$operation] = true; $keys[$key] = true;
        }
        $this->authenticate();
        foreach ($cases['scenarios'] as $case) {
            $name = $case['operation_id']; $def = $this->ops[$name];
            $answers = []; $effects = []; $afterHashes = [];
            foreach (['baseline','v2'] as $role) {
                $path = $def['path'];
                foreach (['id','action_id'] as $token) {
                    if (str_contains($path, '{' . $token . '}')) {
                        $value = $this->interpolate($case['path_ids'][$token] ?? null, $role);
                        if (! is_string($value) && ! is_int($value)) {
                            throw new M34Failure('Non-numeric positive ID in ' . $case['key']);
                        }
                        $value = (string) $value;
                        if (! preg_match('/^[1-9][0-9]{0,19}$/D', $value)) {
                            throw new M34Failure('Invalid/missing positive ID ' . $token . ' in ' . $case['key']);
                        }
                        $path = str_replace('{' . $token . '}', $value, $path);
                    }
                }
                $payload = $this->interpolate($case['body'], $role);
                $before = $this->databases[$role]->snapshot();
                $answers[$role] = $this->http[$role]->request($def['method'], $path, $payload, 'en');
                $afterHashes[$role] = $this->databases[$role]->snapshot();
                $effects[$role] = M34CloneDb::changed($before, $afterHashes[$role]);
                $this->responses[$role][$case['key']] = $answers[$role]['json'];
                if ($answers[$role]['status'] !== $def['status']) {
                    throw new M34Failure('Positive replay STOP on ' . $role . ' ' . $name . ': expected HTTP ' . $def['status'] . ' got ' . $answers[$role]['status'] . '. Clones must be restored.');
                }
            }
            $same = false; $err = '';
            try {
                $same = M34Parity::identical(M34Parity::wire($answers['baseline'], 'en'), M34Parity::wire($answers['v2'], 'en'));
                if (! $same) { $err = 'Baseline/v2 positive HTTP JSON mismatch'; }
            } catch (M34Failure $e) { $err = $e->getMessage(); }
            $sameEffects = $effects['baseline'] === $effects['v2'];
            if (! $sameEffects) { $err .= ' / Different changed business tables'; }
            // Any unequal raw table digest requires additional review; never
            // count side effects as fully proven solely by equal row counts.
            $hashesEqual = $afterHashes['baseline'] === $afterHashes['v2'];
            $passed = $same && $sameEffects && $hashesEqual;
            if (! $passed) { $this->failures++; }
            $this->results[] = [
                'operation' => $name, 'case' => 'positive', 'pass' => $passed,
                'baseline_status' => $answers['baseline']['status'], 'v2_status' => $answers['v2']['status'],
                'changed_baseline_tables' => $effects['baseline'], 'changed_v2_tables' => $effects['v2'],
                'full_business_table_digests_match' => $hashesEqual,
                'reason' => $err . ($hashesEqual ? '' : ' / Raw business table hashes differ; inspect timestamps/identities/Shield hashes manually'),
            ];
        }
        return $this->report('positive');
    }

    private function assertFilled(mixed $value): void
    {
        if (is_array($value)) { foreach ($value as $item) { $this->assertFilled($item); } return; }
        if (is_string($value) && (str_contains($value, 'FIXTURE_') || str_contains($value, '__REPLACE__'))) {
            throw new M34Failure('A positive scenario contains an unfilled fixture placeholder');
        }
    }

    private function interpolate(mixed $value, string $role): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) { $value[$key] = $this->interpolate($item, $role); }
            return $value;
        }
        if (! is_string($value)) { return $value; }
        if (preg_match('/^\$\{ENV:([A-Z][A-Z0-9_]*)\}$/D', $value, $env)) {
            return M34Safety::environment($env[1]);
        }
        if (preg_match('/\{\{([^{}]+)\}\}/', $value)) {
            if (! preg_match('/^\{\{([A-Za-z][A-Za-z0-9_]*)\.(data(?:\.[A-Za-z_][A-Za-z0-9_]*)+)\}\}$/D', $value, $matches)) {
                throw new M34Failure('Only entire-string {{scenario.data.path}} references are allowed');
            }
            $selected = $this->responses[$role][$matches[1]] ?? null;
            if ($selected === null) { throw new M34Failure('Unresolved fixture reference in ' . $role . ': ' . $value); }
            foreach (explode('.', $matches[2]) as $key) {
                if (! is_array($selected) || ! array_key_exists($key, $selected)) { throw new M34Failure('Missing captured JSON field ' . $value); }
                $selected = $selected[$key];
            }
            return $selected;
        }
        if (str_contains($value, 'FIXTURE_') || str_contains($value, '__REPLACE__')) {
            throw new M34Failure('Unfilled fixture placeholder; stop positive replay');
        }
        return $value;
    }

    /** @return array<string,mixed> */
    private function report(string $mode): array
    {
        return [
            'milestone' => 'M3.4', 'mode' => $mode,
            'checked' => count($this->results), 'passed' => count($this->results) - $this->failures,
            'failed' => $this->failures, 'full_http_baseline_parity' => $this->failures === 0,
            'clones_required' => true, 'original_databases_touched' => false,
            'results' => $this->results,
        ];
    }
}
