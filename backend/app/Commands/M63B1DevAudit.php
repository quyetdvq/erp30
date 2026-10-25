<?php

declare(strict_types=1);

namespace App\Commands;

use App\Shared\Media\ImageUrl;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use InvalidArgumentException;
use RuntimeException;

/** SELECT-only Shield User avatar metadata assertion. Never changes a user. */
final class M63B1DevAudit extends BaseCommand
{
    protected $group='DinkERP';
    protected $name='m63b1:db-audit';
    protected $description='SELECT-only DEV Shield User avatar fixture and metadata check.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        require_once ROOTPATH.'tools/m63b1/M63B1AvatarPlan.php';
        if (count($params)<1 || count($params)>2) {
            throw new InvalidArgumentException('Usage: php spark m63b1:db-audit USER_ID [EMPTY|PUBLIC_FILENAME]');
        }
        $id=\M63B1AvatarPlan::userId((string)$params[0]);
        $expected=(string)($params[1]??'EMPTY');
        if ($expected!=='EMPTY' && ImageUrl::for($expected,'avatar')===null) {
            throw new InvalidArgumentException('Only safe public avatar filenames may be audited.');
        }
        $db=db_connect();
        $query=$db->table((string)config('Auth')->tables['users'])
            ->select('id, active, status, deleted_at, avatar_filename')->where('id',$id)->get();
        if ($query===false) throw new RuntimeException('Shield user SELECT failed.');
        $row=$query->getRowArray();
        if (!is_array($row) || $row['deleted_at']!==null || (int)$row['active']!==1) {
            throw new RuntimeException('Target is not a live active DEV Shield account.');
        }
        $raw=$row['avatar_filename']??null;
        $stored=is_string($raw) && $raw!=='' ? $raw : null;
        if ($stored!==($expected==='EMPTY'?null:$expected)) {
            throw new RuntimeException('Existing avatar metadata differs from expected value. Never overwrite it with QA.');
        }
        // Extra SELECT-only origin attestation for the delegated DEV QA runner.
        // A configured .env does not prove the connected database name.
        $databaseQuery = $db->query('SELECT DATABASE() AS database_name');
        if ($databaseQuery === false) throw new RuntimeException('Database name SELECT failed.');
        $databaseRow = $databaseQuery->getRowArray();
        $databaseName = $databaseRow['database_name'] ?? null;
        if (!is_string($databaseName) || $databaseName === '') {
            throw new RuntimeException('Connected database name unavailable.');
        }
        $result=['status'=>'PASS','id'=>$id,'active'=>true,'avatar_filename'=>$stored,
            'database_name'=>$databaseName];
        CLI::write('M6.3B.1 READ-ONLY SQL: '.($stored===null?'PASS_DEV_AVATAR_METADATA_EMPTY':'PASS_DEV_AVATAR_METADATA_MATCH'));
        CLI::write('User id='.$id.'; avatar='.($stored===null?'EMPTY':'MATCHED_PUBLIC_FILENAME').'; no SQL writes.');
        CLI::write('M63B1_DB_JSON='.json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    }
}
