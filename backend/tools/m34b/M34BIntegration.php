<?php

declare(strict_types=1);

require_once __DIR__ . '/M34BSandbox.php';
require_once __DIR__ . '/M34BPlan.php';
require_once __DIR__ . '/M34BChildDiagnostics.php';

/** Single local CI4 server, unique synthetic MariaDB sandbox; never baseline live HTTP. */
final class M34BIntegration
{
    private mixed $process = null;
    /** @var array<int,resource> */
    private array $pipes = [];
    private array $results = [];
    private array $failed = [];
    private string $origin = '';
    private array $context = [];
    private bool $attested = false;
    private bool $loggedIn = false;
    private int $positive = 0;
    private int $negative = 0;
    private int $csrf = 0;
    private int $authenticatedInvalid = 0;
    private int $translatedPairs = 0;

    /** @param array<string,array{method:string,path:string,status:int}> $operations */
    public function __construct(
        private readonly string $root,
        private readonly M34BSandbox $sandbox,
        private readonly array $operations,
        private readonly array $fixture,
        private readonly string $mysqlUser,
        private readonly string $mysqlPassword,
        private readonly int $mysqlPort,
        private readonly string $logDirectory,
    ) {
        M34BPlan::assertCoverage($operations);
    }

    /** @return array<string,mixed> */
    public function run(): array
    {
        $start = microtime(true);
        try {
            $port = self::unusedPort();
            $this->origin = 'http://127.0.0.1:' . $port;
            M34BSafety::assertUrl($this->origin);
            $env = $this->childEnv($port);
            $this->ensureSessionDirectory();
            $this->childAttest($env);
            $this->launchServer($env, $port);
            $this->probeUntrustedRequests();
            // Do not mutate even a disposable database if earlier safety
            // protocol checks failed; preserve evidence for diagnosis.
            if ($this->failed === []) {
                $this->probeAuthenticatedAndPositive();
            }
        } catch (Throwable $e) {
            $this->failed[] = ['phase'=>'setup_or_execution', 'reason'=>M34BSafety::sanitizedException($e)];
        } finally {
            $this->stopServer();
        }
        return [
            'milestone'=>'M3.4B',
            'evidence'=>'single Backend v2 local HTTP + isolated synthetic MariaDB; NOT live-baseline parity',
            'verdict'=>$this->failed === [] && $this->positive === 29 && $this->negative === 58
                && $this->csrf === 29 && $this->authenticatedInvalid === 58
                && $this->translatedPairs > 0 && $this->attested && $this->loggedIn
                && $this->rollback === 'PASS_DB_ATOMICITY_NOT_BASELINE_PARITY' ? 'PASS' : 'NEEDS_FIX_OR_VERIFY',
            'contract_operations'=>count($this->operations),
            'unauthenticated_bilingual_probes'=>$this->negative,
            'invalid_csrf_probes'=>$this->csrf,
            'authenticated_invalid_bilingual_probes'=>$this->authenticatedInvalid,
            'distinct_translated_error_pairs'=>$this->translatedPairs,
            'successful_positive_mutations'=>$this->positive,
            'child_database_attested'=>$this->attested,
            'shield_session_authenticated'=>$this->loggedIn,
            'sandbox_name'=>$this->sandbox->name,
            'sandbox_retained_for_review'=>true,
            'real_db_rollback'=>$this->rollbackStatus(),
            'runtime_seconds'=>round(microtime(true)-$start, 2),
            'failed'=>$this->failed,
            'results'=>$this->results,
            'limits'=>['No baseline live HTTP parity', 'No full concurrency/Isolation-level parity',
                'Mock/fixture assertions and local MariaDB evidence are different levels'],
        ];
    }

    private string $rollback = 'NOT_RUN';
    private function rollbackStatus(): string { return $this->rollback; }

