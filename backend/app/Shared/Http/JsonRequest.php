<?php

declare(strict_types=1);

namespace App\Shared\Http;

use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\IncomingRequest;

final class JsonRequest
{
    /** @return array<string, mixed> */
    public static function readObject(IncomingRequest $request): array
    {
        $raw = trim((string) $request->getBody());
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new BadRequestException('Malformed JSON request body.');
        }
        return $decoded;
    }
}
