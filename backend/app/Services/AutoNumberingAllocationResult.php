<?php

declare(strict_types=1);

namespace App\Services;

/** Immutable generated identifier result for the later Resource adapter. */
final readonly class AutoNumberingAllocationResult
{
    public function __construct(
        public string $identifier,
        public int $sequenceValue,
        public int $seriesId,
        public int $scopeOrganizationId,
        public string $periodKey,
        public bool $generated = true,
    ) {
    }

    /** @return array<string, int|string|bool> */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'sequence_value' => $this->sequenceValue,
            'series_id' => $this->seriesId,
            'scope_organization_id' => $this->scopeOrganizationId,
            'period_key' => $this->periodKey,
            'generated' => $this->generated,
        ];
    }
}
