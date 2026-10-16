<?php

declare(strict_types=1);

use App\Bootstrap\FoundationFactory;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Integration\Foundation\LocationLifecycleAdapter;
use App\Modules\Foundation\Application\Ports\LocationRepositoryInterface;
use App\Modules\Foundation\Contracts\LocationErrors;
use App\Modules\Foundation\Infrastructure\Persistence\SqlLocationRepository;
use App\Repositories\FoundationLocationRepository;
use App\Services\FoundationLocationLifecycleAdapter;
use App\Services\FoundationLocationService;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal No DatabaseTestTrait: contract and failure-path tests only. */
final class BeReorg001FoundationCompatibilityTest extends TestCase
{
    public function testLegacyPublicSignaturesAndConstantsMatchThePristineArchive(): void
    {
        $file = dirname(__DIR__) . '/_support/Reorg/legacy_foundation_contracts.json';
        $expected = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
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

    public function testBothRepositoryEntrypointsImplementTheFoundationPort(): void
    {
        $this->assertTrue(is_a(FoundationLocationRepository::class, LocationRepositoryInterface::class, true));
        $this->assertTrue(is_a(SqlLocationRepository::class, LocationRepositoryInterface::class, true));
        $this->assertSame('WF_TRANSITION_REQUIRED', LocationErrors::ERROR_TRANSITION_REQUIRED);
        $this->assertSame(FoundationLocationService::ERROR_CODE_DUPLICATE, LocationErrors::ERROR_CODE_DUPLICATE);
    }

    public function testFactoryUsesTheSuppliedPortWithoutRebindingItToTheConnection(): void
    {
        $row = ['id' => 7, 'code' => 'EXPLICIT', 'name' => 'Explicit port', 'status' => 'ACTIVE'];
        $repository = $this->createMock(LocationRepositoryInterface::class);
        $repository->expects($this->once())->method('find')->with('provinces', 7)->willReturn($row);
        $db = $this->createMock(BaseConnection::class);
        $db->expects($this->never())->method('table');
        $this->assertSame($row, FoundationFactory::service(repository: $repository, db: $db)->province(7));
    }

    public function testLegacyServiceHonorsAnExplicitRepositoryEvenWithAnotherConnection(): void
    {
        $row = ['id' => 7, 'code' => 'EXPLICIT', 'name' => 'Explicit repository', 'status' => 'ACTIVE'];
        $result = $this->createMock(CodeIgniter\Database\ResultInterface::class);
        $result->method('getRowArray')->willReturn($row);
        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('where')->willReturnSelf();
        $builder->method('get')->willReturn($result);
        $repositoryDb = $this->createMock(BaseConnection::class);
        $repositoryDb->expects($this->once())->method('table')->with('provinces')->willReturn($builder);
        $serviceDb = $this->createMock(BaseConnection::class);
        $serviceDb->expects($this->never())->method('table');
        $service = new FoundationLocationService(
            repository: new FoundationLocationRepository(db: $repositoryDb), db: $serviceDb,
        );
        $this->assertSame($row, $service->province(7));
    }

    public function testFactoryCreatesFreshServicesAndDoesNotCaptureTheClockDuringConstruction(): void
    {
        $clock = new class implements App\Infrastructure\Time\Clock {
            public function now(): DateTimeImmutable
            {
                throw new RuntimeException('Construction must not capture an operation timestamp.');
            }
        };
        $db = $this->createMock(BaseConnection::class);
        $db->expects($this->never())->method('table');
        $first = FoundationFactory::service(db: $db, clock: $clock);
        $second = FoundationFactory::service(db: $db, clock: $clock);
        $this->assertNotSame($first, $second);
        $this->assertNotSame(FoundationFactory::repository($db), FoundationFactory::repository($db));
    }

    /** @return array<string,array{class-string}> */
    public static function repositoryClasses(): array
    {
        return ['legacy' => [FoundationLocationRepository::class], 'module' => [SqlLocationRepository::class]];
    }

    #[DataProvider('repositoryClasses')]
    public function testQueryFailureIsPersistenceErrorNotNotFound(string $class): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('where')->willReturnSelf();
        $builder->method('get')->willReturn(false);
        $db = $this->createMock(BaseConnection::class);
        $db->method('table')->willReturn($builder);
        try {
            (new $class(db: $db))->find('provinces', 1);
            $this->fail('Query failure must not become not-found.');
        } catch (PersistenceException $exception) {
            $this->assertSame('LOCATION_PERSISTENCE_FAILED', $exception->getErrorCode());
            $this->assertSame(['operation' => 'find'], $exception->getContext());
        }
    }

