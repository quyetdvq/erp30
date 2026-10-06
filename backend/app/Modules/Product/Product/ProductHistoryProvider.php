<?php

declare(strict_types=1);

namespace App\Modules\Product\Product;

/** Replaceable transaction-history boundary for semantic Product mutations. */
interface ProductHistoryProvider
{
    public function hasTransactionHistory(int $productId): bool;
}
