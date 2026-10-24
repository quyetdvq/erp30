<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

/** A source-local or persistent Product reference resolved during preflight. */
final readonly class ProductImportReference
{
    public const SOURCE = 'SOURCE';
    public const PERSISTENT = 'PERSISTENT';

    /**
     * @param array<string, mixed>|null $product
     */
    public function __construct(
        public string $kind,
        public string $key,
        public string $identity,
        public ?int $sourceRowNumber = null,
        public ?int $productId = null,
        public ?array $product = null,
    ) {
    }

    public function isSource(): bool
    {
        return $this->kind === self::SOURCE;
    }

    public function isPersistent(): bool
    {
        return $this->kind === self::PERSISTENT;
    }
}
