<?php

declare(strict_types=1);

namespace App\Shared\Http;

use CodeIgniter\HTTP\ResponseInterface;

final class ApiResponder
{
    /** @param array<string, mixed> $meta */
    public static function success(ResponseInterface $response, mixed $data, int $status = 200, array $meta = []): ResponseInterface
    {
        self::headers($response);
        if ($status === 204) {
            return $response->setStatusCode(204)->setBody('');
        }
        return $response->setStatusCode($status)->setJSON(ApiEnvelope::success($data, $meta));
    }

    /** @param list<array<string,mixed>> $details */
    public static function error(ResponseInterface $response, string $code, int $status, array $details = []): ResponseInterface
    {
        self::headers($response);
        $message = lang(self::translationKey($code, $status), [], RequestMetadata::locale());
        if (! is_string($message)) {
            $message = (string) implode(' ', $message);
        }
        return $response->setStatusCode($status)->setJSON(ApiEnvelope::failure($code, $message, $details));
    }

    public static function message(string $code, int $status): string
    {
        $value = lang(self::translationKey($code, $status), [], RequestMetadata::locale());
        return is_string($value) ? $value : implode(' ', $value);
    }

    public static function headers(ResponseInterface $response): void
    {
        $response->setHeader('X-Request-ID', RequestMetadata::id());
        $response->setHeader('Content-Language', RequestMetadata::locale());
    }

    /** Keep baseline messages intact; only explicitly reviewed extension codes use ApiRuntime. */
    private static function translationKey(string $code, int $status): string
    {
        if ($code === 'CONTACT_PRIMARY_SCOPE_UNAVAILABLE') {
            return 'ApiRuntime.contactPrimaryScopeUnavailable';
        }
        if ($code === 'CONTACT_STATE_REQUIRES_SETSTATE') {
            return 'ApiRuntime.contactStateRequiresSetstate';
        }
        if (str_starts_with($code, 'CONTACT_')) {
            return 'Contact.' . $code;
        }
        if (str_starts_with($code, 'PRICE_LIST_')) {
            return 'PriceListRuntime.' . $code;
        }
        if (str_starts_with($code, 'PRODUCT_GROUP_')) {
            return 'ProductGroup.' . $code;
        }
        if (str_starts_with($code, 'PRODUCT_UOM_')) {
            return 'ProductUomMaster.' . $code;
        }
        // Keep the two reviewed v1.2.4 translation keys byte-for-byte.
        if ($code === 'IMPORT_FILE_TOO_LARGE' || $code === 'IMPORT_ROW_LIMIT_EXCEEDED') {
            return 'Api.' . self::messageKey($code, $status);
        }
        if (str_starts_with($code, 'IMPORT_') || str_starts_with($code, 'PRODUCT_IMPORT_')) {
            return 'ImportRuntime.' . $code;
        }
        if (str_starts_with($code, 'PRODUCT_') || str_starts_with($code, 'COMBO_')
            || str_starts_with($code, 'EVALUATION_ORGANIZATION_HINT_')) {
            return 'ProductRuntime.' . $code;
        }
        if ($code === 'ACCESS_MUTATION_NOT_ENABLED') {
            return 'ApiRuntime.mutationDisabled';
        }
        return 'Api.' . self::messageKey($code, $status);
    }

    private static function messageKey(string $code, int $status): string
    {
        return match ($code) {
            'USER_AVATAR_INVALID_SIZE' => 'userAvatarInvalidSize',
            'USER_AVATAR_INVALID_TYPE' => 'userAvatarInvalidType',
            'USER_AVATAR_INVALID_CONTENT' => 'userAvatarInvalidContent',
            'USER_AVATAR_NOT_FOUND' => 'userAvatarNotFound',
            'USER_AVATAR_CONCURRENT_UPDATE' => 'userAvatarConcurrentUpdate',
            'USER_AVATAR_STORAGE_FAILED' => 'userAvatarStorageFailed',
            'INVALID_CREDENTIALS' => 'invalidCredentials',
            'PROFILE_UPDATE_FAILED' => 'profileUpdateFailed',
            'POLICY_SCOPE_ALREADY_EXISTS' => 'policyScopeAlreadyExists',
            'IMPORT_FILE_TOO_LARGE' => 'importFileTooLarge',
            'IMPORT_ROW_LIMIT_EXCEEDED' => 'importRowLimitExceeded',
            default => match ($status) {
                400 => 'invalidRequest', 401 => 'unauthenticated', 403 => 'forbidden',
                404 => 'notFound', 409 => 'conflict', 422 => 'validation', default => 'serverError',
            },
        };
    }
}
