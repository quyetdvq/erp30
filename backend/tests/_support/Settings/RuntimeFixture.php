<?php

declare(strict_types=1);

namespace Tests\Support\Settings;

use App\Database\Seeds\ResourceActionSeeder;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Infrastructure\Time\EvaluationTime;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;
use Tests\Support\Authorization\EligibleActorFixture;

/** Disposable Display Settings authorization + Organization fixture. */
final class RuntimeFixture
{
    public int $group;
    public int $company;
    public int $user;

    /** @param BaseConnection<mixed,mixed> $db */
    public function __construct(private readonly BaseConnection $db, array $actions = ['view', 'edit'])
    {
        (new ResourceActionSeeder(new Database()))->run();
        $this->group = $this->organization('GROUP');
        $this->company = $this->organization('COMPANY', $this->group);
        $this->user = $this->insert('users', ['username' => 'be005-' . uniqid(), 'status' => 'active', 'active' => 1]);
        $code = 'BE005-' . strtoupper(substr(uniqid(), -6));
        $localGroup = $this->insert('groups', ['code' => $code, 'name' => 'BE005 settings', 'status' => 'ACTIVE']);
        $this->insert('auth_groups_users', ['user_id' => $this->user, 'group' => strtolower($code)]);
        $resource = $db->table('resources')->where('type', 'display_setting')->get()->getRowArray();
        if ($resource === null) { throw new RuntimeException('Missing display_setting resource.'); }
        foreach ($actions as $action) {
            $row = $db->table('resource_actions')->where(['resource_id' => (int) $resource['id'], 'code' => $action])->get()->getRowArray();
            if ($row === null) { throw new RuntimeException('Missing display_setting action: ' . $action); }
            $this->insert('group_permissions', ['group_id' => $localGroup, 'resource_action_id' => (int) $row['id']]);
        }
        $organizationResource = $db->table('resources')->where('type', 'organization')->get()->getRowArray();
        if ($organizationResource !== null) {
            $view = $db->table('resource_actions')->where(['resource_id' => (int) $organizationResource['id'], 'code' => 'view'])->get()->getRowArray();
            if ($view !== null) {
                $this->insert('group_permissions', ['group_id' => $localGroup, 'resource_action_id' => (int) $view['id']]);
            }
        }
        EligibleActorFixture::assignAtOrganization($db, $this->user, $this->group);
    }

    public static function clock(): Clock
    {
        return new class implements Clock {
            public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-10-16T07:00:00Z'); }
        };
    }

    public function operation(): OperationContext
    {
        return new OperationContext($this->user, new EvaluationTime(new DateTimeImmutable('2026-10-16T07:00:00Z')), 'be-reorg-005', false);
    }

    public function cleanupRollbackBlockers(): void
    {
        // Display Settings permissions do not trigger migration 000015's Contact rollback blocker.
    }

    private function organization(string $type, ?int $parent = null): int
    {
        return $this->insert('organizations', ['type' => $type, 'parent_id' => $parent, 'code' => 'BE005-O-' . uniqid(), 'name' => $type . ' BE005', 'status' => 'ACTIVE']);
    }

    /** @param array<string,mixed> $data */
    private function insert(string $table, array $data): int
    {
        if (! $this->db->table($table)->insert($data + ['created_at' => '2026-10-16 07:00:00'])) {
            throw new RuntimeException('Could not create Settings fixture row: ' . $table);
        }
        return (int) $this->db->insertID();
    }
}
