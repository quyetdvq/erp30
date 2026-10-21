<?php

declare(strict_types=1);

use App\Modules\Workflow\Lifecycle\Infrastructure\Persistence\SqlLifecycleRepository;
use App\Repositories\LifecycleRepository;
use App\Services\LifecycleService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;

/** @internal */
final class BeReorg009LifecycleCompatibilityTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;

    public function testLegacyRepositoryConstructorConstantsAndThirtyNineMethods(): void
    {
        $legacy = new ReflectionClass(LifecycleRepository::class);
        $sql = new ReflectionClass(SqlLifecycleRepository::class);
        $this->assertTrue($legacy->isFinal());
        $this->assertTrue($sql->isFinal());
        $publicConstants = static function (ReflectionClass $reflection): array {
            $values = [];
            foreach ($reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) {
                $values[$constant->getName()] = $constant->getValue();
            }
            return $values;
        };
        $this->assertSame($publicConstants($sql), $publicConstants($legacy));
        $this->assertCount(7, $publicConstants($legacy));
        $methods = array_values(array_filter($legacy->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $m): bool => $m->getName() !== '__construct'));
        $this->assertCount(39, $methods);
        $this->assertSame(['db'], array_map(static fn (ReflectionParameter $p): string => $p->getName(), $legacy->getConstructor()->getParameters()));
        foreach ($methods as $method) {
            $canonical = $sql->getMethod($method->getName());
            $this->assertSame((string) $method->getReturnType(), (string) $canonical->getReturnType(), $method->getName());
            $this->assertSame(array_map(static fn (ReflectionParameter $p): string => $p->getName(), $method->getParameters()),
                array_map(static fn (ReflectionParameter $p): string => $p->getName(), $canonical->getParameters()));
        }
    }

    public function testRepositoryAliasesDelegateToOneSqlInstance(): void
    {
        $legacy = new LifecycleRepository($this->db);
        $property = new ReflectionProperty($legacy, 'delegate');
        $sql = $property->getValue($legacy);
        $this->assertInstanceOf(SqlLifecycleRepository::class, $sql);
        $this->assertSame($this->db, (new ReflectionProperty($sql, 'db'))->getValue($sql));
        $this->assertNull($legacy->findLifecycle(999999999));
        $this->assertSame($legacy->findLifecycle(999999999), $legacy->find(999999999));
        $this->assertSame($legacy->findLifecycleByCode('BE009-NOT-FOUND'), $legacy->findByCode('BE009-NOT-FOUND'));
        $this->assertSame($legacy->findLifecycleForUpdate(999999999), $legacy->findForUpdate(999999999));
        $this->assertSame(42, $legacy->transactional(static fn (): int => 42));
        $this->assertSame($sql, $property->getValue($legacy));
    }

    public function testLegacyServiceConstructorPreservesSevenPositionalAndNamedParameters(): void
    {
        $reflection = new ReflectionClass(LifecycleService::class);
        $parameters = $reflection->getConstructor()->getParameters();
        $this->assertSame(['repository', 'authorization', 'audit', 'adapters', 'db', 'mutationContext', 'clock'],
            array_map(static fn (ReflectionParameter $p): string => $p->getName(), $parameters));
        foreach ($parameters as $parameter) {
            $this->assertTrue($parameter->isDefaultValueAvailable());
            $this->assertSame($parameter->getName() === 'adapters' ? [] : null, $parameter->getDefaultValue());
        }
        $repo = new LifecycleRepository($this->db);
        $byName = new LifecycleService(repository: $repo, db: $this->db);
        $byPosition = new LifecycleService($repo, null, null, [], $this->db);
        foreach ([$byName, $byPosition] as $instance) {
            $this->assertInstanceOf(\App\Modules\Workflow\Lifecycle\Application\Ports\LifecycleApplicationPort::class,
                (new ReflectionProperty($instance, 'engine'))->getValue($instance));
        }
    }

    public function testLegacyServiceRetainsEightConstantsAndFiveMethods(): void
    {
        $legacy = new ReflectionClass(LifecycleService::class);
        $this->assertTrue($legacy->isFinal());
        $this->assertSame([
            'ERROR_LIFECYCLE_NOT_FOUND' => 'WF_LIFECYCLE_NOT_FOUND',
            'ERROR_INVALID_CONFIGURATION' => 'WF_INVALID_LIFECYCLE_CONFIGURATION',
            'ERROR_STATE_NOT_IN_LIFECYCLE' => 'WF_STATE_NOT_IN_LIFECYCLE',
            'ERROR_INVALID_TRANSITION' => 'WF_INVALID_TRANSITION',
            'ERROR_AUTHORIZATION_DENIED' => 'AUTH_POLICY_DENIED',
            'ERROR_TARGET_NOT_FOUND' => 'WF_TARGET_NOT_FOUND',
            'ERROR_TARGET_ADAPTER_UNAVAILABLE' => 'WF_TARGET_ADAPTER_UNAVAILABLE',
            'ERROR_ADAPTER_REGISTRATION_INVALID' => 'WF_TARGET_ADAPTER_REGISTRATION_INVALID',
        ], $legacy->getConstants());
        $public = array_map(static fn (ReflectionMethod $m): string => $m->getName(), $legacy->getMethods(ReflectionMethod::IS_PUBLIC));
        sort($public);
        $this->assertSame(['__construct', 'configure', 'execute', 'registerTargetAdapter', 'resolveTransition', 'validateConfiguration'], $public);
        foreach (['configure', 'execute', 'resolveTransition', 'validateConfiguration', 'registerTargetAdapter'] as $method) {
            $engineMethod = new ReflectionMethod(\App\Modules\Workflow\Lifecycle\Application\LifecycleApplicationService::class, $method);
            $legacyMethod = $legacy->getMethod($method);
            $this->assertSame((string) $engineMethod->getReturnType(), (string) $legacyMethod->getReturnType());
            $this->assertSame(array_map(static fn (ReflectionParameter $p): string => $p->getName(), $engineMethod->getParameters()),
                array_map(static fn (ReflectionParameter $p): string => $p->getName(), $legacyMethod->getParameters()));
        }
    }

    public function testAdapterRegistrationUsesSameInstanceAndRejectsDuplicates(): void
    {
        require_once __DIR__ . '/../_support/Workflow/BeReorg009LifecycleFixture.php';
        $snapshot = \Tests\Support\Workflow\BeReorg009LifecycleFixture::snapshot(7, 8);
        $adapter = new \Tests\Support\Workflow\BeReorg009Adapter($snapshot);
        $service = new LifecycleService(db: $this->db, adapters: [$adapter]);
        $engine = (new ReflectionProperty($service, 'engine'))->getValue($service);
        $this->assertSame($adapter, (new ReflectionProperty($engine, 'adapters'))->getValue($engine)['product']);
        try {
            $service->registerTargetAdapter(new \Tests\Support\Workflow\BeReorg009Adapter($snapshot));
            $this->fail('Duplicate adapter registration must fail closed');
        } catch (\App\Exceptions\DomainException $error) {
            $this->assertSame(LifecycleService::ERROR_ADAPTER_REGISTRATION_INVALID, $error->getErrorCode());
        }
        $this->assertSame($engine, (new ReflectionProperty($service, 'engine'))->getValue($service));
        $this->expectException(\App\Exceptions\DomainException::class);
        new LifecycleService(db: $this->db, adapters: ['wrong' => $adapter]);
    }

    public function testLegacyServicePreservesReturnShapesAndExceptionClasses(): void
    {
        require_once __DIR__ . '/../_support/Workflow/BeReorg009LifecycleFixture.php';
        $fixture = \Tests\Support\Workflow\BeReorg009LifecycleFixture::class;
        [$id, $from, $to] = $fixture::transition($this->db);
        $service = new LifecycleService(repository: new LifecycleRepository($this->db), db: $this->db);
        $this->assertSame(['lifecycle', 'states', 'transitions'], array_keys($service->validateConfiguration($id)));
        $this->assertSame($to, (int) $service->resolveTransition($id, $from, 'approve')['to_state_id']);
        try {
            $service->resolveTransition($id, $from, 'nonexistent');
            $this->fail('Unknown transition must fail');
        } catch (\App\Exceptions\DomainException $exception) {
            $this->assertSame(LifecycleService::ERROR_INVALID_TRANSITION, $exception->getErrorCode());
        }
    }
}
