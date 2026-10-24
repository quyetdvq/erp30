<?php

declare(strict_types=1);

require __DIR__ . '/../app/Shared/Read/PositiveId.php';
require __DIR__ . '/../app/Shared/Read/UtcBusinessDate.php';
require __DIR__ . '/../app/Shared/Read/FlatHierarchy.php';

use App\Shared\Read\FlatHierarchy;
use App\Shared\Read\PositiveId;
use App\Shared\Read\UtcBusinessDate;

$checks = 0;
function checkM2(string $name, bool $condition): void
{
    global $checks;
    $checks++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$name}\n");
        exit(1);
    }
    echo "PASS: {$name}\n";
}

function rejectsM2(callable $callback, string $expectedException): bool
{
    try {
        $callback();
    } catch (Throwable $error) {
        return is_a($error, $expectedException);
    }
    return false;
}

checkM2('positive integer ID preserved', PositiveId::value(23) === '23');
checkM2('numeric string ID normalized without floats', PositiveId::value('000123') === '123');
checkM2('MySQL BIGINT UNSIGNED maximum accepted', PositiveId::value('18446744073709551615') === '18446744073709551615');
checkM2('ID zero rejected', rejectsM2(fn () => PositiveId::value('000'), InvalidArgumentException::class));
checkM2('ID negative rejected', rejectsM2(fn () => PositiveId::value(-1), InvalidArgumentException::class));
checkM2('ID exponent rejected', rejectsM2(fn () => PositiveId::value('1e3'), InvalidArgumentException::class));
checkM2('ID float-like string rejected', rejectsM2(fn () => PositiveId::value('1.0'), InvalidArgumentException::class));
checkM2('ID spaces rejected', rejectsM2(fn () => PositiveId::value(' 1'), InvalidArgumentException::class));
checkM2('ID overflows unsigned BIGINT rejected', rejectsM2(fn () => PositiveId::value('18446744073709551616'), InvalidArgumentException::class));

checkM2('UTC date before Bangkok midnight',
    UtcBusinessDate::at(new DateTimeImmutable('2026-10-24 00:30:00 +07:00')) === '2026-10-23');
checkM2('UTC date after midnight with negative offset',
    UtcBusinessDate::at(new DateTimeImmutable('2026-10-23 23:59:59 -04:00')) === '2026-10-24');
checkM2('UTC date accepts leap day', UtcBusinessDate::validate('2024-02-29') === '2024-02-29');
checkM2('UTC date rejects impossible day', rejectsM2(fn () => UtcBusinessDate::validate('2026-02-29'), InvalidArgumentException::class));
checkM2('UTC date rejects clock suffix', rejectsM2(fn () => UtcBusinessDate::validate('2026-10-23 10:00:00'), InvalidArgumentException::class));

$assignment = [
    'status' => 'ACTIVE', 'effective_from' => '2026-10-10', 'effective_to' => '2026-10-23',
];
checkM2('assignment effective on opening date', UtcBusinessDate::effective($assignment, '2026-10-10'));
checkM2('assignment effective on closing date', UtcBusinessDate::effective($assignment, '2026-10-23'));
checkM2('assignment not effective the day before', ! UtcBusinessDate::effective($assignment, '2026-10-09'));
checkM2('assignment not effective the day after', ! UtcBusinessDate::effective($assignment, '2026-10-24'));
checkM2('open-ended active assignment effective', UtcBusinessDate::effective([
    'status' => 'ACTIVE', 'effective_from' => '2026-10-20', 'effective_to' => null,
], '2026-11-01'));
checkM2('inactive assignment is never effective', ! UtcBusinessDate::effective(array_replace($assignment, ['status' => 'INACTIVE']), '2026-10-22'));
checkM2('draft assignment is never effective', ! UtcBusinessDate::effective(array_replace($assignment, ['status' => 'DRAFT']), '2026-10-22'));
checkM2('missing start date does not create a scope', ! UtcBusinessDate::effective(['status' => 'ACTIVE'], '2026-10-22'));

$raw = [
    ['id' => '1', 'parent_id' => null, 'code' => 'GROUP', 'status' => 'ACTIVE'],
    ['id' => '2', 'parent_id' => '1', 'code' => 'COMPANY', 'status' => 'ACTIVE'],
    ['id' => '3', 'parent_id' => 2, 'code' => 'BRANCH', 'status' => 'ACTIVE'],
    ['id' => '4', 'parent_id' => '3', 'code' => 'WAREHOUSE', 'status' => 'ACTIVE'],
    ['id' => '5', 'parent_id' => '2', 'code' => 'SIBLING', 'status' => 'INACTIVE'],
    ['id' => '9', 'parent_id' => null, 'code' => 'OTHER', 'status' => 'ACTIVE'],
];
$tree = new FlatHierarchy($raw);
checkM2('hierarchy root set preserved', array_column($tree->roots(), 'code') === ['GROUP', 'OTHER']);
checkM2('hierarchy root-first ancestor order', array_column($tree->ancestorChain('4'), 'code') === ['GROUP', 'COMPANY', 'BRANCH', 'WAREHOUSE']);
checkM2('hierarchy descendant direction is downward only', $tree->descendantIds(2) === ['2', '3', '5', '4']);
checkM2('descendant walk includes self', $tree->descendantIds(4) === ['4']);
checkM2('inactive node remains in factual tree', in_array('5', $tree->descendantIds(2), true));
checkM2('unknown node rejected', rejectsM2(fn () => $tree->ancestorChain(999), UnexpectedValueException::class));
checkM2('duplicate nodes rejected', rejectsM2(fn () => new FlatHierarchy([
    ['id' => '1', 'parent_id' => null], ['id' => 1, 'parent_id' => null],
]), UnexpectedValueException::class));
checkM2('orphan parent rejected', rejectsM2(fn () => new FlatHierarchy([
    ['id' => 1, 'parent_id' => 2],
]), UnexpectedValueException::class));
checkM2('cycle rejected', rejectsM2(fn () => new FlatHierarchy([
    ['id' => 1, 'parent_id' => 2], ['id' => 2, 'parent_id' => 1],
]), UnexpectedValueException::class));
checkM2('self-parent rejected', rejectsM2(fn () => new FlatHierarchy([
    ['id' => 1, 'parent_id' => 1],
]), UnexpectedValueException::class));

// Authorized page 3 and 4: do not fetch/reveal hidden ancestor 2 or root 1.
$authorized = [$raw[2], $raw[3]];
$visible = FlatHierarchy::visibleRows($authorized);
checkM2('invisible parent masked', $visible[0]['parent_id'] === null);
checkM2('visible local child relation preserved', $visible[1]['parent_id'] === '3');
checkM2('projected order and metadata preserved', array_column($visible, 'code') === ['BRANCH', 'WAREHOUSE']);
checkM2('projection does not mutate original authorized rows', $authorized[0]['parent_id'] === 2);
checkM2('empty page stays empty', FlatHierarchy::visibleRows([]) === []);
checkM2('duplicate IDs in authorized page rejected', rejectsM2(fn () => FlatHierarchy::visibleRows([$raw[0], $raw[0]]), UnexpectedValueException::class));

// Location's province->ward parent chain can use the same safe unsigned ID contract.
checkM2('location province identifier exact', PositiveId::value('9223372036854775808') === '9223372036854775808');

printf("TOTAL: %d M2 assertions passed.\n", $checks);
