<?php

declare(strict_types=1);

require_once __DIR__.'/M63CRacePlan.php';
require_once dirname(__DIR__).'/m63b2/M63B2DevDelegation.php';

/**
 * Explicit DEV-only two-HTTP-writer Avatar CAS test.
 * A third local MySQL connection holds SELECT ... FOR UPDATE on exactly one
 * approved, empty Shield User. Two independent logged-in Apache clients must
 * be observed WAITING on the same InnoDB row before that lock is released.
 * No production service, schema, PBAC or public-upload policy is modified.
 */
final class M63CAvatarRace
{
    /** @var array<string,resource> */
    private array $workers = [];
    private ?PDO $lockDb = null;
    private bool $locked = false;
    private string $phase = 'begin';
    private readonly string $nonce;
    private readonly string $dir;
    private readonly string $root;
    private bool $safeCleanup = false;
    private bool $writesPossible = false;
    private string $mysqlAttestStep = 'not_started';

    public function __construct()
    {
        $this->root = dirname(__DIR__, 2);
        $this->nonce = bin2hex(random_bytes(12));
        $this->dir = $this->root.'/writable/m63c/run_'.$this->nonce;
    }

    /**
     * SELECT-only local PDO / table-engine / telemetry readiness. This never
     * starts HTTP workers or obtains a FOR UPDATE row lock.
     * @return array<string,mixed>
     */
    public function mysqlCheck(string $id, string $database): array
    {
        $result = [
            'verdict' => 'FAIL_DEV_AVATAR_MYSQL_ATTEST',
            'phase' => 'attest_local_mysql',
            'mode' => 'READ_ONLY_MYSQL_ATTEST',
            'target_user_id' => $id,
            'database' => $database,
            'mysql_attest_step' => null,
            'failure_code' => null,
            'partial_state_review_required' => false,
            'production_cutover' => 'BLOCKED',
        ];
        try {
            [$pdo, $files] = $this->attestLocalMysql($id, $database);
            unset($pdo, $files);
            $result['phase'] = 'complete';
            $result['mysql_attest_step'] = 'complete';
            $result['verdict'] = 'PASS_DEV_AVATAR_MYSQL_ATTEST_READ_ONLY';
        } catch (Throwable $error) {
            $result['mysql_attest_step'] = $this->mysqlAttestStep;
            $result['failure_code'] = $this->attestFailureCode($error);
        }
        return $result;
    }

    /**
     * Reused by --mysql-check and --apply; neither mode infers credentials
     * from the CI4 DB connection, and both verify the same pinned database.
     * @return array{0:PDO,1:array<string,true>}
     */
    private function attestLocalMysql(string $id, string $database): array
    {
        $this->mysqlAttestStep = 'credentials';
        $user = getenv('M63C_MYSQL_USER');
        $password = getenv('M63C_MYSQL_PASSWORD');
        if ($password === false) $password = ''; // XAMPP permits empty local password.
        $port = (string) (getenv('M63C_MYSQL_PORT') ?: '3306');
        if (!is_string($user) || $user === '' || !is_string($password)
            || preg_match('/\A[0-9]{1,5}\z/D', $port) !== 1
            || (int)$port < 1 || (int)$port > 65535) {
            throw new RuntimeException('Invalid local MySQL credentials/port.');
        }

        $this->mysqlAttestStep = 'pdo_driver';
        if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('PDO MySQL driver not available.');
        }

