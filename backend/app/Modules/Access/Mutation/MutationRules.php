<?php

declare(strict_types=1);

namespace App\Modules\Access\Mutation;

use App\Exceptions\DomainException;
use App\Modules\Access\ResourceStateCatalog;
use App\Shared\Read\PositiveId;
use InvalidArgumentException;

/**
 * Canonical, side-effect-free input checks. Fields deliberately mirror the
 * v1.2.4 write boundaries; client audit columns and arbitrary SQL keys never
 * pass through to a repository.
 */
final class MutationRules
{
    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function group(array $input, bool $create): array
    {
        self::only($input, ['code', 'name', 'description', 'status'], 'AUTH_GROUP_FIELD_UNSUPPORTED');
        $out = [];
        if (array_key_exists('code', $input)) {
            $out['code'] = strtoupper(self::nonempty($input['code'], 'code', 'AUTH_GROUP_INVALID'));
        }
        if (array_key_exists('name', $input)) {
            $out['name'] = self::nonempty($input['name'], 'name', 'AUTH_GROUP_INVALID', false);
        }
        if (array_key_exists('description', $input)) {
            $out['description'] = self::nullableString($input['description'], 'description');
        }
        if (array_key_exists('status', $input)) {
            // Group service uppercases but does not trim status in v1.2.4.
            $raw = $input['status'];
            $out['status'] = strtoupper(is_string($raw) ? $raw : (is_scalar($raw) ? (string) $raw : ''));
            if (! in_array($out['status'], ['ACTIVE', 'INACTIVE'], true)) {
                throw new DomainException('AUTH_GROUP_INVALID', ['field' => 'status']);
            }
        }
        if ($create && (! isset($out['code'], $out['name']))) {
            throw new DomainException('AUTH_GROUP_INVALID', ['field' => 'group']);
        }
        if ($create) {
            $out['status'] ??= 'ACTIVE';
        }
        if (! $create && $out === []) {
            throw new DomainException('AUTH_GROUP_UPDATE_EMPTY');
        }
        return $out;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function resource(array $input, bool $create): array
    {
        self::only($input, ['type', 'name', 'domain', 'lifecycle_id', 'status']);
        if (! $create && array_key_exists('status', $input)) {
            throw new DomainException('NATIVE_STATE_REQUIRES_SETSTATE', ['resource' => 'resource']);
        }
        if (! $create && array_key_exists('type', $input)) {
            throw new DomainException('RESOURCE_TYPE_IMMUTABLE', ['field' => 'type']);
        }
        if (! $create && array_key_exists('domain', $input)) {
            throw new DomainException('RESOURCE_DOMAIN_IMMUTABLE', ['field' => 'domain']);
        }
        $out = [];
        foreach (['type', 'name'] as $key) {
            if (array_key_exists($key, $input)) {
                $out[$key] = strtolower(self::nonempty($input[$key], $key));
            }
        }
        if (array_key_exists('domain', $input)) {
            $domain = strtoupper(self::nonempty($input['domain'], 'domain', 'RESOURCE_DOMAIN_INVALID'));
            if (! in_array($domain, ['SYSTEM', 'MASTER', 'ORGANIZATION'], true)) {
                throw new DomainException('RESOURCE_DOMAIN_INVALID', ['field' => 'domain']);
            }
            $out['domain'] = $domain;
        }
        if (array_key_exists('lifecycle_id', $input)) {
            $out['lifecycle_id'] = $input['lifecycle_id'] === null ? null : self::id($input['lifecycle_id'], 'lifecycle_id');
        }
        if ($create) {
            if (! isset($out['type'], $out['name'], $out['domain'])) {
                throw new DomainException('VALIDATION_ERROR', ['field' => 'resource']);
            }
            if (array_key_exists('status', $input) && $input['status'] !== 'ACTIVE') {
                throw new DomainException('NATIVE_STATE_REQUIRES_SETSTATE', ['resource' => 'resource']);
            }
            self::checkBinding($out['type'], $out['lifecycle_id'] ?? null);
            $out['lifecycle_id'] ??= null;
            $out['status'] = 'ACTIVE';
        } elseif ($out === []) {
            throw new DomainException('VALIDATION_ERROR');
        }
        return $out;
    }

    public static function checkBinding(string $type, ?string $lifecycleId): void
    {
        // The official ten independent types must not be assigned Lifecycles;
        // all other types must have one. Unknown types never become native.
        try {
            ResourceStateCatalog::assertBinding($type, $lifecycleId);
        } catch (\UnexpectedValueException $exception) {
            throw new DomainException('RESOURCE_LIFECYCLE_INVALID', ['field' => 'lifecycle_id'], $exception);
        }
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function resourceAction(array $input, bool $create): array
    {
        self::only($input, ['code', 'description']);
        $out = [];
        if (array_key_exists('code', $input)) {
            $out['code'] = strtolower(self::nonempty($input['code'], 'code'));
        }
        if ($create && ! isset($out['code'])) {
            throw new DomainException('VALIDATION_ERROR', ['field' => 'code']);
        }
        if (array_key_exists('description', $input)) {
            $out['description'] = self::nullableString($input['description'], 'description');
        }
        if (! $create && $out === []) {
            throw new DomainException('VALIDATION_ERROR');
        }
        return $out;
    }

    /** @param array<string,mixed> $input @return list<string> */
    public static function idList(array $input, string $field): array
    {
        $value = $input[$field] ?? null;
        if (! is_array($value) || ! array_is_list($value)) {
            throw new DomainException('VALIDATION_ERROR', ['field' => $field]);
        }
        $seen = [];
        $out = [];
        foreach ($value as $id) {
            $canonical = self::id($id, $field);
            if (! isset($seen[$canonical])) {
                $seen[$canonical] = true;
                $out[] = $canonical;
            }
        }
        return $out;
    }

    public static function id(mixed $value, string $field): string
    {
        if (! is_int($value) && ! is_string($value)) {
            throw new DomainException('VALIDATION_ERROR', ['field' => $field]);
        }
        try {
            return PositiveId::value($value);
        } catch (InvalidArgumentException) {
            throw new DomainException('VALIDATION_ERROR', ['field' => $field]);
        }
    }

    public static function status(mixed $value, string $code = 'VALIDATION_ERROR'): string
    {
        $state = is_string($value) ? strtoupper(trim($value)) : '';
        if (! in_array($state, ['ACTIVE', 'INACTIVE'], true)) {
            throw new DomainException($code, ['field' => 'status']);
        }
        return $state;
    }

    /** @param array<string,mixed> $input @param list<string> $allowed */
    private static function only(array $input, array $allowed, string $errorCode = 'VALIDATION_ERROR'): void
    {
        // Baseline ignores client-supplied audit columns in service, rather than
        // persisting them. Unknown business keys are rejected at this boundary.
        foreach ($input as $field => $value) {
            if (in_array($field, ['id','created_by','updated_by','created_at','updated_at'], true)) {
                continue;
            }
            if (! in_array($field, $allowed, true)) {
                throw new DomainException($errorCode, ['field' => (string) $field]);
            }
        }
    }

    private static function nonempty(mixed $value, string $field, string $code = 'VALIDATION_ERROR', bool $trim = true): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException($code, ['field' => $field]);
        }
        return $trim ? trim($value) : $value;
    }

    private static function nullableString(mixed $value, string $field): ?string
    {
        if ($value !== null && ! is_string($value)) {
            throw new DomainException('VALIDATION_ERROR', ['field' => $field]);
        }
        return $value;
    }
}
