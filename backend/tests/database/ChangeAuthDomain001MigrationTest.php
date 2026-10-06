<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

require_once APPPATH . 'Database/Migrations/2026-10-05-000010_NormalizeResourceDomains.php';

/** @internal */
final class ChangeAuthDomain001MigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testForwardMigrationNormalizesLegacyNullableDomainRowsAndEnforcesNotNull(): void
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $migration = new App\Database\Migrations\NormalizeResourceDomains(Config\Database::forge($this->db));
        $migration->down();
        $this->db->table('resources')->where('type', 'province')->update(['domain' => null]);
        $this->db->table('resources')->where('type', 'contact')->update(['domain' => 'COMPANY']);
        $this->db->table('resources')->where('type', 'employee_assignment')->update(['domain' => 'MASTER']);
        $this->db->table('resources')->where('type', 'organization')->update(['domain' => 'SYSTEM']);

        $migration->up();

        $domains = $this->db->table('resources')->select('type, domain')->orderBy('type')->get()->getResultArray();
        $map = array_column($domains, 'domain', 'type');
        $this->assertSame('MASTER', $map['province']);
        $this->assertSame('ORGANIZATION', $map['contact']);
        $this->assertSame('ORGANIZATION', $map['employee_assignment']);
        $this->assertSame('ORGANIZATION', $map['organization']);
        $table = $this->db->protectIdentifiers($this->db->prefixTable('resources'));
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) AS total FROM {$table} WHERE domain IS NULL OR domain = ''")->getRowArray()['total']);
    }
}
