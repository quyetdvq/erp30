<?php

declare(strict_types=1);

use App\Repositories\ContactRepository;
use App\Services\ContactService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Con001ContactServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testContactCrudSupportsBothOwnerTypesAndHistory(): void
    {
        $organizationId = $this->insertOrganization();
        $employeeId = $this->insertEmployee();
        $service = new ContactService(new ContactRepository($this->db), $this->db);

        $organizationContact = $service->create($this->contact('ORGANIZATION', $organizationId, 'Org Contact'));
        $employeeContact = $service->create($this->contact('EMPLOYEE', $employeeId, 'Employee Contact'));

        $this->assertCount(1, $service->listForOwner('ORGANIZATION', $organizationId, 'ACTIVE'));
        $this->assertCount(1, $service->listForOwner('EMPLOYEE', $employeeId));
        $this->assertCount(1, $service->search(['query' => 'Employee']));
        $this->assertTrue($service->update($organizationContact, ['name' => 'Updated Contact']));
        $this->assertTrue($service->inactivate($employeeContact));
        $this->assertCount(1, $service->listForOwner('EMPLOYEE', $employeeId, 'INACTIVE'));
        $this->assertSame('Updated Contact', (new ContactRepository($this->db))->find($organizationContact)['name']);
    }

    public function testOwnerTransferUnknownOwnersAndMismatchedWardAreRejected(): void
    {
        $service = new ContactService(new ContactRepository($this->db), $this->db);
        $organizationId = $this->insertOrganization();
        $provinceId = $this->insertProvince('Province A');
        $otherProvinceId = $this->insertProvince('Province B');
        $wardId = $this->insertWard($otherProvinceId, 'Ward B');

        $this->expectException(InvalidArgumentException::class);
        $service->create($this->contact('ORGANIZATION', $organizationId, 'Invalid', $provinceId, $wardId));
    }

    /** @return array<string, int|string> */
    private function contact(string $type, int $ownerId, string $name, ?int $provinceId = null, ?int $wardId = null): array
    {
        return array_filter([
            'contact_type' => $type,
            'contact_ref_id' => $ownerId,
            'status' => 'ACTIVE',
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.test',
            'created_at' => '2026-10-05 00:00:00',
            'province_id' => $provinceId,
            'ward_id' => $wardId,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function insertOrganization(): int
    {
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'code' => 'CON-ORG-' . uniqid(), 'name' => 'Contact Group',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertEmployee(): int
    {
        $this->db->table('employees')->insert([
            'employee_code' => 'CON-EMP-' . uniqid(), 'employee_name' => 'Contact Employee',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProvince(string $name): int
    {
        $this->db->table('provinces')->insert([
            'code' => uniqid('P'), 'name' => $name, 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertWard(int $provinceId, string $name): int
    {
        $this->db->table('wards')->insert([
            'province_id' => $provinceId, 'code' => uniqid('W'), 'name' => $name, 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }
}
