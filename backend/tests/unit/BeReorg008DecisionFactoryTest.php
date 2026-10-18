<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Authorization\EffectiveProfileResolver;
use App\Authorization\ShieldIdentityAdapter;
use App\Bootstrap\AuthorizationDecisionFactory;
use App\Infrastructure\Mutation\OperationContext;
use App\Modules\Authorization\Decision\Application\PbacDecisionEngine;
use App\Modules\Authorization\Decision\Application\Ports\PbacDecisionPort;
use App\Repositories\PolicyRepository;
use App\Repositories\ResourceCatalogRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Authorization\BeReorg008DecisionFixture;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Ensures one canonical engine and explicit connection/collaborator ownership. */
final class BeReorg008DecisionFactoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $namespace = null;
    private BeReorg008DecisionFixture $fixture;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
        $this->fixture = new BeReorg008DecisionFixture($this->db);
    }

    protected function tearDown(): void
    {
        $this->fixture->restoreTrackedInactivePolicies();
        $this->fixture->restoreTrackedResourceStatuses();
        $this->resetServices();
        parent::tearDown();
    }

    public function testFactoryCreatesIndependentCanonicalEngines(): void
    {
        $first = AuthorizationDecisionFactory::create(db: $this->db);
        $second = AuthorizationDecisionFactory::create(db: $this->db);
        $this->assertInstanceOf(PbacDecisionPort::class, $first);
        $this->assertInstanceOf(PbacDecisionEngine::class, $first);
        $this->assertNotSame($first, $second);
        $this->assertSame([], (new ReflectionClass(AuthorizationDecisionFactory::class))->getProperties());
        $this->assertFalse((new ReflectionClass(AuthorizationDecisionFactory::class))->getConstructor()->isPublic());
    }

    public function testFactoryPreservesSuppliedCollaboratorIdentity(): void
    {
        $identities = new ShieldIdentityAdapter($this->db);
        $profiles = new EffectiveProfileResolver($identities);
        $policies = new PolicyRepository($this->db);
        $catalog = new ResourceCatalogRepository($this->db);
        $engine = AuthorizationDecisionFactory::create(
            profiles: $profiles, policies: $policies, identities: $identities,
            db: $this->db, catalog: $catalog,
        );
        foreach (['profiles' => $profiles, 'policies' => $policies, 'identities' => $identities, 'catalog' => $catalog] as $name => $object) {
            $this->assertSame($object, $this->property($engine, $name), $name);
        }
        $this->assertNotSame($engine, AuthorizationDecisionFactory::create(profiles: $profiles, db: $this->db));
        $this->assertSame($profiles, $this->property(AuthorizationDecisionFactory::create(profiles: $profiles, db: $this->db), 'profiles'));
    }

    public function testDefaultCollaboratorsUseOneSuppliedConnection(): void
    {
        $engine = AuthorizationDecisionFactory::create(db: $this->db);
        $identities = $this->property($engine, 'identities');
        $this->assertSame($this->db, $this->property($identities, 'db'));
        $catalog = $this->property($engine, 'catalog');
        $this->assertSame($this->db, $this->property($catalog, 'db'));
        $policies = $this->property($engine, 'policies');
        $this->assertSame($this->db, $this->property($this->property($policies, 'delegate'), 'db'));
        $this->assertSame($this->db, $this->property($this->property($this->property($engine, 'profiles'), 'identityAdapter'), 'db'));
        $this->assertSame($this->db, $this->property($this->property($this->property($engine, 'employees'), 'delegate'), 'db'));
        $this->assertSame($this->db, $this->property($this->property($this->property($this->property($this->property($engine, 'workflowStates'), 'engine'), 'reader'), 'resources'), 'db'));
        $names = array_map(static fn (ReflectionParameter $p): string => $p->getName(),
            (new ReflectionClass(PbacDecisionEngine::class))->getConstructor()->getParameters());
        $this->assertSame(['profiles', 'contexts', 'policies', 'employees', 'assignments',
            'organizations', 'identities', 'db', 'catalog', 'workflowStates'], $names);

        $actor = $this->fixture->seedActor(false);
        $decision = $engine->authorize($this->fixture->operation($actor['user_id']), 'price_list', 'view',
            $this->fixture->request($actor['company_id']));
        $this->assertSame('DENY', $decision->decision);
        $this->assertSame('AUTH_NO_BASE_PERMISSION', $decision->reasonCode);
    }

    public function testFacadeOwnsOneEngineAndNoImplicitAllow(): void
    {
        $actor = $this->fixture->seedActor(false);
        $service = new AuthorizationService(db: $this->db);
        $engine = $this->property($service, 'engine');
        $this->assertInstanceOf(PbacDecisionPort::class, $engine);
        $operation = $this->fixture->operation($actor['user_id']);
        $request = $this->fixture->request($actor['company_id']);
        $result = $service->withReadOnlyBatch($operation, function () use ($service, $engine, $operation, $request): array {
            $this->assertNotNull($this->property($engine, 'readOnlyContext'));
            return [$service->authorize($operation, 'price_list', 'view', $request)->toArray(),
                $service->authorize($operation, 'price_list', 'view', $request)->toArray()];
        });
        $this->assertSame($result[0], $result[1]);
        $this->assertSame('DENY', $result[0]['decision']);
        $this->assertSame('AUTH_NO_BASE_PERMISSION', $result[0]['reason_code']);
        $this->assertSame($engine, $this->property($service, 'engine'));
        $this->assertNull($this->property($engine, 'readOnlyContext'));
    }

    private function property(object $object, string $property): mixed
    {
        return (new ReflectionProperty($object, $property))->getValue($object);
    }
}
