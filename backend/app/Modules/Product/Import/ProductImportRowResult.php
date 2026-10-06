<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

/** In-memory outcome for one logical Product import row. */
final readonly class ProductImportRowResult
{
    public function __construct(
        public int $rowNumber,
        public bool $success,
        public ?int $targetRefId = null,
        public ?string $entityKey = null,
        public ?string $error = null,
        public ?string $errorCode = null,
    ) {
    }

    public static function success(int $rowNumber, int $targetRefId, ?string $entityKey = null): self
    {
        return new self($rowNumber, true, $targetRefId, $entityKey);
    }

    public static function error(int $rowNumber, string $errorCode): self
    {
        return new self($rowNumber, false, null, null, $errorCode, $errorCode);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isError(): bool
    {
        return ! $this->success;
    }

    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }

    public function getTargetRefId(): ?int
    {
        return $this->targetRefId;
    }

    public function getEntityKey(): ?string
    {
        return $this->entityKey;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
