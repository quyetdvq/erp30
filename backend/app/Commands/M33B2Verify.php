<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Access\Mutation\PolicyMutationRepository;
use App\Modules\Access\Mutation\PolicyScopeUniqueIndex;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use UnexpectedValueException;

/**
 * M3.3B2 read-only runtime audit: no transaction or DML is issued.
 * These samples do not demonstrate HTTP mutation/rollback parity.
 */
final class M33B2Verify extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'm33b2:verify';
    protected $description = 'Read-only Policy/Policy Action integrity and write-PBAC readiness audit.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        $maxUsers = $this->limit($params[0] ?? '10');
        $maxPolicies = $this->limit($params[1] ?? '50');
        $db = db_connect();
        $usersTable = (string) config('Auth')->tables['users'];
        foreach (['policies', 'policy_actions', 'resources', 'resource_actions',
            'organizations', 'workflow_states', 'lifecycle_states', $usersTable] as $table) {
            if (! $db->tableExists($table)) {
                throw new UnexpectedValueException('M3.3B2 missing required table: ' . $table);
            }
        }
        // Five-dimensional uniqueness must be enforced by a real SQL UNIQUE
        // index; an application-level pre-check alone is unsafe under races.
        // The former SHOW INDEX query returned false on the reported XAMPP
        // setup. Use scoped metadata SELECT and expose driver errors instead.
        // Prefix must match the physical table used by the query builder.
        $index = $db->query(PolicyScopeUniqueIndex::SQL, [
            $db->getPrefix() . 'policies',
            PolicyScopeUniqueIndex::NAME,
        ]);
        if ($index === false) {
            $error = $db->error();
            throw new UnexpectedValueException('SQL failed: Policy unique scope index metadata '
                . '(' . (string) ($error['code'] ?? 'unknown') . '): '
                . (string) ($error['message'] ?? 'unknown database error'));
        }
        PolicyScopeUniqueIndex::assertValid($index->getResultArray());
        CLI::write('PASS: five-dimensional Policy scope UNIQUE index is enforced by MariaDB.', 'green');
        $entry = $db->table('resources')->where('type', 'policy')->get();
        $catalog = $this->one($entry, 'Policy resource');
        if ($catalog === null || $catalog['domain'] !== 'ORGANIZATION') {
            throw new UnexpectedValueException('Policy Resource must exist in ORGANIZATION domain.');
        }
        $actions = $db->table('resource_actions')->select('code')
            ->where('resource_id', $catalog['id'])->get();
        $list = array_column($this->many($actions, 'Policy actions'), 'code');
        foreach (['create', 'edit', 'setstate', 'delete'] as $action) {
            if (! in_array($action, $list, true)) {
                throw new UnexpectedValueException('Missing Policy PBAC action: ' . $action);
            }
        }
        CLI::write('PASS: Policy CREATE/EDIT/SETSTATE/DELETE registered in catalog.', 'green');
        $roots = $db->table('organizations')->select('id')
            ->where('type', 'GROUP')->where('parent_id', null)->get();
        $rootRows = $this->many($roots, 'GROUP root');
        if (count($rootRows) !== 1) {
            throw new UnexpectedValueException('Exactly one GROUP root required for Policy CREATE scope.');
        }
        $rootId = (string) $rootRows[0]['id'];
        $policies = $this->many($db->table('policies')->orderBy('id', 'ASC')
            ->limit($maxPolicies)->get(), 'Policy sample');
        $repository = new PolicyMutationRepository($db);
        $states = new ResourceStateRepository($db);
        $integrity = 0;
        foreach ($policies as $policy) {
            $resource = $repository->resource((string) $policy['resource_id']);
            if ($resource === null) {
                throw new UnexpectedValueException('Policy has missing Resource: ' . $policy['id']);
            }
            $states->assertPolicyState((string) $resource['type'], $policy['state_id']);
            foreach ($repository->actions((string) $policy['id']) as $action) {
                $relation = $db->table('resource_actions')->select('id')
                    ->where('id', $action['resource_action_id'])
                    ->where('resource_id', $policy['resource_id'])->get();
                if ($this->one($relation, 'Policy Action scope') === null) {
                    throw new UnexpectedValueException('Orphan/foreign Action on Policy: ' . $policy['id']);
                }
                if (! in_array((string) $action['effect'], ['ALLOW', 'DENY', 'ABSOLUTE_DENY'], true)) {
                    throw new UnexpectedValueException('Invalid Policy effect: ' . $policy['id']);
                }
                $integrity++;
            }
        }
        CLI::write('PASS: ' . count($policies) . ' sampled Policy State/scope definitions; '
            . $integrity . ' Policy Actions validated.', 'green');
        $userRows = $this->many($db->table($usersTable)->select('id')
            ->where('deleted_at', null)->orderBy('id', 'ASC')
            ->limit($maxUsers)->get(), 'Shield users');
        $decisions = $allow = $deny = $errors = 0;
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        foreach ($userRows as $user) {
            try {
                $access = new UnifiedAccessService(new User(['id' => $user['id']]), $db, $now);
                foreach (['create', 'edit', 'setstate', 'delete'] as $action) {
                    $contexts = $action === 'create' ? [null] : $policies;
                    foreach ($contexts as $row) {
                        $decision = $access->decideMutation('policy', $action, $row,
                            $action === 'create' ? ['organization_id' => $rootId] : []);
                        $decisions++;
                        if ($decision['allowed']) { $allow++; } else { $deny++; }
                    }
                }
            } catch (Throwable $error) {
                $errors++;
                if ($errors <= 10) {
                    CLI::write('ERROR: ' . get_class($error) . ': ' . $error->getMessage(), 'red');
                }
            }
        }
        CLI::write('M3.3B2 POLICY readiness: users=' . count($userRows)
            . ', policies=' . count($policies) . ', checked=' . $decisions
            . ', allowed=' . $allow . ', denied=' . $deny . ', errors=' . $errors);
        CLI::write('NOTE: zero writes; no HTTP/transaction equivalence to running baseline.', 'yellow');
        CLI::write('NOTE: DINKERP_M33B_WRITE_MODE=off remains the default.', 'yellow');
        if ($decisions === 0 || $errors !== 0) {
            throw new UnexpectedValueException('M3.3B2 Policy runtime readiness failed.');
        }
        CLI::write('PASS: sampled Policy write decisions, no database mutations.', 'green');
    }

    private function limit(string $value): int
    {
        if (preg_match('/\A[1-9][0-9]{0,2}\z/D', $value) !== 1 || (int) $value > 50) {
            throw new UnexpectedValueException('M3.3B2 sample limits must be 1..50.');
        }
        return (int) $value;
    }

    /** @return array<string,mixed>|null */
    private function one(mixed $result, string $where): ?array
    {
        if ($result === false) {
            throw new UnexpectedValueException('SQL failed: ' . $where);
        }
        return $result->getRowArray();
    }

    /** @return list<array<string,mixed>> */
    private function many(mixed $result, string $where): array
    {
        if ($result === false) {
            throw new UnexpectedValueException('SQL failed: ' . $where);
        }
        return $result->getResultArray();
    }
}
