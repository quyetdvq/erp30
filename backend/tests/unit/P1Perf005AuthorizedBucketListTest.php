<?php

declare(strict_types=1);

use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQuery;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Infrastructure\Api\SqlAuthorizedBucketListSource;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal Effective Authorization buckets must retain exact PHP list results. */
final class P1Perf005AuthorizedBucketListTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testGuardRejectsSearchSortAndNoncanonicalNumericFilters(): void
    {
        $adapter = new CommonListQueryAdapter();
        $allowlist = new CommonListQueryAllowlist(
            search: ['name'], filters: ['parent_id', 'status'], sort: ['id', 'name'],
        );
        $this->assertTrue(SqlAuthorizedBucketListSource::supports('department', $adapter->parse([], $allowlist)));
        $this->assertTrue(SqlAuthorizedBucketListSource::supports('department', $adapter->parse(['sort' => '-id'], $allowlist)));
        $this->assertFalse(SqlAuthorizedBucketListSource::supports('department', $adapter->parse(['q' => 'Dept'], $allowlist)));
        $this->assertFalse(SqlAuthorizedBucketListSource::supports('department', $adapter->parse(['sort' => 'name'], $allowlist)));
        $this->assertFalse(SqlAuthorizedBucketListSource::supports('department', $adapter->parse(['filter' => ['parent_id' => '01']], $allowlist)));
        $this->assertFalse(SqlAuthorizedBucketListSource::supports('department', $adapter->parse(['filter' => ['parent_id' => '-1']], $allowlist)));
        $this->assertFalse(SqlAuthorizedBucketListSource::supports('department', $adapter->parse(['filter' => ['parent_id' => '99999999999999999999999']], $allowlist)));
        $this->assertFalse(SqlAuthorizedBucketListSource::supports('organization', $adapter->parse([], $allowlist)));
    }

    public function testSqlCountAndPageEqualPhpForExactStateAndNullableParent(): void
    {
        foreach (['ACTIVE', 'active', 'INACTIVE', 'ACTIVE'] as $index => $status) {
            $this->assertTrue($this->db->table('departments')->insert([
                'code' => 'PERF005-D-' . uniqid() . '-' . $index,
                'name' => 'Department ' . $index,
                'status' => $status,
                'created_at' => '2026-10-11 00:00:00',
            ]));
        }
        $all = $this->db->table('departments')->orderBy('id', 'ASC')->get()->getResultArray();
        $allowed = array_values(array_filter($all, static fn (array $row): bool => $row['status'] === 'ACTIVE'));
        $allowedPhp = new AuthorizedArrayListSource($allowed);
        $allowedSql = new SqlAuthorizedBucketListSource($this->db, 'department', [['status' => 'ACTIVE']]);
        $adapter = new CommonListQueryAdapter();
        $allowlist = new CommonListQueryAllowlist(search: ['name'], filters: ['status', 'parent_id'], sort: ['id', 'name']);
        foreach ([
            ['page_size' => '2'],
            ['page_size' => '2', 'page' => '2', 'sort' => '-id'],
            ['filter' => ['status' => ['ACTIVE', 'active']]],
            ['filter' => ['parent_id' => '']],
            ['filter' => ['parent_id' => '5']],
        ] as $input) {
            $query = $adapter->parse($input, $allowlist);
            $this->assertSame($allowedPhp->countAuthorized($query), $allowedSql->countAuthorized($query), json_encode($input));
            $this->assertSame(
                array_values(iterator_to_array($allowedPhp->fetchAuthorized($query))),
                array_values(iterator_to_array($allowedSql->fetchAuthorized($query))),
                json_encode($input),
            );
        }
        $none = new SqlAuthorizedBucketListSource($this->db, 'department', []);
        $query = $adapter->parse([], $allowlist);
        $this->assertSame(0, $none->countAuthorized($query));
        $this->assertSame([], $none->fetchAuthorized($query));
    }

    public function testOrganizationStateBucketsNeverBleedIntoSiblingScope(): void
    {
        $rootCode = 'PERF005-O-' . uniqid();
        $this->assertTrue($this->db->table('organizations')->insert([
            'code' => $rootCode, 'name' => 'Group', 'type' => 'GROUP',
            'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]));
        $rootId = (int) $this->db->insertID();
        $otherCode = 'PERF005-O-' . uniqid();
        $this->assertTrue($this->db->table('organizations')->insert([
            'code' => $otherCode, 'name' => 'Sibling', 'type' => 'COMPANY', 'parent_id' => $rootId,
            'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]));
        $otherId = (int) $this->db->insertID();
        $this->assertTrue($this->db->table('employees')->insert([
            'employee_code' => 'PERF005-E-' . uniqid(), 'employee_name' => 'Employee',
            'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]));
        $employeeId = (int) $this->db->insertID();
        foreach ([$rootId, $otherId] as $orgId) {
            foreach (['ACTIVE', 'INACTIVE'] as $status) {
                $this->assertTrue($this->db->table('employee_assignments')->insert([
                    'employee_id' => $employeeId, 'organization_id' => $orgId,
                    'effective_from' => '2026-10-01', 'status' => $status,
                    'created_at' => '2026-10-11 00:00:00',
                ]));
            }
        }
        $all = $this->db->table('employee_assignments')->orderBy('id', 'ASC')->get()->getResultArray();
        $allowed = array_values(array_filter($all, static fn (array $row): bool =>
            (int) $row['organization_id'] === $rootId && $row['status'] === 'ACTIVE'));
        $sql = new SqlAuthorizedBucketListSource($this->db, 'employee_assignment', [
            ['organization_id' => $rootId, 'status' => 'ACTIVE'],
        ]);
        $adapter = new CommonListQueryAdapter();
        $allowlist = new CommonListQueryAllowlist([], ['organization_id', 'status', 'employee_id'], ['id']);
        foreach ([[], ['filter' => ['status' => ['ACTIVE', 'INACTIVE']]], ['filter' => ['organization_id' => (string) $otherId]]] as $input) {
            $query = $adapter->parse($input, $allowlist);
            $php = new AuthorizedArrayListSource($allowed);
            $this->assertSame($php->countAuthorized($query), $sql->countAuthorized($query));
            $this->assertSame($php->fetchAuthorized($query), $sql->fetchAuthorized($query));
        }
    }
}
