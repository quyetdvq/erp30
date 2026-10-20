<?php

declare(strict_types=1);

use App\Infrastructure\Import\Persistence\ImportJobRowRepository;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;

/** @internal */
final class P1Perf002ImportRowPagingTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;

    public function testTerminalJobSqlPaginationPreservesRowOrderAndStatusFilters(): void
    {
        $this->db->table('import_jobs')->insert([
            'entity_type' => 'PRODUCT', 'mode' => 'CREATE_ONLY', 'source_format' => 'CSV',
            'status' => 'COMPLETED', 'created_at' => '2026-10-11 00:00:00',
        ]);
        $jobId = (int) $this->db->insertID();
        $this->db->table('import_job_rows')->insertBatch([
            ['import_job_id' => $jobId, 'row_number' => 5, 'row_status' => 'ERROR', 'entity_key' => 'E', 'created_at' => '2026-10-11 00:00:00'],
            ['import_job_id' => $jobId, 'row_number' => 2, 'row_status' => 'SUCCESS', 'entity_key' => 'B', 'created_at' => '2026-10-11 00:00:00'],
            ['import_job_id' => $jobId, 'row_number' => 4, 'row_status' => 'SUCCESS', 'entity_key' => 'D', 'created_at' => '2026-10-11 00:00:00'],
            ['import_job_id' => $jobId, 'row_number' => 1, 'row_status' => 'ERROR', 'entity_key' => 'A', 'created_at' => '2026-10-11 00:00:00'],
            ['import_job_id' => $jobId, 'row_number' => 3, 'row_status' => 'SKIPPED', 'entity_key' => 'C', 'created_at' => '2026-10-11 00:00:00'],
            // Historical malformed status proves the SQL filter is byte-exact
            // even when the MariaDB column collation is case-insensitive.
            ['import_job_id' => $jobId, 'row_number' => 6, 'row_status' => 'success', 'entity_key' => 'F', 'created_at' => '2026-10-11 00:00:00'],
        ]);

        $repository = new ImportJobRowRepository($this->db);
        self::assertSame(6, $repository->countPageRows($jobId));
        self::assertSame(2, $repository->countPageRows($jobId, ['ERROR']));
        self::assertSame(2, $repository->countPageRows($jobId, ['SUCCESS']));

        $queries = [];
        $listener = static function ($query) use (&$queries): void {
            if (str_contains(strtolower((string) $query), 'import_job_rows')) {
                $queries[] = (string) $query;
            }
        };
        Events::on('DBQuery', $listener);
        try {
            $total = $repository->countPageRows($jobId, ['SUCCESS', 'ERROR']);
            $page = $repository->findPageRows($jobId, 2, 1, 'ASC', ['SUCCESS', 'ERROR']);
            $reverse = $repository->findPageRows($jobId, 2, 0, 'DESC', ['SUCCESS', 'ERROR']);
        } finally {
            Events::removeListener('DBQuery', $listener);
        }

        self::assertCount(3, $queries, 'Count plus two pages must issue three SELECTs, never one per row.');
        self::assertSame(4, $total);
        self::assertSame([2, 4], array_map(static fn (array $row): int => (int) $row['row_number'], $page));
        self::assertSame([5, 4], array_map(static fn (array $row): int => (int) $row['row_number'], $reverse));
        self::assertSame([], $repository->findPageRows($jobId, 10, 10));
    }
}
