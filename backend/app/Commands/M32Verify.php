<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Access\ReadViewGate;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User;
use Throwable;
use DateTimeImmutable;
use DateTimeZone;
use UnexpectedValueException;

/**
 * Read-only parity sample against the *already accepted M2.1.1 VIEW gate*.
 * Not a substitute for independent baseline HTTP PBAC parity and not a
 * permission to enable the M3.2 enforce mode automatically.
 */
final class M32Verify extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'm32:verify';
    protected $description = 'Read-only per-actor M2.1.1 vs M3.2 VIEW parity on persisted MariaDB records.';

    /** @var array<string,string> */
    private const TABLES = [
        'province' => 'provinces', 'ward' => 'wards', 'organization' => 'organizations',
        'department' => 'departments', 'position' => 'positions',
        'employee' => 'employees', 'employee_assignment' => 'employee_assignments',
    ];

    /** @param list<string> $params */
    public function run(array $params): void
    {
        $maxUsers = $this->limit($params[0] ?? '12', 50, 'max_users');
        $maxRows = $this->limit($params[1] ?? '25', 100, 'max_rows_per_resource');
        $db = db_connect();
        $auth = config('Auth')->tables;
        foreach (array_merge([$auth['users']], array_values(self::TABLES)) as $table) {
            if (! $db->tableExists($table)) {
                throw new UnexpectedValueException('Missing parity source table: ' . $table);
            }
        }
        $result = $db->table($auth['users'])->select('id')->where('deleted_at', null)
            ->orderBy('id', 'ASC')->limit($maxUsers)->get();
        if ($result === false) {
            throw new UnexpectedValueException('Unable to load persisted Shield users.');
        }
        $users = $result->getResultArray();
        if ($users === []) {
            throw new UnexpectedValueException('No persisted Shield users for parity.');
        }
        $records = [];
        $empty = [];
        foreach (self::TABLES as $resource => $table) {
            $result = $db->table($table)->orderBy('id', 'ASC')->limit($maxRows)->get();
            if ($result === false) {
                throw new UnexpectedValueException('Unable to load table: ' . $table);
            }
            $records[$resource] = $result->getResultArray();
            if ($records[$resource] === []) {
                $empty[] = $resource;
            }
        }
        $checked = 0;
        $mismatch = 0;
        $errors = 0;
        $allowed = 0;
        $denied = 0;
        foreach ($users as $userRow) {
            $user = new User(['id' => $userRow['id']]);
            try {
                $evaluationInstant = new DateTimeImmutable('now', new DateTimeZone('UTC'));
                $legacy = new ReadViewGate($user, $db, $evaluationInstant);
                $unified = new UnifiedAccessService($user, $db, $evaluationInstant);
            } catch (Throwable $e) {
                $errors++;
                CLI::write('ERROR: actor snapshot failed (' . get_class($e) . ').', 'red');
                continue;
            }
            foreach ($records as $type => $rows) {
                foreach ($rows as $row) {
                    try {
                        $old = $legacy->canView($type, $row);
                        $new = $unified->canView($type, $row);
                        $checked++;
                        if ($old) { $allowed++; } else { $denied++; }
                        if ($old !== $new) {
                            $mismatch++;
                            if ($mismatch <= 12) {
                                CLI::write('MISMATCH: resource=' . $type . ', old=' . (int) $old . ', new=' . (int) $new, 'red');
                            }
                        }
                    } catch (Throwable $e) {
                        $errors++;
                        if ($errors <= 12) {
                            CLI::write('ERROR: resource=' . $type . ', exception=' . get_class($e) . ': ' . $e->getMessage(), 'red');
                        }
                    }
                }
            }
        }
        CLI::write('M3.2 parity: users=' . count($users)
            . ', checked=' . $checked . ', allowed=' . $allowed . ', denied=' . $denied
            . ', mismatch=' . $mismatch . ', errors=' . $errors);
        if ($allowed === 0 || $denied === 0) {
            CLI::write('NOTE: sample covers only one decision outcome; add negative/positive account fixtures.', 'yellow');
        }
        if ($empty !== []) {
            CLI::write('NOTE: empty resources were not runtime-verified: ' . implode(', ', $empty), 'yellow');
        }
        CLI::write('NOTE: this compares M2.1.1 against M3.2 on persisted data; it does NOT compare against a separately running backend_basline.', 'yellow');
        CLI::write('NOTE: read-only historical PBAC comparison; runtime always uses UnifiedAccessService.', 'yellow');
        if ($checked === 0 || $mismatch !== 0 || $errors !== 0) {
            CLI::write('FAIL: M3.2 parity is NOT approved for enforcement.', 'red');
            throw new UnexpectedValueException('M3.2 parity failed; investigate canonical runtime decisions before production rollout.');
        }
        CLI::write('PASS: sampled M3.2 VIEW decisions equal the accepted M2.1.1 read gate.', 'green');
    }

    private function limit(string $value, int $max, string $field): int
    {
        if (preg_match('/\A[1-9][0-9]{0,2}\z/D', $value) !== 1
            || (int) $value > $max) {
            throw new UnexpectedValueException('Invalid ' . $field . ', expected 1..' . $max);
        }
        return (int) $value;
    }
}
