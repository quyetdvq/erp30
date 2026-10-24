<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User;
use Throwable;

/**
 * Read-only investigation of Product 403 errors for a developer's account.
 * Reveals which authorization layer rejects the request, without mutating
 * groups, permissions, policies or the user's Shield state.
 */
final class M62ProductAccessAudit extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'm62:access-check';
    protected $description = 'Read-only Product Group/UOM/Product VIEW PBAC diagnostics.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        $username = (string) ($params[0] ?? '');
        if (preg_match('/\A[a-zA-Z0-9_.@-]{1,100}\z/D', $username) !== 1) {
            CLI::write('Usage: php spark m62:access-check USERNAME', 'yellow');
            return;
        }
        $db = db_connect();
        $tables = config('Auth')->tables;
        $record = $db->table($tables['users'])->select('id')
            ->where('username', $username)->where('deleted_at', null)->get()->getRowArray();
        if (! is_array($record)) {
            CLI::write('User not found.', 'red');
            return;
        }
        $access = new UnifiedAccessService(new User(['id' => $record['id']]), $db);
        $map = ['product_group' => 'product_groups', 'product_uom' => 'product_uoms', 'product' => 'products'];
        foreach ($map as $type => $table) {
            $base = $access->hasBaseViewPermission($type);
            $status = $base ? 'BASE_GRANTED' : 'AUTH_NO_BASE_PERMISSION';
            try {
                $row = $db->table($table)->orderBy('id', 'ASC')->limit(1)->get()->getRowArray();
                if (is_array($row)) {
                    $decision = $access->decidePersisted($type, 'view', $row);
                    $status = $decision['reason'];
                } elseif ($base) {
                    $status = 'BASE_GRANTED_NO_SAMPLE_ROW';
                }
            } catch (Throwable $error) {
                $status = 'AUDIT_READ_ERROR_' . get_class($error);
            }
            CLI::write($type . ': base_permission=' . ($base ? 'YES' : 'NO')
                . '; sample_decision=' . $status);
        }
        CLI::write('READ ONLY. A missing ERP base grant or DENY policy must be corrected in the existing Access configuration; no bypass is applied.', 'yellow');
    }
}
