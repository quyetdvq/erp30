<?php

declare(strict_types=1);

namespace App\Modules\Product\Combo;

use App\Modules\Product\Conversion\ProductConversionSql;

/** Reuse the exact Access-derived MASTER-state SQL scope from M7.1. */
final class ProductComboSql
{
    /** @param list<string> $viewableStates @return array{sql:string,binds:list<string>} */
    public static function authorizedOwner(string $id, array $viewableStates, string $prefix=''): array
    {
        return ProductConversionSql::authorizedProduct($id, $viewableStates, $prefix);
    }
}
