<?php

declare(strict_types=1);

namespace App\Shared\Http;

/** Per-request response metadata, explicitly reset on each request. */
final class RequestMetadata
{
    private static ?string $id = null;
    private static string $locale = 'vi';

    public static function begin(string $headerId, string $locale): void
    {
        self::$id = self::validId($headerId) ? $headerId : self::generateId();
        self::$locale = in_array($locale, ['vi', 'en'], true) ? $locale : 'vi';
    }

    public static function id(): string
    {
        if (self::$id === null) {
            self::$id = self::generateId();
        }
        return self::$id;
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function clear(): void
    {
        self::$id = null;
        self::$locale = 'vi';
    }

    public static function validId(string $header): bool
    {
        return preg_match('/\A[0-9A-Za-z][0-9A-Za-z._:-]{0,127}\z/D', $header) === 1;
    }

    private static function generateId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
