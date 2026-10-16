<?php

declare(strict_types=1);

namespace Tests\Support\Organization;

use App\Database\Seeds\ResourceActionSeeder;
use App\Infrastructure\Time\Clock;
use App\Repositories\LifecycleRepository;
use App\Repositories\PolicyRepository;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;
use Tests\Support\Authorization\EligibleActorFixture;

/** Synthetic, disposable test data only. No production users or credentials. */
final class RuntimeFixture
{
    public int $group;
    public int $company;
    public int $writer;
    public int $reader;
    /** @var array<string,int> */
    public array $states = [];

    /** @param BaseConnection<mixed,mixed> $db */
    public function __construct(private readonly BaseConnection $db)
    {
        (new ResourceActionSeeder(new Database()))->run();
        $this->group = $this->insert('organizations', [
            'type' => 'GROUP', 'parent_id' => null, 'code' => 'BR002-G',
            'name' => 'Organization test group', 'status' => 'ACTIVE',
        ]);
        $this->company = $this->insert('organizations', [
            'type' => 'COMPANY', 'parent_id' => $this->group, 'code' => 'BR002-C',
            'name' => 'Organization test company', 'status' => 'ACTIVE',
        ]);
        $this->writer = $this->actor('writer', ['view', 'create', 'edit', 'setstate']);
        $this->reader = $this->actor('reader', ['view']);
        $repo = new LifecycleRepository($db);
        $lifecycle = $repo->insertLifecycle(['code' => 'BR002-LC', 'name' => 'Organization test', 'status' => 'ACTIVE']);
        foreach (['ACTIVE', 'INACTIVE', 'CLOSED'] as $i => $code) {
            $row = $db->table('workflow_states')->where('code', $code)->get()->getRowArray();
            $this->states[$code] = $row === null
                ? $repo->insertWorkflowState(['code' => $code, 'name' => $code, 'status' => 'ACTIVE'])
                : (int) $row['id'];
            $repo->insertLifecycleState([
                'lifecycle_id' => $lifecycle, 'state_id' => $this->states[$code], 'sequence' => $i + 1,
                'is_initial' => $i === 0 ? 1 : 0, 'is_final' => $code === 'CLOSED' ? 1 : 0,
            ]);
        }
        $repo->insertTransition([
            'lifecycle_id' => $lifecycle, 'from_state_id' => $this->states['ACTIVE'],
            'to_state_id' => $this->states['INACTIVE'], 'action_code' => 'setstate', 'status' => 'ACTIVE',
        ]);
        foreach (['organization'] as $resource) {
            $db->table('resources')->where('type', $resource)->update(['lifecycle_id' => $lifecycle]);
        }
    }

    public static function clock(): Clock
    {
        return new class implements Clock {
            public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-10-16T02:30:00Z'); }
        };
    }

    public function deny(string $resource, string $action, int $user, ?int $state = null): void
    {
        $row = $this->db->table('resources')->where('type', $resource)->get()->getRowArray();
        if ($row === null) { throw new RuntimeException('Missing seeded resource.'); }
        (new PolicyRepository($this->db))->insertWithActions([
            'organization_id' => $this->group, 'resource_id' => (int) $row['id'], 'state_id' => $state,
            'participant_type' => 'USER', 'participant_ref_id' => $user,
        ], [['resource_action_id' => $this->actionId($resource, $action), 'effect' => 'ABSOLUTE_DENY']]);
    }

    /** @param array<string,mixed> $data */
    private function insert(string $table, array $data): int
    {
        if (! $this->db->table($table)->insert($data + ['created_at' => '2026-10-16 02:30:00'])) {
            throw new RuntimeException('Could not create synthetic Organization fixture: ' . $table);
        }
        return (int) $this->db->insertID();
    }

    /** @param list<string> $actions */
    private function actor(string $name, array $actions): int
    {
        $user = $this->insert('users', ['username' => 'br002-' . $name, 'status' => 'active', 'active' => 1]);
        $code = 'BR002-' . strtoupper($name);
        $group = $this->insert('groups', ['code' => $code, 'name' => $name, 'status' => 'ACTIVE']);
        $this->insert('auth_groups_users', ['user_id' => $user, 'group' => strtolower($code)]);
        foreach (['organization'] as $resource) {
            foreach ($actions as $action) {
                $this->insert('group_permissions', ['group_id' => $group, 'resource_action_id' => $this->actionId($resource, $action)]);
            }
        }
        EligibleActorFixture::assignAtOrganization($this->db, $user, $this->group);
        return $user;
    }

    private function actionId(string $resource, string $action): int
    {
        $row = $this->db->table('resource_actions ra')->select('ra.id')
            ->join('resources r', 'r.id = ra.resource_id')->where('r.type', $resource)
            ->where('ra.code', $action)->get()->getRowArray();
        if ($row === null) { throw new RuntimeException('Missing seeded action: ' . $resource . '.' . $action); }
        return (int) $row['id'];
    }
}
