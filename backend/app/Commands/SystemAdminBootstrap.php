<?php

declare(strict_types=1);

namespace App\Commands;

use App\Database\Seeds\PrivilegedAuthorizationGroupSeeder;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

final class SystemAdminBootstrap extends BaseCommand
{
    protected $group = 'DinkERP';

    protected $name = 'systemadmin:bootstrap';

    protected $description = 'Ensure SYSTEM_ADMIN permissions and optionally provision one Shield membership.';

    protected $usage = 'systemadmin:bootstrap [userId]';

    protected $arguments = [
        'userId' => 'Optional Shield user ID to add to SYSTEM_ADMIN membership.',
    ];

    public function run(array $params)
    {
        $userId = $this->parseUserId($params[0] ?? null);
        $db = db_connect();

        if ($userId !== null) {
            $this->assertUserExists($db, $userId);
        }

        (new PrivilegedAuthorizationGroupSeeder(new Database(), $db))->run();

        if ($userId !== null) {
            $this->ensureMembership($db, $userId);
            CLI::write(sprintf('User %d is a SYSTEM_ADMIN member.', $userId), 'green');
        } else {
            CLI::write('SYSTEM_ADMIN permissions ensured; no membership changed.', 'green');
        }
    }

    private function parseUserId(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (! is_scalar($value) || ! ctype_digit((string) $value) || (int) $value < 1) {
            throw new RuntimeException('Shield user ID must be a positive integer.');
        }

        return (int) $value;
    }

    /** @param BaseConnection<mixed, mixed> $db */
    private function assertUserExists(BaseConnection $db, int $userId): void
    {
        $exists = $db->table(config('Auth')->tables['users'])->where('id', $userId)->countAllResults() > 0;
        if (! $exists) {
            throw new RuntimeException("Shield user ID {$userId} does not exist.");
        }
    }

    /** @param BaseConnection<mixed, mixed> $db */
    private function ensureMembership(BaseConnection $db, int $userId): void
    {
        $table = config('Auth')->tables['groups_users'];
        $group = strtolower(PrivilegedAuthorizationGroupSeeder::GROUP_CODE);
        $exists = $db->table($table)->where('user_id', $userId)->where('group', $group)->countAllResults() > 0;
        if (! $exists) {
            $db->table($table)->insert([
                'user_id' => $userId,
                'group' => $group,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
