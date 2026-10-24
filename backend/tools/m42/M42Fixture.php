<?php

declare(strict_types=1);

require_once __DIR__ . '/../m34b/M34BSandbox.php';

/**
 * M4-only disposable fixture. All source schema access is information_schema
 * metadata; all row reads/writes happen in the nonce-attested M34B sandbox.
 */
final class M42Fixture
{
    private const M4_TABLES = [
        'settings' => ['class', 'key', 'value', 'type', 'context'],
        'configuration_audit_events' => ['configuration_type', 'context_type', 'setting_key', 'operation', 'actor_user_id'],
        'auto_numbering_series' => ['resource_id', 'series_code', 'pattern', 'is_default', 'status'],
        'auto_numbering_counters' => ['numbering_series_id', 'scope_organization_id', 'period_key', 'last_value'],
    ];
    private const TRACKED = ['settings', 'configuration_audit_events', 'auto_numbering_series',
        'auto_numbering_counters', 'organizations'];
    private const INSERTABLE = ['lifecycles', 'lifecycle_states', 'lifecycle_transitions',
        'resources', 'resource_actions', 'group_permissions', 'auto_numbering_counters',
        'policies', 'policy_actions'];

    public function __construct(private readonly M34BSandbox $sandbox, private readonly PDO $db)
    {
        $this->attest();
    }

    /** Check extra M4 schema BEFORE provisioning the clone; never read source business rows. */
    public static function inspectTemplate(PDO $server, string $schema, bool $fallback): void
    {
        $tables = M34BSandbox::inspectTemplate($server, $schema, $fallback);
        $prefix = M34BSandbox::detectPrefix($tables);
        $columns = $server->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
        foreach (self::M4_TABLES as $name => $fields) {
            $columns->execute([$schema, $prefix.$name]);
            $found = array_fill_keys($columns->fetchAll(PDO::FETCH_COLUMN), true);
            foreach ($fields as $field) {
                if (!isset($found[$field])) {
                    throw new M34BIncompleteTemplate('M4 sandbox template missing '.$name.'.'.$field);
                }
            }
        }
    }

    public static function connect(M34BSandbox $sandbox, string $user, string $password, int $port): self
    {
        $db = M34BSandbox::connect('127.0.0.1', $port, $user, $password);
        $db->exec('USE '.M34BSafety::identifier($sandbox->name));
        return new self($sandbox, $db);
    }

    public function attest(): void
    {
        $this->sandbox->attest();
        if ((string)$this->db->query('SELECT DATABASE()')->fetchColumn() !== $this->sandbox->name) {
            throw new M34BFailure('M4 fixture is not connected to the owned sandbox.');
        }
        $query = $this->db->query("SELECT run_id FROM m34b_sandbox_guard WHERE id=1 AND purpose='M34B_DISPOSABLE_SCHEMA'");
        if (!hash_equals($this->sandbox->nonce, (string)$query->fetchColumn())) {
            throw new M34BFailure('M4 sandbox guard did not match nonce.');
        }
    }

    public function table(string $logical): string
    {
        if (!in_array($logical, [...self::TRACKED, ...self::INSERTABLE, 'workflow_states'], true)) {
            throw new M34BFailure('M4 SQL table name not allowlisted.');
        }
        return M34BSafety::identifier($this->sandbox->tablePrefix().$logical);
    }

    /** @param array<string,mixed> $values */
    public function insert(string $table, array $values): string
    {
        if (!in_array($table, self::INSERTABLE, true)) {
            throw new M34BFailure('M4 fixture INSERT to unapproved table refused.');
        }
        $this->attest();
        $columns = array_keys($values);
        $names = implode(',',array_map([M34BSafety::class, 'identifier'], $columns));
        $sql = 'INSERT INTO '.$this->table($table).' ('.$names.') VALUES ('.implode(',', array_fill(0, count($columns), '?')).')';
        $this->db->prepare($sql)->execute(array_values($values));
        return (string)$this->db->lastInsertId();
    }

