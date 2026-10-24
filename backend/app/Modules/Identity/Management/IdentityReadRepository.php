<?php

declare(strict_types=1);

namespace App\Modules\Identity\Management;

use App\Shared\Media\ImageUrl;
use CodeIgniter\Database\BaseConnection;
use UnexpectedValueException;

/** Shield users/direct permissions query boundary. PBAC is handled in IdentityReadService. */
final class IdentityReadRepository
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $connection = null)
    {
        $this->db = $connection ?? db_connect();
    }

    /**
     * Projection equivalent to baseline ShieldSecurityUserRepository. Never
     * return identities.password_hash, private avatar_filename, or permission
     * strings. Authorization is performed on each projected user afterward.
     * @return list<array<string,mixed>>
     */
    public function securityUsers(): array
    {
        $tables = config('Auth')->tables;
        $raw = $this->many($this->db->table($tables['users'] . ' u')
            ->select('u.id, u.username, u.active, u.status, u.created_at, u.full_name AS name, u.avatar_filename, ai.secret AS email')
            ->join($tables['identities'] . ' ai', "ai.user_id = u.id AND ai.type = 'email_password'", 'left')
            ->where('u.deleted_at', null)->orderBy('u.id', 'ASC')->get(), 'Shield security users');
        $users = [];
        foreach ($raw as $row) {
            $id = (string) $row['id'];
            $filename = $row['avatar_filename'] ?? null;
            $users[$id] = [
                'id' => $id, 'username' => (string) ($row['username'] ?? ''),
                'name' => $row['name'] === null ? null : (string) $row['name'],
                'has_avatar' => is_string($filename) && $filename !== '',
                'avatar_url' => ImageUrl::for(is_string($filename) ? $filename : null, 'avatar'),
                'email' => $row['email'] === null ? null : (string) $row['email'],
                'active' => (bool) ($row['active'] ?? false),
                'status' => $row['status'] === null ? null : strtoupper((string) $row['status']),
                'created_at' => $row['created_at'] === null ? null : (string) $row['created_at'],
                'groups' => [], 'direct_permission_count' => 0,
            ];
        }
        if ($users === []) {
            return [];
        }
        $members = $this->many($this->db->table($tables['groups_users'] . ' gu')
            ->select('gu.user_id, gu.group AS code, g.id AS group_id, g.name, g.status')
            ->join('groups g', 'g.code = gu.group', 'left')
            ->orderBy('gu.user_id', 'ASC')->orderBy('gu.group', 'ASC')->get(), 'Shield security user groups');
        foreach ($members as $member) {
            $id = (string) $member['user_id'];
            if (isset($users[$id])) {
                $users[$id]['groups'][] = [
                    'id' => $member['group_id'] === null ? null : (string) $member['group_id'],
                    'code' => (string) ($member['code'] ?? ''),
                    'name' => $member['name'] === null ? null : (string) $member['name'],
                    'status' => $member['status'] === null ? null : strtoupper((string) $member['status']),
                ];
            }
        }
        $counts = $this->many($this->db->table($tables['permissions_users'])
            ->select('user_id, COUNT(*) AS permission_count')->groupBy('user_id')->get(), 'Direct permission counts');
        foreach ($counts as $count) {
            $id = (string) $count['user_id'];
            if (isset($users[$id])) {
                $users[$id]['direct_permission_count'] = (int) $count['permission_count'];
            }
        }
        return array_values($users);
    }

    /**
     * Target-scoped source for GET /users/{id}/permissions. Do not expose
     * accounts deleted in Shield. This row is used ONLY as persisted PBAC
     * facts and never returned to the client as a user profile.
     * @return array<string,mixed>|null
     */
    public function userPermissionTarget(string $id): ?array
    {
        $table = config('Auth')->tables['users'];
        return $this->first($this->db->table($table)
            ->select('id, status, active')->where('id', $id)
            ->where('deleted_at', null)->get(), 'Shield permission target');
    }

    /**
     * Exact ShieldIdentityAdapter::directPermissionTokens semantics: use
     * direct grants only, normalize case, intersect the live ERP Resource /
     * Action catalog, deduplicate and return alphabetically ordered tokens.
     * @return list<string>
     */
    public function directPermissionTokens(string $id): array
    {
        $direct = $this->many($this->db->table(config('Auth')->tables['permissions_users'])
            ->select('permission')->where('user_id', $id)->orderBy('permission', 'ASC')->get(),
            'Shield direct user permissions');
        $catalogRows = $this->many($this->db->table('resource_actions ra')
            ->select('ra.id AS resource_action_id, r.type AS resource_type, ra.code AS action')
            ->join('resources r', 'r.id = ra.resource_id')
            ->orderBy('r.type', 'ASC')->orderBy('ra.code', 'ASC')->get(), 'ERP permission catalog');
        $catalog = [];
        foreach ($catalogRows as $row) {
            $type = strtolower(trim((string) ($row['resource_type'] ?? '')));
            $action = strtolower(trim((string) ($row['action'] ?? '')));
            $actionId = filter_var($row['resource_action_id'] ?? null,
                FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($type === '' || $action === '' || $actionId === false || str_contains($type . $action, '.')) {
                throw new UnexpectedValueException('Invalid ERP base permission catalog mapping.');
            }
            $catalog[$type . '.' . $action] = true;
        }
        $tokens = [];
        foreach ($direct as $row) {
            $permission = strtolower(trim((string) ($row['permission'] ?? '')));
            if ($permission !== '' && isset($catalog[$permission])) {
                $tokens[$permission] = true;
            }
        }
        return array_keys($tokens);
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
