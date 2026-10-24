<?php

declare(strict_types=1);

namespace App\Repositories;

/** @deprecated Compatibility alias; canonical class lives in Modules/Resource. */
class_alias(\App\Modules\Resource\Infrastructure\Persistence\SqlResourceCatalogRepository::class, __NAMESPACE__ . '\\ResourceCatalogRepository');
