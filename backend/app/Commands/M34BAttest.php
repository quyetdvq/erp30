<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/** Internal CLI guard; NEVER registered as an HTTP route. */
final class M34BAttest extends BaseCommand
{
    protected $group = 'DinkERP Internal QA';
    protected $name = 'm34b:attest';
    protected $description = 'Read-only: attest that the child CI4 process connects to its unique M3.4B sandbox.';
    protected $arguments = ['name' => 'Generated schema', 'nonce' => 'Run token'];

    public function run(array $params)
    {
        $name = (string) ($params[0] ?? '');
        $nonce = (string) ($params[1] ?? '');
        if (preg_match('/\Aerp_m34b_[a-f0-9]{16}_sandbox\z/D', $name) !== 1
            || preg_match('/\A[a-f0-9]{32}\z/D', $nonce) !== 1
            || $name !== 'erp_m34b_' . substr($nonce, 0, 16) . '_sandbox') {
            throw new \RuntimeException('M34B child schema/nonce guard mismatch.');
        }
        // Fail before even opening a CI4 database connection if the process
        // lost its disposable-schema context or inherited an unsafe schema.
        if (ENVIRONMENT !== 'development') {
            throw new \RuntimeException('M34B_CHILD_ENVIRONMENT: Unexpected CI4 child environment.');
        }
        if (getenv('M34B_ONLY_SCHEMA') !== $name || getenv('M34B_ONLY_NONCE') !== $nonce
            || getenv('database.default.database') !== $name
            || getenv('database.tests.database') !== $name
            || getenv('database.default.hostname') !== '127.0.0.1'
            || getenv('database.tests.hostname') !== '127.0.0.1') {
            throw new \RuntimeException('M34B_CHILD_GUARD: Child did not receive matching sandbox settings.');
        }
        // Use the same cached Config instance as Database::connect(). A fresh
        // `new Database()` is not authoritative if CI4 already resolved config.
        $configured = config(Database::class);
        if ($configured->defaultGroup !== 'default') {
            throw new \RuntimeException('M34B_CHILD_CONFIG_GROUP: Unexpected CI4 default group.');
        }
        if (($configured->default['database'] ?? null) !== $name
            || ($configured->tests['database'] ?? null) !== $name
            || ($configured->default['hostname'] ?? null) !== '127.0.0.1'
            || ($configured->tests['hostname'] ?? null) !== '127.0.0.1') {
            throw new \RuntimeException('M34B_CHILD_CONFIG_SCHEMA: CI4 config differs from sandbox.');
        }
        $db = Database::connect();
        $actual = (string) ($db->query('SELECT DATABASE() AS selected_schema')->getRowArray()['selected_schema'] ?? '');
        if ($actual !== $name) {
            throw new \RuntimeException('M34B_CHILD_CONFIG_SCHEMA: CI4 selected a non-sandbox database.');
        }
        $expectedPrefix = getenv('database.tests.DBPrefix');
        if (! is_string($expectedPrefix) || $expectedPrefix !== getenv('database.default.DBPrefix')
            || $db->DBPrefix !== $expectedPrefix
            || preg_match('/\A(?:[a-z][a-z0-9_]{0,30}_)?\z/D', $expectedPrefix) !== 1) {
            throw new \RuntimeException('M34B_CHILD_PREFIX: CI4 DBPrefix mismatch.');
        }
        $row = $db->query('SELECT run_id,purpose FROM m34b_sandbox_guard WHERE id=1')->getRowArray();
        if (! is_array($row) || ($row['purpose'] ?? null) !== 'M34B_DISPOSABLE_SCHEMA'
            || ! is_string($row['run_id'] ?? null) || ! hash_equals($nonce, $row['run_id'])) {
            throw new \RuntimeException('M34B_CHILD_MARKER: Sandbox ownership marker mismatch.');
        }
        CLI::write('M34B_ATTEST_OK ' . $name);
    }
}
