<?php

declare(strict_types=1);

namespace App\Modules\Access\Ports;

/** Only factual, read-only organization scope data for PBAC; never permissions. */
interface OrganizationFactsPort
{
    /** @return list<array<string,mixed>> */
    public function groupRoots(): array;

    /** @return list<array<string,mixed>> */
    public function ancestorChain(int|string $organizationId): array;
}
