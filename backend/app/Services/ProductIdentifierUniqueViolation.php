<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;

/** Typed boundary for a database layer that exposes products.code metadata. */
final class ProductIdentifierUniqueViolation extends DomainException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('NUM_PRODUCT_CODE_UNIQUE_COLLISION', ['constraint' => 'products.code'], $previous);
    }
}
