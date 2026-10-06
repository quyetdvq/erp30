<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

/** A raw import cell with its mutation-relevant structural state preserved. */
final readonly class ProductImportCell
{
    public const VALUE = 'VALUE';
    public const BLANK = 'BLANK';
    public const EXPLICIT_NULL = 'EXPLICIT_NULL';
    public const UPDATE_NO_CHANGE = 'NO_CHANGE';

    private function __construct(
        public string $state,
        public mixed $value,
    ) {
    }

    public static function fromRaw(mixed $value): self
    {
        if ($value === null || $value === '') {
            return new self(self::BLANK, null);
        }
        if ($value === '__NULL__') {
            return new self(self::EXPLICIT_NULL, null);
        }

        return new self(self::VALUE, $value);
    }

    public function isValue(): bool
    {
        return $this->state === self::VALUE;
    }

    public function isBlank(): bool
    {
        return $this->state === self::BLANK;
    }

    public function isExplicitNull(): bool
    {
        return $this->state === self::EXPLICIT_NULL;
    }

    /** CREATE semantics: both blank and explicit null produce a null value. */
    public function resolveForCreate(): mixed
    {
        return $this->isValue() ? $this->value : null;
    }

    /** UPDATE/UPSERT-existing semantics: blank means the field is untouched. */
    public function resolveForUpdate(): mixed
    {
        return $this->isBlank() ? self::UPDATE_NO_CHANGE : $this->resolveForCreate();
    }
}
