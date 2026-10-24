<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Repositories\OrganizationRepository;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;

/** @internal */
final class P1Org001OrganizationRepositoryTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;

    public function testOrganizationCrudAndLookup(): void
    {
        $repository = new OrganizationRepository($this->db);
        $previousGroupCount = count($repository->findAll(['type' => 'GROUP', 'status' => 'ACTIVE']));
        $code = 'ORG-REPO-' . uniqid();
        $id = $repository->insert([
            'type' => 'GROUP', 'code' => $code, 'name' => 'Repository Group',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        $this->assertSame($code, $repository->find($id)['code']);
        $this->assertSame($id, (int) $repository->findByCode($code)['id']);
        $this->assertCount($previousGroupCount + 1, $repository->findAll(['type' => 'GROUP', 'status' => 'ACTIVE']));
        $this->assertTrue($repository->update($id, ['name' => 'Updated Group']));
        $this->assertSame('Updated Group', $repository->find($id)['name']);
    }

    public function testRepositoryRejectsUnknownFieldsAndEmptyUpdates(): void
    {
        $repository = new OrganizationRepository($this->db);
        try {
            $repository->insert([
                'type' => 'GROUP', 'code' => 'ORG-REPO-002', 'name' => 'Invalid',
                'status' => 'ACTIVE', 'not_a_column' => true,
            ]);
            $this->fail('Expected unsupported field rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(OrganizationRepository::ERROR_FIELD_UNSUPPORTED, $exception->getErrorCode());
            $this->assertSame('not_a_column', $exception->getContext()['field']);
        }
    }

    public function testRepositoryCatalogUsesStableCodesInBothLocales(): void
    {
        $code = OrganizationRepository::ERROR_FIELD_UNSUPPORTED;
        $this->assertNotSame(
            lang('Organization.' . $code, [], 'vi'),
            lang('Organization.' . $code, [], 'en'),
        );
    }
}
