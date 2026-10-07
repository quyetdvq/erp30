<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

use App\Exceptions\DomainException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use Throwable;

/** Maps framework/domain failures to the stable P1 HTTP contract. */
final class ApiErrorMapper
{
    /** @return array{status:int, code:string, message:string, details:list<array<string, string>>} */
    public static function map(Throwable $exception, int $frameworkStatus = 500): array
    {
        $status = self::status($exception, $frameworkStatus);
        $code = self::code($exception, $status);
        $details = self::details($exception, $code);

        return [
            'status' => $status,
            'code' => $code,
            'message' => ApiTransport::message($code, $status),
            'details' => $details,
        ];
    }

    private static function status(Throwable $exception, int $frameworkStatus): int
    {
        if ($exception instanceof PageNotFoundException) {
            return 404;
        }

        if ($exception instanceof BadRequestException) {
            return 400;
        }

        if ($exception instanceof DomainException) {
            $code = strtoupper($exception->getErrorCode());
            if ($code === 'DISPLAY_SETTING_NOT_FOUND') {
                return 422;
            }
            return match (true) {
                str_contains($code, 'NOT_FOUND') || str_contains($code, 'NOTFOUND') => 404,
                str_contains($code, 'UNAUTHENTICATED') || str_contains($code, 'INVALID_CREDENTIAL') => 401,
                str_contains($code, 'DENIED') || str_contains($code, 'FORBIDDEN') => 403,
                str_contains($code, 'CONCURRENT') || str_contains($code, 'CONFLICT') || str_contains($code, 'DUPLICATE') || str_contains($code, 'IMMUTABLE') || $code === 'POLICY_SCOPE_ALREADY_EXISTS' => 409,
                str_contains($code, 'CONNECTION') || str_contains($code, 'PERSISTENCE') || str_contains($code, 'TRANSACTION') || str_contains($code, 'LOCK_FAILED') => 500,
                default => 422,
            };
        }

        return in_array($frameworkStatus, [400, 401, 403, 404, 409, 422], true) ? $frameworkStatus : 500;
    }

    private static function code(Throwable $exception, int $status): string
    {
        if ($exception instanceof DomainException && $status !== 500) {
            return strtoupper($exception->getErrorCode());
        }

        return match ($status) {
            400 => 'MALFORMED_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'RESOURCE_NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'VALIDATION_FAILED',
            default => 'INTERNAL_SERVER_ERROR',
        };
    }

    /** @return list<array{field:string, code:string, message:string}> */
    private static function details(Throwable $exception, string $code): array
    {
        if (! $exception instanceof DomainException) {
            return [];
        }

        $field = $exception->getContext()['field'] ?? null;
        if (! is_string($field) || $field === '') {
            return [];
        }

        return [[
            'field' => $field,
            'code' => $code,
            'message' => ApiTransport::message($code, 422),
        ]];
    }
}
