<?php

declare(strict_types=1);

namespace App\Modules\Organization\Mutation;

use App\Exceptions\DomainException;
use App\Shared\Read\PositiveId;
use DateTimeImmutable;
use InvalidArgumentException;

/** Pure field, date and identifier rules matching the pinned API-010 schema. */
final class OrganizationMutationRules
{
    private const FIELDS = ['parent_id', 'type', 'code', 'name', 'legal_name', 'tax_code', 'address',
        'province_id', 'ward_id', 'description', 'status', 'active_from', 'active_to'];
    private const TEXT_REQUIRED = ['code', 'name'];
    private const TEXT_OPTIONAL = ['legal_name', 'tax_code', 'address', 'description'];
    private const TYPES = ['GROUP','COMPANY','BRANCH','PLANT','WAREHOUSE'];

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function payload(array $input, bool $create): array
    {
        $out = [];
        foreach ($input as $field => $value) {
            if (in_array($field, ['created_by', 'updated_by', 'created_at', 'updated_at'], true)) {
                continue;
            }
            if (!in_array($field, self::FIELDS, true)) {
                throw new DomainException('ORG_FIELD_UNSUPPORTED', ['field' => (string)$field]);
            }
            if (in_array($field, ['parent_id','province_id','ward_id'], true)) {
                $out[$field] = self::nullableId($value, $field);
            } elseif ($field === 'type') {
                $out[$field] = self::type($value);
            } elseif ($field === 'status') {
                $out[$field] = self::status($value);
            } elseif (in_array($field, ['active_from','active_to'], true)) {
                $out[$field] = self::date($value, $field);
            } elseif (in_array($field, self::TEXT_REQUIRED, true)) {
                if (!is_string($value) || trim($value) === '') {
                    throw new DomainException('ORG_FIELD_REQUIRED', ['field'=>$field]);
                }
                $out[$field] = $value;
            } elseif (in_array($field, self::TEXT_OPTIONAL, true)) {
                if ($value !== null && !is_string($value)) {
                    throw new DomainException('ORG_FIELD_UNSUPPORTED', ['field'=>$field]);
                }
                $out[$field] = $value;
            }
        }
        if ($create) {
            foreach (['type', 'code', 'name'] as $field) {
                if (!array_key_exists($field, $out)) {
                    throw new DomainException('ORG_FIELD_REQUIRED', ['field'=>$field]);
                }
            }
        } elseif ($out === []) {
            throw new DomainException('ORG_UPDATE_EMPTY');
        }
        return $out;
    }

    public static function type(mixed $value): string
    {
        $type = is_string($value) ? strtoupper(trim($value)) : '';
        if (!in_array($type, self::TYPES, true)) {
            throw new DomainException('ORG_INVALID_PARENT', ['field'=>'type']);
        }
        return $type;
    }

    public static function id(mixed $value, string $field='id'): string
    {
        if (!is_string($value) && !is_int($value)) {
            throw new DomainException('ORG_REFERENCE_INVALID', ['field'=>$field]);
        }
        try {
            return PositiveId::value($value);
        } catch (InvalidArgumentException $e) {
            throw new DomainException('ORG_REFERENCE_INVALID', ['field'=>$field], $e);
        }
    }

    public static function nullableId(mixed $value, string $field): ?string
    {
        return $value === null ? null : self::id($value, $field);
    }

    public static function status(mixed $value): string
    {
        $normalized = is_string($value) ? strtoupper(trim($value)) : '';
        if (preg_match('/\A[A-Z][A-Z0-9_:-]{0,49}\z/D', $normalized) !== 1) {
            throw new DomainException('ORG_FIELD_UNSUPPORTED', ['field'=>'status']);
        }
        return $normalized;
    }

    public static function date(mixed $value, string $field): ?string
    {
        if ($value === null) return null;
        if (!is_string($value)) {
            throw new DomainException('ORG_ACTIVE_DATE_INVALID', ['field'=>$field]);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || $date->format('Y-m-d') !== $value
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new DomainException('ORG_ACTIVE_DATE_INVALID', ['field'=>$field]);
        }
        return $value;
    }

    /** @param array<string,mixed> $candidate */
    public static function assertDates(array $candidate): void
    {
        foreach (['active_from','active_to'] as $field) {
            self::date($candidate[$field] ?? null, $field);
        }
        if (($candidate['active_from'] ?? null) !== null && ($candidate['active_to'] ?? null) !== null
            && $candidate['active_from'] > $candidate['active_to']) {
            throw new DomainException('ORG_ACTIVE_DATE_INVALID', ['field'=>'active_to']);
        }
    }
}
