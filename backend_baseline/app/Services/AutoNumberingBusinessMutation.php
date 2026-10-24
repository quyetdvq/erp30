<?php

declare(strict_types=1);

namespace App\Services;

/** Narrow business callback boundary kept independent of Resource adapters. */
interface AutoNumberingBusinessMutation
{
    public function isIdentifierOccupied(string $identifier): bool;

    /** @throws AutoNumberingIdentifierCollision when the final unique guard collides. */
    public function persist(string $identifier): void;
}
