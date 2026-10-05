<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

final class PersistenceException extends DomainException
{
    public function __construct(string $errorCode, string $operation, ?Throwable $previous = null)
    {
        parent::__construct($errorCode, ['operation' => $operation], $previous);
    }
}
