<?php

declare(strict_types=1);

namespace App\Modules\Numbering\Configuration;

use RuntimeException;

/**
 * Pure, fail-closed projection for the Auto Numbering screen. The caller
 * supplies persisted Resource rows, authoritative VIEW + configure_numbering
 * decisions, and one batched SQL reader. No frontend-provided Resource IDs.
 */
final class NumberingOverview
{
    /**
     * @param list<array<string,mixed>> $resourceRows Persisted, type-ordered Resources
     * @param callable(array<string,mixed>):bool $canConfigure
     * @param callable(list<string>):list<array<string,mixed>> $readSeries
     * @return array{resources:list<array<string,mixed>>,series:list<array<string,mixed>>}
     */
    public static function assemble(array $resourceRows, callable $canConfigure, callable $readSeries): array
    {
        $resources = [];
        $ids = [];
        $seenResources = [];
        foreach ($resourceRows as $persisted) {
            // Run the authoritative decision BEFORE placing anything in a
            // public Resource/series list. Never trust UI action hints.
            if (!$canConfigure($persisted)) continue;
            $id = NumberingRules::id($persisted['id'] ?? null);
            if (isset($seenResources[$id])) throw new RuntimeException('Duplicate Resource in numbering overview.');
            $seenResources[$id] = true;
            $ids[] = $id;
            $resources[] = [
                'id' => $id,
                'type' => (string) $persisted['type'],
                'name' => (string) $persisted['name'],
                'domain' => (string) $persisted['domain'],
                'status' => (string) $persisted['status'],
                'allowed_actions' => ['configure_numbering' => true],
            ];
        }

        if ($ids === []) return ['resources' => [], 'series' => []];

        // The series query may return ONLY rows for the already-authorized
        // Resource set; enforce that invariant even if a query is modified.
        $byResource = array_fill_keys($ids, []);
        $seenSeries = [];
        foreach ($readSeries($ids) as $raw) {
            $resourceId = NumberingRules::id($raw['resource_id'] ?? null);
            $seriesId = NumberingRules::id($raw['id'] ?? null);
            if (!array_key_exists($resourceId, $byResource) || isset($seenSeries[$seriesId])) {
                throw new RuntimeException('Invalid or cross-Resource numbering overview row.');
            }
            $seenSeries[$seriesId] = true;
            $byResource[$resourceId][] = NumberingRules::visible($raw);
        }
        $series = [];
        foreach ($ids as $id) {
            foreach ($byResource[$id] as $row) $series[] = $row;
        }
        return ['resources' => $resources, 'series' => $series];
    }
}
