<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/m34/M34Core.php';

/** Read-only inspector for manually marked and independently hosted M4 clones. */
final class M42CCloneDb
{
    private PDO $pdo;
    private string $prefix;

    /** Business tables: every request under this harness MUST leave them unchanged. */
    private const TRACKED = [
        'users', 'auth_identities', 'auth_groups_users', 'auth_permissions_users',
        'authorization_groups', 'group_permissions', 'resources', 'resource_actions',
        'policies', 'policy_actions', 'workflow_states', 'lifecycles',
        'lifecycle_states', 'lifecycle_transitions', 'organizations',
        'settings', 'configuration_audit_events', 'auto_numbering_series', 'auto_numbering_counters',
    ];
    private const REQUIRED = [
        'users', 'resources', 'policies', 'organizations', 'settings',
        'configuration_audit_events', 'auto_numbering_series', 'auto_numbering_counters',
    ];

    /** @param array<string,mixed> $spec */
    public function __construct(array $spec, string $role, string $runId)
    {
        if (!extension_loaded('pdo_mysql')) {
            throw new M34Failure('M4.2C requires pdo_mysql for attesting two separate SQL clones');
        }
        $this->pdo = new PDO(
            $spec['dsn'], $spec['db_user'], M34Safety::environment($spec['db_password_env']), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]
        );
        $actual = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($actual !== $spec['verified_db_name']) {
            throw new M34Failure('M4.2C refused unexpected SQL database');
        }
        $check = $this->pdo->prepare('SELECT run_id FROM m34_clone_guard WHERE clone_role = ? LIMIT 1');
        $check->execute([$role]);
        if (!hash_equals($runId, (string) $check->fetchColumn())) {
            throw new M34Failure('M4.2C rejected missing/mismatched independently marked clone');
        }
        $stmt = $this->pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
        $existing = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
        if (isset($existing['users']) && isset($existing['dinktech_users'])) {
            throw new M34Failure('Ambiguous physical table prefix in clone');
        }
        $this->prefix = isset($existing['dinktech_users']) ? 'dinktech_' : '';
        foreach (self::REQUIRED as $name) {
            if (!isset($existing[$this->prefix . $name])) {
                throw new M34Failure('M4.2C missing required M4 clone table: ' . $name);
            }
        }
        foreach (self::TRACKED as $name) {
            if (!isset($existing[$this->prefix . $name])) {
                // Do not silently skip an absent tracked table in only one clone.
                throw new M34Failure('M4.2C missing tracked table: ' . $name);
            }
        }
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    /** @return array<string,string> keyed per-table SHA-256 without exposing business rows */
    public function snapshot(): array
    {
        $snapshot = [];
        foreach (self::TRACKED as $logical) {
            $table = '`' . $this->prefix . $logical . '`'; // both prefix and table are hardcoded allowlisted identifiers
            $count = (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
            if ($count > 50000) {
                throw new M34Failure('M4.2C snapshot row limit exceeded for ' . $logical . '; use smaller disposable clones');
            }
            $rows = $this->pdo->query('SELECT * FROM ' . $table)->fetchAll();
            $canonical = [];
            foreach ($rows as $row) {
                if ($logical === 'users') {
                    unset($row['last_active']); // ignored by existing M3.4 parity convention
                }
                ksort($row, SORT_STRING);
                $canonical[] = json_encode($row, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            }
            sort($canonical, SORT_STRING);
            $snapshot[$logical] = hash('sha256', implode("\n", $canonical));
        }
        return $snapshot;
    }

    /** @return list<string> */
    public static function changed(array $before, array $after): array
    {
        $changed = [];
        foreach (self::TRACKED as $logical) {
            if (($before[$logical] ?? null) !== ($after[$logical] ?? null)) {
                $changed[] = $logical;
            }
        }
        return $changed;
    }
}
