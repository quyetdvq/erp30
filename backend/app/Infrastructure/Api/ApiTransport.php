<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

use App\Infrastructure\Decimal\Decimal;
use App\Infrastructure\RequestContext;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeInterface;
use JsonSerializable;
use Traversable;

/** Common P1 JSON transport for the /api/v1 surface. */
final class ApiTransport
{
    /** @param array<string, mixed> $meta */
    public static function success(
        ResponseInterface $response,
        mixed $data = null,
        int $status = 200,
        array $meta = [],
    ): ResponseInterface {
        if ($status === 204) {
            $response->setHeader('X-Request-ID', self::ensureRequestId());
            $response->setHeader('Content-Language', self::locale());

            return $response->setStatusCode(204)->setBody('');
        }

        return self::prepare($response, [
            'data' => self::normalize($data),
            'meta' => self::meta($meta),
        ])->setStatusCode($status);
    }

    /**
     * Emit the canonical collection envelope used by authorized list APIs.
     *
     * Collection totals are transport metadata and therefore remain JSON
     * integers; entity BIGINT identifiers are still normalized as strings.
     *
     * @param iterable<mixed> $data
     */
    public static function collection(
        ResponseInterface $response,
        iterable $data,
        int $page,
        int $pageSize,
        int|string $total,
        int $status = 200,
    ): ResponseInterface {
        if ($page < 1 || $pageSize < 1 || (is_int($total) && $total < 0)) {
            throw new \InvalidArgumentException('Collection metadata is invalid.');
        }
        $numericTotal = self::collectionTotal($total);

        return self::success($response, $data, $status, [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $numericTotal,
        ]);
    }

    private static function collectionTotal(int|string $total): int
    {
        if (is_int($total)) {
            if ($total < 0) {
                throw new \InvalidArgumentException('Collection total cannot be negative.');
            }

            return $total;
        }

        if (preg_match('/\A(?:0|[1-9][0-9]*)\z/', $total) !== 1) {
            throw new \InvalidArgumentException('Collection total must be a non-negative integer.');
        }

        $numericTotal = filter_var($total, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if (! is_int($numericTotal)) {
            throw new \InvalidArgumentException('Collection total exceeds the JSON integer transport range.');
        }

        return $numericTotal;
    }

    /** @param list<array<string, mixed>> $details */
    public static function error(
        ResponseInterface $response,
        string $code,
        string $message,
        int $status,
        array $details = [],
    ): ResponseInterface {
        return self::prepare($response, [
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => self::normalize($details),
            ],
            'meta' => self::meta(),
        ])->setStatusCode($status);
    }

    public static function message(string $code, int $status): string
    {
        $key = match ($code) {
            'INVALID_CREDENTIALS' => 'invalidCredentials',
            'PROFILE_UPDATE_FAILED' => 'profileUpdateFailed',
            default => match ($status) {
                400 => 'invalidRequest',
                401 => 'unauthenticated',
                403 => 'forbidden',
                404 => 'notFound',
                409 => 'conflict',
                422 => 'validation',
                default => 'serverError',
            },
        };

        $message = lang('Api.' . $key, [], self::locale());

        return is_string($message) ? $message : implode(' ', $message);
    }

    public static function ensureRequestId(): string
    {
        $requestId = RequestContext::requestId();
        if ($requestId !== null && $requestId !== '') {
            return $requestId;
        }

        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $requestId = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
        RequestContext::begin($requestId, self::locale());

        return $requestId;
    }

    public static function normalize(mixed $value, ?string $key = null): mixed
    {
        if ($value instanceof Decimal) {
            return $value->value;
        }

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
            $normalized = [];
            $isList = array_is_list($value);
            foreach ($value as $childKey => $childValue) {
                $normalizedKey = is_string($childKey) ? self::snakeCase($childKey) : $childKey;
                $normalized[$normalizedKey] = self::normalize(
                    $childValue,
                    is_string($key) && self::isIdentifierKey($key) ? $key : (is_string($normalizedKey) ? $normalizedKey : null),
                );
            }
            if ($isList) {
                return array_values($normalized);
            }

            return $normalized;
        }

        if (is_int($value) && is_string($key) && self::isIdentifierKey($key)) {
            return (string) $value;
        }

        if ((is_int($value) || is_float($value)) && is_string($key) && self::isDecimalKey($key)) {
            return (string) $value;
        }

        if (is_object($value)) {
            return self::normalize(get_object_vars($value), $key);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function prepare(ResponseInterface $response, array $payload): ResponseInterface
    {
        $response->setHeader('X-Request-ID', self::ensureRequestId());
        $response->setHeader('Content-Language', self::locale());

        return $response->setJSON($payload);
    }

    /** @param array<string, mixed> $meta
     *  @return array<string, mixed>
     */
    private static function meta(array $meta = []): array
    {
        return array_merge($meta, ['request_id' => self::ensureRequestId()]);
    }

    private static function locale(): string
    {
        return in_array(RequestContext::locale(), ['vi', 'en'], true)
            ? RequestContext::locale()
            : 'vi';
    }

    private static function snakeCase(string $key): string
    {
        $key = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key) ?? $key;

        return strtolower(str_replace('-', '_', $key));
    }

    private static function isIdentifierKey(string $key): bool
    {
        return $key === 'id'
            || str_ends_with($key, '_id')
            || str_ends_with($key, '_ids')
            || $key === 'row_version'
            || str_ends_with($key, '_version');
    }

    private static function isDecimalKey(string $key): bool
    {
        return preg_match('/(?:amount|price|quantity|factor|rate|weight|decimal|money)$/i', $key) === 1;
    }
}
