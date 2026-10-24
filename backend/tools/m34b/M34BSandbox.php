<?php

declare(strict_types=1);

require_once __DIR__ . '/M34BSafety.php';

/** Owns a new disposable schema. No SQL mutation is ever issued to the template database. */
final class M34BSandbox
{
    /** @var list<string> */
    public const REQUIRED_TABLES = [
        'users', 'auth_identities', 'auth_groups_users', 'auth_permissions_users',
        'employees', 'employee_assignments', 'organizations', 'groups',
        'group_permissions', 'resources', 'resource_actions', 'policies',
        'policy_actions', 'workflow_states', 'lifecycles', 'lifecycle_states', 'lifecycle_transitions',
    ];
    private ?PDO $database = null;
    private string $tablePrefix = '';
    private bool $created = false;
    private array $fixture = [];

    public function __construct(
        private readonly PDO $server,
        private readonly string $template,
        public readonly string $name,
        public readonly string $nonce,
        private readonly bool $trustedMetadataFallback = false,
    ) {
        M34BSafety::metadataSourceName($template, $trustedMetadataFallback);
        M34BSafety::assertSandbox($name, $nonce);
    }

    public static function connect(string $host, int $port, string $user, string $password): PDO
    {
        M34BSafety::mysqlEndpoint($host, $port);
        if (! extension_loaded('pdo_mysql')) {
            throw new M34BFailure('PHP extension pdo_mysql is required for real MariaDB integration.');
        }
        return new PDO('mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4', $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }

    /**
     * Resolve the one complete physical table namespace from information_schema.
     * Both plain `users` and prefixed `dinktech_users` are valid; mixing two
     * complete namespaces in a single template is ambiguous and fails closed.
     *
     * @param list<string> $physicalTables
     */
    public static function detectPrefix(array $physicalTables): string
    {
        $known = array_fill_keys($physicalTables, true);
        $candidates = ['' => true];
        foreach ($physicalTables as $physical) {
            if (preg_match('/\A([a-z][a-z0-9_]{0,30}_)users\z/D', $physical, $matched) === 1) {
                $candidates[$matched[1]] = true;
            }
        }
        $complete = [];
        foreach (array_keys($candidates) as $prefix) {
            foreach (self::REQUIRED_TABLES as $logical) {
                if (! isset($known[$prefix . $logical])) {
                    continue 2;
                }
            }
            $complete[] = $prefix;
        }
        if (count($complete) === 1) {
            return $complete[0];
        }
        if (count($complete) > 1) {
            throw new M34BIncompleteTemplate('Template has multiple complete prefixed table namespaces; refusing ambiguous source.');
        }
        $sample = isset($known['dinktech_users']) ? 'dinktech_users' : 'users';
        throw new M34BIncompleteTemplate(
            'Local schema template is missing a COMPLETE required table namespace (sample ' . $sample
            . '; inspected ' . count($physicalTables) . ' physical tables); no sandbox was created.'
        );
    }

    /**
     * Read ONLY information_schema metadata. This is intentionally callable
     * BEFORE CREATE DATABASE so an incomplete erp_test can be distinguished
     * from a complete local erp structure. It never SELECTs source rows.
     *
     * @return list<string> Exact physical table names to clone
     */
    public static function inspectTemplate(PDO $server, string $template, bool $trustedFallback = false): array
    {
        M34BSafety::metadataSourceName($template, $trustedFallback);
        $query = $server->prepare('SELECT TABLE_NAME FROM information_schema.TABLES '
            . "WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
        $query->execute([$template]);
        $tableRows = $query->fetchAll(PDO::FETCH_COLUMN);
        $tables = array_values(array_filter($tableRows, static fn ($t): bool => is_string($t)
            && preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $t) === 1));
        if (count($tables) !== count($tableRows)) {
            throw new M34BFailure('Schema template contains unsupported table identifiers.');
        }
        $prefix = self::detectPrefix($tables);
        // Only copy tables in the one validated physical namespace.
        // No unrelated or mixed-prefixed tables may enter the disposable DB.
        $selected = array_values(array_filter($tables,
            static fn (string $physical): bool => $prefix === '' || str_starts_with($physical, $prefix)));
        // Validate fixture columns before provisioning anything. This is
        // information_schema metadata only: no customer/source row reads.
        $requiredColumns = [
            'users'=>['id','username','status','active','full_name','created_at','updated_at'],
            'auth_identities'=>['id','user_id','type','secret','secret2','created_at','updated_at'],
            'auth_groups_users'=>['user_id','group','created_at'],
            'organizations'=>['id','type','parent_id','code','name','status','created_at'],
            'employees'=>['id','user_id','employee_code','employee_name','status','created_at'],
            'employee_assignments'=>['id','employee_id','organization_id','effective_from','status','is_primary'],
            'resources'=>['id','type','name','domain','lifecycle_id','status'],
            'resource_actions'=>['id','resource_id','code'],
            'groups'=>['id','code','name','status'],
            'group_permissions'=>['group_id','resource_action_id'],
            'policies'=>['id','organization_id','resource_id','participant_type','participant_ref_id','status','normalized_state_id'],
            'policy_actions'=>['id','policy_id','resource_action_id','effect'],
            'workflow_states'=>['id','code','name','status'],
            'lifecycles'=>['id','code','name','status'],
            'lifecycle_states'=>['lifecycle_id','state_id','sequence','is_initial','is_final'],
            'lifecycle_transitions'=>['lifecycle_id','from_state_id','to_state_id','action_code','status'],
        ];
        $metadata = $server->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
        foreach ($requiredColumns as $logical => $names) {
            $physical = $prefix . $logical;
            $metadata->execute([$template, $physical]);
            $available = array_fill_keys($metadata->fetchAll(PDO::FETCH_COLUMN),true);
            foreach ($names as $field) {
                if (! isset($available[$field])) {
                    throw new M34BIncompleteTemplate('Local test schema needs v1.2.4 migration: missing '
                        . $physical . '.' . $field . ' (no sandbox created).');
                }
            }
        }
        return $selected;
    }

    /** Prefix is derived from the physical tables of the source, not request input. */
    public function tablePrefix(): string
    {
        if (! $this->created) {
            throw new M34BFailure('Sandbox has not been provisioned.');
        }
        return $this->tablePrefix;
    }

    /** Map approved logical fixture table names to the actual sandbox tables. */
    public function tableName(string $logical): string
    {
        if (! in_array($logical, self::REQUIRED_TABLES, true)) {
            throw new M34BFailure('Unknown inspected table');
        }
        if (! $this->created) {
            throw new M34BFailure('Sandbox has not been provisioned.');
        }
        return $this->tablePrefix . $logical;
    }

    public function provision(): void
    {
        $tables = self::inspectTemplate($this->server, $this->template, $this->trustedMetadataFallback);
        $this->tablePrefix = self::detectPrefix($tables);
        $check = $this->server->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $check->execute([$this->name]);
        if ($check->fetchColumn() !== false) {
            throw new M34BFailure('Sandbox name collision. Refusing to attach to any existing database.');
        }
        // Only DDL ever executed on the server connection, and only for the
        // generated nonce-specific schema. Never CREATE/DROP on source.
        $this->server->exec('CREATE DATABASE ' . M34BSafety::identifier($this->name)
            . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->created = true;
        $this->server->exec('USE ' . M34BSafety::identifier($this->name));
        $this->server->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tables as $table) {
                $query = 'SHOW CREATE TABLE ' . M34BSafety::identifier($this->template) . '.'
                    . M34BSafety::identifier($table);
                $row = $this->server->query($query)->fetch(PDO::FETCH_NUM);
                $ddl = $row[1] ?? null;
                if (! is_string($ddl) || ! preg_match('/\ACREATE TABLE `/D', $ddl)) {
                    throw new M34BFailure('Could not read schema-only DDL for ' . $table);
                }
                // Fail closed if a source table contains an explicit cross-schema
                // foreign key. Never create a sandbox table that references
                // ANY table in the original source or another database.
                if (preg_match('/\bREFERENCES\s+`[^`]+`\s*\.\s*`/i', $ddl) === 1) {
                    throw new M34BFailure('Cross-database foreign-key reference in source DDL: ' . $table);
                }
                // Never carry the live schema's auto-increment counter into
                // synthetic fixtures. Do not read any source table rows.
                $ddl = preg_replace('/\sAUTO_INCREMENT=[0-9]+\b/i', '', $ddl);
                if (! is_string($ddl)) {
                    throw new M34BFailure('Invalid schema-only DDL for ' . $table);
                }
                // Original SHOW CREATE TABLE DDL contains only names/definitions;
                // no SELECT * or INSERT from the template source is permitted.
                $this->server->exec($ddl);
            }
        } finally {
            $this->server->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        $this->server->exec('CREATE TABLE m34b_sandbox_guard ('
            . 'id TINYINT UNSIGNED PRIMARY KEY, run_id CHAR(32) NOT NULL, '
            . 'purpose VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL) ENGINE=InnoDB');
        $stmt = $this->server->prepare('INSERT INTO m34b_sandbox_guard(id,run_id,purpose,created_at) VALUES (1,?,\'M34B_DISPOSABLE_SCHEMA\',UTC_TIMESTAMP())');
        $stmt->execute([$this->nonce]);
        $this->database = $this->server;
        $this->attest();
    }

    public function attest(): void
    {
        if (! $this->created || $this->database === null) {
            throw new M34BFailure('Sandbox not created by this process.');
        }
        $current = (string) $this->database->query('SELECT DATABASE()')->fetchColumn();
        M34BSafety::assertSandbox($current, $this->nonce);
        $mark = $this->database->query("SELECT run_id FROM m34b_sandbox_guard WHERE id=1 AND purpose='M34B_DISPOSABLE_SCHEMA'")->fetchColumn();
        if (! is_string($mark) || ! hash_equals($this->nonce, $mark)) {
            throw new M34BFailure('MariaDB sandbox guard mismatch.');
        }
    }

    /** @return array<string,mixed> synthetic fixtures only */
    public function seed(): array
    {
        $this->attest();
        $db = $this->database;
        if ($db === null) { throw new M34BFailure('Sandbox not initialized.'); }
        $now = gmdate('Y-m-d H:i:s');
        $root = $this->insert('organizations', [
            'type'=>'GROUP','parent_id'=>null,'code'=>'GROUP_M34B_TEST','name'=>'M34B Test Root',
            'status'=>'ACTIVE','created_at'=>$now,
        ]);
        $states = [];
        foreach (['ACTIVE','INACTIVE','PENDING','CLOSED'] as $code) {
            $states[$code] = $this->insert('workflow_states', [
                'code'=>$code,'name'=>'M34B ' . $code,'status'=>'ACTIVE','created_at'=>$now,
            ]);
        }
        $group = $this->insert('groups', [
            'code'=>'SYSTEM_ADMIN','name'=>'M34B Admin Group','status'=>'ACTIVE','created_at'=>$now,
        ]);
        $catalog = [
            'authorization_group' => ['SYSTEM', ['view','create','edit','setstate','assign_member','assign_permission']],
            'resource' => ['SYSTEM', ['view','create','edit','setstate','delete']],
            'policy' => ['ORGANIZATION', ['view','create','edit','setstate','delete']],
            'workflow_state' => ['SYSTEM', ['view','create','edit','setstate']],
            'lifecycle' => ['SYSTEM', ['view','create','edit','setstate']],
            'user' => ['SYSTEM', ['view','create','edit','setstate','reset_password','assign_permission']],
        ];
        $resources = []; $actions = [];
        foreach ($catalog as $type => [$domain, $verbs]) {
            $resources[$type] = $this->insert('resources', [
                'type'=>$type,'name'=>$type,'domain'=>$domain,'lifecycle_id'=>null,
                'status'=>'ACTIVE','created_at'=>$now,
            ]);
            foreach ($verbs as $verb) {
                $id = $this->insert('resource_actions', [
                    'resource_id'=>$resources[$type],'code'=>$verb,'created_at'=>$now,
                ]);
                $actions[$type . '.' . $verb] = $id;
                $this->insert('group_permissions', [
                    'group_id'=>$group,'resource_action_id'=>$id,'created_at'=>$now,
                ]);
            }
        }
        // No personal/company data is copied from template: only a disposable,
        // randomly credentialed synthetic Shield identity is created here.
        $username = 'm34b_' . substr($this->nonce, 0, 12);
        $email = $username . '@example.test';
        $password = bin2hex(random_bytes(18)) . 'Aa!9';
        $user = $this->insert('users', [
            'username'=>$username,'status'=>'active','active'=>1,
            'full_name'=>'Synthetic M34B Tester','created_at'=>$now,'updated_at'=>$now,
        ]);
        $this->insert('auth_identities', [
            'user_id'=>$user,'type'=>'email_password', 'secret'=>$email,
            'secret2'=>password_hash($password, PASSWORD_BCRYPT), 'created_at'=>$now,'updated_at'=>$now,
        ]);
        $this->insert('auth_groups_users', [
            'user_id'=>$user,'group'=>'system_admin','created_at'=>$now,
        ]);
        $employee = $this->insert('employees', [
            'user_id'=>$user,'employee_code'=>'M34B-E001','employee_name'=>'Synthetic M34B Tester',
            'status'=>'ACTIVE','created_at'=>$now,
        ]);
        $this->insert('employee_assignments', [
            'employee_id'=>$employee,'organization_id'=>$root,'status'=>'ACTIVE','is_primary'=>1,
            'effective_from'=>'2020-01-01','effective_to'=>null,'created_at'=>$now,
        ]);
        $this->fixture = compact('root','states','group','resources','actions','user','username','email','password');
        return $this->fixture;
    }

    /** @return array<string,string> table => SHA-256 */
    public function fingerprint(): array
    {
        $this->attest();
        $tables = [
            'users','auth_identities','auth_groups_users','auth_permissions_users',
            'employees','employee_assignments','organizations','groups','group_permissions',
            'resources','resource_actions','policies','policy_actions',
            'workflow_states','lifecycles','lifecycle_states','lifecycle_transitions',
        ];
        $out = [];
        foreach ($tables as $table) {
            $rows = $this->database->query('SELECT * FROM ' . M34BSafety::identifier($this->tableName($table)))->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                // Shield login updates these volatile activity markers even
                // when all protected business data remains unchanged.
                if ($table === 'users') { unset($row['last_active']); }
                if ($table === 'auth_identities') { unset($row['last_used_at']); }
                ksort($row);
            } unset($row);
            usort($rows, static fn ($a,$b): int => strcmp((string)($a['id'] ?? json_encode($a)), (string)($b['id'] ?? json_encode($b))));
            $out[$table] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        }
        return $out;
    }

