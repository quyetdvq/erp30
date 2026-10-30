<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Supplier\SupplierSetupDoctor;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

/** One-command read-only post-migration/seed verification. */
final class SupplierDoctor extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'supplier:doctor';
    protected $description = 'READ ONLY verify Supplier 000028 schema, Resources, Actions and Lifecycles.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        if ($params !== []) {
            throw new RuntimeException('Usage: php spark supplier:doctor');
        }
        $report = (new SupplierSetupDoctor(db_connect()))->inspect();
        $db = $report['connection'];
        CLI::write('SUPPLIER SETUP DOCTOR - READ ONLY');
        CLI::write('DB: ' . $db['schema'] . ' @ ' . $db['server'] . ':' . $db['port']
            . ' | prefix=' . $db['prefix'] . ' | env=' . $db['environment']);
        foreach ($report['checks'] as $check) {
            CLI::write(($check['pass'] ? '[PASS] ' : '[FAIL] ') . $check['label']
                . ': ' . $check['detail'], $check['pass'] ? 'green' : 'red');
        }
        CLI::write('RESULT: ' . $report['status'] . ' (schema/catalog only)',
            $report['status'] === 'PASS' ? 'green' : 'red');
        CLI::write('No DB writes. DEV-clone identity/backup, PBAC grants, HTTP/CAS and Bank security NOT verified.');
        if ($report['status'] !== 'PASS') {
            throw new RuntimeException('SUP_DOCTOR_SCHEMA_OR_CATALOG_FAILED');
        }
    }
}
