<?php

declare(strict_types=1);

namespace App\Infrastructure;

final class RequestContext
{
    private static ?string $requestId = null;
    private static string $locale = 'vi';

    public static function begin(string $requestId, string $locale): void
    {
        self::$requestId = $requestId;
        self::$locale = $locale;
    }

    public static function requestId(): ?string
    {
        return self::$requestId;
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function reset(): void
    {
        self::$requestId = null;
        self::$locale = 'vi';
    }
}
