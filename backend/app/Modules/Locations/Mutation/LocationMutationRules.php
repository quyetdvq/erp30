<?php

declare(strict_types=1);

namespace App\Modules\Locations\Mutation;

use App\Exceptions\DomainException;
use App\Shared\Read\PositiveId;
use InvalidArgumentException;

/** Baseline province/ward field rules, with no database or authorization dependency. */
final class LocationMutationRules
{
    private const FIELDS = [
        'province' => ['code', 'name', 'status'],
        'ward' => ['province_id', 'code', 'name', 'name_full', 'status'],
    ];

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function payload(string $type, array $input, bool $create): array
    {
        $allowed = self::FIELDS[$type] ?? throw new InvalidArgumentException('Unsupported Location resource');
        $out = [];
        foreach ($input as $field => $value) {
            // Never trust client audit columns. Matches the v1.2.4 Location service.
            if (in_array($field, ['created_by', 'updated_by', 'created_at', 'updated_at'], true)) {
                continue;
            }
            if (! in_array($field, $allowed, true)) {
                throw new DomainException('LOCATION_FIELD_UNSUPPORTED', ['field' => (string) $field]);
            }
            if ($field === 'province_id') {
                $out[$field] = self::provinceId($value);
            } elseif ($field === 'status') {
                $out[$field] = self::status($value);
            } elseif ($field === 'name_full') {
                if ($value !== null && ! is_string($value)) {
                    throw new DomainException('LOCATION_FIELD_UNSUPPORTED', ['field' => $field]);
                }
                $out[$field] = $value;
            } else {
                if (! is_string($value) || trim($value) === '') {
                    throw new DomainException('LOCATION_FIELD_REQUIRED', ['field' => $field]);
                }
                $out[$field] = $value;
            }
        }
        if ($create) {
            $required = $type === 'ward' ? ['province_id', 'code', 'name'] : ['code', 'name'];
            foreach ($required as $field) {
                if (! array_key_exists($field, $out)) {
                    throw new DomainException('LOCATION_FIELD_REQUIRED', ['field' => $field]);
                }
            }
        } elseif ($out === []) {
            throw new DomainException('LOCATION_UPDATE_EMPTY');
        }
        return $out;
    }

    public static function provinceId(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new DomainException('WARD_PROVINCE_NOT_FOUND', ['field' => 'province_id']);
        }
        try {
            return PositiveId::value($value);
        } catch (InvalidArgumentException $exception) {
            throw new DomainException('WARD_PROVINCE_NOT_FOUND', ['field' => 'province_id'], $exception);
        }
    }

    public static function id(mixed $value, string $type): string
    {
        if (! in_array($type, ['province', 'ward'], true)) {
            throw new InvalidArgumentException('Unsupported Location resource');
        }
        $code = strtoupper($type) . '_NOT_FOUND';
        if (! is_string($value) && ! is_int($value)) {
            throw new DomainException($code);
        }
        try {
            return PositiveId::value($value);
        } catch (InvalidArgumentException $exception) {
            throw new DomainException($code, [], $exception);
        }
    }

    public static function status(mixed $value): string
    {
        $status = is_string($value) ? strtoupper(trim($value)) : '';
        if (preg_match('/\A[A-Z][A-Z0-9_:-]{0,49}\z/D', $status) !== 1) {
            throw new DomainException('LOCATION_STATUS_INVALID', ['field' => 'status']);
        }
        return $status;
    }
}