    private static function unusedPort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $err, $msg);
        if ($socket === false) { throw new M34BFailure('Cannot allocate a local PHP test server port'); }
        $addr = stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int) substr(strrchr((string)$addr, ':'), 1);
        if ($port < 1024 || $port > 65535) { throw new M34BFailure('Invalid test port'); }
        return $port;
    }

    /** @return array<string,string> */
    private function childEnv(int $port): array
    {
        $env = [];
        foreach (['PATH','Path','SYSTEMROOT','SystemRoot','WINDIR','windir','TEMP','TMP','TMPDIR',
            'HOME','USERPROFILE','HOMEDRIVE','HOMEPATH','COMSPEC','COMSPEC','APPDATA','LOCALAPPDATA',
            'PHPRC','PHP_INI_SCAN_DIR'] as $key) {
            $value = getenv($key);
            if (is_string($value)) { $env[$key] = $value; }
        }
        // Filter child environment: do not propagate production DB credentials,
        // OAuth tokens, unrelated secrets, or a caller's production write flags.
        return [
            ...$env, 'CI_ENVIRONMENT'=>'development', 'DINKERP_M33B_WRITE_MODE'=>'enabled',
            'M34B_ONLY_SCHEMA'=>$this->sandbox->name, 'M34B_ONLY_NONCE'=>$this->sandbox->nonce,
            'app.baseURL'=>$this->origin, 'app.forceGlobalSecureRequests'=>'false',
            'database.default.hostname'=>'127.0.0.1',
            'database.default.port'=>(string)$this->mysqlPort,
            'database.default.database'=>$this->sandbox->name,
            'database.default.username'=>$this->mysqlUser,
            'database.default.password'=>$this->mysqlPassword,
            'database.default.DBDriver'=>'MySQLi',
            'database.default.DBPrefix'=>$this->sandbox->tablePrefix(),
            // Use normal CI4 HTTP/session behavior: `testing` is reserved for PHPUnit.
            // Override BOTH database groups to the same nonce-owned sandbox,
            // so no connection group can silently select the source schema.
            'database.tests.hostname'=>'127.0.0.1',
            'database.tests.port'=>(string)$this->mysqlPort,
            'database.tests.database'=>$this->sandbox->name,
            'database.tests.username'=>$this->mysqlUser,
            'database.tests.password'=>$this->mysqlPassword,
            'database.tests.DBDriver'=>'MySQLi',
            'database.tests.DBPrefix'=>$this->sandbox->tablePrefix(),
            'session.savePath'=>$this->logDirectory . DIRECTORY_SEPARATOR . 'session',
            'auth.allowRemembering'=>'false',
        ];
    }

    /** @param array<string,string> $env */
    private function childAttest(array $env): void
    {
        $cmd = [PHP_BINARY, $this->root . '/backend/tools/m34b/child_spark.php',
            'm34b:attest', $this->sandbox->name, $this->sandbox->nonce];
        $proc = proc_open($cmd, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes,
            $this->root . '/backend', $env);
        if (! is_resource($proc)) { throw new M34BFailure('Cannot start sandbox DB attestation'); }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $status = proc_close($proc);
        $marker = 'M34B_ATTEST_OK ' . $this->sandbox->name;
        $passed = $status === 0 && preg_match('/(?:\A|\R)' . preg_quote($marker, '/') . '(?:\R|\z)/D', $stdout) === 1;
        $summary = M34BChildDiagnostics::summarize($stdout, $stderr, $status, $passed);
        // Save only fixed-code diagnostic metadata; NEVER raw child output,
        // as CI4 error pages and stack traces can contain credentials.
        $path = $this->logDirectory . '/child_attestation.json';
        $encoded = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (! is_string($encoded) || file_put_contents($path, $encoded . "\n", LOCK_EX) === false) {
            throw new M34BFailure('Unable to write sanitized child attestation status.');
        }
        if (! $passed) {
            throw new M34BFailure('Forked CI4 sandbox attestation refused: ' . $summary['code']
                . ' (child exit ' . $status . '). ' . $summary['advice']
                . ' Diagnostic: backend/writable/m34b/run_*/child_attestation.json');
        }
        $this->attested = true;
    }

    private function ensureSessionDirectory(): void
    {
        $session = $this->logDirectory . '/session';
        if (! is_dir($session) && ! mkdir($session, 0700, true) && ! is_dir($session)) {
            throw new M34BFailure('Cannot create isolated PHP session directory.');
        }
    }

    /** @param array<string,string> $env */
    private function launchServer(array $env, int $port): void
    {
        $this->ensureSessionDirectory();
        $cmd = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t',
            $this->root . '/backend/public', $this->root . '/backend/tools/m34b/router.php'];
        $this->process = proc_open($cmd, [
            0=>['pipe','r'],
            1=>['file',$this->logDirectory.'/http_stdout.log','a'],
            2=>['file',$this->logDirectory.'/http_stderr.log','a'],
        ], $this->pipes, $this->root . '/backend', $env);
        if (! is_resource($this->process)) { throw new M34BFailure('Could not start loopback PHP server'); }
        for ($i=0; $i<50; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $error, $message, 0.1);
            if (is_resource($socket)) { fclose($socket); return; }
            usleep(100000);
        }
        throw new M34BFailure('Loopback test server failed to start. Read http_stderr.log.');
    }

    private function stopServer(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            foreach ($this->pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
            proc_close($this->process);
            $this->process = null;
        }
    }

    private function probeUntrustedRequests(): void
    {
        $client = new M34Http($this->origin);
        $before = $this->sandbox->fingerprint();
        foreach ($this->operations as $name => $op) {
            $path = M34Contract::missingTargetPath($op);
            foreach (['en','vi'] as $locale) {
                $response = $client->request($op['method'], $path, [], $locale, 'omit');
                $pass = in_array($response['status'], [401,403], true);
                try { M34Parity::wire($response, $locale); }
                catch (M34Failure) { $pass = false; }
                if (! $pass) {
                    $this->failed[] = ['phase'=>'unauth', 'operation'=>$name, 'locale'=>$locale,
                        'status'=>$response['status']];
                }
                $this->negative++;
            }
        }
        if ($before !== $this->sandbox->fingerprint()) {
            $this->failed[] = ['phase'=>'unauth','reason'=>'Unauthenticated probes altered tracked ERP/Shield business tables'];
        }
    }

    private function probeAuthenticatedAndPositive(): void
    {
        $client = new M34Http($this->origin);
        $this->sandbox->attest();
        $client->login($this->fixture['email'], $this->fixture['password']);
        $this->loggedIn = true;
        $this->sandbox->attest();
        $beforeCsrf = $this->sandbox->fingerprint();
        foreach ($this->operations as $name => $op) {
            $path = M34Contract::missingTargetPath($op);
            $response = $client->request($op['method'], $path, [], 'en', 'invalid');
            $pass = $response['status'] === 403;
            try { M34Parity::wire($response, 'en'); }
            catch (M34Failure) { $pass = false; }
            if (! $pass) {
                $this->failed[] = ['phase'=>'csrf','operation'=>$name,'status'=>$response['status']];
            }
            $this->csrf++;
        }
        if ($beforeCsrf !== $this->sandbox->fingerprint()) {
            $this->failed[] = ['phase'=>'csrf','reason'=>'Bad-CSRF probes altered tracked business tables'];
        }
        if ($this->failed !== []) { return; }
        $this->probeAuthenticatedValidation($client);
        if ($this->failed !== []) { return; }
        $this->context = [
            'root'=>$this->fixture['root'], 'state_active'=>$this->fixture['states']['ACTIVE'],
            'state_inactive'=>$this->fixture['states']['INACTIVE'],
            'resource_resource'=>$this->fixture['resources']['resource'],
            'action_resource_view'=>$this->fixture['actions']['resource.view'],
            'admin_group'=>$this->fixture['group'], 'admin_user'=>$this->fixture['user'],
            'token'=>$this->sandbox->nonce,
            'new_user_password'=>bin2hex(random_bytes(14)).'aB8!',
            'reset_password'=>bin2hex(random_bytes(14)).'aB8!',
        ];
        $this->probeAbsoluteDeny($client);
        if ($this->failed !== []) { return; }
        foreach (M34BPlan::order() as $name) {
            $definition = $this->operations[$name];
            $case = M34BPlan::case($name, $this->context);
            $before = $this->sandbox->fingerprint();
            $answer = $client->request($definition['method'], $case['path'], $case['body'], 'en');
            $good = $answer['status'] === $definition['status'];
            try { M34Parity::wire($answer,'en'); }
            catch (M34Failure) { $good = false; }
            $key = M34BPlan::createdKey($name);
            if ($key !== null && $good) {
                $id = $answer['json']['data']['id'] ?? null;
                if ((is_int($id) || is_string($id)) && preg_match('/\A[1-9][0-9]*\z/D', (string)$id)) {
                    $this->context[$key] = (string) $id;
                } else { $good = false; }
            }
            $after = $this->sandbox->fingerprint();
            $changed = array_keys(array_filter($after, static fn ($hash,$table): bool => $hash !== ($before[$table] ?? null), ARRAY_FILTER_USE_BOTH));
            // HTTP 2xx alone is not evidence of a database mutation.
            if ($good && $changed === []) { $good = false; }
            if ($good) { $this->positive++; }
            $this->results[] = [
                'case'=>$name,'expected_status'=>$definition['status'],'actual_status'=>$answer['status'],
                'pass'=>$good,'changed_tables'=>$changed,
                'error_code'=>$answer['json']['error']['code'] ?? null,
            ];
            if (! $good) {
                $this->failed[] = ['phase'=>'positive','operation'=>$name,
                    'expected'=>$definition['status'],'actual'=>$answer['status'],
                    'error_code'=>$answer['json']['error']['code'] ?? null];
                return; // dependent IDs no longer trustworthy; preserve sandbox.
            }
            if ($name === 'setResourceState') {
                // Restore before DELETE; this extra request is reported and is
                // not miscounted as one of the 29 covered operations.
                $restore = $client->request('POST', '/api/v1/resources/'.$this->context['new_resource']
                    .'/actions/set-state', ['status'=>'ACTIVE'],'en');
                if ($restore['status'] !== 200) {
                    $this->failed[] = ['phase'=>'dependency','reason'=>'Resource restoration before DELETE failed'];
                    return;
                }
            }
        }
        $this->checkShieldPasswordHash();
        $this->probeRealRollback($client);
    }

    /**
     * Real authenticated validation/not-found error envelopes in vi/en.
     * These intentionally reference missing targets or send empty payloads;
     * they must never reach a mutating SQL branch.
     */
    private function probeAuthenticatedValidation(M34Http $client): void
    {
        $before = $this->sandbox->fingerprint();
        foreach ($this->operations as $name => $op) {
            $path = M34Contract::missingTargetPath($op);
            $pair = [];
            foreach (['en', 'vi'] as $locale) {
                $response = $client->request($op['method'], $path, [], $locale);
                $good = $response['status'] >= 400 && $response['status'] < 500;
                try { M34Parity::wire($response, $locale); }
                catch (M34Failure) { $good = false; }
                if (! $good) {
                    $this->failed[] = ['phase'=>'authenticated_invalid', 'operation'=>$name,
                        'locale'=>$locale, 'status'=>$response['status']];
                }
                $pair[$locale] = $response['json']['error'] ?? null;
                $this->authenticatedInvalid++;
            }
            if (is_array($pair['en']) && is_array($pair['vi'])
                && ($pair['en']['code'] ?? null) === ($pair['vi']['code'] ?? null)
                && ($pair['en']['message'] ?? null) !== ($pair['vi']['message'] ?? null)) {
                $this->translatedPairs++;
            }
        }
        if ($this->translatedPairs === 0) {
            $this->failed[] = ['phase'=>'translation', 'reason'=>'No translated vi/en error-message pair on real HTTP'];
        }
        if ($before !== $this->sandbox->fingerprint()) {
            $this->failed[] = ['phase'=>'authenticated_invalid',
                'reason'=>'Invalid authenticated requests altered tracked business tables'];
        }
    }

    /** Quoted sandbox physical table name, never a source schema reference. */
    private function qTable(string $logical): string
    {
        return M34BSafety::identifier($this->sandbox->tableName($logical));
    }

    private function probeAbsoluteDeny(M34Http $client): void
    {
        // A real persisted ABSOLUTE_DENY policy must veto the actor's
        // GROUP+Shield base permission. This is created only inside the
        // nonce-owned sandbox and fully removed before positive replay.
        $db = $this->sandboxDb();
        $now = gmdate('Y-m-d H:i:s');
        $id = null;
        try {
            $db->prepare('INSERT INTO ' . $this->qTable('policies')
                . ' (organization_id,resource_id,state_id,participant_type,participant_ref_id,status,created_at) '
                . "VALUES (?,?,NULL,'GROUP',?,'ACTIVE',?)")
                ->execute([$this->context['root'],$this->fixture['resources']['authorization_group'],
                    $this->fixture['group'],$now]);
            $id = (string)$db->lastInsertId();
            $db->prepare('INSERT INTO ' . $this->qTable('policy_actions')
                . ' (policy_id,resource_action_id,effect,created_at) VALUES (?,?,?,?)')
                ->execute([$id,$this->fixture['actions']['authorization_group.create'],'ABSOLUTE_DENY',$now]);
            $before = $this->sandbox->fingerprint();
            $response = $client->request('POST','/api/v1/authorization-groups',
                ['code'=>'M34B_VETO_'.strtoupper(substr($this->sandbox->nonce,0,10)), 'name'=>'Must not exist'], 'en');
            $good = $response['status'] === 403;
            try { M34Parity::wire($response,'en'); }
            catch (M34Failure) { $good = false; }
            if (! $good || $before !== $this->sandbox->fingerprint()) {
                $this->failed[] = ['phase'=>'pbac','reason'=>'ABSOLUTE_DENY did not veto the real HTTP create',
                    'status'=>$response['status']];
            } else {
                $this->results[] = ['case'=>'real_absolute_deny_veto','pass'=>true,'status'=>403];
            }
        } finally {
            if ($id !== null) {
                $db->prepare('DELETE FROM ' . $this->qTable('policy_actions') . ' WHERE policy_id=?')->execute([$id]);
                $db->prepare('DELETE FROM ' . $this->qTable('policies') . ' WHERE id=?')->execute([$id]);
            }
        }
    }

    private function checkShieldPasswordHash(): void
    {
        $db = $this->sandboxDb();
        $query = $db->prepare('SELECT secret2 FROM ' . $this->qTable('auth_identities')
            . " WHERE user_id=? AND type='email_password' LIMIT 1");
        $query->execute([$this->context['new_user']]);
        $hash = $query->fetchColumn();
        if (! is_string($hash) || ! password_verify($this->context['reset_password'], $hash)) {
            $this->failed[] = ['phase'=>'shield','reason'=>'Real MariaDB Shield password hash did not match reset input'];
        } else {
            $this->results[] = ['case'=>'shield_hash_after_reset','pass'=>true];
        }
    }

    private function probeRealRollback(M34Http $client): void
    {
        // Install a deterministic, sandbox-only trigger: the Policy INSERT
        // succeeds, then the Policy Action INSERT raises a real MariaDB error.
        // This exercises the service's transaction rollback, not an early
        // request-validation veto or a mock database.
        $db = $this->sandboxDb();
        $trigger = 'm34b_forced_action_rollback';
        $db->exec('CREATE TRIGGER `'.$trigger.'` BEFORE INSERT ON ' . $this->qTable('policy_actions') . ' '
            . "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='M34B_FORCED_INSERT_FAILURE'");
        try {
            $before = $this->sandbox->fingerprint();
            $body = [
                'organization_id'=>$this->context['root'],
                'resource_id'=>$this->context['resource_resource'],
                'participant_type'=>'GROUP','participant_ref_id'=>$this->context['admin_group'],
                'state_id'=>null,
                'actions'=>[[
                    'resource_action_id'=>$this->context['action_resource_view'],'effect'=>'ALLOW',
                ]],
            ];
            $answer = $client->request('POST','/api/v1/policies',$body,'en');
            $after = $this->sandbox->fingerprint();
            $validEnvelope = true;
            try { M34Parity::wire($answer, 'en'); }
            catch (M34Failure) { $validEnvelope = false; }
            if ($answer['status'] < 400 || ! $validEnvelope || $before !== $after) {
                $this->rollback = 'FAIL';
                $this->failed[] = ['phase'=>'rollback','reason'=>'Forced SQL Policy Action INSERT not rolled back atomically',
                    'status'=>$answer['status']];
            } else {
                $this->rollback = 'PASS_DB_ATOMICITY_NOT_BASELINE_PARITY';
                $this->results[] = ['case'=>'forced_mariadb_policy_action_insert_rollback',
                    'pass'=>true,'status'=>$answer['status'],'tracked_table_digests_unchanged'=>true];
            }
        } finally {
            // TRIGGER is on the nonce-specific sandbox connection, and its
            // name is fixed by source code, never from untrusted input.
            $db->exec('DROP TRIGGER IF EXISTS `'.$trigger.'`');
        }
    }

    private function sandboxDb(): PDO
    {
        // No connection to template database: this is a disposable sandbox DB.
        $db = M34BSandbox::connect('127.0.0.1', $this->mysqlPort, $this->mysqlUser, $this->mysqlPassword);
        $db->exec('USE ' . M34BSafety::identifier($this->sandbox->name));
        $r = $db->query("SELECT run_id FROM m34b_sandbox_guard WHERE id=1 AND purpose='M34B_DISPOSABLE_SCHEMA'")->fetchColumn();
        if (! is_string($r) || ! hash_equals($this->sandbox->nonce,$r)) {
            throw new M34BFailure('Auxiliary read-only SQL connection failed sandbox attestation.');
        }
        return $db;
    }
}
