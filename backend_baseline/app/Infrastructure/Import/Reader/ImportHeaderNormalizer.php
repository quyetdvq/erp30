<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Reader;

/** Applies only the generic, format-independent header normalization rule. */
final class ImportHeaderNormalizer
{
    public function normalize(mixed $header): string
    {
        if (! is_string($header)) {
            return '';
        }

        return strtolower(trim($header));
    }

    /** @param list<mixed> $headers
     *  @return list<string>
     */
    public function normalizeAll(array $headers): array
    {
        return array_map(fn (mixed $header): string => $this->normalize($header), $headers);
    }
}
