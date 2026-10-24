<?php

declare(strict_types=1);

/**
 * BE-ARCH-001: vendor-free characterization of API-010 Organization tree output.
 * Preserves the behavior from erp_v1.2.2.php, without DB/authorization bypass.
 * Run: php backend/tools/test-architecture-organization-tree.php
 */
require_once dirname(__DIR__) . '/app/Modules/Organization/Application/VisibleOrganizationTreeProjector.php';

use App\Modules\Organization\Application\VisibleOrganizationTreeProjector;

/** @param list<array<string,mixed>> $rows
 *  @return list<array<string,mixed>>
 */
function legacyTreeProjection(array $rows): array
{
    // Frozen v1.2.2 algorithm; deliberately not called by production code.
    $visibleIds = [];
    foreach ($rows as $row) {
        if (isset($row['id'])) {
            $visibleIds[(string) $row['id']] = true;
        }
    }
    foreach ($rows as &$row) {
        if (($row['parent_id'] ?? null) !== null && ! isset($visibleIds[(string) $row['parent_id']])) {
            $row['parent_id'] = null;
        }
    }
    unset($row);
    return $rows;
}

$cases = [
    'empty page' => [],
    'parent and child both visible' => [
        ['id' => '1', 'parent_id' => null, 'name' => 'Root'],
        ['id' => '2', 'parent_id' => 1, 'name' => 'Child'],
    ],
    'parent absent from authorized page' => [
        ['id' => '22', 'parent_id' => '1', 'name' => 'Orphaned (visually)'],
    ],
    'multiple levels with denied intermediate ancestor' => [
        ['id' => 4, 'parent_id' => 3, 'status' => 'ACTIVE'],
        ['id' => 1, 'parent_id' => null],
        ['id' => 3, 'parent_id' => 2],
    ],
    'string coercion and missing keys' => [
        ['id' => '01', 'parent_id' => 1],
        ['id' => 1, 'parent_id' => null],
        ['id' => null, 'parent_id' => 'missing'],
        ['parent_id' => 1, 'status' => 'INACTIVE'],
    ],
    'duplicate ids and nonsequential order' => [
        ['id' => '7', 'parent_id' => '123'],
        ['id' => '7', 'parent_id' => null],
        ['id' => '9', 'parent_id' => 7],
    ],
    'parent across page boundary is not fetched' => [
        ['id' => '100', 'parent_id' => '1', 'secret_column' => 'unchanged'],
        ['id' => '101', 'parent_id' => '100', 'secret_column' => null],
    ],
];
$projector = new VisibleOrganizationTreeProjector();
foreach ($cases as $name => $rows) {
    $expected = legacyTreeProjection($rows);
    $actual = $projector->project($rows);
    if ($actual !== $expected) {
        throw new RuntimeException('Tree parity mismatch: ' . $name);
    }
    if ($rows !== ($cases[$name])) {
        throw new RuntimeException('Tree modified its input: ' . $name);
    }
}
$serviceSource = (string) file_get_contents(dirname(__DIR__) . '/app/Services/Api010FoundationOrganizationContactService.php');
if (preg_match('/function tree\(array \$query\).*?\$this->list\(\x27organization\x27, \$query\).*?VisibleOrganizationTreeProjector/s', $serviceSource) !== 1) {
    throw new RuntimeException('API-010 must authorize and page before projecting tree.');
}
if (str_contains((string) file_get_contents(dirname(__DIR__) . '/app/Modules/Organization/Application/VisibleOrganizationTreeProjector.php'), 'db_connect(')) {
    throw new RuntimeException('Projector cannot query hidden parents.');
}
echo 'BE-ARCH-001 ORGANIZATION TREE PASS: ' . count($cases) . ' parity vectors; no DB or HTTP change.' . PHP_EOL;
