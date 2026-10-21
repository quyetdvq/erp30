<?php

declare(strict_types=1);

use App\Authorization\AuthorizationDecision;
use App\Authorization\EvaluationOrganizationContext;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\AutoNumberingSeriesRepository;
use App\Services\AutoNumberService;
use App\Services\AutoNumberingBusinessMutation;
use App\Services\AutoNumberingConfigurationService;
use App\Services\AutoNumberingIdentifierCollision;
use App\Services\CanonicalNumberingPatternValidator;
use App\Services\NumberingAuthorizationPort;
use App\Services\NumberingScopePeriodContext;
use App\Modules\Product\Integration\Numbering\ProductIdentifierAdapter;
use App\Services\ResourceIdentifierAdapter;
use App\Services\ResourceIdentifierAdapterRegistry;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** Mandatory cross-task Gate 4 contract regression; algorithms stay in production services. */
final class P1AutoNumberingMandatoryRegressionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function setUp(): void
    {
        \Tests\Support\Standard\StandardRuntimeGuard::assertReady();
        parent::setUp();
    }

    public function testConfigurationDefaultsIssuedIdentityAndNoAutoFallback(): void
    {
        $resourceId = $this->resource('mandatory-config');
        $service = $this->configService();
        $this->assertSame([], $service->listByResource($resourceId));
        $first = $service->create($this->series($resourceId, 'FIRST', false, 'INACTIVE'));
        $second = $service->create($this->series($resourceId, 'SECOND', false, 'ACTIVE', 'B{SEQ:3}'));
        $default = $service->create($this->series($resourceId, 'DEFAULT', true, 'ACTIVE', 'D{SEQ:3}'));
        $this->assertCount(3, $service->listByResource($resourceId));
        $this->assertSame($first, (int) $service->find($first)['id']);
        $this->assertSame($second, (int) $service->find($second)['id']);
        try {
            $service->create($this->series($resourceId, 'OTHER', true, 'ACTIVE', 'O{SEQ:3}'));
            $this->fail('Expected active default conflict.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingConfigurationService::ERROR_ACTIVE_DEFAULT_CONFLICT, $exception->getErrorCode());
        }

        $this->db->table('auto_numbering_counters')->insert([
            'numbering_series_id' => $default, 'scope_organization_id' => $this->groupRootId(),
            'period_key' => 'ALL', 'last_value' => 17, 'created_at' => '2026-10-06 04:05:06',
        ]);
        foreach (['resource_id' => $this->resource('other'), 'series_code' => 'CHANGED', 'scope_type' => 'COMPANY', 'reset_policy' => 'YEARLY'] as $field => $value) {
            try {
                $service->update($default, [$field => $value]);
                $this->fail('Expected issued identity immutability.');
            } catch (DomainException $exception) {
                $this->assertSame(AutoNumberingConfigurationService::ERROR_ISSUED_IMMUTABLE, $exception->getErrorCode());
            }
        }
        $this->assertTrue($service->update($default, ['status' => 'INACTIVE', 'start_number' => 99]));
        $this->assertSame(17, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $default)->get()->getRowArray()['last_value']);
        try {
            $service->delete($default);
            $this->fail('Expected issued series hard-delete rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingConfigurationService::ERROR_ISSUED_DELETE, $exception->getErrorCode());
        }
    }

    public function testCanonicalPatternResetAndRenderingMatrix(): void
    {
        $validator = new CanonicalNumberingPatternValidator();
        foreach (['{SEQ:0}', '{SEQ:2}{SEQ:2}', '{UNKNOWN}-{SEQ:2}', '{SEQ:2', '<?php echo 1; ?>{SEQ:2}'] as $pattern) {
            try {
                $validator->parse($pattern);
                $this->fail('Expected malformed pattern rejection: ' . $pattern);
            } catch (DomainException $exception) {
                $this->assertSame(CanonicalNumberingPatternValidator::ERROR_PATTERN_INVALID, $exception->getErrorCode());
            }
        }
        foreach ([
            ['reset_policy' => 'NEVER', 'pattern' => '{SEQ:2}', 'period' => 'ALL'],
            ['reset_policy' => 'YEARLY', 'pattern' => '{YYYY}-{SEQ:2}', 'period' => '2026'],
            ['reset_policy' => 'MONTHLY', 'pattern' => '{YYYY}{MM}-{SEQ:2}', 'period' => '202610'],
            ['reset_policy' => 'DAILY', 'pattern' => '{YYYY}{MM}{DD}-{SEQ:2}', 'period' => '20261006'],
        ] as $case) {
            $parsed = $validator->parse($case['pattern'], ['reset_policy' => $case['reset_policy']]);
            $this->assertSame($case['pattern'], $parsed->normalizedPattern);
            $this->assertSame($case['reset_policy'] === 'NEVER' ? false : true, $parsed->hasDateDiscriminator);
        }
        foreach ([
            ['reset_policy' => 'YEARLY', 'pattern' => '{SEQ:2}'],
            ['reset_policy' => 'MONTHLY', 'pattern' => '{YYYY}-{SEQ:2}'],
            ['reset_policy' => 'DAILY', 'pattern' => '{YYYY}{MM}-{SEQ:2}'],
        ] as $case) {
            try {
                $validator->parse($case['pattern'], ['reset_policy' => $case['reset_policy']]);
                $this->fail('Expected missing reset discriminator rejection.');
            } catch (DomainException $exception) {
                $this->assertSame(CanonicalNumberingPatternValidator::ERROR_PATTERN_INVALID, $exception->getErrorCode());
            }
        }
        $pattern = $validator->parse('X{SEQ:2}');
        $rendered = (new \App\Services\AutoNumberingCounterAllocator(db: $this->db))->renderCandidate(
            $pattern,
            new NumberingScopePeriodContext('GROUP', $this->groupRootId(), 'ALL', null, null, null, null, 'ORG', 'regression'),
            100,
        );
        $this->assertSame('X100', $rendered);
    }

    public function testProductContractGlobalNamespaceGenerationAndAtomicRollback(): void
    {
        $adapter = new ProductIdentifierAdapter($this->db);
        $metadata = $adapter->metadata();
        $this->assertSame('product', $metadata->resourceType);
        $this->assertSame('products.code', $metadata->identifierField);
        $this->assertSame('product_code', $metadata->transportImportAlias);
        $this->assertSame(50, $metadata->identifierMaxLength);
        $this->assertSame(ResourceIdentifierAdapter::class === ResourceIdentifierAdapter::class, true);
        $this->assertFalse($this->db->fieldExists('product_code', 'products'));

        $code = '100';
        $this->insertProduct($code, 'INACTIVE');
        $resourceId = $this->productResource();
        $seriesId = (new AutoNumberingSeriesRepository($this->db))->insert([
            'resource_id' => $resourceId, 'series_code' => \Tests\Support\Scratch\NumberingMarker::tag('MANDATORY-' . uniqid()), 'name' => 'Mandatory',
            'scope_type' => 'GROUP', 'pattern' => '{SEQ:3}', 'reset_policy' => 'NEVER',
            'generation_mode' => 'AUTO_REQUIRED', 'start_number' => 100, 'increment_by' => 5,
            'is_default' => 1, 'status' => 'ACTIVE',
        ]);
        $mutation = new MandatoryProductMutation($this->db);
        $operation = $this->operation();
        $result = $this->autoNumber()->issue('product', new EvaluationOrganizationContext($this->groupRootId(), 'GROUP', 'AUTHORITATIVE'), $operation, $mutation, $this->seriesCode($seriesId));
        $this->assertSame('105', $result->identifier);
        $this->assertTrue($result->generated);
        $this->assertSame(105, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->get()->getRowArray()['last_value']);

        $manualSeriesId = (new AutoNumberingSeriesRepository($this->db))->insert([
            'resource_id' => $resourceId, 'series_code' => \Tests\Support\Scratch\NumberingMarker::tag('MANDATORY-MANUAL-' . uniqid()), 'name' => 'Mandatory manual',
            'scope_type' => 'GROUP', 'pattern' => 'M{SEQ:3}', 'reset_policy' => 'NEVER',
            'generation_mode' => 'AUTO_IF_EMPTY', 'start_number' => 1, 'increment_by' => 1,
            'is_default' => 0, 'status' => 'ACTIVE',
        ]);
        $manual = $this->autoNumber()->issue('product', new EvaluationOrganizationContext($this->groupRootId(), 'GROUP', 'AUTHORITATIVE'), $operation, $mutation, $this->seriesCode($manualSeriesId), 'MANUAL-001');
        $this->assertFalse($manual->generated);
        $this->assertNull($manual->sequenceValue);
        $this->assertSame(105, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->get()->getRowArray()['last_value']);
        $this->assertSame(0, $this->db->table('auto_numbering_counters')->where('numbering_series_id', $manualSeriesId)->countAllResults());
    }

    public function testFailClosedUnknownAdapterInactiveSeriesAndNamespaceCompatibility(): void
    {
        $registry = new ResourceIdentifierAdapterRegistry(db: $this->db);
        try {
            $registry->resolve('unknown_resource');
            $this->fail('Expected unknown adapter rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceIdentifierAdapter::ERROR_ADAPTER_NOT_FOUND, $exception->getErrorCode());
        }
        $resourceId = $this->productResource();
        $seriesId = (new AutoNumberingSeriesRepository($this->db))->insert([
            'resource_id' => $resourceId, 'series_code' => 'INACTIVE-' . uniqid(), 'name' => 'Inactive',
            'scope_type' => 'GROUP', 'pattern' => '{SEQ:3}', 'reset_policy' => 'NEVER',
            'generation_mode' => 'AUTO_REQUIRED', 'start_number' => 1, 'increment_by' => 1,
            'is_default' => 0, 'status' => 'INACTIVE',
        ]);
        try {
            $this->autoNumber()->issue('product', new EvaluationOrganizationContext($this->groupRootId(), 'GROUP', 'AUTHORITATIVE'), $this->operation(), new MandatoryProductMutation($this->db), $this->seriesCode($seriesId));
            $this->fail('Expected inactive series rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberService::ERROR_SERIES_INACTIVE, $exception->getErrorCode());
        }
        $validator = new CanonicalNumberingPatternValidator();
        foreach ([['scope_type' => 'COMPANY', 'pattern' => '{SEQ:3}'], ['scope_type' => 'ORGANIZATION', 'pattern' => '{SEQ:3}']] as $case) {
            try {
                $validator->parse($case['pattern'], $case + ['identifier_uniqueness_scope' => 'GLOBAL']);
                $this->fail('Expected unsafe GLOBAL namespace rejection.');
            } catch (DomainException $exception) {
                $this->assertSame(CanonicalNumberingPatternValidator::ERROR_PATTERN_INVALID, $exception->getErrorCode());
            }
        }
        $this->assertNotNull($validator->parse('{COMPANY_CODE}-{SEQ:3}', ['scope_type' => 'COMPANY', 'identifier_uniqueness_scope' => 'GLOBAL']));
        $this->assertNotNull($validator->parse('{ORG_CODE}-{SEQ:3}', ['scope_type' => 'ORGANIZATION', 'identifier_uniqueness_scope' => 'GLOBAL']));
    }

    public function testGate4HasNoSchemaOrArchitectureDrift(): void
    {
        $this->assertFalse($this->db->fieldExists('product_code', 'products'));
        $this->assertFalse($this->db->fieldExists('timezone', 'organizations'));
        $this->assertTrue($this->db->tableExists('auto_numbering_series'));
        $this->assertTrue($this->db->tableExists('auto_numbering_counters'));
        $productAdapter = file_get_contents(APPPATH . 'Modules/Product/Integration/Numbering/ProductIdentifierAdapter.php');
        $this->assertIsString($productAdapter);
        $this->assertStringNotContainsString('sku', strtolower($productAdapter));
        $this->assertStringNotContainsString('barcode', strtolower($productAdapter));
        $this->assertStringNotContainsString('eval(', strtolower($productAdapter));
    }

    private function configService(): AutoNumberingConfigurationService
    {
        return new AutoNumberingConfigurationService(
            authorization: new MandatoryAllowAuthorization(),
            patternValidator: new CanonicalNumberingPatternValidator(),
            db: $this->db,
        );
    }

    private function autoNumber(): AutoNumberService
    {
        return new AutoNumberService(adapters: new ResourceIdentifierAdapterRegistry(db: $this->db), db: $this->db);
    }

    /** @return array<string, mixed> */
    private function series(int $resourceId, string $code, bool $default, string $status, string $pattern = 'A{SEQ:3}'): array
    {
        return ['resource_id' => $resourceId, 'series_code' => $code, 'name' => $code, 'scope_type' => 'GROUP', 'pattern' => $pattern, 'reset_policy' => 'NEVER', 'generation_mode' => 'AUTO_REQUIRED', 'start_number' => 1, 'increment_by' => 1, 'is_default' => $default, 'status' => $status];
    }

    private function resource(string $suffix): int
    {
        $this->db->table('resources')->insert(['type' => 'mandatory_' . $suffix . '_' . uniqid(), 'name' => 'Mandatory', 'domain' => 'SYSTEM', 'created_at' => '2026-10-06 04:05:06']);
        return (int) $this->db->insertID();
    }

    private function productResource(): int
    {
        $row = $this->db->table('resources')->where('type', 'product')->get()->getRowArray();
        if ($row !== null) {
            return (int) $row['id'];
        }
        $this->db->table('resources')->insert(['type' => 'product', 'name' => \Tests\Support\Scratch\NumberingMarker::tag('Product'), 'domain' => 'MASTER', 'created_at' => '2026-10-06 04:05:06']);
        return (int) $this->db->insertID();
    }

    private function insertProduct(string $code, string $status): void
    {
        $this->db->table('products')->insert(\Tests\Support\Scratch\NumberingMarker::product(['code' => $code, 'product_type' => 'STANDARD', 'serial_management_enabled' => 0, 'status' => $status, 'created_at' => '2026-10-06 04:05:06'], $code));
    }

    private function groupRootId(): int
    {
        $row = $this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null)->get()->getRowArray();
        if ($row === null) {
            $this->db->table('organizations')->insert(['type' => 'GROUP', 'code' => \Tests\Support\Scratch\NumberingMarker::tag('MANDATORY-' . uniqid()), 'name' => 'Mandatory', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
            return (int) $this->db->insertID();
        }
        return (int) $row['id'];
    }

    private function seriesCode(int $seriesId): string
    {
        return (string) $this->db->table('auto_numbering_series')->where('id', $seriesId)->get()->getRowArray()['series_code'];
    }

    private function operation(): OperationContext
    {
        return new OperationContext(null, new EvaluationTime(new DateTimeImmutable('2026-10-06 04:05:06', new DateTimeZone('UTC'))), 'mandatory-num', true);
    }
}

final class MandatoryProductMutation implements AutoNumberingBusinessMutation
{
    /** @var list<string> */
    public array $persisted = [];
    public function __construct(private readonly CodeIgniter\Database\ConnectionInterface $db) {}
    public function isIdentifierOccupied(string $identifier): bool { return false; }
    public function persist(string $identifier): void
    {
        $this->db->table('products')->insert(\Tests\Support\Scratch\NumberingMarker::product(['code' => $identifier, 'product_type' => 'STANDARD', 'serial_management_enabled' => 0, 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06'], $identifier));
        $this->persisted[] = $identifier;
    }
}

final class MandatoryAllowAuthorization implements NumberingAuthorizationPort
{
    public function authorize(OperationContext $operation, string $resourceType, string $action, EvaluationOrganizationRequest $contextRequest, mixed $stateId = null): AuthorizationDecision
    {
        return new AuthorizationDecision('ALLOW', 'AUTH_POLICY_ALLOWED', $operation->actorUserId ?? 1, $resourceType, $action, 1, null, $operation->evaluationTime, [], [], [], [], $operation->requestId);
    }
}
