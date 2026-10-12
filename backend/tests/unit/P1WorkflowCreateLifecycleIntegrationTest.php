<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Repositories\LifecycleRepository;
use App\Services\InitialCreateStateNormalizer;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal Exercises persisted Resource/Lifecycle metadata on a disposable test database. */
final class P1WorkflowCreateLifecycleIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testConfiguredInitialStateComesFromPersistedLifecycle(): void
    {
        (new ResourceActionSeeder(new \Config\Database()))->run();
        $repo = new LifecycleRepository($this->db);
        $initial = $this->activeWorkflowStateId('INACTIVE');
        $other = $this->activeWorkflowStateId('ACTIVE');
        $lifecycleId = $repo->insertLifecycle([
            'code' => 'WF-CREATE-' . uniqid(), 'name' => 'Province workflow', 'status' => 'ACTIVE',
        ]);
        $repo->insertLifecycleState([
            'lifecycle_id' => $lifecycleId, 'state_id' => $initial,
            'sequence' => 1, 'is_initial' => 1, 'is_final' => 0,
        ]);
        $repo->insertLifecycleState([
            'lifecycle_id' => $lifecycleId, 'state_id' => $other,
            'sequence' => 2, 'is_initial' => 0, 'is_final' => 1,
        ]);
        $this->assertTrue($this->db->table('resources')
            ->where('type', 'province')->update(['lifecycle_id' => $lifecycleId]));

        $resolver = new ResourceWorkflowStateResolver($this->db);
        $resolver->assertLifecycleStateCompatibility('province', $lifecycleId);
        $result = (new InitialCreateStateNormalizer($resolver))->normalize('province', [
            'code' => 'PV-51', 'name' => 'Province 51',
        ]);
        $this->assertSame('INACTIVE', $result['status']);

        try {
            (new InitialCreateStateNormalizer($resolver))->normalize('province', [
                'code' => 'PV-52', 'name' => 'Province 52', 'status' => 'ACTIVE',
            ]);
            $this->fail('Caller status must not override the configured initial State.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceWorkflowStateResolver::ERROR_INITIAL_STATE_INVALID, $exception->getErrorCode());
        }
    }

    public function testUnsupportedWorkflowMemberCannotBeAssignedToNativeMaster(): void
    {
        (new ResourceActionSeeder(new \Config\Database()))->run();
        $repo = new LifecycleRepository($this->db);
        $draft = $this->activeWorkflowStateId('DRAFT');
        $lifecycleId = $repo->insertLifecycle([
            'code' => 'WF-DRAFT-' . uniqid(), 'name' => 'Draft incompatible with Product', 'status' => 'ACTIVE',
        ]);
        $repo->insertLifecycleState([
            'lifecycle_id' => $lifecycleId, 'state_id' => $draft,
            'sequence' => 1, 'is_initial' => 1, 'is_final' => 0,
        ]);

        try {
            (new ResourceWorkflowStateResolver($this->db))
                ->assertLifecycleStateCompatibility('product', $lifecycleId);
            $this->fail('Product currently persists only ACTIVE/INACTIVE.');
        } catch (DomainException $exception) {
            $this->assertSame(InitialCreateStateNormalizer::ERROR_UNSUPPORTED_INITIAL, $exception->getErrorCode());
        }
    }

    private function activeWorkflowStateId(string $code): int
    {
        $row = $this->db->table('workflow_states')->where('code', $code)->get()->getRowArray();
        if ($row !== null) {
            $this->db->table('workflow_states')->where('id', $row['id'])->update(['status' => 'ACTIVE']);
            return (int) $row['id'];
        }
        $this->db->table('workflow_states')->insert([
            'code' => $code, 'name' => $code, 'status' => 'ACTIVE',
            'created_at' => '2026-10-12 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }
}
