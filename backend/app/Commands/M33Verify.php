<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Access\Management\AccessReadContract;
use App\Modules\Access\Management\AccessReadRepository;
use App\Modules\Identity\Management\IdentityReadRepository;
use App\Modules\Workflow\Management\WorkflowReadContract;
use App\Modules\Workflow\Management\WorkflowReadRepository;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use UnexpectedValueException;

/**
 * Non-mutating readiness verification for M3.3 READ routes.
 * No database writes; not an independent HTTP/baseline equivalence test.
 */
final class M33Verify extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'm33:verify';
    protected $description = 'Read-only Access catalog/PBAC smoke test on persisted MariaDB users and rows.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        $maxUsers = $this->limit($params[0] ?? '10', 50);
        $maxRows = $this->limit($params[1] ?? '25', 100);
        $db = db_connect();
        $userTable = config('Auth')->tables['users'];
        foreach (array_merge([$userTable], array_values(AccessReadContract::TABLES), array_values(WorkflowReadContract::TABLES),
            ['resource_actions', 'workflow_states', config('Auth')->tables['identities'],
                config('Auth')->tables['groups_users'], config('Auth')->tables['permissions_users']]) as $table) {
            if (! $db->tableExists($table)) {
                throw new UnexpectedValueException('Missing M3.3 Access catalog table: ' . $table);
            }
        }
        $result = $db->table($userTable)->select('id')->where('deleted_at', null)
            ->orderBy('id', 'ASC')->limit($maxUsers)->get();
        if ($result === false || ($users = $result->getResultArray()) === []) {
            throw new UnexpectedValueException('No readable Shield users for M3.3 verification.');
        }
        $repo = new AccessReadRepository($db);
        $workflow = new WorkflowReadRepository($db);
        $identity = new IdentityReadRepository($db);
        $records = [];
        $empty = [];
        foreach (AccessReadContract::TABLES as $type => $_table) {
            // Use authoritative fixed repository projections (Policy status and
            // AuthorizationLog JOIN exactly as baseline).
            $records[$type] = $repo->sample($type, $maxRows);
            if ($records[$type] === []) {
                $empty[] = $type;
            }
        }
        foreach (WorkflowReadContract::TABLES as $type => $_table) {
            $records[$type] = $workflow->sample($type, $maxRows);
            if ($records[$type] === []) {
                $empty[] = $type;
            }
        }
        $records['user'] = array_slice($identity->securityUsers(), 0, $maxRows);
        if ($records['user'] === []) {
            $empty[] = 'user';
        }
        $checked = $allowed = $denied = $errors = 0;
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        foreach ($users as $userRow) {
            try {
                $evaluator = new UnifiedAccessService(new User(['id' => $userRow['id']]), $db, $now);
            } catch (Throwable $error) {
                $errors++;
                CLI::write('ERROR: unable to resolve actor context: ' . get_class($error), 'red');
                continue;
            }
            foreach ($records as $type => $rows) {
                foreach ($rows as $row) {
                    try {
                        $decision = $evaluator->decidePersisted($type, 'view', $row);
                        $checked++;
                        if ($decision['allowed']) { $allowed++; } else { $denied++; }
                    } catch (Throwable $error) {
                        $errors++;
                        if ($errors <= 12) {
                            CLI::write('ERROR: ' . $type . ': ' . get_class($error) . ' ' . $error->getMessage(), 'red');
                        }
                    }
                }
            }
        }
        CLI::write('M3.3 READ readiness: users=' . count($users) . ', checked=' . $checked
            . ', allowed=' . $allowed . ', denied=' . $denied . ', errors=' . $errors);
        if ($empty !== []) {
            CLI::write('SKIP: no persisted rows for ' . implode(', ', $empty), 'yellow');
        }
        CLI::write('NOTE: authoritative runtime VIEW checks only; no baseline HTTP parity, writes or enablement of mutation endpoints.', 'yellow');
        if ($checked === 0 || $errors !== 0) {
            CLI::write('FAIL: Access read routes need correction before Frontend acceptance.', 'red');
            throw new UnexpectedValueException('M3.3 runtime verification failed.');
        }
        CLI::write('PASS: M3.3 sampled Access VIEW decisions completed without exceptions or DB writes.', 'green');
    }

    private function limit(string $value, int $maximum): int
    {
        if (preg_match('/\A[1-9][0-9]{0,2}\z/D', $value) !== 1 || (int) $value > $maximum) {
            throw new UnexpectedValueException('M3.3 verification limit must be in 1..' . $maximum);
        }
        return (int) $value;
    }
}
