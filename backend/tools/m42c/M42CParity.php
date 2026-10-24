<?php

declare(strict_types=1);

require_once __DIR__ . '/M42CPlan.php';
require_once __DIR__ . '/M42CCloneDb.php';
require_once __DIR__ . '/M42CClient.php';

/** Paired real HTTP responses from two independently marked, operator-hosted local clones. */
final class M42CParity
{
    /** @var array<string,M42CCloneDb> */
    private array $databases = [];
    /** @var array<string,M42CClient> */
    private array $clients = [];
    /** @var list<array<string,mixed>> */
    private array $results = [];
    private int $failures = 0;

    /** @param array<string,mixed> $config @param array<string,array<string,mixed>> $ops */
    public function __construct(private readonly array $config, private readonly array $ops)
    {
        foreach (['baseline', 'v2'] as $role) {
            $this->databases[$role] = new M42CCloneDb($config[$role], $role, $config['run_id']);
            $this->clients[$role] = new M42CClient($config[$role]['base_url']);
        }
        if ($this->databases['baseline']->prefix() !== $this->databases['v2']->prefix()) {
            throw new M34Failure('Different physical prefixes: clones were not created from the same schema');
        }
    }

    /** @return array<string,mixed> */
    public function preflight(): array
    {
        $a = $this->databases['baseline']->snapshot();
        $b = $this->databases['v2']->snapshot();
        $changed = M42CCloneDb::changed($a, $b);
        return [
            'phase' => 'PREFLIGHT_READ_ONLY', 'status' => $changed === [] ? 'PASS_CLONE_PREFLIGHT' : 'BLOCKED_DIFFERING_CLONES',
            'marker_guarded_clones' => 2, 'identical_starting_m4_access_rows' => $changed === [],
            'different_tables' => $changed, 'physical_table_prefix' => $this->databases['v2']->prefix(),
            'operation_footprint' => count($this->ops), 'requests_sent' => 0, 'writes_performed_by_runner' => 0,
            'production_write' => 'BLOCKED', 'successful_write_parity' => 'NOT_TESTED',
            'http_application_provenance' => 'OPERATOR_ATTESTED_NOT_AUTOMATED',
        ];
    }

    private function requireSameStartingRows(): void
    {
        $pre = $this->preflight();
        if ($pre['status'] !== 'PASS_CLONE_PREFLIGHT') {
            throw new M34Failure('Two manually marked clones contain different M4/Access rows: restore BOTH from one snapshot');
        }
    }

    private function login(): void
    {
        foreach (['baseline', 'v2'] as $role) {
            $spec = $this->config[$role];
            $this->clients[$role]->login(
                M34Safety::environment($spec['login_user_env']),
                M34Safety::environment($spec['login_password_env'])
            );
        }
    }

    /**
     * One safe, strictly paired local comparison. Error response bodies are
     * SHA-hashed in reports, never copied with potential business data.
     * @param array<string,mixed>|null $body
     */
    private function compare(string $name, string $case, string $path, ?array $body, string $locale, string $csrf): void
    {
        $op = $this->ops[$name] ?? throw new M34Failure('Unregistered M4 operation in compare');
        if (!M42CPlan::pathAllowed($name, $path, $this->ops)) {
            throw new M34Failure('Refusing arbitrary path for M4 operation ' . $name);
        }
        $read = $op['read_only'];
        $replies = [];
        $normalized = [];
        $issues = [];
        foreach (['baseline', 'v2'] as $role) {
            $client = $csrf === 'omit'
                ? new M42CClient($this->config[$role]['base_url'])
                : $this->clients[$role];
            $before = $this->databases[$role]->snapshot();
            $replies[$role] = $client->request($op['method'], $path, $body, $locale, $csrf);
            $after = $this->databases[$role]->snapshot();
            $changed = M42CCloneDb::changed($before, $after);
            if ($changed !== []) {
                throw new M34Failure('STOP: ' . $role . ' clone mutated tracked SQL on ' . $name
                    . ' / ' . $case . ' [' . implode(',', $changed) . ']. Restore both clones');
            }
            try {
                $normalized[$role] = M34Parity::wire($replies[$role], $locale);
                $status = $replies[$role]['status'];
                if ($read && $status !== $op['status']) {
                    $issues[] = $role . ': expected frozen success HTTP ' . $op['status'] . ', got ' . $status;
                }
                if (!$read && ($status < 400 || $status >= 500)) {
                    $issues[] = $role . ': denied request produced non-4xx HTTP ' . $status;
                }
                if (!$read && ($replies[$role]['json']['error']['code'] ?? null) === 'ACCESS_MUTATION_NOT_ENABLED') {
                    $issues[] = $role . ': mutation feature gate is off, cannot compare runtime denial semantics';
                }
                if (($replies[$role]['headers']['content-language'] ?? null) !== $locale) {
                    $issues[] = $role . ': Content-Language mismatch';
                }
            } catch (Throwable $error) {
                $issues[] = $role . ': invalid normalized API envelope: ' . $error->getMessage();
            }
        }
        $same = isset($normalized['baseline'], $normalized['v2'])
            && M34Parity::identical($normalized['baseline'], $normalized['v2']);
        if (!$same) $issues[] = 'Frozen baseline and v2 status/JSON are not identical';
        $passed = $issues === [];
        if (!$passed) $this->failures++;
        $this->results[] = [
            'operation_id' => $name, 'case' => $case, 'locale' => $locale, 'pass' => $passed,
            'baseline_status' => $replies['baseline']['status'] ?? null,
            'v2_status' => $replies['v2']['status'] ?? null,
            'baseline_error_code' => $replies['baseline']['json']['error']['code'] ?? null,
            'v2_error_code' => $replies['v2']['json']['error']['code'] ?? null,
            'baseline_normalized_sha256' => isset($normalized['baseline'])
                ? hash('sha256', json_encode($normalized['baseline'], JSON_THROW_ON_ERROR)) : null,
            'v2_normalized_sha256' => isset($normalized['v2'])
                ? hash('sha256', json_encode($normalized['v2'], JSON_THROW_ON_ERROR)) : null,
            'issues' => $issues,
        ];
    }

