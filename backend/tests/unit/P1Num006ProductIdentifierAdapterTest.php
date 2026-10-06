<?php

declare(strict_types=1);

use App\Authorization\EvaluationOrganizationContext;
use App\Authorization\AuthorizationDecision;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\AutoNumberingSeriesRepository;
use App\Services\AutoNumberService;
use App\Services\AutoNumberingBusinessMutation;
use App\Services\AutoNumberingIdentifierCollision;
use App\Services\NumberingScopePeriodContext;
use App\Modules\Product\Integration\Numbering\ProductIdentifierAdapter;
use App\Modules\Product\Integration\Numbering\ProductIdentifierUniqueViolation;
use App\Services\AutoNumberingConfigurationService;
use App\Services\CanonicalNumberingPatternValidator;
use App\Services\NumberingAuthorizationPort;
use App\Services\ResourceIdentifierAdapter;
use App\Services\ResourceIdentifierAdapterRegistry;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Num006ProductIdentifierAdapterTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testCanonicalMetadataAndExplicitRegistryRegistration(): void
    {
        $adapter = new ProductIdentifierAdapter($this->db);
        $metadata = $adapter->metadata();
        $this->assertSame('product', $metadata->resourceType);
        $this->assertSame('products.code', $metadata->identifierField);
        $this->assertSame(50, $metadata->identifierMaxLength);
        $this->assertSame('GLOBAL', $metadata->identifierUniquenessScope);
        $this->assertSame('product_code', $metadata->transportImportAlias);
        $this->assertFalse($this->db->fieldExists('product_code', 'products'));

        $registry = new ResourceIdentifierAdapterRegistry(db: $this->db);
        $this->assertInstanceOf(ProductIdentifierAdapter::class, $registry->resolve('product'));
        $this->assertNotContains('fallback', $registry->resourceTypes());
        $this->expectExceptionMessage(ResourceIdentifierAdapter::ERROR_ADAPTER_NOT_FOUND);
        $registry->resolve('customer');
    }

    public function testGlobalOccupancyIgnoresStatusAndEvaluationContext(): void
    {
        $code = 'NUM006-OCC-' . strtoupper(bin2hex(random_bytes(4)));
        $this->insertProduct($code, 'INACTIVE');
        $adapter = new ProductIdentifierAdapter($this->db);
        $contextA = $this->context(101, 'COMPANY');
        $contextB = $this->context(202, 'ORGANIZATION');

        $this->assertTrue($adapter->isIdentifierOccupied($code, $contextA));
        $this->assertTrue($adapter->isIdentifierOccupied($code, $contextB));
        $this->assertFalse($adapter->isIdentifierOccupied($code . '-NEW', $contextB));
    }

    public function testOccupancyQueryFailureIsTypedAndManualLengthIsStrict(): void
    {
        $connection = $this->createMock(CodeIgniter\Database\ConnectionInterface::class);
        $connection->method('table')->willThrowException(new RuntimeException('database unavailable'));
        try {
            (new ProductIdentifierAdapter($connection))->isIdentifierOccupied('X', $this->context(1, 'GROUP'));
            $this->fail('Expected Product identifier persistence failure.');
        } catch (PersistenceException $exception) {
            $this->assertSame(ProductIdentifierAdapter::ERROR_PERSISTENCE_FAILED, $exception->getErrorCode());
        }

        $adapter = new ProductIdentifierAdapter($this->db);
        $adapter->validateManualIdentifier(str_repeat('A', 50), $this->context(1, 'GROUP'));
        try {
            $adapter->validateManualIdentifier(str_repeat('A', 51), $this->context(1, 'GROUP'));
            $this->fail('Expected Product code length rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceIdentifierAdapter::ERROR_IDENTIFIER_TOO_LONG, $exception->getErrorCode());
        }
    }

    public function testOnlyProductsCodeUniqueConflictIsClassified(): void
    {
        $adapter = new ProductIdentifierAdapter($this->db);
        $this->assertTrue($adapter->isIdentifierCollision(new ProductIdentifierUniqueViolation()));
        $this->assertFalse($adapter->isIdentifierCollision(new RuntimeException('duplicate sku')));
        $this->assertFalse($adapter->isIdentifierCollision(new RuntimeException('foreign key failure')));

        $code = 'NUM006-COLL-' . strtoupper(bin2hex(random_bytes(4)));
        $this->insertProduct($code, 'ACTIVE');
        try {
            $this->db->table('products')->insert($this->product($code));
            $this->fail('Expected products.code unique violation.');
        } catch (DatabaseException $exception) {
            $this->assertTrue($adapter->isIdentifierCollision($exception), $exception->getMessage());
        }

        $sku = 'NUM006-SKU-' . strtoupper(bin2hex(random_bytes(3)));
        $barcode = 'NUM006-BAR-' . strtoupper(bin2hex(random_bytes(3)));
        $this->db->table('products')->insert($this->product('NUM006-SKU-OWNER-' . strtoupper(bin2hex(random_bytes(3))), 'ACTIVE', $sku, $barcode));
        try {
            $this->db->table('products')->insert($this->product('NUM006-SKU-DUP-' . strtoupper(bin2hex(random_bytes(3))), 'ACTIVE', $sku, null));
            $this->fail('Expected products.sku unique violation.');
        } catch (DatabaseException $exception) {
            $this->assertFalse($adapter->isIdentifierCollision($exception), $exception->getMessage());
        }
        try {
            $this->db->table('products')->insert($this->product('NUM006-BAR-DUP-' . strtoupper(bin2hex(random_bytes(3))), 'ACTIVE', null, $barcode));
            $this->fail('Expected products.barcode unique violation.');
        } catch (DatabaseException $exception) {
            $this->assertFalse($adapter->isIdentifierCollision($exception), $exception->getMessage());
        }
    }

    public function testProductAdapterUsesGlobalOccupancyInsideAutoNumberTransaction(): void
    {
        $resource = $this->productResource();
        $seriesId = (new AutoNumberingSeriesRepository($this->db))->insert([
            'resource_id' => $resource, 'series_code' => 'NUM006-' . strtoupper(bin2hex(random_bytes(3))),
            'name' => 'Product test', 'scope_type' => 'GROUP', 'pattern' => '{SEQ:3}',
            'reset_policy' => 'NEVER', 'generation_mode' => 'AUTO_REQUIRED', 'start_number' => 1,
            'increment_by' => 1, 'is_default' => 0, 'status' => 'ACTIVE',
        ]);
        $this->insertProduct('001', 'INACTIVE');
        $business = new Num006ProductMutation($this->db);
        $group = $this->groupRoot();
        $operation = new OperationContext(null, new EvaluationTime(new DateTimeImmutable('2026-10-06 04:05:06', new DateTimeZone('UTC'))), 'num006-generated', true);
        $result = (new AutoNumberService(
            adapters: new ResourceIdentifierAdapterRegistry(db: $this->db),
            db: $this->db,
        ))->issue(
            'product',
            new EvaluationOrganizationContext((int) $group['id'], 'GROUP', 'AUTHORITATIVE'),
            $operation,
            $business,
            (string) $this->db->table('auto_numbering_series')->where('id', $seriesId)->get()->getRowArray()['series_code'],
        );

        $this->assertSame('002', $result->identifier);
        $this->assertSame(2, $result->sequenceValue);
        $this->assertSame(['002'], $business->persisted);
        $this->assertSame(2, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->get()->getRowArray()['last_value']);
    }

    public function testProductGlobalMetadataFeedsCanonicalPatternCompatibility(): void
    {
        $resource = $this->productResource();
        $service = new AutoNumberingConfigurationService(
            authorization: new Num006AllowAuthorization(),
            patternValidator: new CanonicalNumberingPatternValidator(),
            db: $this->db,
        );
        foreach ([
            ['scope_type' => 'COMPANY', 'pattern' => '{SEQ:3}'],
            ['scope_type' => 'ORGANIZATION', 'pattern' => '{SEQ:3}'],
        ] as $invalid) {
            try {
                $service->create($this->seriesPayload($resource, $invalid['scope_type'], $invalid['pattern']));
                $this->fail('Expected GLOBAL namespace discriminator rejection.');
            } catch (DomainException $exception) {
                $this->assertSame(CanonicalNumberingPatternValidator::ERROR_PATTERN_INVALID, $exception->getErrorCode());
            }
        }
        $companyId = $service->create($this->seriesPayload($resource, 'COMPANY', '{COMPANY_CODE}-{SEQ:3}'));
        $organizationId = $service->create($this->seriesPayload($resource, 'ORGANIZATION', '{ORG_CODE}-{SEQ:3}'));
        $this->assertGreaterThan(0, $companyId);
        $this->assertGreaterThan(0, $organizationId);
    }

    public function testRollbackDoesNotPersistProductOrCounter(): void
    {
        $resource = $this->productResource();
        $seriesId = (new AutoNumberingSeriesRepository($this->db))->insert([
            'resource_id' => $resource, 'series_code' => 'NUM006-ROLL-' . strtoupper(bin2hex(random_bytes(3))),
            'name' => 'Product rollback', 'scope_type' => 'GROUP', 'pattern' => '{SEQ:3}',
            'reset_policy' => 'NEVER', 'generation_mode' => 'AUTO_REQUIRED', 'start_number' => 10,
            'increment_by' => 1, 'is_default' => 0, 'status' => 'ACTIVE',
        ]);
        $group = $this->groupRoot();
        $business = new Num006FailingProductMutation($this->db);
        $operation = new OperationContext(null, new EvaluationTime(new DateTimeImmutable('2026-10-06 04:05:06', new DateTimeZone('UTC'))), 'num006-rollback', true);
        try {
            (new AutoNumberService(adapters: new ResourceIdentifierAdapterRegistry(db: $this->db), db: $this->db))->issue(
                'product', new EvaluationOrganizationContext((int) $group['id'], 'GROUP', 'AUTHORITATIVE'),
                $operation, $business, (string) $this->db->table('auto_numbering_series')->where('id', $seriesId)->get()->getRowArray()['series_code'],
            );
            $this->fail('Expected business mutation failure.');
        } catch (DomainException $exception) {
            $this->assertSame('NUM_COUNTER_TRANSACTION_FAILED', $exception->getErrorCode());
        }
        $this->assertSame(0, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->countAllResults());
    }

    private function insertProduct(string $code, string $status): int
    {
        $this->db->table('products')->insert($this->product($code, $status));
        return (int) $this->db->insertID();
    }

    /** @return array<string, int|string> */
    private function product(string $code, string $status = 'ACTIVE', ?string $sku = null, ?string $barcode = null): array
    {
        $product = [
            'code' => $code, 'product_type' => 'STANDARD', 'serial_management_enabled' => 0,
            'status' => $status, 'created_at' => '2026-10-06 04:05:06',
        ];
        if ($sku !== null) {
            $product['sku'] = $sku;
        }
        if ($barcode !== null) {
            $product['barcode'] = $barcode;
        }
        return $product;
    }

    private function productResource(): int
    {
        $row = $this->db->table('resources')->where('type', 'product')->get()->getRowArray();
        if ($row !== null) {
            return (int) $row['id'];
        }
        $this->db->table('resources')->insert(['type' => 'product', 'name' => 'Product', 'domain' => 'MASTER', 'created_at' => '2026-10-06 04:05:06']);
        return (int) $this->db->insertID();
    }

    /** @return array<string, int|string> */
    private function seriesPayload(int $resourceId, string $scopeType, string $pattern): array
    {
        return [
            'resource_id' => $resourceId, 'series_code' => 'NUM006-CFG-' . strtoupper(bin2hex(random_bytes(3))),
            'name' => 'Product compatibility', 'scope_type' => $scopeType, 'pattern' => $pattern,
            'reset_policy' => 'NEVER', 'generation_mode' => 'AUTO_REQUIRED', 'start_number' => 1,
            'increment_by' => 1, 'is_default' => 0, 'status' => 'ACTIVE',
        ];
    }

    /** @return array<string, mixed> */
    private function groupRoot(): array
    {
        $row = $this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null)->get()->getRowArray();
        if ($row === null) {
            $this->db->table('organizations')->insert(['type' => 'GROUP', 'code' => 'NUM006-GROUP-' . uniqid(), 'name' => 'NUM006', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
            $row = $this->db->table('organizations')->where('id', $this->db->insertID())->get()->getRowArray();
        }
        if ($row === null) {
            throw new RuntimeException('Product test group root missing.');
        }
        return $row;
    }

    private function context(int $organizationId, string $scopeType): NumberingScopePeriodContext
    {
        return new NumberingScopePeriodContext($scopeType, $organizationId, 'ALL', null, null, null, null, 'NUM006', 'product');
    }
}

final class Num006ProductMutation implements AutoNumberingBusinessMutation
{
    /** @var list<string> */
    public array $persisted = [];
    public function __construct(private readonly CodeIgniter\Database\ConnectionInterface $db)
    {
    }
    public function isIdentifierOccupied(string $identifier): bool
    {
        return false;
    }
    public function persist(string $identifier): void
    {
        $this->db->table('products')->insert(['code' => $identifier, 'product_type' => 'STANDARD', 'serial_management_enabled' => 0, 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $this->persisted[] = $identifier;
    }
}

final class Num006FailingProductMutation implements AutoNumberingBusinessMutation
{
    public function __construct(private readonly CodeIgniter\Database\ConnectionInterface $db)
    {
    }
    public function isIdentifierOccupied(string $identifier): bool
    {
        return false;
    }
    public function persist(string $identifier): void
    {
        $this->db->table('products')->insert(['code' => $identifier, 'product_type' => 'STANDARD', 'serial_management_enabled' => 0, 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        throw new RuntimeException('forced product rollback');
    }
}

final class Num006AllowAuthorization implements NumberingAuthorizationPort
{
    public function authorize(OperationContext $operation, string $resourceType, string $action, EvaluationOrganizationRequest $contextRequest, mixed $stateId = null): AuthorizationDecision
    {
        return new AuthorizationDecision('ALLOW', 'AUTH_POLICY_ALLOWED', $operation->actorUserId ?? 1, $resourceType, $action, 1, null, $operation->evaluationTime, [], [], [], [], $operation->requestId);
    }
}
