<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Import\Persistence\ImportJobRepository;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Product\Import\ProductImportJobService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Imp001ProductImportJobServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testAllProductModesAndFormatsPersistPendingZeroCountJobs(): void
    {
        $service = new ProductImportJobService(db: $this->db);
        $operation = $this->operation(41);

        foreach ([
            [ProductImportJobService::MODE_CREATE_ONLY, ProductImportJobService::FORMAT_CSV],
            [ProductImportJobService::MODE_UPDATE_ONLY, ProductImportJobService::FORMAT_XLSX],
            [ProductImportJobService::MODE_UPSERT, ProductImportJobService::FORMAT_CSV],
        ] as [$mode, $format]) {
            $id = $service->create([
                'entity_type' => 'product', 'mode' => strtolower($mode), 'source_format' => strtolower($format),
            ], $operation);
            $row = $service->findById($id);
            $this->assertNotNull($row);
            $this->assertSame('PRODUCT', $row['entity_type']);
            $this->assertSame($mode, $row['mode']);
            $this->assertSame($format, $row['source_format']);
            $this->assertSame('PENDING', $row['status']);
            $this->assertSame('0', (string) $row['total_rows']);
            $this->assertSame('0', (string) $row['success_rows']);
            $this->assertSame('0', (string) $row['error_rows']);
        }
    }

    public function testOptionalSourceMetadataAndTrustedAuditArePersisted(): void
    {
        $service = new ProductImportJobService(db: $this->db);
        $operation = $this->operation(77);
        $id = $service->create([
            'entity_type' => 'PRODUCT', 'mode' => 'CREATE_ONLY', 'source_format' => 'CSV',
            'source_file_name' => 'products.csv', 'source_file_hash' => hash('sha256', 'products'),
            'created_by' => 999999999, 'created_at' => '1999-01-01 00:00:00',
            'status' => 'COMPLETED', 'total_rows' => 99,
        ], $operation);
        $row = $service->find($id);

        $this->assertSame('products.csv', $row['source_file_name']);
        $this->assertSame(hash('sha256', 'products'), $row['source_file_hash']);
        $this->assertSame('77', (string) $row['created_by']);
        $this->assertSame('2026-10-06 04:05:06', $row['created_at']);
        $this->assertSame('PENDING', $row['status']);
        $this->assertSame('0', (string) $row['total_rows']);
    }

    public function testInvalidEntityModeAndFormatAreRejected(): void
    {
        $service = new ProductImportJobService(db: $this->db);
        $operation = $this->operation(41);
        $base = ['entity_type' => 'PRODUCT', 'mode' => 'CREATE_ONLY', 'source_format' => 'CSV'];
        $this->assertDomainCode(fn () => $service->create(array_merge($base, ['entity_type' => 'CUSTOMER']), $operation), ProductImportJobService::ERROR_INVALID_ENTITY_TYPE);
        $this->assertDomainCode(fn () => $service->create(array_merge($base, ['mode' => 'MERGE']), $operation), ProductImportJobService::ERROR_INVALID_MODE);
        $this->assertDomainCode(fn () => $service->create(array_merge($base, ['source_format' => 'TXT']), $operation), ProductImportJobService::ERROR_INVALID_FORMAT);
    }

    public function testFindByIdDistinguishesNotFoundFromPersistenceFailure(): void
    {
        $service = new ProductImportJobService(db: $this->db);
        $this->assertNull($service->findById(999999999));

        $connection = $this->createMock(CodeIgniter\Database\ConnectionInterface::class);
        $connection->method('table')->willThrowException(new RuntimeException('database unavailable'));
        $failed = new ProductImportJobService(repository: new ImportJobRepository($connection), db: $connection);
        $this->expectException(PersistenceException::class);
        $failed->findById(1);
    }

    public function testInfrastructureBoundaryHasNoProductDependencyAndNoGenericModuleExists(): void
    {
        $repositoryPath = dirname(__DIR__, 4) . '/app/Infrastructure/Import/Persistence/ImportJobRepository.php';
        $contents = (string) file_get_contents($repositoryPath);
        $this->assertStringNotContainsString('App\\Modules\\Product', $contents);
        $this->assertFileDoesNotExist(dirname(__DIR__, 4) . '/app/Modules/Import');
        $serviceContents = (string) file_get_contents(dirname(__DIR__, 4) . '/app/Modules/Product/Import/ProductImportJobService.php');
        $this->assertStringContainsString('App\\Infrastructure\\Import\\Persistence\\ImportJobRepository', $serviceContents);
    }

    private function operation(int $actorId): OperationContext
    {
        return new OperationContext(
            $actorId,
            new EvaluationTime(new DateTimeImmutable('2026-10-06 04:05:06', new DateTimeZone('UTC'))),
            'imp-001-test',
            false,
        );
    }

    private function assertDomainCode(Closure $callback, string $code): void
    {
        try {
            $callback();
            $this->fail('Expected domain failure ' . $code);
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }
}
