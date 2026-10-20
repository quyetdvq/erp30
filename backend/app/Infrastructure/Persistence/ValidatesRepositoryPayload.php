<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Exceptions\DomainException;

/** Canonical allowlist and required-field validation for repository payloads. */
trait ValidatesRepositoryPayload
{
    /** @param array<string,mixed> $data
     *  @param list<string> $required
     *  @return array<string,mixed>
     */
    private function payload(array $data, array $required = []): array
    {
        foreach ($data as $field => $_value) {
            if (! in_array($field, self::FIELDS, true)) {
                throw new DomainException(self::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                throw new DomainException(self::ERROR_FIELD_REQUIRED, ['field' => $field]);
            }
        }

        return $data;
    }
}
