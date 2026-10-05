<?php

declare(strict_types=1);

use App\Repositories\OrganizationRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Org001OrganizationRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testOrganizationCrudAndLookup(): void
    {
        $repository = new OrganizationRepository($this->db);
        $id = $repository->insert([
            'type' => 'GROUP', 'code' => 'ORG-REPO-001', 'name' => 'Repository Group',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        $this->assertSame('ORG-REPO-001', $repository->find($id)['code']);
        $this->assertSame($id, (int) $repository->findByCode('ORG-REPO-001')['id']);
        $this->assertCount(1, $repository->findAll(['type' => 'GROUP', 'status' => 'ACTIVE']));
        $this->assertTrue($repository->update($id, ['name' => 'Updated Group']));
        $this->assertSame('Updated Group', $repository->find($id)['name']);
    }

    public function testRepositoryRejectsUnknownFieldsAndEmptyUpdates(): void
    {
        $repository = new OrganizationRepository($this->db);
        $this->expectException(InvalidArgumentException::class);
        $repository->insert([
            'type' => 'GROUP', 'code' => 'ORG-REPO-002', 'name' => 'Invalid',
            'status' => 'ACTIVE', 'not_a_column' => true,
        ]);
    }
}
