<?php

declare(strict_types=1);

use App\Database\Migrations\SplitContactAuthorizationResources;
use App\Database\Seeds\ResourceActionSeeder;
use App\Infrastructure\Mutation\MutationContext;
use App\Services\ContactLifecycleAdapter;
use App\Services\LifecycleService;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal Contact DRAFT migration + type-aware transition preservation. */
final class ChangeContactAuth001DraftLifecycleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function tearDown(): void
    {
        try {
            if ($this->db !== null && $this->db->tableExists($this->db->prefixTable('resources'), false)) {
                // Re-enable the legacy Resource only in the isolated test DB,
                // preventing an unrelated migration rollback guard from failing.
                $this->db->table('resources')->where('type', 'contact')->update(['status' => 'ACTIVE']);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testDraftInitialPreservesExistingActiveAndAllowsOwnerAwareActivation(): void
    {
        [$lifecycleId, $draftId, $activeId, $ownerId] = $this->prepareLifecycle(true);
        $this->db->table('contacts')->insert([
            'contact_type' => 'ORGANIZATION', 'contact_ref_id' => $ownerId,
            'status' => 'ACTIVE', 'name' => 'Existing', 'created_at' => '2026-10-11 00:00:00',
        ]);
        $existingId = (int) $this->db->insertID();
        $migration = new SplitContactAuthorizationResources(Config\Database::forge($this->db));
        $migration->up();

        foreach (['organization_contact', 'employee_contact'] as $type) {
            $resource = $this->db->table('resources')->where('type', $type)->get()->getRowArray();
            $this->assertSame($lifecycleId, (int) $resource['lifecycle_id']);
            $this->assertSame('ACTIVE', $resource['status']);
            $resolver = new ResourceWorkflowStateResolver($this->db);
            $this->assertSame('DRAFT', $resolver->initialCode($type));
            $resolver->assertLifecycleStateCompatibility($type, $lifecycleId);
            $this->assertSame(0, $this->db->table('group_permissions gp')
                ->join('resource_actions ra', 'ra.id = gp.resource_action_id')
                ->where('ra.resource_id', $resource['id'])->countAllResults());
        }
        $this->assertSame('ACTIVE', (string) $this->db->table('contacts')->where('id', $existingId)->get()->getRow('status'));
        $this->assertSame('INACTIVE', (string) $this->db->table('resources')->where('type', 'contact')->get()->getRow('status'));

        $this->db->table('contacts')->insert([
            'contact_type' => 'ORGANIZATION', 'contact_ref_id' => $ownerId,
            'status' => 'DRAFT', 'name' => 'New draft', 'created_at' => '2026-10-11 00:00:00',
        ]);
        $draftContactId = (int) $this->db->insertID();
        $operation = MutationContext::system(service('clock'))->begin();
        $adapter = new ContactLifecycleAdapter('organization_contact', $this->db);
        $snapshot = $adapter->load($draftContactId, $operation);
        $this->assertSame($draftId, $snapshot->currentStateId);
        $transition = (new LifecycleService(db: $this->db))->resolveTransition($lifecycleId, $draftId, 'setstate');
        $this->assertSame($activeId, (int) $transition['to_state_id']);
        $adapter->transactional(function () use ($adapter, $snapshot, $draftId, $activeId, $operation): void {
            $locked = $adapter->lockAndReload($snapshot, $operation);
            $adapter->mutate($locked, $draftId, $activeId, $operation);
        });
        $this->assertSame('ACTIVE', (string) $this->db->table('contacts')->where('id', $draftContactId)->get()->getRow('status'));

        // The same Contact must never be addressable by the other logical Resource.
        $this->expectException(\App\Exceptions\DomainException::class);
        $this->expectExceptionMessage(LifecycleService::ERROR_TARGET_NOT_FOUND);
        (new ContactLifecycleAdapter('employee_contact', $this->db))->load($draftContactId, $operation);
    }

    public function testDraftInitialWithoutActivationTransitionBlocksMigration(): void
    {
        $this->prepareLifecycle(false);
        try {
            (new SplitContactAuthorizationResources(Config\Database::forge($this->db)))->up();
            $this->fail('A DRAFT-only Lifecycle without activation must not become an unusable Contact Resource.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('DRAFT to ACTIVE transition missing', $exception->getMessage());
        }
        $this->assertSame('ACTIVE', (string) $this->db->table('resources')->where('type', 'contact')->get()->getRow('status'));
    }

    public function testContactBindingUsesMembersWithoutMandatingAnyParticularTransition(): void
    {
        [$lifecycleId] = $this->prepareLifecycle(false);
        // The historical split migration has its own safety precondition.
        // New Lifecycle configuration itself is generic for all non-native
        // Resources and only enforces ACTIVE membership and one initial.
        (new ResourceWorkflowStateResolver($this->db))
            ->assertLifecycleStateCompatibility('organization_contact', $lifecycleId);
        $this->assertSame('DRAFT', (new ResourceWorkflowStateResolver($this->db))->initialCode('contact'));
    }

    public function testMigrationRejectsNonCanonicalPersistedContactStatus(): void
    {
        [,,,$ownerId] = $this->prepareLifecycle(true);
        $this->db->table('contacts')->insert([
            'contact_type' => 'ORGANIZATION', 'contact_ref_id' => $ownerId,
            'status' => 'active', 'name' => 'Noncanonical', 'created_at' => '2026-10-11 00:00:00',
        ]);
        try {
            (new SplitContactAuthorizationResources(Config\Database::forge($this->db)))->up();
            $this->fail('Byte-noncanonical status must be rejected even under case-insensitive collation.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('persisted Contact owner/State requires review', $exception->getMessage());
        }
        $this->assertSame('ACTIVE', (string) $this->db->table('resources')->where('type', 'contact')->get()->getRow('status'));
    }

    /** @return array{int,int,int,int} */
    private function prepareLifecycle(bool $withActivation): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $this->db->table('lifecycles')->insert([
            'code' => uniqid('CONTACT-DRAFT-LC-'), 'name' => 'Contact draft test',
            'status' => 'ACTIVE', 'created_at' => '2026-10-11 00:00:00',
        ]);
        $lifecycleId = (int) $this->db->insertID();
        $stateIds = [];
        foreach (['DRAFT', 'ACTIVE'] as $code) {
            $state = $this->db->table('workflow_states')->where('code', $code)->get()->getRowArray();
            if ($state === null) {
                $this->db->table('workflow_states')->insert([
                    'code' => $code, 'name' => $code, 'status' => 'ACTIVE',
                    'created_at' => '2026-10-11 00:00:00',
                ]);
                $stateIds[$code] = (int) $this->db->insertID();
            } else {
                $stateIds[$code] = (int) $state['id'];
            }
            $this->db->table('lifecycle_states')->insert([
                'lifecycle_id' => $lifecycleId, 'state_id' => $stateIds[$code],
                'sequence' => $code === 'DRAFT' ? 1 : 2, 'is_initial' => $code === 'DRAFT' ? 1 : 0,
                'is_final' => 0, 'created_at' => '2026-10-11 00:00:00',
            ]);
        }
        if ($withActivation) {
            $this->db->table('lifecycle_transitions')->insert([
                'lifecycle_id' => $lifecycleId,
                'from_state_id' => $stateIds['DRAFT'], 'to_state_id' => $stateIds['ACTIVE'],
                'action_code' => 'setstate', 'status' => 'ACTIVE',
                'created_at' => '2026-10-11 00:00:00',
            ]);
        }
        $this->db->table('resources')->where('type', 'contact')->update([
            'lifecycle_id' => $lifecycleId, 'status' => 'ACTIVE',
        ]);
        $this->db->table('organizations')->insert([
            'code' => uniqid('CONTACT-DRAFT-O-'), 'name' => 'Contact owner',
            'type' => 'GROUP', 'status' => 'ACTIVE',
            'created_at' => '2026-10-11 00:00:00',
        ]);
        $organizationId = (int) $this->db->insertID();

        return [$lifecycleId, $stateIds['DRAFT'], $stateIds['ACTIVE'], $organizationId];
    }
}
