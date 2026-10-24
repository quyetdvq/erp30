<?php

declare(strict_types=1);

use App\Bootstrap\OrganizationFactory;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Modules\Organization\Application\Ports\OrganizationRepositoryInterface;
use App\Repositories\OrganizationRepository;
use App\Services\OrganizationService;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ResultInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal No DatabaseTestTrait and no real connection is opened. */
final class BeReorg002OrganizationCompatibilityTest extends TestCase
{
    public function testLegacyPublicSignaturesAndConstantsMatchCheckpoint001B(): void
    {
        $expected = json_decode((string) file_get_contents(
            dirname(__DIR__) . '/_support/Reorg/legacy_organization_contracts.json',
        ), true, 512, JSON_THROW_ON_ERROR);
        foreach ($expected as $class => $contract) {
            $reflection = new ReflectionClass($class);
            $constants = [];
            foreach ($reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) {
                $constants[$constant->getName()] = $constant->getValue();
            }
            ksort($constants);
            $methods = [];
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $parameters = [];
                foreach ($method->getParameters() as $parameter) {
                    $parameters[] = [
                        'name' => $parameter->getName(), 'type' => (string) $parameter->getType(),
                        'optional' => $parameter->isOptional(), 'has_default' => $parameter->isDefaultValueAvailable(),
                        'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                        'by_reference' => $parameter->isPassedByReference(), 'variadic' => $parameter->isVariadic(),
                    ];
                }
                $methods[$method->getName()] = [
                    'static' => $method->isStatic(), 'return_type' => (string) $method->getReturnType(),
                    'returns_reference' => $method->returnsReference(), 'parameters' => $parameters,
                ];
            }
            ksort($methods);
            $this->assertSame($contract, ['final' => $reflection->isFinal(), 'constants' => $constants, 'methods' => $methods], $class);
        }
    }

    public function testRepositoryPortCoversExactlyTheLegacyRepositoryMethods(): void
    {
        $port = new ReflectionClass(OrganizationRepositoryInterface::class);
        $names = array_map(static fn (ReflectionMethod $method): string => $method->getName(), $port->getMethods());
        sort($names);
        $this->assertSame(['find', 'findAll', 'findByCode', 'insert', 'provinceExists', 'update', 'wardBelongsToProvince', 'wardExists'], $names);
        foreach (self::repositories() as [$class]) {
            $this->assertTrue(is_a($class, OrganizationRepositoryInterface::class, true));
        }
    }

    public function testFactoryHonorsSuppliedPortAndNeverRebindsItsReads(): void
    {
        $row = ['id' => 17, 'parent_id' => null, 'type' => 'COMPANY', 'status' => 'ACTIVE'];
        $port = $this->createMock(OrganizationRepositoryInterface::class);
        $port->expects($this->once())->method('find')->with(17)->willReturn($row);
        $db = $this->createMock(BaseConnection::class);
        $db->expects($this->never())->method('table');
        $this->assertSame($row, OrganizationFactory::service(repository: $port, db: $db)->resolveCompanyOwner('17'));
    }

    public function testLegacyNamedArgumentsPreserveExplicitRepositoryConnection(): void
    {
        $row = ['id' => 18, 'parent_id' => null, 'type' => 'COMPANY', 'status' => 'ACTIVE'];
        $result = $this->createMock(ResultInterface::class);
        $result->method('getRowArray')->willReturn($row);
        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('where')->willReturnSelf();
        $builder->method('get')->willReturn($result);
        $repositoryDb = $this->createMock(BaseConnection::class);
        $repositoryDb->expects($this->once())->method('table')->with('organizations')->willReturn($builder);
        $serviceDb = $this->createMock(BaseConnection::class);
        $serviceDb->expects($this->never())->method('table');
        $service = new OrganizationService(repository: new OrganizationRepository(db: $repositoryDb), db: $serviceDb);
        $this->assertSame([$row], $service->resolveAncestorChain(18));
    }

    public function testFactoryReturnsFreshServicesAndRepositoriesWithoutOpeningTheConnection(): void
    {
        $db = $this->createMock(BaseConnection::class);
        $db->expects($this->never())->method('table');
        $this->assertNotSame(OrganizationFactory::service(db: $db), OrganizationFactory::service(db: $db));
        $this->assertNotSame(OrganizationFactory::repository(db: $db), OrganizationFactory::repository(db: $db));
    }

    /** @return array<string,array{class-string}> */
    public static function repositories(): array
    {
        return [
            'legacy' => [OrganizationRepository::class],
            'module' => [App\Modules\Organization\Infrastructure\Persistence\SqlOrganizationRepository::class],
        ];
    }

    #[DataProvider('repositories')]
    public function testReadFailureIsNotConvertedToMissingRecord(string $class): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('where')->willReturnSelf();
        $builder->method('get')->willReturn(false);
        $db = $this->createMock(BaseConnection::class);
        $db->method('table')->willReturn($builder);
        try {
            (new $class(db: $db))->find(77);
            $this->fail('Persistence failure must not be treated as a missing organization.');
        } catch (PersistenceException $exception) {
            $this->assertSame('ORG_PERSISTENCE_FAILED', $exception->getErrorCode());
            $this->assertSame(['operation' => 'find'], $exception->getContext());
        }
    }

    #[DataProvider('repositories')]
    public function testInvalidPayloadsFailBeforeDatabaseAccess(string $class): void
    {
        $db = $this->createMock(BaseConnection::class);
        $db->expects($this->never())->method('table');
        $repo = new $class(db: $db);
        foreach ([
            ['insert', ['type' => 'GROUP'], 'ORG_FIELD_REQUIRED', ['field' => 'code']],
            ['update', [], 'ORG_UPDATE_EMPTY', ['operation' => 'update']],
            ['update', ['unknown_column' => 1], 'ORG_FIELD_UNSUPPORTED', ['field' => 'unknown_column']],
        ] as [$method, $data, $code, $context]) {
            try {
                $method === 'insert' ? $repo->insert($data) : $repo->update(77, $data);
                $this->fail('Invalid payload was accepted.');
            } catch (DomainException $exception) {
                $this->assertSame($code, $exception->getErrorCode());
                $this->assertSame($context, $exception->getContext());
            }
        }
    }
}
