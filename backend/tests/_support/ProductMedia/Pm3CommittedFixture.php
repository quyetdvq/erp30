<?php

declare(strict_types=1);

namespace Tests\Support\ProductMedia;

use App\Repositories\LifecycleRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\PolicyRepository;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Tests\Support\Authorization\EligibleActorFixture;

/**
 * PM-3 has real, committed HTTP fixtures, unlike PM-0's rollback-only fixture.
 * Seed ONLY its required catalog rows on the same guarded connection/transaction
 * and record every insert/change for verified-ID teardown and crash recovery.
 * Do not call ResourceActionSeeder: it seeds unrelated catalog entries and can
 * use a different connection, outside this fixture's transaction/ledger.
 */
final class Pm3CommittedFixture
{
    private const STAMP = '2026-10-19 00:00:00';

    public int $group;
    public int $company;
    public int $writer;
    public int $reader;
    public string $writerGroupCode;
    public string $readerGroupCode;

    /** @var array<string, array{id:int,name:string,domain:mixed,status:string,lifecycle_id:mixed,created_by_fixture:bool}> */
    public array $resourceSnapshots = [];
    /** @var array<string, array{id:int,name:string,status:string,created_by_fixture:bool}> */
    public array $workflowSnapshots = [];
    /** @var list<array{id:int,resource_id:int,type:string,code:string,description:string}> */
    public array $createdActions = [];

    /** @var array<string,int> */
    private array $states = [];

