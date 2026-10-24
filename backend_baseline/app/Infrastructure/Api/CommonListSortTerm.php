<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

/** One validated client/server sort term. */
final readonly class CommonListSortTerm
{
    public function __construct(
        public string $field,
        public string $direction,
    ) {
        if (! in_array($this->direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException('Sort direction must be ASC or DESC.');
        }
    }

    /** @return array{field:string,direction:string} */
    public function toArray(): array
    {
        return ['field' => $this->field, 'direction' => $this->direction];
    }
}
