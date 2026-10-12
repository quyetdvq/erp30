<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Infrastructure\Api\SqlAuthorizedBucketListSource;
use App\Repositories\FoundationLocationRepository;
use App\Services\FoundationLocationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal DB-backed regression for location lookups, history and address integrity. */
final class P1Loc010OptimizationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testNewProvinceSelectionRequiresActiveButHistoricalWardCanStillBeEdited(): void
    {
        $inactive = $this->province('INACTIVE');
        $active = $this->province('ACTIVE');
        $service = $this->service();

        $this->assertCode(
            FoundationLocationService::ERROR_PROVINCE_INACTIVE,
            fn () => $service->createWard($this->wardInput($inactive)),
        );
        $wardId = $service->createWard($this->wardInput($active));
        $this->db->table('provinces')->where('id', $active)->update(['status' => 'INACTIVE']);
        $this->assertTrue($service->updateWard($wardId, ['name' => 'Historical Ward Updated']));
        $this->assertSame('Historical Ward Updated', (string) $service->ward($wardId)['name']);
        $this->assertCode(
            FoundationLocationService::ERROR_PROVINCE_INACTIVE,
            fn () => $service->updateWard($wardId, ['province_id' => $inactive]),
        );
        $this->assertSame($active, (int) $service->ward($wardId)['province_id']);
    }

    public function testWardReparentRejectsExistingOrganizationAndContactAddressPairs(): void
    {
        $oldProvince = $this->province('ACTIVE');
        $newProvince = $this->province('ACTIVE');
        $service = $this->service();
        $wardId = $service->createWard($this->wardInput($oldProvince));
        $orgId = $this->organization($oldProvince, $wardId);

        $this->assertCode(
            FoundationLocationService::ERROR_WARD_REFERENCED,
            fn () => $service->updateWard($wardId, ['province_id' => $newProvince]),
        );
        $this->assertSame($oldProvince, (int) $service->ward($wardId)['province_id']);

        $this->db->table('organizations')->where('id', $orgId)->update(['ward_id' => null]);
        $this->db->table('contacts')->insert([
            'contact_type' => 'ORGANIZATION', 'contact_ref_id' => $orgId,
            'name' => 'Address contact', 'status' => 'ACTIVE',
            'province_id' => $oldProvince, 'ward_id' => $wardId,
            'created_at' => '2026-10-01 00:00:00',
        ]);
        $this->assertCode(
            FoundationLocationService::ERROR_WARD_REFERENCED,
            fn () => $service->updateWard($wardId, ['province_id' => $newProvince]),
        );
        $this->assertSame($oldProvince, (int) $service->ward($wardId)['province_id']);
    }

    public function testSqlLocationCandidateNarrowingKeepsExactPhpSearchAndSortSemantics(): void
    {
        $province = $this->province('ACTIVE');
        $other = $this->province('ACTIVE');
        $this->wardRow($province, 'North Omega', 'ACTIVE');
        $this->wardRow($province, 'north alpha', 'ACTIVE');
        $this->wardRow($province, 'South', 'ACTIVE');
        $this->wardRow($province, 'North Hidden', 'INACTIVE');
        $this->wardRow($other, 'North Elsewhere', 'ACTIVE');

        $adapter = new CommonListQueryAdapter();
        $allowlist = new CommonListQueryAllowlist(
            search: ['code', 'name', 'name_full'],
            filters: ['status', 'province_id', 'code', 'name'],
            sort: ['name', 'status'],
        );
        $request = [
            'q' => 'NORTH', 'sort' => 'name', 'page' => '1', 'page_size' => '1',
            'filter' => ['province_id' => (string) $province, 'status' => 'ACTIVE'],
        ];
        $query = $adapter->parse($request, $allowlist);
        $buckets = [['status' => 'ACTIVE']];
        $source = new SqlAuthorizedBucketListSource($this->db, 'ward', $buckets);
        $candidates = $source->fetchLocationCandidates($query);
        $actual = $adapter->paginate($request, $allowlist, new AuthorizedArrayListSource($candidates));
        $this->assertSame('2', $actual->total);
        $this->assertSame(['north alpha'], array_column(iterator_to_array($actual->data), 'name'));
        $this->assertTrue(array_reduce($candidates, static fn (bool $carry, array $row): bool =>
            $carry && (int) $row['province_id'] === $province && $row['status'] === 'ACTIVE', true));
    }

    private function service(): FoundationLocationService
    {
        // Location creation resolves the canonical Resource Lifecycle first.
        // Seed the same catalog used by the application before exercising the service.
        (new ResourceActionSeeder(new \Config\Database()))->run();

        return new FoundationLocationService(new FoundationLocationRepository($this->db), $this->db);
    }

    private function province(string $status): int
    {
        $this->db->table('provinces')->insert([
            'code' => uniqid('LP'), 'name' => 'Location Province', 'status' => $status,
            'created_at' => '2026-10-01 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    /** @return array{province_id:int,code:string,name:string,status:string} */
    private function wardInput(int $provinceId): array
    {
        return ['province_id' => $provinceId, 'code' => uniqid('LW'),
            'name' => 'Location Ward', 'status' => 'ACTIVE'];
    }

    private function wardRow(int $provinceId, string $name, string $status): void
    {
        $this->db->table('wards')->insert([
            'province_id' => $provinceId, 'code' => uniqid('LW'), 'name' => $name,
            'status' => $status, 'created_at' => '2026-10-01 00:00:00',
        ]);
    }

    private function organization(int $provinceId, int $wardId): int
    {
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'code' => uniqid('LO'), 'name' => 'Location Owner',
            'status' => 'ACTIVE', 'province_id' => $provinceId, 'ward_id' => $wardId,
            'created_at' => '2026-10-01 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function assertCode(string $code, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected domain rejection: ' . $code);
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }
}
