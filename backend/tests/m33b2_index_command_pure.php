<?php

declare(strict_types=1);

namespace CodeIgniter\CLI {
    abstract class BaseCommand {}

    final class CLI
    {
        public static function write(string $line, ?string $color = null): void {}
    }
}

namespace {
    require_once __DIR__ . '/../app/Modules/Access/Mutation/PolicyScopeUniqueIndex.php';
    require_once __DIR__ . '/../app/Commands/M33B2Verify.php';

    final class FakeIndexResult
    {
        public function __construct(private array $rows) {}
        public function getResultArray(): array { return $this->rows; }
    }

    final class FakeIndexDB
    {
        public array $calls = [];
        public bool $queryFails = false;

        public function __construct(public array $rows) {}
        public function getPrefix(): string { return 'erp_'; }
        public function tableExists(string $name): bool { return true; }
        public function query(string $sql, array $parameters): FakeIndexResult|false
        {
            $this->calls[] = ['sql' => $sql, 'parameters' => $parameters];
            return $this->queryFails ? false : new FakeIndexResult($this->rows);
        }
        public function error(): array { return ['code' => 1064, 'message' => 'simulated MariaDB syntax error']; }
        public function table(string $name): never { throw new \RuntimeException('INDEX_GATE_PASSED'); }
    }

    function db_connect(): FakeIndexDB { return $GLOBALS['m33b2_fake_db']; }
    function config(string $name): object { return (object) ['tables' => ['users' => 'users']]; }

    $columns = ['organization_id', 'resource_id', 'normalized_state_id', 'participant_type', 'participant_ref_id'];
    $valid = [];
    foreach ($columns as $i => $name) {
        $valid[] = ['seq_in_index' => $i + 1, 'column_name' => $name, 'non_unique' => 0, 'sub_part' => null];
    }

    $n = 0;
    $check = static function (bool $yes, string $message) use (&$n): void {
        if (! $yes) { throw new \RuntimeException('FAIL: ' . $message); }
        $n++;
        echo 'PASS: ' . $message . PHP_EOL;
    };

    $db = new FakeIndexDB($valid);
    $GLOBALS['m33b2_fake_db'] = $db;
    try {
        (new \App\Commands\M33B2Verify())->run([]);
        throw new \RuntimeException('FAIL: command did not reach next read');
    } catch (\RuntimeException $exception) {
        $check($exception->getMessage() === 'INDEX_GATE_PASSED',
            'runtime command passes valid five-column index gate');
    }
    $check(count($db->calls) === 1, 'runtime issued exactly one metadata query at index gate');
    $check(str_contains($db->calls[0]['sql'], 'information_schema.STATISTICS'),
        'runtime executed information_schema metadata SQL');
    $check($db->calls[0]['parameters'] === ['erp_policies', 'uq_policies_scope'],
        'runtime binds physical table prefix and exact baseline index name');

    $db = new FakeIndexDB([]);
    $GLOBALS['m33b2_fake_db'] = $db;
    try {
        (new \App\Commands\M33B2Verify())->run([]);
        throw new \RuntimeException('FAIL: missing constraint allowed');
    } catch (\UnexpectedValueException $exception) {
        $check(str_contains($exception->getMessage(), 'Do not enable Policy mutations'),
            'runtime fails closed for missing MariaDB UNIQUE index');
    }

    $db = new FakeIndexDB($valid);
    $db->queryFails = true;
    $GLOBALS['m33b2_fake_db'] = $db;
    try {
        (new \App\Commands\M33B2Verify())->run([]);
        throw new \RuntimeException('FAIL: failed metadata query allowed');
    } catch (\UnexpectedValueException $exception) {
        $check(str_contains($exception->getMessage(), '1064')
            && str_contains($exception->getMessage(), 'simulated MariaDB syntax error'),
            'runtime includes the actual database error on query failure');
    }

    echo 'TOTAL: ' . $n . ' M3.3B2 index-command assertions passed.' . PHP_EOL;
}
