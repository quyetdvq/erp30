<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Workflow\Mutation\WorkflowMutationRules;
use App\Modules\Access\ResourceStateCatalog;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use UnexpectedValueException;

/** Strict READ-ONLY Workflow schema, configuration and mutation-PBAC audit. */
final class M33B4Verify extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'm33b4:verify';
    protected $description = 'Audit Workflow State/Lifecycle mutations without database writes.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        $actorLimit = $this->limit($params[0] ?? '10');
        $targetLimit = $this->limit($params[1] ?? '30');
        $db = db_connect();
        foreach (['resources', 'resource_actions', 'workflow_states', 'lifecycles',
            'lifecycle_states', 'lifecycle_transitions', 'policies', 'policy_actions',
            'organizations', 'groups', 'group_permissions', 'employees', 'employee_assignments'] as $table) {
            if (! $db->tableExists($table)) {
                throw new UnexpectedValueException('M3.3B4 required table missing: ' . $table);
            }
        }
        foreach (['workflow_state','lifecycle'] as $type) {
            $resource = $this->one($db->table('resources')->select('id, domain, status')
                ->where('type', $type)->get(), $type . ' Resource');
            if ($resource === null || ($resource['domain'] ?? '') !== 'SYSTEM') {
                throw new UnexpectedValueException('Workflow Resource must be in SYSTEM domain: ' . $type);
            }
            $actions = $this->many($db->table('resource_actions')->select('code')
                ->where('resource_id', $resource['id'])->get(), $type . ' Actions');
            $codes = array_fill_keys(array_column($actions, 'code'), true);
            foreach (['view','create','edit','setstate'] as $action) {
                if (! isset($codes[$action])) {
                    throw new UnexpectedValueException('Missing Workflow Action: ' . $type . '.' . $action);
                }
            }
        }
        if (count(ResourceStateCatalog::independentTypes()) !== 10) {
            throw new UnexpectedValueException('Official ten Resource State exceptions changed.');
        }
        CLI::write('PASS: Workflow/Lifecycle mutation Actions and 10 exception catalog verified.', 'green');
        $states = $this->many($db->table('workflow_states')->select('id, status')
            ->orderBy('id', 'ASC')->limit($targetLimit)->get(), 'workflow state sample');
        foreach ($states as $state) {
            if (! in_array($state['status'], ['ACTIVE', 'INACTIVE'], true)) {
                throw new UnexpectedValueException('Invalid persisted Workflow State: ' . $state['id']);
            }
        }
        $lifecycles = $this->many($db->table('lifecycles')->select('id, status')
            ->orderBy('id', 'ASC')->limit($targetLimit)->get(), 'lifecycle sample');
        $checkedStates = 0;
        $checkedTransitions = 0;
        foreach ($lifecycles as $lifecycle) {
            if (! in_array($lifecycle['status'], ['ACTIVE', 'INACTIVE'], true)) {
                throw new UnexpectedValueException('Invalid Lifecycle status: ' . $lifecycle['id']);
            }
            $id = (string) $lifecycle['id'];
            $members = $this->many($db->table('lifecycle_states')->where('lifecycle_id', $id)->get(), 'lifecycle states');
            $transitions = $this->many($db->table('lifecycle_transitions')->where('lifecycle_id', $id)->get(), 'lifecycle transitions');
            $checkedStates += count($members);
            $checkedTransitions += count($transitions);
            $active = [];
            foreach ($members as $member) {
                $active[(string) $member['state_id']] = $this->one($db->table('workflow_states')
                    ->select('id')->where('id', $member['state_id'])->where('status', 'ACTIVE')
                    ->get(), 'member Workflow State') !== null;
            }
            WorkflowMutationRules::assertConfiguration($lifecycle, $members, $transitions,
                static fn (string $stateId): bool => $active[$stateId] ?? false);
        }
        CLI::write('PASS: ' . count($lifecycles) . ' sampled Lifecycles; ' . $checkedStates
            . ' memberships; ' . $checkedTransitions . ' transitions validated.', 'green');

        $auth = config('Auth')->tables;
        $users = $this->many($db->table($auth['users'])->select('id, status, active')
            ->where('deleted_at', null)->orderBy('id', 'ASC')->limit($actorLimit)->get(), 'actors');
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $checked = 0; $allowed = 0; $denied = 0; $errors = 0;
        foreach ($users as $user) {
            try {
                $access = new UnifiedAccessService(new User(['id' => $user['id']]), $db, $now);
                $decisions = [
                    $access->decideMutation('workflow_state', 'create', null),
                    $access->decideMutation('lifecycle', 'create', null),
                ];
                foreach ($states as $target) {
                    foreach (['edit','setstate'] as $action) {
                        $decisions[] = $access->decideMutation('workflow_state', $action, $target);
                    }
                }
                foreach ($lifecycles as $target) {
                    foreach (['edit','setstate'] as $action) {
                        $decisions[] = $access->decideMutation('lifecycle', $action, $target);
                    }
                }
                foreach ($decisions as $decision) {
                    ++$checked;
                    if ($decision['allowed']) { ++$allowed; } else { ++$denied; }
                }
            } catch (Throwable $exception) {
                ++$errors;
                if ($errors <= 5) {
                    CLI::write('ERROR: ' . get_class($exception) . ': ' . $exception->getMessage(), 'red');
                }
            }
        }
        CLI::write('M3.3B4 WORKFLOW readiness: actors=' . count($users)
            . ', workflow_states=' . count($states) . ', lifecycles=' . count($lifecycles)
            . ', checked=' . $checked . ', allowed=' . $allowed
            . ', denied=' . $denied . ', errors=' . $errors);
        CLI::write('NOTE: read-only; no HTTP/baseline transaction or mutation parity.', 'yellow');
        CLI::write('NOTE: DINKERP_M33B_WRITE_MODE=off remains default.', 'yellow');
        if ($checked === 0 || $errors > 0) {
            throw new UnexpectedValueException('M3.3B4 Workflow PBAC audit failed.');
        }
        CLI::write('PASS: sampled Workflow mutation-PBAC decisions without writes.', 'green');
    }

    private function limit(string $raw): int
    {
        if (preg_match('/\A[1-9][0-9]{0,2}\z/D', $raw) !== 1 || (int) $raw > 50) {
            throw new UnexpectedValueException('Sample limits must be integers between 1 and 50.');
        }
        return (int) $raw;
    }

    /** @return array<string,mixed>|null */
    private function one(mixed $result, string $label): ?array
    {
        if ($result === false) { throw new UnexpectedValueException('SQL failed: ' . $label); }
        return $result->getRowArray();
    }

    /** @return list<array<string,mixed>> */
    private function many(mixed $result, string $label): array
    {
        if ($result === false) { throw new UnexpectedValueException('SQL failed: ' . $label); }
        return $result->getResultArray();
    }
}
