<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Read-only SQL audit for an existing explicitly-labelled M6.2B.2 fixture. */
final class M63A1DevAudit extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'm63a1:db-audit';
    protected $description = 'SELECT-only Product image metadata audit before/after M6.3A.1 QA.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        require_once ROOTPATH.'tools/m62b3/M62B3Fixture.php';
        if (count($params)!==2) {
            throw new \InvalidArgumentException('Usage: php spark m63a1:db-audit M62B2_XXXXXXXXXXXX PRODUCT_ID');
        }
        $marker=\M62B3Fixture::marker((string)$params[0]);
        $id=\M62B3Fixture::id((string)$params[1]);
        $db=db_connect();
        $rows=$db->table('products')->where('sku','DEV_'.$marker)->get()->getResultArray();
        if (count($rows)!==1) throw new \RuntimeException('M6.3A.1 SQL fixture is not unique.');
        $row=$rows[0];
        \M62B3Fixture::assertRow($row,$marker,$id);
        $empty=($row['image_filename']??null)===null
            && ($row['image_mime']??null)===null
            && ($row['image_size_bytes']??null)===null;
        CLI::write('M6.3A.1 READ-ONLY SQL: '.($empty?'PASS_DEV_PRODUCT_MEDIA_METADATA_EMPTY':'FAIL_DEV_PRODUCT_MEDIA_METADATA_EMPTY'));
        CLI::write('Product id='.$id.'; marker='.$marker.'; filename='.($empty?'NULL':'NON_EMPTY'));
        CLI::write('NOTE: checks metadata only; HTTP/Apache and physical orphan cleanup require the separate media runner.');
        if (!$empty) throw new \RuntimeException('Fixture media metadata not empty. Inspect before rerunning media mutations.');
    }
}
