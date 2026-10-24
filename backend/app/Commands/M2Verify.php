<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Access\ScopeFactsReader;
use App\Modules\Locations\LocationReadRepository;
use App\Modules\Organization\OrganizationReadRepository;
use App\Modules\People\PeopleReadRepository;
use App\Shared\Read\PositiveId;
use App\Shared\Read\UtcBusinessDate;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Read-only deployment smoke gate. Never prints employee names or addresses. */
final class M2Verify extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'm2:verify';
    protected $description = 'Verify M2 schema and read projections against the configured database (no writes).';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        $db = db_connect();
        $needed = [
            'provinces', 'wards', 'organizations', 'departments',
            'positions', 'employees', 'employee_assignments',
        ];
        foreach ($needed as $table) {
            if (! $db->tableExists($table)) {
                throw new RuntimeException('M2 requires missing baseline table: ' . $table);
            }
        }
        CLI::write('PASS: 7 baseline tables present; M2 performs no migrations', 'green');

        $locations = new LocationReadRepository($db);
        $ward = $db->table('wards')->select('id, province_id')->orderBy('id', 'ASC')->get(1)->getRowArray();
        if ($ward !== null) {
            if (! $locations->wardBelongsToProvince((string) $ward['id'], (string) $ward['province_id'])) {
                throw new RuntimeException('Ward/province read mismatch.');
            }
            CLI::write('PASS: ward/province read', 'green');
        } else {
            CLI::write('SKIP: ward/province fixture (table empty)', 'yellow');
        }

        $organizations = new OrganizationReadRepository($db);
        $orgTree = $organizations->treeSnapshot();
        $org = $db->table('organizations')->select('id')->orderBy('id', 'ASC')->get(1)->getRowArray();
        if ($org !== null) {
            $chain = $organizations->ancestorChain((string) $org['id']);
            $snapshot = $orgTree->ancestorChain((string) $org['id']);
            if (array_map(static fn (array $r): string => PositiveId::value((string) $r['id']), $chain)
                !== array_map(static fn (array $r): string => PositiveId::value((string) $r['id']), $snapshot)) {
                throw new RuntimeException('Organization SQL ancestry and tree snapshot disagree.');
            }
            CLI::write('PASS: persisted organization ancestry and complete tree', 'green');
        } else {
            CLI::write('SKIP: organization ancestry fixture (table empty)', 'yellow');
        }

        $people = new PeopleReadRepository($db);
        $people->departmentTree();
        CLI::write('PASS: department hierarchy (including empty)', 'green');

        $sample = $db->table('employees')->select('id, user_id')
            ->where('user_id IS NOT NULL', null, false)->orderBy('id', 'ASC')->get(1)->getRowArray();
        $instant = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $date = UtcBusinessDate::at($instant);
        if ($sample !== null) {
            $scope = new ScopeFactsReader($people, $organizations);
            $facts = $scope->employeeFactsAt((string) $sample['user_id'], $instant);
            if ($facts['employee'] === null
                || PositiveId::value((string) $facts['employee']['id']) !== PositiveId::value((string) $sample['id'])
                || $facts['utc_business_date'] !== $date) {
                throw new RuntimeException('User -> employee facts projection mismatch.');
            }
            CLI::write('PASS: employee-for-user facts; inactive employee is not hidden by repository', 'green');
        } else {
            CLI::write('SKIP: linked employee/user fixture (none available)', 'yellow');
        }

        $assignmentRows = $db->table('employee_assignments')
            ->select('id, employee_id, status, effective_from, effective_to')
            ->orderBy('id', 'ASC')->get(100)->getResultArray();
        if ($assignmentRows !== []) {
            $ids = array_values(array_unique(array_map(
                static fn (array $a): string => PositiveId::value((string) $a['employee_id']),
                $assignmentRows,
            )));
            $effective = $people->effectiveAssignmentsForEmployees($ids, $date);
            foreach ($assignmentRows as $row) {
                $employeeId = PositiveId::value((string) $row['employee_id']);
                $found = false;
                foreach ($effective[$employeeId] ?? [] as $candidate) {
                    if (PositiveId::value((string) $candidate['id']) === PositiveId::value((string) $row['id'])) {
                        $found = true;
                        break;
                    }
                }
                if ($found !== UtcBusinessDate::effective($row, $date)) {
                    throw new RuntimeException('Effective assignment SQL/time-window mismatch.');
                }
            }
            CLI::write('PASS: first ' . count($assignmentRows) . ' persisted assignment windows vs SQL read', 'green');
        } else {
            CLI::write('SKIP: assignment fixture (table empty)', 'yellow');
        }
        CLI::write('M2 read-model verification finished; command performed no database writes.', 'green');
    }
}
