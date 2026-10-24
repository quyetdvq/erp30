<?php

declare(strict_types=1);

namespace App\Modules\Access\Management;

use CodeIgniter\Database\BaseConnection;
use UnexpectedValueException;

/**
 * M3.3 bounded, read-only Access catalog persistence. No domain decisions.
 * List SQL does NOT paginate before PBAC; the application service alone may
 * apply filtering/count/pagination after deciding visibility per persisted row.
 */
final class AccessReadRepository
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $connection = null)
    {
        $this->db = $connection ?? db_connect();
    }

    /** @return list<array<string,mixed>> */
    public function all(string $type): array
    {
        return $this->readRows($type, null);
    }

    /** Bounded SQL sample for read-only runtime verification, not pagination. */
    public function sample(string $type, int $limit): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Sample size must be 1..100.');
        }
        return $this->readRows($type, $limit);
    }

    /** @return list<array<string,mixed>> */
    private function readRows(string $type, ?int $limit): array
    {
        $table = AccessReadContract::table($type);
        $query = $type === 'authorization_log'
            ? $this->db->table('authorization_logs al')->select('al.*, r.type AS resource_type')
                ->join('resources r', 'r.id = al.resource_id')
                ->orderBy('al.id', 'ASC')
            : $this->db->table($table)->orderBy('id', 'ASC');
        if ($type === 'policy') {
            // Ignore DB-generated scope hash/columns (not in v1.2.4 contract).
            $query->select('id, organization_id, resource_id, state_id, status, participant_type, participant_ref_id, created_by, updated_by, created_at, updated_at');
        }
        if ($limit !== null) {
            $query->limit($limit);
        }
        return $this->many($query->get(), 'Access list: ' . $type);
    }

    /** @return array<string,mixed>|null */
    public function one(string $type, string $id): ?array
    {
        $table = AccessReadContract::table($type);
        $query = $this->db->table($table)->where('id', $id);
        if ($type === 'policy') {
            $query->select('id, organization_id, resource_id, state_id, status, participant_type, participant_ref_id, created_by, updated_by, created_at, updated_at');
        }
        return $this->first($query->get(), 'Access detail: ' . $type);
    }

    /**
     * Fetch only linked actions for already authorized/filtered page rows.
     * The caller is responsible for gating EVERY parent before calling.
     * @param list<array<string,mixed>> $parents
     * @return list<array<string,mixed>>
     */
    public function pageActions(string $type, array $parents): array
    {
        if ($parents === []) {
            return [];
        }
        if (! in_array($type, ['resource', 'policy'], true)) {
            throw new \InvalidArgumentException('Unsupported action relation.');
        }
        $key = $type === 'resource' ? 'resource_id' : 'policy_id';
        $table = $type === 'resource' ? 'resource_actions' : 'policy_actions';
        $ids = array_values(array_unique(array_map(static fn (array $row): string => (string) $row['id'], $parents)));
        $results = [];
        foreach (array_chunk($ids, 200) as $batch) {
            $items = $this->many($this->db->table($table)->whereIn($key, $batch)
                ->orderBy($key, 'ASC')->orderBy($type === 'resource' ? 'code' : 'id', 'ASC')
                ->orderBy('id', 'ASC')->get(), 'Access action relation');
            array_push($results, ...$items);
        }
        return $results;
    }

    /**
     * Sub-resource is gated through its already-authorized owning parent.
     * No separate all-member/user enumeration is publicly exposed.
     * @return list<array<string,mixed>>
     */
    public function related(string $relation, array $parent): array
    {
        $id = (string) $parent['id'];
        switch ($relation) {
            case 'members':
                $authTables = config('Auth')->tables;
                $builder = $this->db->table($authTables['groups_users'] . ' gu')
                    ->select('u.id, u.username, u.active, u.status')
                    ->join($authTables['users'] . ' u', 'u.id = gu.user_id')
                    ->where('gu.group', strtolower((string) $parent['code']))
                    ->where('u.deleted_at', null)->orderBy('u.id', 'ASC');
                break;
            case 'permissions':
                $builder = $this->db->table('group_permissions')
                    ->where('group_id', $id)->orderBy('resource_action_id', 'ASC');
                break;
            case 'actions':
                $builder = $this->db->table('resource_actions')
                    ->where('resource_id', $id)->orderBy('id', 'ASC');
                break;
            default:
                throw new \InvalidArgumentException('Unsupported Access relation.');
        }
        return $this->many($builder->get(), 'Access relation: ' . $relation);
    }

    /** @return list<array<string,mixed>> */
    private function many(mixed $result, string $where): array
    {
        if ($result === false) {
            $error = $this->db->error();
            throw new UnexpectedValueException($where . ' SQL failed: ' . ($error['message'] ?? 'unknown'));
        }
        return $result->getResultArray();
    }

    /** @return array<string,mixed>|null */
    private function first(mixed $result, string $where): ?array
    {
        if ($result === false) {
            $error = $this->db->error();
            throw new UnexpectedValueException($where . ' SQL failed: ' . ($error['message'] ?? 'unknown'));
        }
        return $result->getRowArray();
    }
}