    /** Read-only business API parity (7 GET + dry-run POST), only explicitly ready scenarios. */
    public function read(string $file): array
    {
        $ready = self::checkedReadCases($file, $this->ops);
        $this->requireSameStartingRows();
        $this->login();
        foreach (M42CPlan::readNames($this->ops) as $name) {
            foreach (['en', 'vi'] as $locale) {
                $this->compare($name, 'read_only', $ready[$name]['path'], $ready[$name]['body'], $locale, 'auto');
            }
        }
        return $this->report('READ_ONLY_PAIRED_PARITY', 16);
    }

    /** @return array<string,array{path:string,body:array|null}> */
    public static function checkedReadCases(string $file, array $ops): array
    {
        if (!is_file($file)) throw new M34Failure('Missing explicit M4.2C read-only scenario config');
        $input = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($input) || ($input['purpose'] ?? null) !== 'M42C_READ_ONLY_PARITY'
            || !is_array($input['scenarios'] ?? null) || count($input['scenarios']) !== 8) {
            throw new M34Failure('Read parity requires exactly eight approved read/dry-run scenarios');
        }
        $ready = [];
        foreach ($input['scenarios'] as $case) {
            if (!is_array($case) || ($case['ready'] ?? null) !== true) {
                throw new M34Failure('Read parity scenarios are NOT automatically runnable; explicitly review all eight');
            }
            $name = $case['operation_id'] ?? null;
            $path = $case['path'] ?? null;
            $body = $case['body'] ?? null;
            if (!is_string($name) || !in_array($name, M42CPlan::readNames($ops), true)
                || isset($ready[$name]) || !is_string($path) || !M42CPlan::pathAllowed($name, $path, $ops)
                || ($body !== null && (!is_array($body) || array_is_list($body)))
                || ($ops[$name]['method'] === 'GET' && $body !== null)
                || ($name === 'validateNumberingSeriesConfig' && !is_array($body))) {
                throw new M34Failure('Invalid or duplicated M4.2C read scenario');
            }
            $ready[$name] = ['path' => $path, 'body' => $body];
        }
        if (count($ready) !== 8) throw new M34Failure('Missing read-only operations in M4.2C scenarios');
        return $ready;
    }

    /** Only CSRF-denied and anonymous requests to the six mutation routes. No authorized writes. */
    public function negative(): array
    {
        $this->requireSameStartingRows();
        $this->login();
        foreach (M42CPlan::writeNames($this->ops) as $name) {
            $path = M42CPlan::invalidWritePath($name, $this->ops);
            foreach (['en', 'vi'] as $locale) {
                $this->compare($name, 'bad_csrf', $path, [], $locale, 'invalid');
                $this->compare($name, 'anonymous', $path, [], $locale, 'omit');
            }
        }
        return $this->report('DENIAL_ONLY_PAIRED_PARITY', 24);
    }

    private function report(string $phase, int $expected): array
    {
        if (count($this->results) !== $expected) {
            throw new M34Failure('Incomplete paired M4.2C operation inventory');
        }
        return [
            'phase' => $phase,
            'status' => $this->failures === 0 ? 'PASS_LOCAL_PAIRED_CLONES' : 'FAIL_BASELINE_DIFFERENCE',
            'paired_cases' => count($this->results), 'passed' => count($this->results) - $this->failures,
            'failed' => $this->failures, 'results' => $this->results,
            'baseline_http_and_database' => 'OPERATOR_HOSTED_LOCAL_CLONES',
            'http_application_provenance' => 'OPERATOR_ATTESTED_NOT_AUTOMATED',
            'authorized_mutation_replay' => 'NOT_TESTED',
            'number_issuance' => 'NOT_IMPLEMENTED_NOT_TESTED',
            'production_write' => 'BLOCKED',
        ];
    }
}
