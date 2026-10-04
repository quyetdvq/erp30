<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

final class Admin extends BaseController
{
    public function options(): ResponseInterface
    {
        return $this->response->setStatusCode(204);
    }

    public function users(): ResponseInterface
    {
        if ($guard = $this->adminGuard()) {
            return $guard;
        }

        $authTables = config('Auth')->tables;
        $rows = $this->db()->table($authTables['users'] . ' u')
            ->select('u.id, u.username, u.status, u.active, u.created_at, i.secret AS email')
            ->join($authTables['identities'] . ' i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->where('u.deleted_at', null)
            ->orderBy('u.id', 'DESC')
            ->get()
            ->getResultArray();

        $groups = $this->groupMap(array_map(static fn (array $row): int => (int) $row['id'], $rows));
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['active'] = (bool) $row['active'];
            $row['groups'] = $groups[$row['id']] ?? [];
        }
        unset($row);

        return $this->response->setJSON(['data' => $rows]);
    }

    public function createUser(): ResponseInterface
    {
        if ($guard = $this->adminGuard()) {
            return $guard;
        }

        $payload = $this->request->getJSON(true) ?? [];
        $username = trim((string) ($payload['username'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $groups = $this->normaliseGroups($payload['groups'] ?? []);

        if ($username === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => 'INVALID_USER',
                'message' => 'Tài khoản, email và mật khẩu tối thiểu 8 ký tự là bắt buộc.',
            ]);
        }

        if (! $this->validGroupCodes($groups)) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'UNKNOWN_GROUP']);
        }

        $users = model(UserModel::class);
        $user = $users->createNewUser([
            'username' => $username,
            'active' => true,
            'status' => 'active',
        ]);
        $user->setEmail($email);
        $user->setPassword($password);

        try {
            $this->db()->transStart();
            $users->save($user);
            $id = (int) $users->getInsertID();
            $this->syncUserGroups($id, $groups !== [] ? $groups : ['user']);
            $this->db()->transComplete();
        } catch (\Throwable $exception) {
            $this->db()->transRollback();

            return $this->response->setStatusCode(422)->setJSON([
                'error' => 'USER_CREATE_FAILED',
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->response->setStatusCode(201)->setJSON(['id' => $id]);
    }

    public function updateUser(int $id): ResponseInterface
    {
        if ($guard = $this->adminGuard()) {
            return $guard;
        }

        $users = model(UserModel::class);
        $user = $users->findById($id);
        if (! $user instanceof User) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'USER_NOT_FOUND']);
        }

        $payload = $this->request->getJSON(true) ?? [];
        if (array_key_exists('username', $payload)) {
            $user->username = trim((string) $payload['username']);
        }
        if (array_key_exists('email', $payload) && filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            $user->setEmail(trim((string) $payload['email']));
        }
        if (! empty($payload['password'])) {
            $user->setPassword((string) $payload['password']);
        }
        if (array_key_exists('active', $payload)) {
            $user->active = (bool) $payload['active'];
        }

        $groups = array_key_exists('groups', $payload) ? $this->normaliseGroups($payload['groups']) : null;
        if ($groups !== null && ! $this->validGroupCodes($groups)) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'UNKNOWN_GROUP']);
        }

        try {
            $this->db()->transStart();
            $users->save($user);
            if ($groups !== null) {
                $this->syncUserGroups($id, $groups);
            }
            $this->db()->transComplete();
        } catch (\Throwable $exception) {
            $this->db()->transRollback();

            return $this->response->setStatusCode(422)->setJSON([
                'error' => 'USER_UPDATE_FAILED',
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    public function groups(): ResponseInterface
    {
        if ($guard = $this->adminGuard()) {
            return $guard;
        }

        $rows = $this->db()->table('groups g')
            ->select('g.id, g.code, g.name, g.description, g.status, g.created_at, COUNT(gp.resource_action_id) AS permission_count')
            ->join('group_permissions gp', 'gp.group_id = g.id', 'left')
            ->groupBy('g.id')
            ->orderBy('g.name', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['permission_count'] = (int) $row['permission_count'];
        }
        unset($row);

        return $this->response->setJSON(['data' => $rows]);
    }

    public function createGroup(): ResponseInterface
    {
        return $this->saveGroup();
    }

    public function updateGroup(int $id): ResponseInterface
    {
        return $this->saveGroup($id);
    }

    public function groupPermissions(int $id): ResponseInterface
    {
        if ($guard = $this->adminGuard()) {
            return $guard;
        }

        $assigned = $this->db()->table('group_permissions')
            ->select('resource_action_id')
            ->where('group_id', $id)
            ->get()
            ->getResultArray();

        $catalog = $this->db()->table('resource_actions ra')
            ->select('ra.id, r.type AS resource, r.name AS resource_name, ra.code AS action, ra.description')
            ->join('resources r', 'r.id = ra.resource_id')
            ->orderBy('r.type', 'ASC')
            ->orderBy('ra.code', 'ASC')
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'data' => $catalog,
            'assigned' => array_map(static fn (array $row): int => (int) $row['resource_action_id'], $assigned),
        ]);
    }

    public function updateGroupPermissions(int $id): ResponseInterface
    {
        if ($guard = $this->adminGuard()) {
            return $guard;
        }

        $payload = $this->request->getJSON(true) ?? [];
        $actionIds = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['resource_action_ids'] ?? [])))));
        $valid = $this->db()->table('resource_actions')->select('id')->whereIn('id', $actionIds)->get()->getResultArray();
        if (count($valid) !== count($actionIds)) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'UNKNOWN_RESOURCE_ACTION']);
        }

        $this->db()->transStart();
        $this->db()->table('group_permissions')->where('group_id', $id)->delete();
        foreach ($actionIds as $actionId) {
            $this->db()->table('group_permissions')->insert([
                'group_id' => $id,
                'resource_action_id' => $actionId,
            ]);
        }
        $this->db()->transComplete();

        return $this->response->setJSON(['ok' => true]);
    }

    public function organizations(): ResponseInterface
    {
        if ($guard = $this->adminGuard()) {
            return $guard;
        }

        $rows = $this->db()->table('organizations o')
            ->select('o.id, o.parent_id, o.type, o.code, o.name, o.status, o.active_from, o.active_to, p.name AS parent_name')
            ->join('organizations p', 'p.id = o.parent_id', 'left')
            ->orderBy('o.name', 'ASC')
            ->get()
            ->getResultArray();

        return $this->response->setJSON(['data' => array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['parent_id'] = $row['parent_id'] === null ? null : (int) $row['parent_id'];
            return $row;
        }, $rows)]);
    }

    public function createOrganization(): ResponseInterface
    {
        return $this->saveOrganization();
    }

    public function updateOrganization(int $id): ResponseInterface
    {
        return $this->saveOrganization($id);
    }

    private function saveGroup(?int $id = null): ResponseInterface
    {
        if ($guard = $this->adminGuard()) {
            return $guard;
        }

        $payload = $this->request->getJSON(true) ?? [];
        $code = strtolower(trim((string) ($payload['code'] ?? '')));
        $name = trim((string) ($payload['name'] ?? ''));
        if ($code === '' || $name === '') {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'INVALID_GROUP']);
        }

        $data = [
            'code' => $code,
            'name' => $name,
            'description' => $payload['description'] ?? null,
            'status' => strtoupper((string) ($payload['status'] ?? 'ACTIVE')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $builder = $this->db()->table('groups');
        if ($id === null) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $builder->insert($data);
            return $this->response->setStatusCode(201)->setJSON(['id' => (int) $this->db()->insertID()]);
        }

        $builder->where('id', $id)->update($data);

        return $this->response->setJSON(['ok' => true]);
    }

    private function saveOrganization(?int $id = null): ResponseInterface
    {
        if ($guard = $this->adminGuard()) {
            return $guard;
        }

        $payload = $this->request->getJSON(true) ?? [];
        $type = strtoupper(trim((string) ($payload['type'] ?? 'COMPANY')));
        $code = strtoupper(trim((string) ($payload['code'] ?? '')));
        $name = trim((string) ($payload['name'] ?? ''));
        $allowedTypes = ['GROUP', 'COMPANY', 'BRANCH', 'PLANT', 'WAREHOUSE'];
        if (! in_array($type, $allowedTypes, true) || $code === '' || $name === '') {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'INVALID_ORGANIZATION']);
        }

        $data = [
            'parent_id' => ($payload['parent_id'] ?? null) ?: null,
            'type' => $type,
            'code' => $code,
            'name' => $name,
            'status' => strtoupper((string) ($payload['status'] ?? 'ACTIVE')),
            'active_from' => $payload['active_from'] ?? null,
            'active_to' => $payload['active_to'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $builder = $this->db()->table('organizations');
        if ($id === null) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $builder->insert($data);
            return $this->response->setStatusCode(201)->setJSON(['id' => (int) $this->db()->insertID()]);
        }

        $builder->where('id', $id)->update($data);

        return $this->response->setJSON(['ok' => true]);
    }

    private function adminGuard(): ?ResponseInterface
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'UNAUTHENTICATED']);
        }

        $table = config('Auth')->tables['groups_users'];
        $allowed = $this->db()->table($table)
            ->where('user_id', (int) $user->id)
            ->whereIn('group', ['orgadmin', 'systemadmin'])
            ->countAllResults() > 0;
        if (! $allowed) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'ADMIN_REQUIRED']);
        }

        return null;
    }

    /** @return \CodeIgniter\Database\BaseConnection */
    private function db()
    {
        return db_connect();
    }

    /** @param list<int> $userIds @return array<int, list<string>> */
    private function groupMap(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $rows = $this->db()->table(config('Auth')->tables['groups_users'])
            ->select('user_id, group')
            ->whereIn('user_id', $userIds)
            ->get()
            ->getResultArray();
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['user_id']][] = (string) $row['group'];
        }
        return $map;
    }

    /** @param mixed $groups @return list<string> */
    private function normaliseGroups(mixed $groups): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $group): string => strtolower(trim((string) $group)),
            is_array($groups) ? $groups : [],
        ))));
    }

    /** @param list<string> $codes */
    private function validGroupCodes(array $codes): bool
    {
        if ($codes === []) {
            return true;
        }
        return count($codes) === (int) $this->db()->table('groups')->whereIn('code', $codes)->countAllResults();
    }

    /** @param list<string> $codes */
    private function syncUserGroups(int $userId, array $codes): void
    {
        $table = config('Auth')->tables['groups_users'];
        $this->db()->table($table)->where('user_id', $userId)->delete();
        foreach ($codes as $code) {
            $this->db()->table($table)->insert([
                'user_id' => $userId,
                'group' => $code,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
