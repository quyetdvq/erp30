<?php

declare(strict_types=1);

use App\Bootstrap\WorkflowLifecycleFactory;
use App\Infrastructure\Mutation\MutationContext;
use App\Modules\Workflow\Lifecycle\Application\LifecycleApplicationService;
use App\Modules\Workflow\Lifecycle\Infrastructure\Persistence\SqlLifecycleRepository;
use App\Repositories\LifecycleRepository;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;
use Tests\Support\Workflow\BeReorg009Adapter;
use Tests\Support\Workflow\BeReorg009Audit;
use Tests\Support\Workflow\BeReorg009Authorization;
use Tests\Support\Workflow\BeReorg009LifecycleFixture as Fixture;

require_once __DIR__ . '/../_support/Workflow/BeReorg009LifecycleFixture.php';

/** @internal */
final class BeReorg009LifecycleFactoryTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;

    private function property(object $object, string $name): mixed
    {
        return (new ReflectionProperty($object, $name))->getValue($object);
    }

    public function testFactoryCreatesFreshApplicationPerCallWithoutSharedRegistry(): void
    {
        $first = WorkflowLifecycleFactory::create(db: $this->db);
        $second = WorkflowLifecycleFactory::create(db: $this->db);
        $this->assertInstanceOf(LifecycleApplicationService::class, $first);
        $this->assertNotSame($first, $second);
        $adapter = new BeReorg009Adapter(Fixture::snapshot(7, 8));
        $first->registerTargetAdapter($adapter);
        $this->assertSame($adapter, $this->property($first, 'adapters')['product']);
        $this->assertSame([], $this->property($second, 'adapters'));
    }

    public function testFactoryKeepsEverySuppliedCollaboratorByIdentity(): void
    {
        $repository = new LifecycleRepository($this->db);
        $auth = new BeReorg009Authorization();
        $audit = new BeReorg009Audit();
        $clock = service('clock');
        $context = MutationContext::system($clock);
        $adapter = new BeReorg009Adapter(Fixture::snapshot(7, 8));
        $engine = WorkflowLifecycleFactory::create($repository, $auth, $audit, [$adapter], $this->db, $context, $clock);
        foreach (['repository' => $repository, 'authorization' => $auth, 'audit' => $audit,
            'mutationContext' => $context, 'clock' => $clock, 'db' => $this->db] as $name => $expected) {
            $this->assertSame($expected, $this->property($engine, $name), $name);
        }
        $this->assertSame($adapter, $this->property($engine, 'adapters')['product']);
    }

    public function testFactoryUsesOneSuppliedConnectionForOmittedCollaborators(): void
    {
        $engine = WorkflowLifecycleFactory::create(db: $this->db);
        $this->assertSame($this->db, $this->property($engine, 'db'));
        $repo = (new ReflectionMethod($engine, 'repo'))->invoke($engine);
        $this->assertInstanceOf(SqlLifecycleRepository::class, $repo);
        $this->assertSame($this->db, $this->property($repo, 'db'));
        $authorization = (new ReflectionMethod($engine, 'auth'))->invoke($engine);
        $audit = (new ReflectionMethod($engine, 'audit'))->invoke($engine);
        $decision = $this->property($authorization, 'engine');
        $this->assertSame($this->db, $this->property($this->property($decision, 'catalog'), 'db'));
        $this->assertSame($this->db, $this->property($this->property($this->property($decision, 'policies'), 'delegate'), 'db'));
        $this->assertSame($this->db, $this->property($this->property($audit, 'catalog'), 'db'));
        $this->assertSame($this->db, $this->property($this->property($audit, 'logs'), 'db'));
    }

    public function testFactoryRetainsLazyDefaultsAndOperationContextTiming(): void
    {
        $engine = WorkflowLifecycleFactory::create(db: $this->db);
        $this->assertNull($this->property($engine, 'repository'));
        $this->assertNull($this->property($engine, 'authorization'));
        $this->assertNull($this->property($engine, 'audit'));
        $this->assertNull($this->property($engine, 'mutationContext'));
        $this->assertNull($this->property($engine, 'clock'));
        $operation = (new ReflectionMethod($engine, 'operation'))->invoke($engine);
        $this->assertInstanceOf(\App\Infrastructure\Mutation\OperationContext::class, $operation);
        $this->assertNull($this->property($engine, 'authorization'));
        $this->assertNull($this->property($engine, 'audit'));
    }
}
