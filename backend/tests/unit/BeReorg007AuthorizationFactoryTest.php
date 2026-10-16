<?php

declare(strict_types=1);

use App\Authorization\AuthorizationService;
use App\Authorization\PolicyValidator;
use App\Authorization\ShieldIdentityAdapter;
use App\Bootstrap\AuthorizationAdministrationFactory as Factory;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Modules\Authorization\Administration\Groups\Infrastructure\Persistence\SqlAuthorizationGroupRepository;
use App\Modules\Authorization\Administration\Policies\Infrastructure\Persistence\SqlPolicyRepository;
use App\Repositories\AuthorizationGroupRepository;
use App\Repositories\PolicyRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Authorization\EligibleActorFixture;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal BE007 factory ownership; behavior authority remains P1Auth. */
final class BeReorg007AuthorizationFactoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $namespace = null;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
    }

    protected function tearDown(): void
    {
        $this->resetServices();
        parent::tearDown();
    }

    public function testFactoryUsesOneSuppliedConnectionAcrossDefaults(): void
    {
        $group = Factory::group($this->db);
        $policy = Factory::policy($this->db);
        $this->assertSame($this->db, $this->value($group, 'db'));
        $this->assertSame($this->db, $this->value($policy, 'db'));
        $groupRepository = $this->call($group, 'repo');
        $policyRepository = $this->call($policy, 'repo');
        $this->assertInstanceOf(SqlAuthorizationGroupRepository::class, $groupRepository);
        $this->assertInstanceOf(SqlPolicyRepository::class, $policyRepository);
        $this->assertSame($this->db, $this->value($groupRepository, 'db'));
        $this->assertSame($this->db, $this->value($policyRepository, 'db'));
        $validator = $this->call($policy, 'validator');
        $this->assertInstanceOf(SqlPolicyRepository::class, $this->value($validator, 'repository'));
        $this->assertSame($this->db, $this->value($this->value($validator, 'repository'), 'db'));
        $this->assertSame($this->db, $this->value($this->value($validator, 'catalog'), 'db'));
        $stateResources = $this->value($this->value($validator, 'workflowStates'), 'resources');
        $this->assertSame($this->db, $this->value($stateResources, 'db'));
        $authorization = $this->call($group, 'auth');
        $authPolicy = $this->value($authorization, 'policies');
        $this->assertInstanceOf(PolicyRepository::class, $authPolicy);
        $this->assertSame($this->db, $this->value($this->value($authPolicy, 'delegate'), 'db'));
    }

    public function testInjectedCollaboratorsRemainOwnedAndAreNotRebuilt(): void
    {
        $groups = new AuthorizationGroupRepository($this->db);
        $policies = new PolicyRepository($this->db);
        $validator = new PolicyValidator($policies);
        $authorization = new AuthorizationService(db: $this->db);
        $identities = new ShieldIdentityAdapter($this->db);
        $clock = service('clock');
        $mutation = MutationContext::system($clock);
        $group = Factory::group($this->db, $groups, $authorization, $identities, $mutation, $clock);
        $policy = Factory::policy($this->db, $policies, $validator, $authorization, $mutation, $clock);
        foreach (['repository' => $groups, 'authorization' => $authorization, 'identities' => $identities, 'mutationContext' => $mutation, 'clock' => $clock] as $name => $expected) {
            $this->assertSame($expected, $this->value($group, $name), $name);
        }
        foreach (['repository' => $policies, 'validator' => $validator, 'authorization' => $authorization, 'mutationContext' => $mutation, 'clock' => $clock] as $name => $expected) {
            $this->assertSame($expected, $this->value($policy, $name), $name);
        }
    }

    public function testFactoryDefaultsAreLazyAndFailClosed(): void
    {
        (new ResourceActionSeeder(new Database()))->run();
        $this->db->table('organizations')->insert(['code' => 'BE007-FACTORY-' . uniqid(), 'name' => 'Factory root', 'type' => 'GROUP', 'status' => 'ACTIVE', 'created_at' => '2026-10-16 00:00:00']);
        $organization = (int) $this->db->insertID();
        $this->db->table('users')->insert(['username' => 'be007-denied-' . uniqid(), 'status' => 'active', 'active' => 1, 'created_at' => '2026-10-16 00:00:00']);
        $actor = (int) $this->db->insertID();
        EligibleActorFixture::assignAtOrganization($this->db, $actor, $organization);
        $before = [(int) $this->db->table('groups')->countAllResults(), (int) $this->db->table('policies')->countAllResults()];
        $group = Factory::group($this->db);
        $policy = Factory::policy($this->db);
        $this->assertSame($before, [(int) $this->db->table('groups')->countAllResults(), (int) $this->db->table('policies')->countAllResults()]);
        $this->assertNotSame($group, Factory::group($this->db));
        $this->assertNotSame($policy, Factory::policy($this->db));
        $operation = new OperationContext($actor, new EvaluationTime(new DateTimeImmutable('2026-10-16 00:00:00', new DateTimeZone('UTC'))), 'be007-deny', false);
        try {
            $group->create(['code' => 'BE007-DENIED', 'name' => 'Not allowed'], $operation);
            $this->fail('Mutation without PBAC base permission must fail.');
        } catch (DomainException $exception) {
            $this->assertSame('AUTH_GROUP_AUTHORIZATION_DENIED', $exception->getErrorCode());
        }
        $this->assertSame($before, [(int) $this->db->table('groups')->countAllResults(), (int) $this->db->table('policies')->countAllResults()]);
    }

    public function testFactoryIsStatelessAndLegacyConstructorsKeepNamedArgs(): void
    {
        $factory = new ReflectionClass(Factory::class);
        $this->assertSame([], $factory->getProperties());
        $this->assertFalse($factory->getConstructor()->isPublic());
        $before = [(int) $this->db->table('groups')->countAllResults(), (int) $this->db->table('policies')->countAllResults()];
        $groups = new AuthorizationGroupRepository(db: $this->db);
        $policies = new PolicyRepository(db: $this->db);
        $participants = new App\Authorization\PolicyParticipantValidator(repository: $policies);
        $validator = new PolicyValidator(repository: $policies, participants: $participants);
        $groupService = new App\Services\AuthorizationGroupService(repository: $groups, db: $this->db);
        $policyService = new App\Services\PolicyAdministrationService(repository: $policies, validator: $validator, db: $this->db);
        $this->assertInstanceOf(App\Services\AuthorizationGroupService::class, $groupService);
        $this->assertInstanceOf(App\Services\PolicyAdministrationService::class, $policyService);
        $this->assertSame($before, [(int) $this->db->table('groups')->countAllResults(), (int) $this->db->table('policies')->countAllResults()]);
    }

    private function value(object $object, string $property): mixed
    {
        return (new ReflectionProperty($object, $property))->getValue($object);
    }

    private function call(object $object, string $method): mixed
    {
        return (new ReflectionMethod($object, $method))->invoke($object);
    }
}
