<?php

declare(strict_types=1);

namespace App\Modules\Resource\Domain;

enum ResourceDomain: string
{
    case SYSTEM = 'SYSTEM';
    case MASTER = 'MASTER';
    case ORGANIZATION = 'ORGANIZATION';

    public function isGroupScoped(): bool
    {
        return $this !== self::ORGANIZATION;
    }
}
