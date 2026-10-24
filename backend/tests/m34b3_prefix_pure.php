<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/m34b/M34BSandbox.php';

$n = 0;
function check34b3(string $label, bool $condition): void
{
    global $n;
    if (! $condition) { throw new RuntimeException('FAIL: ' . $label); }
    echo 'PASS: ', $label, PHP_EOL;
    ++$n;
}
function rejected34b3(callable $action, string $class = M34BFailure::class): bool
{
    try { $action(); } catch (Throwable $e) { return $e instanceof $class; }
    return false;
}

/** Fake metadata boundary: no PDO/MySQL connection, no source business row queries. */
final class M34B3FakePDO extends PDO
{
    public array $commands = [];
    public array $executions = [];
    public function __construct(public array $physical, public array $columns, public array $omitColumns = []) {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->commands[] = $query;
        return new M34B3FakeStatement($this, $query);
    }
}
final class M34B3FakeStatement extends PDOStatement
{
    private array $params = [];
    public function __construct(private M34B3FakePDO $fake, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $this->params = $params ?? [];
        $this->fake->executions[] = [$this->sql, $this->params];
        return true;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, ...$args): array
    {
        if (str_contains($this->sql, 'information_schema.TABLES')) {
            return $this->fake->physical;
        }
        if (str_contains($this->sql, 'information_schema.COLUMNS')) {
            $physical = $this->params[1] ?? '';
            return array_values(array_diff($this->fake->columns, $this->fake->omitColumns[$physical] ?? []));
        }
        throw new RuntimeException('Unexpected read: ' . $this->sql);
    }
}

$logical = M34BSandbox::REQUIRED_TABLES;
$prefixed = array_map(static fn (string $name): string => 'dinktech_' . $name, $logical);
$extra = array_map(static fn (int $i): string => 'dinktech_extra_' . $i, range(1, 24));
$sample = [...$prefixed, ...$extra];
check34b3('Physical dinktech_ users and all required tables recognized', M34BSandbox::detectPrefix($sample) === 'dinktech_');
check34b3('41-table prefixed source recognized without source-row reads', count($sample) === 41);
check34b3('Plain unprefixed source still recognized', M34BSandbox::detectPrefix($logical) === '');
check34b3('Incomplete prefixed source fails closed', rejected34b3(
    static fn () => M34BSandbox::detectPrefix(array_values(array_diff($sample, ['dinktech_auth_identities']))),
    M34BIncompleteTemplate::class
));
check34b3('Half-prefixed and half-unprefixed tables fail closed', rejected34b3(
    static fn () => M34BSandbox::detectPrefix([
        ...array_slice($logical, 0, 8), ...array_slice($prefixed, 8),
    ]), M34BIncompleteTemplate::class
));
check34b3('Two complete physical namespaces refused as ambiguous', rejected34b3(
    static fn () => M34BSandbox::detectPrefix([...$logical, ...$prefixed]), M34BIncompleteTemplate::class
));
check34b3('Single unprefixed users table does not fake a complete schema', rejected34b3(
    static fn () => M34BSandbox::detectPrefix(['users']), M34BIncompleteTemplate::class
));

$columns = [
    'id','username','status','active','full_name','created_at','updated_at','user_id','type',
    'secret','secret2','group','parent_id','code','name','employee_code','employee_name',
    'employee_id','organization_id','effective_from','is_primary','domain','lifecycle_id',
    'resource_id','resource_action_id','group_id','participant_type','participant_ref_id',
    'normalized_state_id','policy_id','effect','state_id','sequence','is_initial','is_final',
    'from_state_id','to_state_id','action_code',
];
$fake = new M34B3FakePDO($sample, $columns);
$result = M34BSandbox::inspectTemplate($fake, 'erp_test');
check34b3('Metadata inspection preserves physical prefixed table names', $result === $sample);
check34b3('Read-only inspection queries only information_schema', count($fake->executions) === 17
    && count(array_filter($fake->commands, static fn ($sql) => ! str_contains($sql, 'information_schema'))) === 0);
check34b3('Metadata inspection validates physical prefixed column names', rejected34b3(
    static fn () => M34BSandbox::inspectTemplate(
        new M34B3FakePDO($sample, $columns, ['dinktech_users' => ['username']]), 'erp_test'),
    M34BIncompleteTemplate::class
));
check34b3('Metadata inspection works with plain unprefixed tables',
    M34BSandbox::inspectTemplate(new M34B3FakePDO($logical, $columns), 'erp_test') === $logical);
check34b3('Invalid/unsafe metadata identifier refused before provisioning', rejected34b3(
    static fn () => M34BSandbox::inspectTemplate(new M34B3FakePDO([...$sample, 'bad-table;'], $columns), 'erp_test')
));
check34b3('Non-test source remains forbidden', rejected34b3(
    static fn () => M34BSandbox::inspectTemplate(new M34B3FakePDO($sample, $columns), 'erp')
));

$sandboxSrc = file_get_contents(__DIR__ . '/../tools/m34b/M34BSandbox.php');
$integrationSrc = file_get_contents(__DIR__ . '/../tools/m34b/M34BIntegration.php');
$routerSrc = file_get_contents(__DIR__ . '/../tools/m34b/router.php');
$attestSrc = file_get_contents(__DIR__ . '/../app/Commands/M34BAttest.php');
check34b3('DDL is cloned using exact physical names', str_contains($sandboxSrc, 'M34BSafety::identifier($table)'));
check34b3('Fixture INSERT respects detected physical prefix', str_contains($sandboxSrc, '$this->tableName($table)'));
check34b3('Fingerprint and count resolve logical to prefixed table',
    substr_count($sandboxSrc, '$this->tableName($table)') >= 3);
check34b3('SQL security probes use the sandbox physical table resolver',
    str_contains($integrationSrc, "qTable('policy_actions')") && str_contains($integrationSrc, "qTable('policies')")
    && str_contains($integrationSrc, "qTable('auth_identities')"));
check34b3('CI4 testing child sets both default and tests DB schema',
    str_contains($integrationSrc, "'database.tests.database'=>\$this->sandbox->name")
    && str_contains($integrationSrc, "'database.default.database'=>\$this->sandbox->name"));
check34b3('CI4 testing child sets both database prefixes',
    str_contains($integrationSrc, "'database.tests.DBPrefix'=>\$this->sandbox->tablePrefix()")
    && str_contains($integrationSrc, "'database.default.DBPrefix'=>\$this->sandbox->tablePrefix()"));
check34b3('Child CLI attestation checks the actual DBPrefix', str_contains($attestSrc, '$db->DBPrefix !== $expectedPrefix'));
check34b3('HTTP router refuses mismatched default/tests schemas and prefix',
    str_contains($routerSrc, '$database === $testSchema') && str_contains($routerSrc, '$defaultPrefix === $testPrefix'));
check34b3('No actual source database mutations or existing DB writes added',
    ! str_contains($sandboxSrc, 'INSERT INTO ' . 'erp_test.') && ! str_contains($integrationSrc, 'DROP DATABASE erp'));

echo 'TOTAL: ', $n, ' M3.4B.3 prefixed schema assertions passed.', PHP_EOL;
