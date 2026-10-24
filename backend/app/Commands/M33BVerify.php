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
 * READ-ONLY readiness audit. Tests mutation authorization on existing rows,
 * but NEVER issues INSERT, UPDATE, DELETE or a transaction. It does not
 * prove write/rollback/HTTP equivalence to the running v1.2.4 baseline.
 */
final class M33BVerify extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'm33b:verify';
    protected $description = 'Read-only M3.3B1 write-PBAC and schema readiness audit.';

    private const ACTIONS = [
        'authorization_group' => ['create', 'edit', 'setstate', 'assign_member', 'assign_permission'],
        'resource' => ['create', 'edit', 'setstate', 'delete'],
    ];

    /** @param list<string> $params */
    public function run(array $params): void
    {
        $limit = $this->limit($params[0] ?? '10');
        $db = db_connect();
        $auth = config('Auth')->tables;
        foreach (['groups', 'resources', 'resource_actions', 'group_permissions', 'policies',
            'policy_actions', 'lifecycle_states', 'workflow_states', 'lifecycles',
            $auth['users'], $auth['groups_users'], $auth['permissions_users']] as $table) {
            if (! $db->tableExists($table)) {
                throw new UnexpectedValueException('Missing M3.3B1 required SQL table: ' . $table);
            }
        }
        $missing = [];
        foreach (self::ACTIONS as $type => $actions) {
            $result = $db->table('resources')->select('id, domain, status')->where('type', $type)->get();
            if ($result === false || ($row = $result->getRowArray()) === null || ($row['domain'] ?? null) !== 'SYSTEM') {
                throw new UnexpectedValueException('Invalid Access resource catalog: ' . $type);
            }
            $result = $db->table('resource_actions')->select('code')->where('resource_id', $row['id'])->get();
            if ($result === false) {
                throw new UnexpectedValueException('Cannot load Resource Actions for ' . $type);
            }
            $codes = array_column($result->getResultArray(), 'code');
            foreach ($actions as $action) {
                if (! in_array($action, $codes, true)) {
                    $missing[] = $type . '.' . $action;
                }
            }
        }
        if ($missing !== []) {
            throw new UnexpectedValueException('Missing required write-PBAC actions: ' . implode(', ', $missing));
        }
        CLI::write('PASS: exact Group/Resource mutation Actions registered in persisted catalog.', 'green');
        $users = $db->table($auth['users'])->select('id')->where('deleted_at', null)
            ->orderBy('id', 'ASC')->limit($limit)->get();
        if ($users === false) {
            throw new UnexpectedValueException('Cannot load Shield user sample.');
        }
        $targets = [];
        foreach (['authorization_group' => 'groups', 'resource' => 'resources'] as $type => $table) {
            $result = $db->table($table)->orderBy('id', 'ASC')->limit($limit)->get();
            if ($result === false) {
                throw new UnexpectedValueException('Cannot load ' . $table . ' sample.');
            }
            $targets[$type] = $result->getResultArray();
        }
        $total = $allow = $deny = $errors = 0;
        $time = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        foreach ($users->getResultArray() as $userRow) {
            try {
                $access = new UnifiedAccessService(new User(['id' => $userRow['id']]), $db, $time);
                foreach (self::ACTIONS as $type => $actions) {
                    foreach ($actions as $action) {
                        $records = $action === 'create'
                            ? [null]
                            : $targets[$type];
                        foreach ($records as $record) {
                            $choice = $access->decideMutation($type, $action, $record,
                                $action === 'create' ? ['status' => 'ACTIVE'] : []);
                            $total++;
                            if ($choice['allowed']) { $allow++; } else { $deny++; }
                        }
                    }
                }
            } catch (Throwable $error) {
                $errors++;
                if ($errors <= 10) {
                    CLI::write('ERROR: ' . get_class($error) . ': ' . $error->getMessage(), 'red');
                }
            }
        }
        CLI::write('M3.3B1 WRITE-GATE readiness: checked=' . $total . ', allowed=' . $allow
            . ', denied=' . $deny . ', errors=' . $errors);
        CLI::write('NOTE: zero DB writes. This tests decisions, NOT mutation transactions/HTTP parity.', 'yellow');
        CLI::write('NOTE: new write routes stay disabled unless DINKERP_M33B_WRITE_MODE=enabled.', 'yellow');
        if ($total === 0 || $errors !== 0) {
            throw new UnexpectedValueException('M3.3B1 read-only readiness verification failed.');
        }
        CLI::write('PASS: sampled runtime M3.3B1 mutation authorization decisions.', 'green');
    }

    private function limit(string $value): int
    {
        if (preg_match('/\A[1-9][0-9]{0,2}\z/D', $value) !== 1 || (int) $value > 50) {
            throw new UnexpectedValueException('M3.3B1 sample max_users 1..50.');
        }
        return (int) $value;
    }
}
