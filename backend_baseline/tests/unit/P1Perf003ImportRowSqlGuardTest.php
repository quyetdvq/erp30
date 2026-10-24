<?php

declare(strict_types=1);

use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Services\Api060ProductImportService;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class P1Perf003ImportRowSqlGuardTest extends CIUnitTestCase
{
    public function testFastPathRejectsLiveJobsSearchAndNoncanonicalFilters(): void
    {
        $service = new Api060ProductImportService();
        $adapter = new CommonListQueryAdapter();
        $allowlist = new CommonListQueryAllowlist(
            search: ['entity_key'],
            filters: ['row_status'],
            sort: ['row_number', 'row_status', 'entity_key', 'target_ref_id', 'created_at'],
        );
        $guard = new ReflectionMethod(Api060ProductImportService::class, 'canPageImportRowsInSql');
        $terminal = ['status' => 'COMPLETED'];
        $live = ['status' => 'PROCESSING'];

        $default = $adapter->parse(['sort' => 'row_number'], $allowlist);
        self::assertTrue($guard->invoke($service, $terminal, $default));
        self::assertFalse($guard->invoke($service, $live, $default));
        self::assertTrue($guard->invoke($service, $terminal, $adapter->parse([
            'sort' => '-row_number', 'filter' => ['row_status' => ['SUCCESS', 'ERROR']],
        ], $allowlist)));
        self::assertFalse($guard->invoke($service, $terminal, $adapter->parse([
            'q' => 'Hà Nội', 'sort' => 'row_number',
        ], $allowlist)));
        self::assertFalse($guard->invoke($service, $terminal, $adapter->parse([
            'sort' => 'entity_key',
        ], $allowlist)));
        self::assertFalse($guard->invoke($service, $terminal, $adapter->parse([
            'sort' => 'row_number', 'filter' => ['row_status' => 'success'],
        ], $allowlist)));
    }
}
