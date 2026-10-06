<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

/** Deterministic directed graph for read-only import dependency planning. */
final class ProductImportDependencyGraph
{
    /** @var array<string, array<string, true>> */
    private array $dependencies = [];

    /** @var array<string, array<string, true>> */
    private array $dependents = [];

    public function addNode(string $node): void
    {
        $this->dependencies[$node] ??= [];
        $this->dependents[$node] ??= [];
    }

    /** Add a prerequisite edge: $node cannot run before $prerequisite. */
    public function addDependency(string $node, string $prerequisite): void
    {
        $this->addNode($node);
        $this->addNode($prerequisite);
        $this->dependencies[$node][$prerequisite] = true;
        $this->dependents[$prerequisite][$node] = true;
    }

    /** @return list<string> */
    public function getNodes(): array
    {
        return array_keys($this->dependencies);
    }

    /** @return list<string> */
    public function getDependencies(string $node): array
    {
        return array_keys($this->dependencies[$node] ?? []);
    }

    /**
     * @param array<string, int> $sourceRows
     * @return list<string>
     */
    public function topologicalOrder(array $sourceRows = []): array
    {
        $remaining = [];
        foreach ($this->dependencies as $node => $dependencies) {
            $remaining[$node] = count($dependencies);
        }

        $available = array_keys(array_filter($remaining, static fn (int $count): bool => $count === 0));
        $order = [];
        while ($available !== []) {
            usort($available, static function (string $left, string $right) use ($sourceRows): int {
                return [$sourceRows[$left] ?? PHP_INT_MAX, $left] <=> [$sourceRows[$right] ?? PHP_INT_MAX, $right];
            });
            $node = array_shift($available);
            $order[] = $node;
            foreach (array_keys($this->dependents[$node] ?? []) as $dependent) {
                $remaining[$dependent]--;
                if ($remaining[$dependent] === 0) {
                    $available[] = $dependent;
                }
            }
        }

        if (count($order) !== count($remaining)) {
            $unresolved = array_keys(array_filter($remaining, static fn (int $count): bool => $count > 0));
            sort($unresolved, SORT_STRING);
            throw new ProductImportGraphException(ProductImportGraphException::ERROR_CYCLE, [
                'nodes' => implode(',', $unresolved),
            ]);
        }

        return $order;
    }
}
