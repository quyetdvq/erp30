<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use Throwable;

final class AutoNumberingIdentifierCollision extends DomainException
{
    public const ERROR_CODE = 'NUM_IDENTIFIER_COLLISION';

    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(self::ERROR_CODE, [], $previous);
    }
}
