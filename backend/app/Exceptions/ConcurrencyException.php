<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

final class ConcurrencyException extends DomainException
{
    public const ERROR_CODE = 'CONCURRENT_MODIFICATION';

    public function __construct(string $operation, ?Throwable $previous = null)
    {
        parent::__construct(self::ERROR_CODE, ['operation' => $operation], $previous);
    }
}
