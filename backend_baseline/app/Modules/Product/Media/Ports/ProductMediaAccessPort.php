<?php

declare(strict_types=1);

namespace App\Modules\Product\Media\Ports;

/** Authorized Product read capability provided by the owning Product module. */
interface ProductMediaAccessPort
{
    /** @return array<string,mixed> Persisted Product row after authoritative action authorization. */
    public function authorizedProduct(int $id, string $action): array;

    /** @return array<string,mixed> Public Product DTO, including its independent read authorization. */
    public function publicProduct(int $id): array;
}
