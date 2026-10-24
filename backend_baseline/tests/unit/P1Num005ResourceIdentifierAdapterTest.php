<?php

declare(strict_types=1);

use App\Authorization\EvaluationOrganizationContext;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\AutoNumberingSeriesRepository;
use App\Services\AutoNumberService;
use App\Services\AutoNumberingBusinessMutation;
use App\Services\AutoNumberingIdentifierCollision;
use App\Services\NumberingScopePeriodContext;
use App\Services\ResourceIdentifierAdapter;
use App\Services\ResourceIdentifierAdapterRegistry;
use App\Services\ResourceIdentifierMetadata;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Num005ResourceIdentifierAdapterTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function setUp(): void
    {
        \Tests\Support\Standard\StandardRuntimeGuard::assertReady();
        parent::setUp();
    }

    public function testMetadataAcceptsEveryCanonicalNamespaceAndRejectsUnsafeValues(): void
    {
        foreach (['GLOBAL', 'COMPANY', 'ORGANIZATION'] as $scope) {
            $metadata = new ResourceIdentifierMetadata('fake_' . strtolower($scope), 'records.code', 20, $scope);
            $this->assertSame($scope, $metadata->identifierUniquenessScope);
        }

        foreach ([
            ['', 'records.code', 20, 'GLOBAL'],
            ['fake', '', 20, 'GLOBAL'],
            ['fake', 'records.code', 0, 'GLOBAL'],
            ['fake', 'records.code', 20, 'TENANT'],
        ] as $input) {
            try {
                new ResourceIdentifierMetadata($input[0], $input[1], $input[2], $input[3]);
                $this->fail('Expected metadata rejection.');
            } catch (DomainException $exception) {
                $this->assertSame(ResourceIdentifierAdapter::ERROR_METADATA_INVALID, $exception->getErrorCode());
            }
        }
    }

    public function testRegistryIsExplicitDeterministicAndFailsClosed(): void
    {
        $registry = new ResourceIdentifierAdapterRegistry([
            'zeta' => new Num005FakeAdapter(new ResourceIdentifierMetadata('zeta', 'records.code', 20, 'GLOBAL')),
            'alpha' => new Num005FakeAdapter(new ResourceIdentifierMetadata('alpha', 'records.code', 20, 'COMPANY')),
        ]);
        $this->assertSame(['alpha', 'zeta'], $registry->resourceTypes());
        $this->assertTrue($registry->has('alpha'));
        $this->expectExceptionMessage(ResourceIdentifierAdapter::ERROR_ADAPTER_NOT_FOUND);
        $registry->resolve('missing');
    }

    public function testDuplicateAndKeyMismatchRegistrationAreRejected(): void
    {
        $adapter = new Num005FakeAdapter(new ResourceIdentifierMetadata('fake', 'records.code', 20, 'GLOBAL'));
        $registry = new ResourceIdentifierAdapterRegistry([$adapter]);
        try {
            $registry->register($adapter);
            $this->fail('Expected duplicate registration rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceIdentifierAdapter::ERROR_ADAPTER_REGISTRATION_INVALID, $exception->getErrorCode());
        }

        try {
            new ResourceIdentifierAdapterRegistry(['other' => $adapter]);
            $this->fail('Expected keyed registration rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceIdentifierAdapter::ERROR_ADAPTER_REGISTRATION_INVALID, $exception->getErrorCode());
        }
    }

    public function testFacadeUsesCanonicalSeriesAndManualAutoIfEmptyWithoutCounter(): void
    {
        $resourceType = 'num005_' . strtolower(bin2hex(random_bytes(4)));
        $this->db->table('resources')->insert(['type' => $resourceType, 'name' => \Tests\Support\Scratch\NumberingMarker::tag('NUM005'), 'domain' => 'SYSTEM', 'created_at' => '2026-10-06 04:05:06']);
        $resourceId = (int) $this->db->insertID();
        $group = $this->groupRoot();
        $seriesId = (new AutoNumberingSeriesRepository($this->db))->insert([
            'resource_id' => $resourceId, 'series_code' => \Tests\Support\Scratch\NumberingMarker::tag('DEFAULT'), 'name' => 'Default',
            'scope_type' => 'GROUP', 'pattern' => '{SEQ:3}', 'reset_policy' => 'NEVER',
            'generation_mode' => 'AUTO_IF_EMPTY', 'start_number' => 1, 'increment_by' => 1,
            'is_default' => 1, 'status' => 'ACTIVE',
        ]);
        $adapter = new Num005FakeAdapter(new ResourceIdentifierMetadata($resourceType, 'records.code', 20, 'GLOBAL'));
        $business = new Num005FakeBusiness();
        $service = new AutoNumberService(
            adapters: new ResourceIdentifierAdapterRegistry([$adapter]),
            db: $this->db,
        );
        $operation = new OperationContext(null, new EvaluationTime(new DateTimeImmutable('2026-10-06 04:05:06', new DateTimeZone('UTC'))), 'num005-test', true);
        $manual = $service->issue($resourceType, new EvaluationOrganizationContext((int) $group['id'], 'GROUP', 'AUTHORITATIVE'), $operation, $business, null, 'MANUAL');

        $this->assertFalse($manual->generated);
        $this->assertNull($manual->seriesId);
        $this->assertSame('MANUAL', $manual->identifier);
        $this->assertSame(['MANUAL'], $business->persisted);
        $this->assertSame(0, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->countAllResults());
    }

    public function testFacadeRejectsOversizedGeneratedCandidateBeforeMutation(): void
    {
        $resourceType = \Tests\Support\Scratch\NumberingMarker::resourceType('num005_length_' . strtolower(bin2hex(random_bytes(4))));
        $this->db->table('resources')->insert(['type' => $resourceType, 'name' => \Tests\Support\Scratch\NumberingMarker::tag('NUM005'), 'domain' => 'SYSTEM', 'created_at' => '2026-10-06 04:05:06']);
        $resourceId = (int) $this->db->insertID();
        $group = $this->groupRoot();
        $seriesId = (new AutoNumberingSeriesRepository($this->db))->insert([
            'resource_id' => $resourceId, 'series_code' => \Tests\Support\Scratch\NumberingMarker::tag('DEFAULT'), 'name' => 'Default',
            'scope_type' => 'GROUP', 'pattern' => 'X{SEQ:3}', 'reset_policy' => 'NEVER',
            'generation_mode' => 'AUTO_REQUIRED', 'start_number' => 10000, 'increment_by' => 1,
            'is_default' => 1, 'status' => 'ACTIVE',
        ]);
        $adapter = new Num005FakeAdapter(new ResourceIdentifierMetadata($resourceType, 'records.code', 4, 'GLOBAL'));
        $business = new Num005FakeBusiness();
        $service = new AutoNumberService(adapters: new ResourceIdentifierAdapterRegistry([$adapter]), db: $this->db);
        $operation = new OperationContext(null, new EvaluationTime(new DateTimeImmutable('2026-10-06 04:05:06', new DateTimeZone('UTC'))), 'num005-length', true);
        try {
            $service->issue($resourceType, new EvaluationOrganizationContext((int) $group['id'], 'GROUP', 'AUTHORITATIVE'), $operation, $business);
            $this->fail('Expected generated length rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceIdentifierAdapter::ERROR_IDENTIFIER_TOO_LONG, $exception->getErrorCode());
        }
        $this->assertSame([], $business->persisted);
        $this->assertSame(0, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->countAllResults());
    }

    public function testFacadeDelegatesGeneratedCandidateAndAuthoritativeScopeToAdapter(): void
    {
        $resourceType = \Tests\Support\Scratch\NumberingMarker::resourceType('num005_generated_' . strtolower(bin2hex(random_bytes(4))));
        $this->db->table('resources')->insert(['type' => $resourceType, 'name' => \Tests\Support\Scratch\NumberingMarker::tag('NUM005'), 'domain' => 'SYSTEM', 'created_at' => '2026-10-06 04:05:06']);
        $resourceId = (int) $this->db->insertID();
        $group = $this->groupRoot();
        (new AutoNumberingSeriesRepository($this->db))->insert([
            'resource_id' => $resourceId, 'series_code' => \Tests\Support\Scratch\NumberingMarker::tag('DEFAULT'), 'name' => 'Default',
            'scope_type' => 'GROUP', 'pattern' => '{SEQ:3}', 'reset_policy' => 'NEVER',
            'generation_mode' => 'AUTO_REQUIRED', 'start_number' => 7, 'increment_by' => 1,
            'is_default' => 1, 'status' => 'ACTIVE',
        ]);
        $adapter = new Num005FakeAdapter(new ResourceIdentifierMetadata($resourceType, 'records.code', 20, 'GLOBAL'));
        $business = new Num005FakeBusiness();
        $service = new AutoNumberService(adapters: new ResourceIdentifierAdapterRegistry([$adapter]), db: $this->db);
        $operation = new OperationContext(null, new EvaluationTime(new DateTimeImmutable('2026-10-06 04:05:06', new DateTimeZone('UTC'))), 'num005-generated', true);
        $result = $service->issue($resourceType, new EvaluationOrganizationContext((int) $group['id'], 'GROUP', 'AUTHORITATIVE'), $operation, $business);

        $this->assertTrue($result->generated);
        $this->assertSame(7, $result->sequenceValue);
        $this->assertSame('007', $result->identifier);
        $this->assertSame(['007'], $business->persisted);
        $this->assertNotNull($adapter->lastContext);
        $this->assertSame((int) $group['id'], $adapter->lastContext->scopeOrganizationId);
        $this->assertSame('ALL', $adapter->lastContext->periodKey);
    }

    /** @return array<string, mixed> */
    private function groupRoot(): array
    {
        $group = $this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null)->get()->getRowArray();
        if ($group !== null) {
            return $group;
        }
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'code' => \Tests\Support\Scratch\NumberingMarker::tag('NUM005-GROUP-' . uniqid()), 'name' => 'NUM005 Group',
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);
        $group = $this->db->table('organizations')->where('id', $this->db->insertID())->get()->getRowArray();
        if ($group === null) {
            throw new RuntimeException('NUM005 group fixture failed.');
        }
        return $group;
    }
}

final class Num005FakeAdapter implements ResourceIdentifierAdapter
{
    /** @var list<string> */
    public array $occupied = [];
    public ?NumberingScopePeriodContext $lastContext = null;
    public function __construct(private readonly ResourceIdentifierMetadata $metadata)
    {
    }
    public function metadata(): ResourceIdentifierMetadata
    {
        return $this->metadata;
    }
    public function isIdentifierOccupied(string $identifier, NumberingScopePeriodContext $context): bool
    {
        $this->lastContext = $context;
        return in_array($identifier, $this->occupied, true);
    }
    public function validateManualIdentifier(string $identifier, NumberingScopePeriodContext $context): void
    {
        if ($identifier === '') {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_IDENTIFIER_INVALID);
        }
    }
    public function isIdentifierCollision(Throwable $exception): bool
    {
        return $exception instanceof Num005CanonicalCollision;
    }
}

final class Num005FakeBusiness implements AutoNumberingBusinessMutation
{
    /** @var list<string> */
    public array $persisted = [];
    public function isIdentifierOccupied(string $identifier): bool
    {
        return false;
    }
    public function persist(string $identifier): void
    {
        $this->persisted[] = $identifier;
    }
}

final class Num005CanonicalCollision extends RuntimeException
{
}
