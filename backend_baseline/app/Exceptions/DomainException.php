<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Domain failures expose a stable machine code while retaining diagnostics in
 * the exception chain for logs and server-side troubleshooting.
 */
class DomainException extends RuntimeException
{
    /** @param array<string, scalar|null> $context */
    public function __construct(
        private readonly string $errorCode,
        private readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        if ($errorCode === '') {
            throw new \InvalidArgumentException('Domain error code cannot be empty.');
        }

        parent::__construct($errorCode, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, scalar|null> */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getDiagnosticMessage(): ?string
    {
        return $this->getPrevious()?->getMessage();
    }
}
