<?php

declare(strict_types=1);

namespace Tests\Support\ProductMedia;

use App\Database\Seeds\ResourceActionSeeder;
use App\Infrastructure\Time\Clock;
use App\Repositories\LifecycleRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\PolicyRepository;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;
use Tests\Support\Authorization\EligibleActorFixture;

/**
 * PM-0-only synthetic data. Newly inserted actors and child organizations use
 * unique identifiers; the canonical GROUP root is reused when present.
 * All database writes are enclosed by MediaTestCase's rollback transaction.
 */
final class ProductMediaFixture
{
    public int $group;
    public int $company;
    public int $writer;
    public int $reader;
    public string $writerGroupCode;
    public string $readerGroupCode;

    /** @var array<string, int> */
    public array $states = [];

    /** @param BaseConnection<mixed,mixed> $db */
    public function __construct(private readonly BaseConnection $db)
    {
        // Keep the established resource/action seed semantics from Foundation.
        (new ResourceActionSeeder(new Database()))->run();

        // Inherited regression cases can leave `product` bound to a DRAFT-only
        // Lifecycle (notably P1WorkflowCreateLifecycleIntegrationTest). This
        // fixture creates ACTIVE native-state products; start each PM-0/PM-1
        // test from that explicit catalog baseline. The enclosing MediaTestCase
        // transaction rolls this change back, so no shared catalog state is
        // silently rewritten after the test.
        $product = $db->table('resources')->where('type', 'product')->get()->getRowArray();
        if ($product === null || (int) ($product['id'] ?? 0) <= 0) {
            throw new RuntimeException('Missing seeded Product Media resource: product');
        }
        if (! $db->table('resources')->where('id', (int) $product['id'])
            ->update(['lifecycle_id' => null, 'status' => 'ACTIVE'])) {
            throw new RuntimeException('Unable to isolate native Product Media resource state.');
        }

        // Use a new identifier per test, even after an interrupted earlier run.
        $prefix = 'PM0-' . bin2hex(random_bytes(6));
        $this->writerGroupCode = $prefix . '-WRITER';
        $this->readerGroupCode = $prefix . '-READER';

        // PBAC's EvaluationOrganizationResolver requires exactly one GROUP
        // with a NULL parent. Reuse the canonical root, never insert a second.
        // Query through the same repository/filter used by the resolver.
        $roots = (new OrganizationRepository($db))->findAll([
            'type' => 'GROUP',
            'parent_id' => null,
        ]);
        if (count($roots) > 1) {
            $rootIds = array_map(static fn (array $row): string => (string) ($row['id'] ?? '?'), $roots);
            throw new RuntimeException(sprintf(
                'Unsafe Product Media fixture: found %d root GROUP organizations (ids: %s). ' .
                'Do not modify the database automatically.',
                count($roots),
                implode(',', $rootIds),
            ));
        }
        $this->group = $roots === []
            ? $this->insert('organizations', [
                'type' => 'GROUP', 'parent_id' => null, 'code' => $prefix . '-G',
                'name' => 'Product Media test group', 'status' => 'ACTIVE',
            ])
            : (int) ($roots[0]['id'] ?? 0);
        if ($this->group <= 0) {
            throw new RuntimeException('Unsafe Product Media fixture: canonical GROUP id must be positive.');
        }
        $this->company = $this->insert('organizations', [
            'type' => 'COMPANY', 'parent_id' => $this->group, 'code' => $prefix . '-C',
            'name' => 'Product Media test company', 'status' => 'ACTIVE',
        ]);
        $this->writer = $this->actor($prefix, 'writer', $this->writerGroupCode,
            ['view', 'create', 'edit', 'setstate']);
        $this->reader = $this->actor($prefix, 'reader', $this->readerGroupCode,
            ['view']);

        $repository = new LifecycleRepository($db);
        $lifecycle = $repository->insertLifecycle([
            'code' => $prefix . '-LC', 'name' => 'Product Media test', 'status' => 'ACTIVE',
        ]);
        foreach (['ACTIVE', 'INACTIVE', 'CLOSED'] as $index => $code) {
            $state = $db->table('workflow_states')->where('code', $code)->get()->getRowArray();
            $this->states[$code] = $state === null
                ? $repository->insertWorkflowState(['code' => $code, 'name' => $code, 'status' => 'ACTIVE'])
                : (int) $state['id'];
            // Other suites can also leave native Workflow State definitions
            // disabled. Keep required fixture states active only inside this
            // per-test rollback transaction; do not change PBAC's fail-closed
            // behavior for real application records.
            if ($state !== null && strtoupper((string) ($state['status'] ?? '')) !== 'ACTIVE') {
                if (! $db->table('workflow_states')->where('id', $this->states[$code])
                    ->update(['status' => 'ACTIVE'])) {
                    throw new RuntimeException('Unable to activate Product Media fixture state: ' . $code);
                }
            }
            $repository->insertLifecycleState([
                'lifecycle_id' => $lifecycle,
                'state_id' => $this->states[$code],
                'sequence' => $index + 1,
                'is_initial' => $index === 0 ? 1 : 0,
                'is_final' => $code === 'CLOSED' ? 1 : 0,
            ]);
        }
        $repository->insertTransition([
            'lifecycle_id' => $lifecycle,
            'from_state_id' => $this->states['ACTIVE'],
            'to_state_id' => $this->states['INACTIVE'],
            'action_code' => 'setstate',
            'status' => 'ACTIVE',
        ]);
        foreach (['province', 'ward'] as $resource) {
            $db->table('resources')->where('type', $resource)
                ->update(['lifecycle_id' => $lifecycle]);
        }
    }

