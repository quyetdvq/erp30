<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Access\ResourceStateRepository;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use UnexpectedValueException;

/** No writes. Derives SETSTATE action from the actual DEV Lifecycle, not guessed codes. */
final class M62B3DevPlan extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'm62b3:plan';
    protected $description = 'Read-only Product fixture and Lifecycle transition preflight.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        require_once ROOTPATH.'tools/m62b3/M62B3Fixture.php';
        if (count($params) !== 2) {
            throw new \InvalidArgumentException('Usage: php spark m62b3:plan M62B2_XXXXXXXXXXXX PRODUCT_ID');
        }
        $marker = \M62B3Fixture::marker((string)$params[0]);
        $id = \M62B3Fixture::id((string)$params[1]);
        $db = db_connect();
        $row = $db->table('products')->where('id', $id)->get()->getRowArray();
        if (!is_array($row)) throw new \RuntimeException('Fixture not found. No mutation attempted.');
        \M62B3Fixture::assertRow($row, $marker, $id);
        $repo = new ResourceStateRepository($db);
        $definition = $repo->definitionFor('product');
        if (($definition['mode'] ?? null) !== 'LIFECYCLE') {
            throw new \RuntimeException('Product Resource is not Lifecycle-bound.');
        }
        $current = (string)$row['status'];
        $target = null;
        try {
            $target = $repo->transitionTarget('product', $row, 'setstate');
            \M62B3Fixture::target($target);
            if ($target === $current) $target = null;
        } catch (UnexpectedValueException $error) {
            // A missing or ambiguous transition is not a safe reason to mutate.
            $target = null;
        }
        CLI::write('M6.2B.3 READ-ONLY FIXTURE: PASS_DEV_PRODUCT_FIXTURE; id='.$id.'; marker='.$marker);
        CLI::write('Lifecycle current='.$current.'; next_setstate='.($target ?? 'UNAVAILABLE'));
        CLI::write($target === null
            ? 'M6.2B.3 PLAN: BLOCKED_NO_UNIQUE_ACTIVE_TRANSITION; no write is authorized by this plan.'
            : 'M6.2B.3 PLAN: READY_FOR_EXPLICIT_HTTP_QA; PBAC permission and HTTP mutation NOT_TESTED.');
        CLI::write('Read-only: no product, counter, Lifecycle, or policy data modified.');
    }
}
