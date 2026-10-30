<?php

declare(strict_types=1);

namespace App\Shared\Http;

use DateTimeInterface;
use JsonSerializable;
use Traversable;

/** Only HTTP serialization rules; no domain logic or DAL dependency. */
final class ApiEnvelope
{
    /** @param array<string, mixed> $meta
     *  @return array{data:mixed,meta:array<string,mixed>}
     */
    public static function success(mixed $data, array $meta = []): array
    {
        return ['data' => self::normalize($data), 'meta' => array_merge($meta, ['request_id' => RequestMetadata::id()])];
    }

    /** @param list<array<string,mixed>> $details
     *  @return array{error:array{code:string,message:string,details:list<array<string,mixed>>},meta:array{request_id:string}}
     */
    public static function failure(string $code, string $message, array $details = []): array
    {
        return [
            'error' => ['code' => $code, 'message' => $message, 'details' => self::normalize($details)],
            'meta' => ['request_id' => RequestMetadata::id()],
        ];
    }

    public static function normalize(mixed $value, ?string $key = null): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }
        if ($value instanceof JsonSerializable) {
            return self::normalize($value->jsonSerialize(), $key);
        }
        if ($value instanceof Traversable) {
            $value = iterator_to_array($value);
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $childKey => $child) {
                $normalizedKey = is_string($childKey) ? self::snakeCase($childKey) : $childKey;
                $passedKey = is_string($key) && self::identifierKey($key) ? $key : (is_string($normalizedKey) ? $normalizedKey : null);
                $result[$normalizedKey] = self::normalize($child, $passedKey);
            }
            return array_is_list($value) ? array_values($result) : $result;
        }
        if ($value instanceof \stdClass && get_object_vars($value) === []) {
            // Preserve an intentional empty JSON object as {}. In particular,
            // HIS-POL-002 uses {} to mean a redacted field change. Casting it
            // to [] would change the wire contract and break strict readers.
            return $value;
        }
        if (is_object($value)) {
            return self::normalize(get_object_vars($value), $key);
        }
        if (is_int($value) && is_string($key) && self::identifierKey($key)) {
            return (string) $value;
        }
        if ((is_int($value) || is_float($value)) && is_string($key) && preg_match('/(?:amount|price|quantity|factor|rate|weight|decimal|money)$/i', $key)) {
            return (string) $value;
        }
        return $value;
    }

    private static function identifierKey(string $key): bool
    {
        return $key === 'id' || str_ends_with($key, '_id') || str_ends_with($key, '_ids')
            || $key === 'row_version' || str_ends_with($key, '_version');
    }

    private static function snakeCase(string $key): string
    {
        $key = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key) ?? $key;
        return strtolower(str_replace('-', '_', $key));
    }
}
