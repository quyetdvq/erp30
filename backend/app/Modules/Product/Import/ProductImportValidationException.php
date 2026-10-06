<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

use App\Exceptions\DomainException;

/** Stable validation failure for a Product import document. */
final class ProductImportValidationException extends DomainException
{
}
