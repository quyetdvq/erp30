<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use UnexpectedValueException;

/**
 * Safe M3.3B3 user/Shield migration readiness check: READ-ONLY.
 * No password hashes are selected, returned, or logged. No DB writes.
 */
final class M33B3Verify extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'm33b3:verify';
    protected $description = 'Audit Shield User catalog and sample mutation PBAC, without writing data.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        $maxActors = $this->limit($params[0] ?? '10');
        $maxTargets = $this->limit($params[1] ?? '30');
        $db = db_connect();
        $auth = config('Auth')->tables;
        foreach (['resources', 'resource_actions', 'groups', 'group_permissions',
            'organizations', 'workflow_states', 'policies', 'policy_actions',
            $auth['users'], $auth['identities'], $auth['groups_users'], $auth['permissions_users']]
            as $table) {
            if (! $db->tableExists($table)) {
                throw new UnexpectedValueException('M3.3B3 missing required table: ' . $table);
            }
        }
        $resource = $this->one($db->table('resources')->select('id, type, domain, status')
            ->where('type', 'user')->get(), 'User Resource');
        if ($resource === null || ($resource['domain'] ?? null) !== 'SYSTEM') {
            throw new UnexpectedValueException('User Resource must be registered in SYSTEM domain.');
        }
        $actions = $this->many($db->table('resource_actions')->select('code')
            ->where('resource_id', $resource['id'])->get(), 'User mutation action catalog');
        $have = array_fill_keys(array_column($actions, 'code'), true);
        foreach (['view','create','edit','setstate','reset_password','assign_permission'] as $action) {
            if (! isset($have[$action])) {
                throw new UnexpectedValueException('Missing User action: ' . $action);
            }
        }
        CLI::write('PASS: USER VIEW/CREATE/EDIT/SETSTATE/RESET_PASSWORD/ASSIGN_PERMISSION registered.', 'green');
        $rows = $this->many($db->table($auth['users'])
            ->select('id, active, status')->where('deleted_at', null)
            ->orderBy('id', 'ASC')->limit(max($maxActors, $maxTargets))->get(), 'User target sample');
        $allowedStates = ['ACTIVE','INACTIVE','LOCKED','SUSPENDED','BANNED','DISABLED'];
        foreach ($rows as $row) {
            if (! in_array(strtoupper((string) ($row['status'] ?? '')), $allowedStates, true)
                || ! in_array($row['active'], [true,false,0,1,'0','1'], true)) {
                throw new UnexpectedValueException('Invalid persisted Shield User State for user ' . ($row['id'] ?? '?'));
            }
        }
        CLI::write('PASS: sampled Shield User status and active flags validated.', 'green');
        $actors = array_slice($rows, 0, $maxActors);
        $targets = array_slice($rows, 0, $maxTargets);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $checked = 0; $allow = 0; $deny = 0; $errors = 0;
        foreach ($actors as $actor) {
            try {
                $evaluator = new UnifiedAccessService(new User(['id'=>$actor['id']]), $db, $now);
                $decisions = [$evaluator->decideMutation('user', 'create', null)];
                foreach ($targets as $target) {
                    foreach (['edit','setstate','reset_password','assign_permission'] as $action) {
                        $decisions[] = $evaluator->decideMutation('user', $action, $target);
                    }
                }
                foreach ($decisions as $decision) {
                    $checked++;
                    if ($decision['allowed']) { $allow++; } else { $deny++; }
                }
            } catch (Throwable $exception) {
                $errors++;
                if ($errors <= 5) {
                    CLI::write('ERROR: ' . get_class($exception) . ': ' . $exception->getMessage(), 'red');
                }
            }
        }
        CLI::write('M3.3B3 SHIELD readiness: actors=' . count($actors)
            . ', targets=' . count($targets) . ', checked=' . $checked
            . ', allowed=' . $allow . ', denied=' . $deny . ', errors=' . $errors);
        CLI::write('NOTE: no database writes; not HTTP, password hashing, or MariaDB rollback parity.', 'yellow');
        CLI::write('NOTE: Shield user mutations require canonical PBAC; runtime UAT remains separate.', 'yellow');
        if ($checked === 0 || $errors > 0) {
            throw new UnexpectedValueException('M3.3B3 sampled User mutation PBAC failed.');
        }
        CLI::write('PASS: sampled Shield User mutation authorization without database writes.', 'green');
    }

    private function limit(string $value): int
    {
        if (preg_match('/\A[1-9][0-9]{0,2}\z/D', $value) !== 1 || (int) $value > 50) {
            throw new UnexpectedValueException('M3.3B3 sample limits must be between 1 and 50.');
        }
        return (int) $value;
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
