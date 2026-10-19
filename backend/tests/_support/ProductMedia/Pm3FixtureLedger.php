<?php

declare(strict_types=1);

namespace Tests\Support\ProductMedia;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Narrow cleanup of committed synthetic PM-3 rows for real HTTP subprocesses.
 * The ledger contains no password, cookie, token hash or HTTP request body.
 */
final class Pm3FixtureLedger
{
    /** @param array<string,mixed> $data */
    public static function save(string $path, array $data): void
    {
        $bytes = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $bytes, LOCK_EX) === false || ! rename($tmp, $path)) {
            throw new RuntimeException('Could not persist PM-3 synthetic fixture recovery ledger.');
        }
    }

    /** @return array<string,mixed> */
    public static function load(string $path): array
    {
        if (! is_file($path) || is_link($path)) { throw new RuntimeException('Invalid PM-3 fixture ledger.'); }
        $result = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        // v1 ledgers only existed when a GROUP root already existed; v2
        // records whether the fixture itself created the root, for safe cleanup.
        if (! is_array($result) || ! in_array($result['version'] ?? null, [1, 2], true)
            || ! in_array($result['status'] ?? null, ['PENDING', 'CLEANED'], true)
            || ! is_string($result['run_token'] ?? null)
            || preg_match('/\A[a-f0-9]{32}\z/D', $result['run_token']) !== 1) {
            throw new RuntimeException('Unsupported PM-3 fixture ledger format.');
        }
        return $result;
    }

    /** @param BaseConnection<mixed,mixed> $db
     *  @param array<string,mixed> $ledger
     */
    public static function cleanup(BaseConnection $db, array $ledger): void
    {
        if (($ledger['status'] ?? null) === 'CLEANED') { return; }
        $rows = $ledger['rows'] ?? null;
        if (! is_array($rows)) { throw new RuntimeException('PM-3 fixture ledger lacks row identities.'); }
        foreach (['product', 'company', 'lifecycle'] as $name) {
            if (! is_array($rows[$name] ?? null)
                || ! is_int($rows[$name]['id'] ?? null) || $rows[$name]['id'] < 1
                || ! is_string($rows[$name]['code'] ?? null)
                || ! str_starts_with($rows[$name]['code'], $name === 'product' ? 'PM3-' : 'PM0-')) {
                throw new RuntimeException('Invalid synthetic PM-3 fixture ' . $name . ' identity.');
            }
        }
        if (! is_array($rows['actors'] ?? null) || count($rows['actors']) !== 2
            || ! is_array($rows['groups'] ?? null) || count($rows['groups']) !== 2
            || ! is_array($rows['resources'] ?? null) || count($rows['resources']) !== 3) {
            throw new RuntimeException('Incomplete PM-3 fixture recovery identities.');
        }
        $version = $ledger['version'] ?? null;
        $root = null;
        if ($version === 2) {
            $root = $rows['group_root'] ?? null;
            if (! is_array($root) || ! is_int($root['id'] ?? null) || $root['id'] < 1
                || ! is_string($root['code'] ?? null) || $root['code'] === ''
                || ! is_bool($root['created_by_fixture'] ?? null)) {
                throw new RuntimeException('Invalid PM-3 GROUP root recovery ownership.');
            }
            // The canonical GROUP root may have existed before the run. Only a
            // root with EXACTLY this fixture's random PM0-* code may be deleted.
            $expectedOwnedCode = substr($rows['company']['code'], 0, -strlen('-C')) . '-G';
            if ($root['created_by_fixture'] && $root['code'] !== $expectedOwnedCode) {
                throw new RuntimeException('Refusing to delete GROUP root not owned by this fixture.');
            }
            $group = $db->table('organizations')->select('code, type, parent_id')
                ->where('id', $root['id'])->get()->getRowArray();
            if (! is_array($group) || ($group['code'] ?? null) !== $root['code']
                || ($group['type'] ?? null) !== 'GROUP' || ($group['parent_id'] ?? null) !== null) {
                throw new RuntimeException('PM-3 GROUP root recovery identity mismatch; no deletion permitted.');
            }
        } elseif ($version !== 1) {
            throw new RuntimeException('Unsupported PM-3 GROUP root ledger version.');
        }
        foreach (['product' => 'products', 'company' => 'organizations', 'lifecycle' => 'lifecycles'] as $key => $table) {
            self::assertIdentity($db, $table, $rows[$key]['id'], 'code', $rows[$key]['code']);
        }
        $actorIds = [];
        foreach ($rows['actors'] as $actor) {
            if (! is_array($actor) || ! is_int($actor['id'] ?? null) || $actor['id'] < 1
                || ! is_string($actor['username'] ?? null) || ! str_starts_with($actor['username'], 'pm0-')) {
                throw new RuntimeException('Invalid PM-3 synthetic Shield actor identity.');
            }
            self::assertIdentity($db, 'users', $actor['id'], 'username', $actor['username']);
            $actorIds[] = $actor['id'];
        }
        $groupIds = [];
        foreach ($rows['groups'] as $group) {
            if (! is_array($group) || ! is_int($group['id'] ?? null) || $group['id'] < 1
                || ! is_string($group['code'] ?? null) || ! str_starts_with($group['code'], 'PM0-')) {
                throw new RuntimeException('Invalid PM-3 synthetic permission group identity.');
            }
            self::assertIdentity($db, 'groups', $group['id'], 'code', $group['code']);
            $groupIds[] = $group['id'];
        }
        foreach (['product','province','ward'] as $type) {
            if (! is_array($rows['resources'][$type] ?? null)
                || ! is_int($rows['resources'][$type]['id'] ?? null) || $rows['resources'][$type]['id'] < 1) {
                throw new RuntimeException('Missing PM-3 original resource snapshot.');
            }
            self::assertIdentity($db, 'resources', $rows['resources'][$type]['id'], 'type', $type);
        }
        if (! is_array($rows['workflow_states'] ?? null) || count($rows['workflow_states']) !== 3) {
            throw new RuntimeException('Missing PM-3 workflow-state restore checkpoint.');
        }
        foreach (['ACTIVE','INACTIVE','CLOSED'] as $code) {
            if (! is_array($rows['workflow_states'][$code] ?? null)
                || ! is_int($rows['workflow_states'][$code]['id'] ?? null)
                || ! is_string($rows['workflow_states'][$code]['status'] ?? null)) {
                throw new RuntimeException('Incomplete PM-3 workflow-state snapshot: ' . $code);
            }
            self::assertIdentity($db, 'workflow_states', $rows['workflow_states'][$code]['id'], 'code', $code);
        }
        $actual = $db->query('SELECT DATABASE() AS name, @@port AS port')->getRowArray();
        if (($actual['name'] ?? null) !== 'erp_test' || (int) ($actual['port'] ?? 0) !== 3306) {
            throw new RuntimeException('PM-3 cleanup refused unsafe runtime database.');
        }

        if (! $db->transBegin()) { throw new RuntimeException('Could not begin PM-3 cleanup.'); }
        try {
            $policies = $db->table('policies')->select('id')->where('participant_type', 'USER')
                ->whereIn('participant_ref_id', $actorIds)->get()->getResultArray();
            $policyIds = array_map(static fn (array $x): int => (int) $x['id'], $policies);
            if ($policyIds !== []) {
                $db->table('policy_actions')->whereIn('policy_id', $policyIds)->delete();
                $db->table('policies')->whereIn('id', $policyIds)->delete();
            }
            $db->table('products')->where('id', $rows['product']['id'])->delete();
            foreach ($rows['resources'] as $state) {
                $db->table('resources')->where('id', $state['id'])->update([
                    'status' => $state['status'], 'lifecycle_id' => $state['lifecycle_id'],
                ]);
            }
            $db->table('lifecycle_transitions')->where('lifecycle_id', $rows['lifecycle']['id'])->delete();
            $db->table('lifecycle_states')->where('lifecycle_id', $rows['lifecycle']['id'])->delete();
            $db->table('lifecycles')->where('id', $rows['lifecycle']['id'])->delete();
            foreach ($rows['workflow_states'] as $state) {
                $db->table('workflow_states')->where('id', $state['id'])->update(['status' => $state['status']]);
            }
            $employees = $db->table('employees')->select('id')->whereIn('user_id', $actorIds)->get()->getResultArray();
            $employeeIds = array_map(static fn (array $x): int => (int) $x['id'], $employees);
            if ($employeeIds !== []) {
                $db->table('employee_assignments')->whereIn('employee_id', $employeeIds)->delete();
            }
            $db->table('employees')->whereIn('user_id', $actorIds)->delete();
            // A real Shield login can create login-attempt audit rows. Those
            // belong to these ephemeral actors and must not be left behind.
            if ($db->tableExists('auth_logins')) {
                $db->table('auth_logins')->whereIn('user_id', $actorIds)->delete();
            }
            $db->table('auth_identities')->whereIn('user_id', $actorIds)->delete();
            $db->table('auth_groups_users')->whereIn('user_id', $actorIds)->delete();
            $db->table('group_permissions')->whereIn('group_id', $groupIds)->delete();
            $db->table('groups')->whereIn('id', $groupIds)->delete();
            $db->table('users')->whereIn('id', $actorIds)->delete();
            $db->table('organizations')->where('id', $rows['company']['id'])->delete();
            if ($root !== null && $root['created_by_fixture']) {
                // A concurrent, unrelated child must block cleanup rather
                // than be removed by a broad deletion or cascade.
                if ($db->table('organizations')->where('parent_id', $root['id'])->countAllResults() !== 0) {
                    throw new RuntimeException('PM-3 ephemeral GROUP has unexpected children; recovery is blocked.');
                }
                if ($db->table('organizations')->where('id', $root['id'])
                    ->where('code', $root['code'])->where('type', 'GROUP')
                    ->where('parent_id', null)->delete() === false) {
                    throw new RuntimeException('Could not remove PM-3-owned ephemeral GROUP root.');
                }
            }
            // Verify post-cleanup invariants INSIDE this transaction, so any
            // failure still permits rollback and keeps the PENDING ledger
            // recoverable rather than reporting a false partial cleanup.
            foreach (['product' => 'products', 'company' => 'organizations', 'lifecycle' => 'lifecycles'] as $key => $table) {
                if ($db->table($table)->where('id', $rows[$key]['id'])->countAllResults() !== 0) {
                    throw new RuntimeException('PM-3 cleanup verification failed for ' . $table . '.');
                }
            }
            foreach ($rows['actors'] as $actor) {
                if ($db->table('users')->where('id', $actor['id'])->countAllResults() !== 0) {
                    throw new RuntimeException('PM-3 Shield actor cleanup verification failed.');
                }
            }
            if ($root !== null) {
                $remaining = $db->table('organizations')->select('id, code, type, parent_id')
                    ->where('id', $root['id'])->get()->getRowArray();
                if ($root['created_by_fixture'] ? $remaining !== null
                    : ! is_array($remaining) || ($remaining['code'] ?? null) !== $root['code']
                        || ($remaining['type'] ?? null) !== 'GROUP' || ($remaining['parent_id'] ?? null) !== null) {
                    throw new RuntimeException('PM-3 GROUP root post-cleanup invariant failed.');
                }
            }
            foreach ($rows['resources'] as $state) {
                $row = $db->table('resources')->where('id', $state['id'])->get()->getRowArray();
                if ($row === null || $row['status'] !== $state['status']
                    || ($row['lifecycle_id'] ?? null) != $state['lifecycle_id']) {
                    throw new RuntimeException('PM-3 resource snapshot was not restored.');
                }
            }
            if ($db->transStatus() === false || ! $db->transCommit()) {
                throw new RuntimeException('PM-3 cleanup did not commit.');
            }
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    /** @param BaseConnection<mixed,mixed> $db */
    private static function assertIdentity(BaseConnection $db, string $table, int $id, string $column, string $value): void
    {
        $row = $db->table($table)->select($column)->where('id', $id)->get()->getRowArray();
        if (! is_array($row) || (string) ($row[$column] ?? '') !== $value) {
            throw new RuntimeException('PM-3 cleanup identity mismatch: ' . $table . '/' . $id . '. Stop; do not delete.');
        }
    }
}
