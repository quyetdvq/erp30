<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Read-only SQL follow-up for a labelled M6.2B.2 HTTP DEV fixture. */
final class M62B2DevAudit extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'm62b2:db-audit';
    protected $description = 'Verify an explicitly labelled M6.2B.2 Product fixture via read-only SQL.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        $marker = (string)($params[0] ?? '');
        $id = (string)($params[1] ?? '');
        if (count($params) !== 2 || preg_match('/\AM62B2_[A-F0-9]{12}\z/D', $marker) !== 1
            || preg_match('/\A[1-9][0-9]{0,17}\z/D', $id) !== 1) {
            throw new \InvalidArgumentException('Usage: php spark m62b2:db-audit M62B2_XXXXXXXXXXXX PRODUCT_ID');
        }
        $db = db_connect();
        $sku = 'DEV_'.$marker;
        $comboSku = 'DEV_COMBO_'.$marker;
        $rows = $db->table('products')->select('id,code,sku,description,status')
            ->whereIn('sku', [$sku,$comboSku])->get()->getResultArray();
        $valid = count($rows) === 1
            && (string)($rows[0]['id'] ?? '') === $id
            && (string)($rows[0]['sku'] ?? '') === $sku
            && (string)($rows[0]['code'] ?? '') !== ''
            && ($rows[0]['description'] ?? null) === 'M6.2B.2 HTTP PATCH confirmed '.$marker;
        $comboRows = $db->table('product_combo_items')->select('id')
            ->where('combo_product_id', $id)->get()->getResultArray();
        $valid = $valid && count($comboRows) === 0;
        CLI::write('M6.2B.2 READ-ONLY SQL: '.($valid ? 'PASS_DEV_PRODUCT_SQL_ROWS' : 'FAIL_DEV_PRODUCT_SQL_ROWS'));
        CLI::write('Fixture rows='.count($rows).'; combo components of SIMPLE fixture='.count($comboRows)
            .'; id='.$id.'; marker='.$marker);
        CLI::write('NOTE: confirms persisted Product rows only. Numbering counter rollback, two-writer issuance and baseline parity NOT_TESTED.');
        if (!$valid) throw new \RuntimeException('DEV Product SQL row audit failed; inspect fixture manually.');
    }
}