    #[DataProvider('repositoryClasses')]
    public function testUpdateFailurePreservesPreviousException(string $class): void
    {
        $failure = new RuntimeException('Injected DB failure');
        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('where')->willReturnSelf();
        $builder->method('update')->willThrowException($failure);
        $db = $this->createMock(BaseConnection::class);
        $db->method('table')->willReturn($builder);
        $operation = new App\Infrastructure\Mutation\OperationContext(
            42, new App\Infrastructure\Time\EvaluationTime(new DateTimeImmutable('2026-10-16T00:00:00Z')), 'be001', false,
        );
        try {
            (new $class(db: $db))->update('provinces', 1, ['name' => 'Updated'], $operation);
            $this->fail('Update error must propagate with its diagnostic chain.');
        } catch (PersistenceException $exception) {
            $this->assertSame('LOCATION_PERSISTENCE_FAILED', $exception->getErrorCode());
            $this->assertSame(['operation' => 'update'], $exception->getContext());
            $this->assertSame($failure, $exception->getPrevious());
        }
    }

    public function testInvalidAdapterTypeFailsBeforeResolvingAnyConnection(): void
    {
        foreach ([FoundationLocationLifecycleAdapter::class, LocationLifecycleAdapter::class] as $class) {
            try {
                new $class(type: 'not-a-location');
                $this->fail('Invalid type must fail before default DB resolution.');
            } catch (DomainException $exception) {
                $this->assertSame(App\Services\LifecycleService::ERROR_TARGET_ADAPTER_UNAVAILABLE, $exception->getErrorCode());
                $this->assertSame(['resource' => 'not-a-location'], $exception->getContext());
            }
        }
        $this->expectException(DomainException::class);
        FoundationFactory::lifecycleAdapter(type: 'not-a-location');
    }

    /** @return array<string,array{class-string}> */
    public static function adapterClasses(): array
    {
        return ['legacy' => [FoundationLocationLifecycleAdapter::class], 'integration' => [LocationLifecycleAdapter::class]];
    }

    #[DataProvider('adapterClasses')]
    public function testTransactionBeginFailureDoesNotCallTheCallback(string $class): void
    {
        $db = $this->createMock(BaseConnection::class);
        $db->expects($this->once())->method('transBegin')->willReturn(false);
        $db->expects($this->never())->method('transCommit');
        $db->expects($this->never())->method('transRollback');
        $called = false;
        try {
            (new $class(type: 'ward', db: $db))->transactional(static function () use (&$called): void { $called = true; });
            $this->fail('Expected transaction begin failure.');
        } catch (PersistenceException $exception) {
            $this->assertSame('LOCATION_PERSISTENCE_FAILED', $exception->getErrorCode());
            $this->assertSame(['operation' => 'workflowTransactionBegin'], $exception->getContext());
            $this->assertFalse($called);
        }
    }

    #[DataProvider('adapterClasses')]
    public function testCallbackExceptionIsNotWrappedAndRollsBack(string $class): void
    {
        $failure = new RuntimeException('Callback failed');
        $db = $this->createMock(BaseConnection::class);
        $db->method('transBegin')->willReturn(true);
        $db->expects($this->once())->method('transRollback')->willReturn(true);
        $db->expects($this->never())->method('transCommit');
        try {
            (new $class(type: 'province', db: $db))->transactional(static function () use ($failure): never { throw $failure; });
            $this->fail('Expected callback exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    #[DataProvider('adapterClasses')]
    public function testCommitFailureRetainsIdentityAndRollsBack(string $class): void
    {
        $db = $this->createMock(BaseConnection::class);
        $db->method('transBegin')->willReturn(true);
        $db->method('transStatus')->willReturn(true);
        $db->expects($this->once())->method('transCommit')->willReturn(false);
        $db->expects($this->once())->method('transRollback')->willReturn(true);
        try {
            (new $class(type: 'province', db: $db))->transactional(static fn (): string => 'not committed');
            $this->fail('Expected transaction commit failure.');
        } catch (PersistenceException $exception) {
            $this->assertSame('LOCATION_PERSISTENCE_FAILED', $exception->getErrorCode());
            $this->assertSame(['operation' => 'workflowTransactionCommit'], $exception->getContext());
        }
    }
}