    /** @param BaseConnection<mixed,mixed> $db */
    public function __construct(private readonly BaseConnection $db)
    {
        if (! defined('PRODUCT_MEDIA_GUARDED_RUN') || PRODUCT_MEDIA_GUARDED_RUN !== true
            || getenv('ERP_PM3_WIRE_ENABLE') !== '1') {
            throw new RuntimeException('PM-3 catalog fixture requires the guarded test runner.');
        }
        $prefix = 'PM0-' . bin2hex(random_bytes(6));
        $this->writerGroupCode = $prefix . '-WRITER';
        $this->readerGroupCode = $prefix . '-READER';

        $this->prepareResourceCatalog($prefix);
        $roots = (new OrganizationRepository($db))->findAll(['type' => 'GROUP', 'parent_id' => null]);
        if (count($roots) > 1) {
            throw new RuntimeException('PM-3 refuses multiple canonical GROUP roots.');
        }
        $this->group = $roots === []
            ? $this->insert('organizations', [
                'type' => 'GROUP', 'parent_id' => null, 'code' => $prefix . '-G',
                'name' => 'PM-3 test group', 'status' => 'ACTIVE',
            ])
            : (int) ($roots[0]['id'] ?? 0);
        if ($this->group <= 0) {
            throw new RuntimeException('PM-3 canonical GROUP identity is invalid.');
        }
        $this->company = $this->insert('organizations', [
            'type' => 'COMPANY', 'parent_id' => $this->group, 'code' => $prefix . '-C',
            'name' => 'PM-3 test company', 'status' => 'ACTIVE',
        ]);
        $this->writer = $this->actor($prefix, 'writer', $this->writerGroupCode,
            ['view', 'create', 'edit', 'setstate']);
        $this->reader = $this->actor($prefix, 'reader', $this->readerGroupCode, ['view']);

        $repository = new LifecycleRepository($db);
        $lifecycle = $repository->insertLifecycle([
            'code' => $prefix . '-LC', 'name' => 'PM-3 test lifecycle', 'status' => 'ACTIVE',
        ]);
        foreach (['ACTIVE', 'INACTIVE', 'CLOSED'] as $index => $code) {
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
        foreach (['province', 'ward'] as $type) {
            if (! $db->table('resources')->where('id', $this->resourceSnapshots[$type]['id'])
                ->update(['lifecycle_id' => $lifecycle, 'domain' => 'MASTER'])) {
                throw new RuntimeException('Could not bind PM-3 fixture lifecycle: ' . $type);
            }
        }
    }

    private function prepareResourceCatalog(string $prefix): void
    {
        foreach (['product', 'province', 'ward'] as $type) {
            $row = $this->db->table('resources')->where('type', $type)->get()->getRowArray();
            $created = $row === null;
            if ($created) {
                $name = $prefix . '-RES-' . $type;
                $id = $this->insert('resources', [
                    'type' => $type, 'name' => $name, 'domain' => 'MASTER',
                    'status' => 'ACTIVE',
                ]);
                $row = ['id' => $id, 'name' => $name, 'domain' => 'MASTER',
                    'status' => 'ACTIVE', 'lifecycle_id' => null];
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || ! is_string($row['name'] ?? null)) {
                throw new RuntimeException('PM-3 resource identity is malformed: ' . $type);
            }
            $this->resourceSnapshots[$type] = [
                'id' => $id,
                'name' => $row['name'],
                'domain' => $row['domain'] ?? null,
                'status' => (string) $row['status'],
                'lifecycle_id' => $row['lifecycle_id'] ?? null,
                'created_by_fixture' => $created,
            ];
        }

        foreach (['product' => ['view', 'edit'],
                  'province' => ['view', 'create', 'edit', 'setstate'],
                  'ward' => ['view', 'create', 'edit', 'setstate']] as $type => $actions) {
            $resourceId = $this->resourceSnapshots[$type]['id'];
            foreach ($actions as $code) {
                $existing = $this->db->table('resource_actions')
                    ->where('resource_id', $resourceId)->where('code', $code)
                    ->get()->getRowArray();
                if ($existing !== null) { continue; }
                $description = $prefix . '-ACTION-' . $type . '-' . $code;
                $id = $this->insert('resource_actions', [
                    'resource_id' => $resourceId, 'code' => $code,
                    'description' => $description,
                ]);
                $this->createdActions[] = [
                    'id' => $id, 'resource_id' => $resourceId, 'type' => $type,
                    'code' => $code, 'description' => $description,
                ];
            }
        }
        foreach (['ACTIVE', 'INACTIVE', 'CLOSED'] as $code) {
            $row = $this->db->table('workflow_states')->where('code', $code)->get()->getRowArray();
            $created = $row === null;
            if ($created) {
                $name = $prefix . '-STATE-' . $code;
                $id = $this->insert('workflow_states', [
                    'code' => $code, 'name' => $name, 'status' => 'ACTIVE',
                ]);
                $row = ['id' => $id, 'name' => $name, 'status' => 'ACTIVE'];
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || ! is_string($row['name'] ?? null)) {
                throw new RuntimeException('PM-3 workflow state identity is malformed: ' . $code);
            }
            $this->workflowSnapshots[$code] = [
                'id' => $id, 'name' => $row['name'], 'status' => (string) $row['status'],
                'created_by_fixture' => $created,
            ];
            $this->states[$code] = $id;
            if (strtoupper((string) $row['status']) !== 'ACTIVE'
                && ! $this->db->table('workflow_states')->where('id', $id)->update(['status' => 'ACTIVE'])) {
                throw new RuntimeException('PM-3 could not activate fixture workflow state: ' . $code);
            }
        }
        $product = $this->resourceSnapshots['product'];
        if (! $this->db->table('resources')->where('id', $product['id'])
            ->update(['lifecycle_id' => null, 'status' => 'ACTIVE', 'domain' => 'MASTER'])) {
            throw new RuntimeException('PM-3 could not isolate native Product resource state.');
        }
    }

    public function deny(string $resource, string $action, int $user, ?int $state = null): void
    {
        $snapshot = $this->resourceSnapshots[$resource] ?? null;
        if ($snapshot === null) {
            throw new RuntimeException('PM-3 deny refers to an unseeded resource.');
        }
        (new PolicyRepository($this->db))->insertWithActions([
            'organization_id' => $this->group,
            'resource_id' => $snapshot['id'], 'state_id' => $state,
            'participant_type' => 'USER', 'participant_ref_id' => $user,
        ], [[
            'resource_action_id' => $this->actionId($resource, $action),
            'effect' => 'ABSOLUTE_DENY',
        ]]);
    }

    /** @param array<string,mixed> $data */
    private function insert(string $table, array $data): int
    {
        if (! $this->db->table($table)->insert($data + ['created_at' => self::STAMP])) {
            throw new RuntimeException('PM-3 could not insert transaction-scoped fixture row into ' . $table);
        }
        $id = (int) $this->db->insertID();
        if ($id < 1) { throw new RuntimeException('Invalid PM-3 fixture insert identity: ' . $table); }
        return $id;
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
                $this->grantGroupPermission($group, $this->actionId($resource, $action));
            }
        }
        EligibleActorFixture::assignAtOrganization($this->db, $user, $this->company);
        return $user;
    }

    /**
     * group_permissions has a composite (group_id, resource_action_id) key,
     * NOT an auto-increment id. Never use insertID() for this link table.
     */
    private function grantGroupPermission(int $groupId, int $resourceActionId): void
    {
        if ($groupId < 1 || $resourceActionId < 1) {
            throw new RuntimeException('Invalid PM-3 group permission composite identity.');
        }
        if (! $this->db->table('group_permissions')->insert([
            'group_id' => $groupId,
            'resource_action_id' => $resourceActionId,
            'created_at' => self::STAMP,
        ])) {
            throw new RuntimeException('PM-3 could not insert group permission.');
        }
        // Prove the composite identity exists inside the guarded transaction.
        $row = $this->db->table('group_permissions')
            ->select('group_id, resource_action_id')
            ->where('group_id', $groupId)
            ->where('resource_action_id', $resourceActionId)
            ->get()->getRowArray();
        if (! is_array($row) || (int) ($row['group_id'] ?? 0) !== $groupId
            || (int) ($row['resource_action_id'] ?? 0) !== $resourceActionId) {
            throw new RuntimeException('PM-3 group permission composite identity verification failed.');
        }
    }

    private function actionId(string $resource, string $action): int
    {
        $row = $this->db->table('resource_actions')
            ->select('id')
            ->where('resource_id', $this->resourceSnapshots[$resource]['id'])
            ->where('code', $action)->get()->getRowArray();
        if ($row === null || (int) ($row['id'] ?? 0) < 1) {
            throw new RuntimeException('Missing PM-3 fixture action: ' . $resource . '.' . $action);
        }
        return (int) $row['id'];
    }
}