    public static function clock(): Clock
    {
        return new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-16T02:30:00Z');
            }
        };
    }

    public function deny(string $resource, string $action, int $user, ?int $state = null): void
    {
        $record = $this->db->table('resources')->where('type', $resource)->get()->getRowArray();
        if ($record === null) {
            throw new RuntimeException('Missing seeded Product Media resource: ' . $resource);
        }
        (new PolicyRepository($this->db))->insertWithActions([
            'organization_id' => $this->group,
            'resource_id' => (int) $record['id'],
            'state_id' => $state,
            'participant_type' => 'USER',
            'participant_ref_id' => $user,
        ], [[
            'resource_action_id' => $this->actionId($resource, $action),
            'effect' => 'ABSOLUTE_DENY',
        ]]);
    }

    /** @param array<string, mixed> $data */
    private function insert(string $table, array $data): int
    {
        if (! $this->db->table($table)->insert($data + ['created_at' => '2026-10-16 02:30:00'])) {
            $error = $this->db->error();
            throw new RuntimeException(sprintf(
                'Could not insert Product Media fixture into %s (DB code %s): %s',
                $table,
                (string) ($error['code'] ?? 'unknown'),
                (string) ($error['message'] ?? 'no database diagnostic'),
            ));
        }
        return (int) $this->db->insertID();
    }

    /** @param list<string> $actions */
    private function actor(string $prefix, string $name, string $groupCode, array $actions): int
    {
        $user = $this->insert('users', [
            'username' => strtolower($prefix) . '-' . $name,
            'status' => 'active', 'active' => 1,
        ]);
        $group = $this->insert('groups', [
            'code' => $groupCode, 'name' => $name, 'status' => 'ACTIVE',
        ]);
        $this->insert('auth_groups_users', [
            'user_id' => $user, 'group' => strtolower($groupCode),
        ]);
        foreach (['province', 'ward'] as $resource) {
            foreach ($actions as $action) {
                $this->insert('group_permissions', [
                    'group_id' => $group,
                    'resource_action_id' => $this->actionId($resource, $action),
                ]);
            }
        }
        EligibleActorFixture::assignAtOrganization($this->db, $user, $this->company);
        return $user;
    }

    private function actionId(string $resource, string $action): int
    {
        $row = $this->db->table('resource_actions ra')->select('ra.id')
            ->join('resources r', 'r.id = ra.resource_id')
            ->where('r.type', $resource)->where('ra.code', $action)
            ->get()->getRowArray();
        if ($row === null) {
            throw new RuntimeException('Missing seeded Product Media action: ' . $resource . '.' . $action);
        }
        return (int) $row['id'];
    }
}