    /** @param array<string,mixed> $base */
    public function seed(array $base): array
    {
        $this->attest();
        $now = gmdate('Y-m-d H:i:s');
        $group = (string)$base['group'];
        $root = (string)$base['root'];
        $res = (string)$base['resources']['resource'];
        $seriesPermission = $this->insert('resource_actions', [
            'resource_id'=>$res, 'code'=>'configure_numbering', 'created_at'=>$now,
        ]);
        $this->insert('group_permissions', [
            'group_id'=>$group, 'resource_action_id'=>$seriesPermission, 'created_at'=>$now,
        ]);
        $display = $this->insert('resources', [
            'type'=>'display_setting', 'name'=>'M42 settings', 'domain'=>'ORGANIZATION',
            'lifecycle_id'=>null, 'status'=>'ACTIVE', 'created_at'=>$now,
        ]);
        foreach (['view','edit'] as $code) $this->addAction($display, $group, $code, $now);

        $lifecycle = $this->insert('lifecycles', [
            'code'=>'M42_ORG_'.$this->sandbox->nonce, 'name'=>'M42 Organization workflow',
            'status'=>'ACTIVE', 'created_at'=>$now,
        ]);
        foreach (['ACTIVE','INACTIVE'] as $idx => $state) {
            $this->insert('lifecycle_states', [
                'lifecycle_id'=>$lifecycle, 'state_id'=>$base['states'][$state],
                'sequence'=>$idx+1, 'is_initial'=>$idx===0?1:0,
                'is_final'=>$idx===1?1:0, 'created_at'=>$now,
            ]);
        }
        $this->insert('lifecycle_transitions', [
            'lifecycle_id'=>$lifecycle,
            'from_state_id'=>$base['states']['ACTIVE'],
            'to_state_id'=>$base['states']['INACTIVE'],
            'action_code'=>'archive','status'=>'ACTIVE','created_at'=>$now,
        ]);
        $organizationResource = $this->insert('resources', [
            'type'=>'organization', 'name'=>'M42 Organizations', 'domain'=>'ORGANIZATION',
            'lifecycle_id'=>$lifecycle, 'status'=>'ACTIVE', 'created_at'=>$now,
        ]);
        $orgActions = [];
        foreach (['view','setstate','archive'] as $code) {
            $orgActions[$code] = $this->addAction($organizationResource, $group, $code, $now);
        }
        return ['root'=>$root, 'group'=>$group, 'resource'=>$res,
            'organization_resource'=>$organizationResource, 'organization_action_archive'=>$orgActions['archive'],
            'user'=>$base['user'],'email'=>$base['email'],'password'=>$base['password']];
    }

    private function addAction(string $resource, string $group, string $code, string $now): string
    {
        $action = $this->insert('resource_actions', [
            'resource_id'=>$resource, 'code'=>$code,'created_at'=>$now,
        ]);
        $this->insert('group_permissions', [
            'group_id'=>$group, 'resource_action_id'=>$action,'created_at'=>$now,
        ]);
        return $action;
    }

    /** @return array<string,string> */
    public function snapshot(): array
    {
        $this->attest();
        $result=[];
        foreach (self::TRACKED as $logical) {
            $rows=$this->db->query('SELECT * FROM '.$this->table($logical))->fetchAll(PDO::FETCH_ASSOC);
            $encoded=[];
            foreach ($rows as $row) {
                ksort($row,SORT_STRING);
                $encoded[]=json_encode($row, JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
            }
            sort($encoded,SORT_STRING);
            $result[$logical]=hash('sha256',implode("\n",$encoded));
        }
        return $result;
    }

    /** @return list<string> */
    public static function changed(array $before, array $after): array
    {
        $changed=[];
        foreach (self::TRACKED as $table) {
            if (($before[$table]??null)!==($after[$table]??null)) $changed[]=$table;
        }
        return $changed;
    }

    public function scalar(string $sql, array $values=[]): mixed
    {
        // Read statements are closed-over, reviewed SQL built by the runner.
        if (!preg_match('/\ASELECT\b/i', $sql)) throw new M34BFailure('M4 fixture scalar requires SELECT.');
        $this->attest();
        $stmt=$this->db->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetchColumn();
    }

    public function exec(string $sql, array $values=[]): void
    {
        $this->attest();
        $stmt=$this->db->prepare($sql);
        $stmt->execute($values);
    }
}