        $this->mysqlAttestStep = 'pdo_connection';
        $pdo = new PDO('mysql:host=127.0.0.1;port='.$port.';dbname='.$database.';charset=utf8mb4',
            $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 5]);

        $this->mysqlAttestStep = 'database_identity';
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) {
            throw new RuntimeException('Connected DB differs from explicitly pinned DEV database.');
        }

        $this->mysqlAttestStep = 'users_table_engine';
        $engine = $pdo->query("SELECT ENGINE FROM information_schema.TABLES "
            ."WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users'")->fetchColumn();
        if (strtoupper((string)$engine) !== 'INNODB') {
            throw new RuntimeException('Shield users table is not an attested InnoDB table.');
        }

        $this->mysqlAttestStep = 'target_sql_audit';
        M63B2AvatarAudit::target($id, null);

        $this->mysqlAttestStep = 'avatar_file_inventory';
        $files = M63CRacePlan::files();

        // Queryability is necessary, not sufficient, to prove two lock waits.
        // The latter is evidenced only by the real two-writer --apply run.
        $this->mysqlAttestStep = 'lock_telemetry_query';
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT t.trx_mysql_thread_id) "
            ."FROM information_schema.INNODB_TRX t "
            ."JOIN information_schema.PROCESSLIST p ON p.ID=t.trx_mysql_thread_id "
            ."WHERE p.DB=? AND t.trx_state='LOCK WAIT' "
            ."AND UPPER(COALESCE(t.trx_query,'')) LIKE '%AVATAR_FILENAME%'");
        $stmt->execute([$database]);
        $stmt->fetchColumn();

        $this->mysqlAttestStep = 'complete';
        return [$pdo, $files];
    }

    /** Only fixed error identifiers are exposed; no PDO messages/SQL/passwords. */
    private function attestFailureCode(Throwable $error): string
    {
        if ($this->mysqlAttestStep === 'credentials') return 'MYSQL_CREDENTIALS_OR_PORT_INVALID';
        if ($this->mysqlAttestStep === 'pdo_driver') return 'PDO_MYSQL_DRIVER_MISSING';
        if ($this->mysqlAttestStep === 'pdo_connection') {
            $vendor = $error instanceof PDOException ? (int)($error->errorInfo[1] ?? 0) : 0;
            return match ($vendor) {
                1045, 1044 => 'MYSQL_AUTHENTICATION_DENIED',
                1049 => 'MYSQL_DATABASE_NOT_FOUND',
                2002, 2003 => 'MYSQL_CONNECTION_UNAVAILABLE',
                default => 'MYSQL_PDO_CONNECTION_FAILED',
            };
        }
        return match ($this->mysqlAttestStep) {
            'database_identity' => 'MYSQL_DATABASE_IDENTITY_FAILED',
            'users_table_engine' => 'MYSQL_USERS_ENGINE_NOT_INNODB_OR_UNREADABLE',
            'target_sql_audit' => 'MYSQL_AVATAR_TARGET_AUDIT_FAILED',
            'avatar_file_inventory' => 'MYSQL_AVATAR_FILE_INVENTORY_FAILED',
            'lock_telemetry_query' => 'MYSQL_LOCK_TELEMETRY_QUERY_FAILED',
            default => 'MYSQL_ATTESTATION_FAILED',
        };
    }

    /** @return array<string,mixed> */
    public function run(string $id, string $database, string $adminUsername, string $adminPassword,
        string $targetUsername, string $targetPassword, bool $apply): array
    {
        $result = [
            'verdict' => 'FAIL_DEV_AVATAR_RACE_QA', 'phase' => 'begin',
            'mode' => $apply ? 'EXPLICIT_RACE_WRITE' : 'READ_ONLY_PREFLIGHT',
            'target_user_id' => $id, 'database' => $database,
            'preflight' => false, 'two_independent_sessions' => false,
            'two_mysql_lock_waits_observed' => false, 'lock_wait_connections' => 0,
            'two_http_writers' => false, 'winner_http' => null, 'loser_http' => null,
            'loser_error_code' => null, 'cas_winner_preserved' => false,
            'loser_staged_file_cleaned' => false, 'final_sql_empty' => false,
            'winning_file_deleted' => false, 'partial_state_review_required' => false,
            'forced_filesystem_fault_live_http' => 'NOT_TESTED',
            'independent_baseline_parity' => 'NOT_TESTED',
            'production_cutover' => 'BLOCKED',
        ];
        // The same SELECT-only, Shield-session and record-PBAC preflight as M6.3B.2.
        $this->phase = 'read_only_preflight';
        $pre = (new M63B2DevDelegation())->run($id,
            $adminUsername, $adminPassword, $targetUsername, $targetPassword, false);
        if (($pre['verdict'] ?? null) !== 'PASS_DEV_ADMIN_AVATAR_PREFLIGHT') {
            $result['phase'] = $this->phase;
            $result['preflight_phase'] = $pre['phase'] ?? null;
            return $result;
        }
        $result['preflight'] = true;
        if (!$apply) {
            $result['verdict'] = 'PASS_DEV_AVATAR_RACE_PREFLIGHT';
            $result['phase'] = 'complete';
            return $result;
        }

        $before = [];
        $winnerFilename = null;
        $winningUrl = null;
        try {
            $this->phase = 'attest_local_mysql';
            [$pdo, $before] = $this->attestLocalMysql($id, $database);
            $this->phase = 'start_two_authenticated_workers';
            if (!is_dir(dirname($this->dir)) && !mkdir(dirname($this->dir), 0700, true)) {
                throw new RuntimeException('Private QA run directory unavailable.');
            }
            if (!mkdir($this->dir, 0700)) throw new RuntimeException('Cannot make nonce-owned QA directory.');
            putenv('M63C_RUN_NONCE='.$this->nonce);
            putenv('M63C_TARGET_ID='.$id);
            foreach (['a','b'] as $role) $this->start($role, $id);
            $this->waitFor(['race_a_ready','race_b_ready'], 15);
            $result['two_independent_sessions'] = true;

            $this->phase = 'lock_previously_empty_target';
            if (!$pdo->beginTransaction()) throw new RuntimeException('Cannot begin read-lock transaction.');
            $this->lockDb = $pdo;
            $this->locked = true;
            $select = $pdo->prepare('SELECT id, avatar_filename, active, deleted_at FROM `users` WHERE id=? FOR UPDATE');
            $select->execute([$id]);
            $snapshot = $select->fetch(PDO::FETCH_ASSOC);
            if (!is_array($snapshot) || (string)$snapshot['id'] !== $id
                || (int)$snapshot['active'] !== 1 || $snapshot['deleted_at'] !== null
                || $snapshot['avatar_filename'] !== null && $snapshot['avatar_filename'] !== '') {
                throw new RuntimeException('Target changed before the race lock.');
            }
            $this->phase = 'submit_two_independent_http_posts';
            // From this barrier onward the workers may write via the public API.
            $this->writesPossible = true;
            if (file_put_contents($this->dir.'/race_go', "GO\n", LOCK_EX) === false) {
                throw new RuntimeException('Cannot release race HTTP barrier.');
            }
            $this->waitFor(['race_a_requesting','race_b_requesting'], 8);
            $this->phase = 'observe_two_mysql_lock_waits';
            $count = $this->twoWaitingWriters($pdo, $database, 9);
            $result['lock_wait_connections'] = $count;
            $result['two_mysql_lock_waits_observed'] = $count === 2;
            if ($count !== 2 || is_file($this->dir.'/race_a_result.json')
                || is_file($this->dir.'/race_b_result.json')) {
                throw new RuntimeException('Two independent SQL waits were not established.');
            }
            $this->phase = 'release_lock';
            $this->unlock();
            $this->phase = 'check_http_cas_outcomes';
            $this->waitFor(['race_a_result.json','race_b_result.json'], 28);
            $a = $this->readResult('a');
            $b = $this->readResult('b');
            $winner = M63CRacePlan::results($a, $b, $id);
            $winnerFilename = $winner['filename'];
            $winningUrl = $winner['winner'] === 'a' ? $a['avatar_url'] : $b['avatar_url'];
            $result['two_http_writers'] = true;
            $result['winner_http'] = 200;
            $result['loser_http'] = 409;
            $result['loser_error_code'] = 'USER_AVATAR_CONCURRENT_UPDATE';
            $this->phase = 'verify_winner_sql_static_and_orphan';
            M63B2AvatarAudit::target($id, $winnerFilename);
            $result['cas_winner_preserved'] = true;
            // Both HTTP results are complete and SQL points to this exact QA file.
            // A later static/FS assertion failure must still clean this known winner.
            $this->safeCleanup = true;
            $path = M63B1AvatarPlan::physicalPath($winningUrl);
            $physical = @file_get_contents($path);
            $http = new M63B1AvatarTransport($id);
            $http->login($adminUsername, $adminPassword);
            $static = $http->staticImage($winningUrl);
            if (!is_string($physical) || $physical === '' || ($static['status'] ?? 0) !== 200
                || hash('sha256', $physical) !== hash('sha256', $static['bytes'])) {
                throw new RuntimeException('Winning avatar static image differs from physical bytes.');
            }
            $new = M63CRacePlan::newFiles($before, M63CRacePlan::files());
            if ($new !== [$winnerFilename]) {
                throw new RuntimeException('Loser left a staged orphan or another Avatar write happened.');
            }
            $result['loser_staged_file_cleaned'] = true;
            $this->phase = 'authorized_cleanup';
            $this->deleteExactWinner($id, $winnerFilename, $winningUrl, $http, $result);
            $ifNew = M63CRacePlan::newFiles($before, M63CRacePlan::files());
            if ($ifNew !== []) throw new RuntimeException('New Avatar file remains after cleanup.');
            $result['verdict'] = 'PASS_DEV_AVATAR_TWO_HTTP_WRITER_CAS';
            $result['phase'] = 'complete';
        } catch (Throwable $error) {
            $failedAt = $this->phase;
            // Once two requests have returned, a filename has been proven to
            // belong to this run and SQL points to it, we can safely remove
            // that exact pointer even if a later byte/orphan assertion fails.
            if ($this->safeCleanup && is_string($winnerFilename) && is_string($winningUrl)
                && !$result['final_sql_empty']) {
                try {
                    $this->unlock();
                    $rescue = new M63B1AvatarTransport($id);
                    $rescue->login($adminUsername, $adminPassword);
                    $this->deleteExactWinner($id, $winnerFilename, $winningUrl, $rescue, $result);
                    $result['known_winner_rescued'] = true;
                } catch (Throwable) {
                    $result['known_winner_rescued'] = false;
                }
            }
            $result['phase'] = $failedAt;
            $result['failure_class'] = 'QA_RUNTIME_FAILURE';
            if ($failedAt === 'attest_local_mysql') {
                $result['mysql_attest_step'] = $this->mysqlAttestStep;
                $result['failure_code'] = $this->attestFailureCode($error);
            }
            // No worker can POST until the race_go barrier is created.
            $result['partial_state_review_required'] = $this->writesPossible;
        } finally {
            // Always release test-held row lock, even on timeout/error.
            $this->unlock();
            $this->stop('a');
            $this->stop('b');
            putenv('M63C_RUN_NONCE');
            putenv('M63C_TARGET_ID');
            if (isset($winnerFilename) && !$result['final_sql_empty']) {
                $result['partial_state_review_required'] = true;
            }
        }
        return $result;
    }

    private function start(string $role, string $id): void
    {
        $cmd = [PHP_BINARY, __DIR__.'/M63CRaceWorker.php', $role, $id, $this->nonce];
        $childEnv = getenv();
        if (!is_array($childEnv)) throw new RuntimeException('Worker environment cannot be isolated.');
        // Workers need exactly one session credential, not the other user's
        // password or direct MySQL permission. Only parent owns the row lock.
        foreach (['M63C_MYSQL_USER','M63C_MYSQL_PASSWORD','M63C_MYSQL_PORT',
            'M63C_DEV_QA_WRITE','M63B2_EXPECTED_DATABASE'] as $secret) unset($childEnv[$secret]);
        foreach (($role === 'a'
            ? ['M63C_TARGET_USER','M63C_TARGET_PASSWORD']
            : ['M63C_ADMIN_USER','M63C_ADMIN_PASSWORD']) as $secret) unset($childEnv[$secret]);
        $process = proc_open($cmd, [0 => ['pipe','r'],
            1 => ['file',$this->dir.'/race_'.$role.'_stdout.log','a'],
            2 => ['file',$this->dir.'/race_'.$role.'_stderr.log','a']],
            $pipes, $this->root, $childEnv);
        if (!is_resource($process)) throw new RuntimeException('Cannot start race worker.');
        if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);
        $this->workers[$role] = $process;
    }

    /** @param list<string> $markers */
    private function waitFor(array $markers, int $seconds): void
    {
        $limit = microtime(true) + $seconds;
        do {
            $ready = true;
            foreach ($markers as $marker) {
                if (!is_file($this->dir.'/'.$marker)) { $ready = false; break; }
            }
            if ($ready) return;
            foreach (['a','b'] as $role) {
                if (is_file($this->dir.'/race_'.$role.'_error')) {
                    throw new RuntimeException('Worker rejected its authenticated phase.');
                }
                if (isset($this->workers[$role])) {
                    $state = proc_get_status($this->workers[$role]);
                    if (!$state['running']) throw new RuntimeException('Worker terminated before barrier.');
                }
            }
            usleep(50000);
        } while (microtime(true) < $limit);
        throw new RuntimeException('Race barrier timed out.');
    }

    private function twoWaitingWriters(PDO $pdo, string $database, int $seconds): int
    {
        $sql = "SELECT COUNT(DISTINCT t.trx_mysql_thread_id) FROM information_schema.INNODB_TRX t "
            ."JOIN information_schema.PROCESSLIST p ON p.ID=t.trx_mysql_thread_id "
            ."WHERE p.DB=? AND t.trx_state='LOCK WAIT' "
            ."AND UPPER(COALESCE(t.trx_query,'')) LIKE '%AVATAR_FILENAME%'";
        $stmt = $pdo->prepare($sql);
        $max = 0;
        $limit = microtime(true) + $seconds;
        do {
            $stmt->execute([$database]);
            $value = (int)$stmt->fetchColumn();
            $max = max($max, $value);
            if ($value === 2) return 2;
            if (is_file($this->dir.'/race_a_result.json') || is_file($this->dir.'/race_b_result.json')) break;
            usleep(100000);
        } while (microtime(true) < $limit);
        return $max;
    }

    /** @return array<string,mixed> */
    private function readResult(string $role): array
    {
        $file = $this->dir.'/race_'.$role.'_result.json';
        if (!is_file($file) || is_link($file) || filesize($file) > 4096) {
            throw new RuntimeException('Invalid worker result file.');
        }
        $data = json_decode((string)file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new RuntimeException('Invalid HTTP worker result.');
        return $data;
    }

    /** @param array<string,mixed> $result */
    private function deleteExactWinner(string $id, string $name, string $url,
        M63B1AvatarTransport $admin, array &$result): void
    {
        if (!$this->safeCleanup || M63B1AvatarPlan::filename($url) !== $name) {
            throw new RuntimeException('Unsafe QA cleanup attempt.');
        }
        M63B2AvatarAudit::target($id, $name);
        $deleted = $admin->delete();
        M63B1AvatarPlan::deleted($deleted, $id);
        M63B2AvatarAudit::target($id, null);
        $result['final_sql_empty'] = true;
        if (is_file(M63B1AvatarPlan::physicalPath($url)) || $admin->staticImage($url)['status'] !== 404) {
            throw new RuntimeException('Approved winner file was not physically deleted.');
        }
        $result['winning_file_deleted'] = true;
    }

    private function unlock(): void
    {
        if ($this->locked && $this->lockDb instanceof PDO) {
            try {$this->lockDb->rollBack();} catch (Throwable) {}
        }
        $this->locked = false;
        $this->lockDb = null;
    }

    private function stop(string $role): void
    {
        if (!isset($this->workers[$role])) return;
        $process = $this->workers[$role];
        $state = proc_get_status($process);
        if ($state['running']) proc_terminate($process);
        proc_close($process);
        unset($this->workers[$role]);
    }
}
