<?php

declare(strict_types=1);

/** Pure negative/serialization/order contract; no CI4 bootstrap or database. */
require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Modules/Numbering/Configuration/NumberingRules.php';
require_once __DIR__.'/../app/Modules/Numbering/Configuration/NumberingOverview.php';

use App\Modules\Numbering\Configuration\NumberingOverview;

$count = 0;
$ok = static function (bool $condition, string $label) use (&$count): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    ++$count;
    echo 'PASS: '.$label.PHP_EOL;
};
$reject = static function (callable $block, string $label) use ($ok): void {
    try {$block();} catch (Throwable) { $ok(true, $label); return; }
    throw new RuntimeException('FAIL: '.$label);
};
$resources = [
    ['id'=>'3','type'=>'ORDER','name'=>'Orders','domain'=>'MASTER','status'=>'ACTIVE','view'=>true,'configure'=>true],
    ['id'=>'2','type'=>'PRODUCT','name'=>'Products','domain'=>'MASTER','status'=>'ACTIVE','view'=>true,'configure'=>true],
    ['id'=>'4','type'=>'SECRET','name'=>'Hidden','domain'=>'MASTER','status'=>'ACTIVE','view'=>true,'configure'=>false],
    ['id'=>'5','type'=>'SYSTEM','name'=>'Invisible','domain'=>'SYSTEM','status'=>'ACTIVE','view'=>false,'configure'=>true],
    ['id'=>'6','type'=>'DENIED','name'=>'Absolutely denied','domain'=>'MASTER','status'=>'ACTIVE','view'=>true,'configure'=>false],
];
$series = [
    ['id'=>'20','resource_id'=>'2','series_code'=>'P1','start_number'=>5,'is_default'=>1],
    ['id'=>'30','resource_id'=>'3','series_code'=>'O1','start_number'=>1,'is_default'=>0],
    ['id'=>'21','resource_id'=>'2','series_code'=>'P2','start_number'=>9,'is_default'=>0],
];
$authorized = static fn (array $row): bool => $row['view'] && $row['configure'];
$reads = 0;
$reader = static function (array $ids) use (&$reads, $series, $ok): array {
    ++$reads;
    $ok($ids === ['3','2'], 'One batched read receives only the authorized Resource IDs');
    return $series;
};
$result = NumberingOverview::assemble($resources, $authorized, $reader);
$ok($reads === 1, 'Series data fetched once, never once per Resource');
$ok(array_column($result['resources'], 'id') === ['3','2'], 'Resource options retain server-supplied type order');
$ok(array_column($result['series'], 'id') === ['30','20','21'], 'Series group by Resource order, then query ID order');
$ok($result['resources'][0]['allowed_actions'] === ['configure_numbering'=>true], 'Hints reflect only effective configure access');
$ok(!isset($result['resources'][0]['view']), 'Internal authorization fields cannot leak');
$ok(!in_array('4', array_column($result['resources'], 'id'), true), 'Denied configure Resource removed');
$ok(!in_array('5', array_column($result['resources'], 'id'), true), 'Invisible Resource removed');
$ok(!in_array('6', array_column($result['resources'], 'id'), true), 'ABSOLUTE_DENY-like Resource removed');
$ok($result['series'][1]['start_number'] === '5' && $result['series'][1]['is_default'] === true, 'NumberingRules visible serializer preserved');
$called = false;
$none = NumberingOverview::assemble($resources, static fn (array $r): bool => false,
    static function (array $ids) use (&$called): array {$called = true; return [];});
$ok($none === ['resources'=>[], 'series'=>[]] && !$called, 'No permitted Resources causes no series query');
$reject(static fn () => NumberingOverview::assemble($resources, $authorized,
    static fn (array $ids): array => [['id'=>'40','resource_id'=>'4']]), 'Cross-Resource series rejected, not silently filtered');
$reject(static fn () => NumberingOverview::assemble($resources, $authorized,
    static fn (array $ids): array => [['id'=>'1','resource_id'=>'2'], ['id'=>'1','resource_id'=>'3']]), 'Duplicate series ID rejected');
$reject(static fn () => NumberingOverview::assemble([$resources[0],$resources[0]], $authorized,
    static fn (array $ids): array => []), 'Duplicate Resource ID rejected');
$reject(static fn () => NumberingOverview::assemble([[...$resources[0], 'id'=>'0']], $authorized,
    static fn (array $ids): array => []), 'Invalid Resource ID rejected');
echo 'TOTAL: '.$count.' Numbering overview pure assertions passed.'.PHP_EOL;
