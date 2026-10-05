<?php

declare(strict_types=1);

use App\Database\Seeds\GroupRootSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Seed004GroupRootTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testEmptyScopeCreatesOnlyTheApprovedGroupRoot(): void
    {
        $this->seedRoot();

        $root = $this->rootRows()[0];
        $this->assertSame('GROUP_DINKTECH', $root['code']);
        $this->assertSame('Dinktech Group', $root['name']);
        $this->assertSame('Tập đoàn Dinktech', $root['description']);
        $this->assertSame('GROUP', $root['type']);
        $this->assertSame('ACTIVE', $root['status']);
        foreach (['parent_id', 'legal_name', 'tax_code', 'address', 'province_id', 'ward_id', 'active_from', 'active_to', 'created_by', 'updated_by'] as $field) {
            $this->assertNull($root[$field], $field);
        }
        $this->assertSame(0, $this->db->table('organizations')->where('type', 'COMPANY')->countAllResults());
    }

    public function testCanonicalRootRerunIsAnUntouchedNoOp(): void
    {
        $this->seedRoot();
        $root = $this->rootRows()[0];
        $this->db->table('organizations')->where('id', (int) $root['id'])->update([
            'name' => 'Approved display override',
            'description' => 'Existing business detail',
        ]);

        $this->seedRoot();
        $after = $this->rootRows()[0];
        $this->assertSame((int) $root['id'], (int) $after['id']);
        $this->assertSame('Approved display override', $after['name']);
        $this->assertSame('Existing business detail', $after['description']);
        $this->assertCount(1, $this->rootRows());
    }

    public function testAlternateRootCodeBlocksWithoutRenamingOrCreating(): void
    {
        $this->insertRoot('GROUP_OTHER');

        $this->expectException(RuntimeException::class);
        try {
            $this->seedRoot();
        } finally {
            $this->assertSame(['GROUP_OTHER'], array_column($this->rootRows(), 'code'));
        }
    }

    public function testMultipleGroupRootsBlockAsInvalidExistingState(): void
    {
        $this->insertRoot('GROUP_ONE');
        $this->insertRoot('GROUP_TWO');

        $this->expectException(RuntimeException::class);
        try {
            $this->seedRoot();
        } finally {
            $this->assertSame(['GROUP_ONE', 'GROUP_TWO'], array_column($this->rootRows(), 'code'));
        }
    }

    public function testCanonicalCodeWithIncompatibleTypeBlocks(): void
    {
        $this->insertOrganization('COMPANY', 'GROUP_DINKTECH', null);

        $this->expectException(RuntimeException::class);
        try {
            $this->seedRoot();
        } finally {
            $this->assertSame(0, $this->db->table('organizations')->where('type', 'GROUP')->countAllResults());
            $this->assertSame(1, $this->db->table('organizations')->where('code', 'GROUP_DINKTECH')->countAllResults());
        }
    }

    public function testCanonicalCodeThatIsNotAGroupRootBlocks(): void
    {
        $parentId = $this->insertRoot('GROUP_PARENT');
        $this->insertOrganization('COMPANY', 'GROUP_DINKTECH', $parentId);

        $this->expectException(RuntimeException::class);
        try {
            $this->seedRoot();
        } finally {
            $this->assertSame(['GROUP_PARENT'], array_column($this->rootRows(), 'code'));
            $this->assertSame(1, $this->db->table('organizations')->where('code', 'GROUP_DINKTECH')->countAllResults());
        }
    }

    private function seedRoot(): void
    {
        (new GroupRootSeeder(new \Config\Database()))->run();
    }

    private function insertRoot(string $code): int
    {
        return $this->insertOrganization('GROUP', $code, null);
    }

    private function insertOrganization(string $type, string $code, ?int $parentId): int
    {
        $this->db->table('organizations')->insert([
            'parent_id' => $parentId,
            'type' => $type,
            'code' => $code,
            'name' => $code . ' name',
            'description' => 'existing',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    /** @return list<array<string, mixed>> */
    private function rootRows(): array
    {
        $result = $this->db->table('organizations')
            ->where('type', 'GROUP')
            ->where('parent_id IS NULL', null, false)
            ->orderBy('id', 'ASC')
            ->get();

        return $result === false ? [] : $result->getResultArray();
    }
}
