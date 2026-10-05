<?php

declare(strict_types=1);

use App\Database\Seeds\WorkflowStateSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Seed002WorkflowStateSeedTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    /** @var list<string> */
    private const CODES = [
        'DRAFT',
        'PENDING_APPROVAL',
        'APPROVED',
        'REJECTED',
        'CANCELLED',
    ];

    public function testInitialBootstrapSeedsExactlyFiveRowsWithNameEqualToCode(): void
    {
        $this->seedStates();

        $rows = $this->stateRows();
        $this->assertCount(5, $rows);
        $this->assertSame(self::CODES, array_column($rows, 'code'));
        $this->assertSame(self::CODES, array_column($rows, 'name'));
        $this->assertSame(5, $this->db->table('workflow_states')->whereIn('code', self::CODES)->countAllResults());
        $this->assertSame(0, $this->db->table('lifecycles')->countAllResults());
        $this->assertSame(0, $this->db->table('lifecycle_states')->countAllResults());
        $this->assertSame(0, $this->db->table('lifecycle_transitions')->countAllResults());
    }

    public function testRerunUsesCodeIdentityAndPreservesChangedDisplayName(): void
    {
        $this->seedStates();
        $before = $this->stateRows();

        $this->db->table('workflow_states')
            ->where('code', 'DRAFT')
            ->update(['name' => 'Draft display']);

        $this->seedStates();
        $after = $this->stateRows();

        $this->assertCount(5, $after);
        $this->assertSame(array_column($before, 'code'), array_column($after, 'code'));
        $this->assertSame('Draft display', $after[0]['name']);
        $this->assertSame(self::CODES, array_column($after, 'code'));
        $this->assertSame(1, $this->db->table('workflow_states')->where('code', 'DRAFT')->countAllResults());
        $this->assertNotSame($before[0]['name'], $after[0]['name']);
    }

    private function seedStates(): void
    {
        (new WorkflowStateSeeder(new \Config\Database()))->run();
    }

    /** @return list<array{code: string, name: string}> */
    private function stateRows(): array
    {
        $rows = $this->db->table('workflow_states')
            ->select('code, name')
            ->whereIn('code', self::CODES)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(
            static fn (array $row): array => [
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
            ],
            $rows,
        );
    }
}
