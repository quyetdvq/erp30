<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Modules\Product\Media\Application\ProductImageApplicationService;
use App\Modules\Product\Media\Infrastructure\Access\ProductReadMediaAccessAdapter;
use App\Modules\Product\Media\Infrastructure\Persistence\SqlProductImageMetadataRepository;
use App\Modules\Product\Media\Infrastructure\Storage\LegacyProductImageStorageAdapter;
use App\Modules\Product\Media\Infrastructure\Storage\LocalProductImageStorage;
use App\Services\Api050ProductCatalogService;
use App\Services\ProductImageStorage;
use CodeIgniter\Database\ConnectionInterface;

/** Stateless Product Media composition: the default Product reader is module-owned. */
final class ProductMediaFactory
{
    private function __construct()
    {
    }

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public static function create(
        ?ConnectionInterface $db = null,
        ?Api050ProductCatalogService $catalog = null,
        ?ProductImageStorage $storage = null,
    ): ProductImageApplicationService {
        $db ??= db_connect();
        // Caller-injected catalog retains its exact actor/clock/authorization
        // collaborators; default Media construction never instantiates API-050.
        $readAccess = $catalog !== null
            ? $catalog->productReadAccess()
            : ProductReadAccessFactory::create($db);
        // Default path is owned by Product/Media. Preserve the exact caller-supplied
        // legacy storage object when injected via the public façade.
        $mediaStorage = $storage === null
            ? new LocalProductImageStorage()
            : new LegacyProductImageStorageAdapter($storage);

        return new ProductImageApplicationService(
            new ProductReadMediaAccessAdapter($readAccess),
            new SqlProductImageMetadataRepository($db),
            $mediaStorage,
        );
    }
}
