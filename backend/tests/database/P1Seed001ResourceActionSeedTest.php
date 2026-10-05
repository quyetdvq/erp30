<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Seed001ResourceActionSeedTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    /** @var array<string, list<string>> */
    private const MATRIX = [
        'province' => ['view', 'create', 'edit', 'setstate', 'export'],
        'ward' => ['view', 'create', 'edit', 'setstate', 'export'],
        'contact' => ['view', 'create', 'edit', 'setstate', 'export'],
        'organization' => ['view', 'create', 'edit', 'setstate', 'export'],
        'department' => ['view', 'create', 'edit', 'setstate', 'export'],
        'position' => ['view', 'create', 'edit', 'setstate', 'export'],
        'employee' => ['view', 'create', 'edit', 'setstate', 'export'],
        'employee_assignment' => ['view', 'create', 'edit', 'setstate'],
        'user' => ['view', 'create', 'edit', 'setstate', 'assign_permission'],
        'authorization_group' => ['view', 'create', 'edit', 'setstate', 'assign_member', 'assign_permission'],
        'resource' => ['view', 'create', 'edit', 'delete', 'configure_numbering'],
        'policy' => ['view', 'create', 'edit', 'delete'],
        'authorization_log' => ['view', 'export'],
        'workflow_state' => ['view', 'create', 'edit', 'setstate'],
        'lifecycle' => ['view', 'create', 'edit', 'setstate'],
        'product_group' => ['view', 'create', 'edit', 'setstate', 'export'],
        'product_uom' => ['view', 'create', 'edit', 'setstate', 'export'],
        'product' => ['view', 'create', 'edit', 'setstate', 'import', 'export'],
        'price_list' => ['view', 'create', 'edit', 'setstate', 'export'],
        'import_job' => ['view', 'export'],
    ];

    public function testSeedTwiceProducesExactlyTheApprovedCatalog(): void
    {
        $beforeSideEffects = $this->sideEffectCounts();

        $this->seedCatalog();
        $first = $this->catalogSnapshot();
        $this->seedCatalog();
        $second = $this->catalogSnapshot();

        $this->assertSame($first, $second);
        $this->assertSame($this->normalizedMatrix(), $second);
        $this->assertSame($beforeSideEffects, $this->sideEffectCounts());
        $this->assertSame(20, count($second));
        $this->assertSame(92, array_sum(array_map('count', $second)));
    }

    public function testImportAndNumberingActionsAreScopedExactly(): void
    {
        $this->seedCatalog();
        $catalog = $this->catalogSnapshot();

        $this->assertSame(['create', 'edit', 'export', 'import', 'setstate', 'view'], $catalog['product']);
        $this->assertNotContains('import', array_merge(...array_values(array_diff_key($catalog, ['product' => true]))));
        $this->assertSame(['configure_numbering', 'create', 'delete', 'edit', 'view'], $catalog['resource']);
        $this->assertNotContains('configure_numbering', $catalog['resource'] === [] ? [] : array_values(array_diff($catalog['resource'], ['configure_numbering'])));
        $this->assertContains('assign_member', $catalog['authorization_group']);
        $this->assertContains('assign_permission', $catalog['authorization_group']);
        $this->assertContains('assign_permission', $catalog['user']);
    }

    private function seedCatalog(): void
    {
        (new ResourceActionSeeder(new \Config\Database()))->run();
    }

    /** @return array<string, list<string>> */
    private function normalizedMatrix(): array
    {
        $matrix = self::MATRIX;
        ksort($matrix);
        foreach ($matrix as &$actions) {
            sort($actions);
        }
        unset($actions);

        return $matrix;
    }

    /** @return array<string, list<string>> */
    private function catalogSnapshot(): array
    {
        $rows = $this->db->table('resources r')
            ->select('r.type, ra.code')
            ->join('resource_actions ra', 'ra.resource_id = r.id')
            ->orderBy('r.type', 'ASC')
            ->orderBy('ra.code', 'ASC')
            ->get()
            ->getResultArray();
        $catalog = [];
        foreach ($rows as $row) {
            $catalog[(string) $row['type']][] = (string) $row['code'];
        }

        return $catalog;
    }

    /** @return array<string, int> */
    private function sideEffectCounts(): array
    {
        return [
            'groups' => (int) $this->db->table('groups')->countAllResults(),
            'group_permissions' => (int) $this->db->table('group_permissions')->countAllResults(),
            'policies' => (int) $this->db->table('policies')->countAllResults(),
            'auto_numbering_series' => (int) $this->db->table('auto_numbering_series')->countAllResults(),
            'auto_numbering_counters' => (int) $this->db->table('auto_numbering_counters')->countAllResults(),
        ];
    }
}
