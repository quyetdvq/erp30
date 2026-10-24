<?php

declare(strict_types=1);

namespace App\Modules\Product\Product;

/** P1 has no sales, purchase, or inventory transaction tables yet. */
final class NoProductHistoryProvider implements ProductHistoryProvider
{
    public function hasTransactionHistory(int $productId): bool
    {
        return false;
    }
}