    public function count(string $table): int
    {
        if (! in_array($table, self::REQUIRED_TABLES, true)) { throw new M34BFailure('Unknown inspected table'); }
        $this->attest();
        return (int) $this->database->query('SELECT COUNT(*) FROM ' . M34BSafety::identifier($this->tableName($table)))->fetchColumn();
    }

    public function retained(): bool { return $this->created; }

    /** Destructive ONLY for the schema this exact process just created. */
    public function dropOwnSandbox(): void
    {
        $this->attest();
        $this->server->exec('DROP DATABASE ' . M34BSafety::identifier($this->name));
        $this->created = false;
        $this->database = null;
    }

    /** @param array<string,mixed> $values */
    private function insert(string $table, array $values): string
    {
        if (! in_array($table, self::REQUIRED_TABLES, true)) { throw new M34BFailure('Unapproved fixture write table.'); }
        $this->attest();
        $names = array_keys($values);
        foreach ($names as $name) { M34BSafety::identifier($name); }
        $cols = implode(',', array_map([M34BSafety::class, 'identifier'], $names));
        $sql = 'INSERT INTO ' . M34BSafety::identifier($this->tableName($table)) . ' (' . $cols . ') VALUES ('
            . implode(',', array_fill(0,count($names),'?')) . ')';
        $this->database->prepare($sql)->execute(array_values($values));
        return (string) $this->database->lastInsertId();
    }
}
