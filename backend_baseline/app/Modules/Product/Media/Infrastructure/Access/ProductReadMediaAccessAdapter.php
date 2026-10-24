<?php

declare(strict_types=1);

namespace App\Modules\Product\Media\Infrastructure\Access;

use App\Modules\Product\Media\Ports\ProductMediaAccessPort;
use App\Modules\Product\Product\Application\AuthorizedProductReadService;

/** Product Media depends on the Product read capability, never the API façade. */
final class ProductReadMediaAccessAdapter implements ProductMediaAccessPort
{
    public function __construct(private readonly AuthorizedProductReadService $products)
    {
    }

    /** @return array<string,mixed> */
    public function authorizedProduct(int $id, string $action): array
    {
        return $this->products->authorizedRow($id, $action);
    }

    /** @return array<string,mixed> */
    public function publicProduct(int $id): array
    {
        return $this->products->publicProduct($id);
    }
}
