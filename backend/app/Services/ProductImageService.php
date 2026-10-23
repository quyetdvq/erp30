<?php

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap\ProductMediaFactory;
use App\Modules\Product\Media\Application\ProductImageApplicationService;
use CodeIgniter\Database\ConnectionInterface;

/** Legacy Product Media API kept intact for controller and named-argument consumers. */
final class ProductImageService
{
    private ProductImageApplicationService $application;

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public function __construct(
        ?ConnectionInterface $db = null,
        ?Api050ProductCatalogService $catalog = null,
        ?ProductImageStorage $storage = null,
    ) {
        $this->application = ProductMediaFactory::create($db, $catalog, $storage);
    }

    /** @return array<string,mixed> */
    public function upload(int $id, string $temporaryFile): array
    {
        return $this->application->upload($id, $temporaryFile);
    }

    /** @return array<string,mixed> */
    public function remove(int $id): array
    {
        return $this->application->remove($id);
    }
}
