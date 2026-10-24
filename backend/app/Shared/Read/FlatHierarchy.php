<?php

declare(strict_types=1);

namespace App\Shared\Read;

use UnexpectedValueException;

/**
 * Small in-memory snapshot of a complete organization/department hierarchy.
 * IMPORTANT: a complete unfiltered hierarchy is privileged internal data.
 * A filtered/authorized list must use visibleRows() instead, without fetching
 * hidden parents or exposing their information.
 */
final class FlatHierarchy
{
    /** @var array<string, array<string,mixed>> */
    private array $byId = [];

    /** @var array<string, list<string>> */
    private array $children = [];

    /** @var list<string> */
    private array $order = [];

    /** @param list<array<string,mixed>> $rows */
    public function __construct(array $rows)
    {
        foreach ($rows as $row) {
            $id = self::idOf($row['id'] ?? null);
            if (isset($this->byId[$id])) {
                throw new UnexpectedValueException('Duplicate node id in hierarchy: ' . $id);
            }
            $this->byId[$id] = $row;
            $this->order[] = $id;
        }
        foreach ($this->byId as $id => $row) {
            if (($row['parent_id'] ?? null) === null) {
                continue;
            }
            $parent = self::idOf($row['parent_id']);
            if (! isset($this->byId[$parent])) {
                throw new UnexpectedValueException('Missing parent for node: ' . $id);
            }
            $this->children[$parent][] = (string) $id;
        }
        $validated = [];
        foreach ($this->order as $id) {
            $trail = [];
            $cursor = $id;
            while (! isset($validated[$cursor])) {
                if (isset($trail[$cursor])) {
                    throw new UnexpectedValueException('Cycle in hierarchy at node: ' . $cursor);
                }
                $trail[$cursor] = true;
                $parent = $this->byId[$cursor]['parent_id'] ?? null;
                if ($parent === null) {
                    break;
                }
                $cursor = self::idOf($parent);
            }
            foreach ($trail as $visited => $_) {
                $validated[$visited] = true;
            }
        }
    }

    /** @return list<array<string,mixed>> Root first, requested node last. */
    public function ancestorChain(int|string $nodeId): array
    {
        $id = PositiveId::value($nodeId);
        if (! isset($this->byId[$id])) {
            throw new UnexpectedValueException('Unknown hierarchy node: ' . $id);
        }
        $chain = [];
        while (true) {
            $row = $this->byId[$id];
            $chain[] = $row;
            if (($row['parent_id'] ?? null) === null) {
                return array_reverse($chain);
            }
            $id = self::idOf($row['parent_id']);
        }
    }

    /** @return list<string> Includes the requested node, then descendants, breadth first. */
    public function descendantIds(int|string $nodeId): array
    {
        $id = PositiveId::value($nodeId);
        if (! isset($this->byId[$id])) {
            throw new UnexpectedValueException('Unknown hierarchy node: ' . $id);
        }
        $queue = [$id];
        for ($cursor = 0; $cursor < count($queue); $cursor++) {
            foreach ($this->children[$queue[$cursor]] ?? [] as $child) {
                $queue[] = $child;
            }
        }
        return $queue;
    }

    /** @return list<array<string,mixed>> */
    public function roots(): array
    {
        $result = [];
        foreach ($this->order as $id) {
            if (($this->byId[$id]['parent_id'] ?? null) === null) {
                $result[] = $this->byId[$id];
            }
        }
        return $result;
    }

    /**
     * Project an already-authorized result set, never querying hidden parents.
     * Keep all scalar values and row order unchanged; only hide invisible parent_id.
     *
     * @param list<array<string,mixed>> $authorizedRows
     * @return list<array<string,mixed>>
     */
    public static function visibleRows(array $authorizedRows): array
    {
        $visible = [];
        foreach ($authorizedRows as $row) {
            $id = self::idOf($row['id'] ?? null);
            if (isset($visible[$id])) {
                throw new UnexpectedValueException('Duplicate visible node id: ' . $id);
            }
            $visible[$id] = true;
        }
        foreach ($authorizedRows as &$row) {
            if (($row['parent_id'] ?? null) !== null
                && ! isset($visible[self::idOf($row['parent_id'])])) {
                $row['parent_id'] = null;
            }
        }
        unset($row);
        return $authorizedRows;
    }

    private static function idOf(mixed $id): string
    {
        if (! is_int($id) && ! is_string($id)) {
            throw new UnexpectedValueException('Invalid hierarchy node identifier.');
        }
        try {
            return PositiveId::value($id);
        } catch (\InvalidArgumentException $exception) {
            throw new UnexpectedValueException('Invalid hierarchy node identifier.', 0, $exception);
        }
    }
}
