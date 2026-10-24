<?php

declare(strict_types=1);

namespace App\Modules\Identity\Mutation;

use App\Exceptions\DomainException;
use App\Shared\Read\PositiveId;
use InvalidArgumentException;

/**
 * M3.3B3: exact v1.2.4 ShieldUserIdentityService input contracts.
 * Passwords are validated against Shield policy by the service, not here.
 * Never accept raw password hashes or unreviewed identity/audit attributes.
 */
final class SecurityUserMutationRules
{
    private const STATES = ['ACTIVE','INACTIVE','LOCKED','SUSPENDED','BANNED','DISABLED'];

    public static function id(int|string $input, string $field = 'user_id'): string
    {
        try {
            $id = PositiveId::value($input);
        } catch (InvalidArgumentException) {
            throw new DomainException('USER_NOT_FOUND', [$field => (string) $input]);
        }
        // Shield v1.2.4 uses int IDs; refuse overflowing rather than cast.
        if (strlen($id) > strlen((string) PHP_INT_MAX)
            || (strlen($id) === strlen((string) PHP_INT_MAX) && strcmp($id, (string) PHP_INT_MAX) > 0)) {
            throw new DomainException('USER_NOT_FOUND', [$field => $id]);
        }
        return $id;
    }

    /** @param array<string,mixed> $input
     *  @return array{username:string,email:string,state:string,password:string,name:?string,group_ids:list<string>}
     */
    public static function create(array $input): array
    {
        self::allow($input, ['username','email','password','state','name','group_ids']);
        return [
            'username' => self::username($input['username'] ?? null),
            'email' => self::email($input['email'] ?? null),
            'state' => self::state($input['state'] ?? null),
            'password' => self::password($input['password'] ?? null),
            'name' => array_key_exists('name', $input) ? self::name($input['name']) : null,
            'group_ids' => array_key_exists('group_ids', $input) ? self::groupIds($input['group_ids']) : [],
        ];
    }

    /** @param array<string,mixed> $input @return array<string,string> */
    public static function patch(array $input): array
    {
        self::allow($input, ['username','email','row_version','name']);
        $result = [];
        if (array_key_exists('username', $input)) { $result['username'] = self::username($input['username']); }
        if (array_key_exists('email', $input)) { $result['email'] = self::email($input['email']); }
        if (array_key_exists('name', $input)) { $result['full_name'] = self::name($input['name']); }
        if ($result === []) {
            throw new DomainException('VALIDATION_ERROR');
        }
        return $result;
    }

    /** @param array<string,mixed> $input */
    public static function setState(array $input): string
    {
        self::allow($input, ['state','row_version']);
        return self::state($input['state'] ?? null);
    }

    /** @param array<string,mixed> $input */
    public static function resetPassword(array $input): string
    {
        self::allow($input, ['new_password','row_version']);
        return self::password($input['new_password'] ?? null);
    }

    /** @param array<string,mixed> $input @return list<string> */
    public static function permissions(array $input): array
    {
        self::allow($input, ['permissions']);
        if (! isset($input['permissions']) || ! is_array($input['permissions']) || ! array_is_list($input['permissions'])) {
            throw new DomainException('VALIDATION_ERROR', ['field'=>'permissions']);
        }
        $unique = [];
        foreach ($input['permissions'] as $permission) {
            if (! is_string($permission)
                || preg_match('/\A[a-z0-9_]+\.[a-z0-9_]+\z/D', strtolower(trim($permission))) !== 1) {
                throw new DomainException('VALIDATION_ERROR', ['field'=>'permissions']);
            }
            $token = strtolower(trim($permission));
            $unique[$token] = $token;
        }
        return array_values($unique);
    }

    public static function username(mixed $value): string
    {
        if (! is_string($value)) { throw new DomainException('VALIDATION_ERROR', ['field'=>'username']); }
        $value = strtolower(trim($value));
        if ($value === '' || strlen($value) > 30) {
            throw new DomainException('VALIDATION_ERROR', ['field'=>'username']);
        }
        return $value;
    }

    public static function email(mixed $value): string
    {
        if (! is_string($value)) { throw new DomainException('VALIDATION_ERROR', ['field'=>'email']); }
        $value = strtolower(trim($value));
        if (strlen($value) > 254 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('VALIDATION_ERROR', ['field'=>'email']);
        }
        return $value;
    }

    public static function state(mixed $value): string
    {
        $value = is_string($value) ? strtoupper(trim($value)) : '';
        if (! in_array($value, self::STATES, true)) {
            throw new DomainException('USER_STATE_INVALID', ['field'=>'state']);
        }
        return $value;
    }

    public static function password(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException('USER_PASSWORD_POLICY_INVALID', ['field'=>'password']);
        }
        return $value;
    }

    public static function name(mixed $value): string
    {
        if (! is_string($value) || preg_match('//u', $value) !== 1) {
            throw new DomainException('VALIDATION_ERROR', ['field'=>'name']);
        }
        $value = preg_replace('/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $value);
        if (! is_string($value) || $value === '' || preg_match_all('/./us', $value) > 255) {
            throw new DomainException('VALIDATION_ERROR', ['field'=>'name']);
        }
        return $value;
    }

    /** @return list<string> */
    public static function groupIds(mixed $ids): array
    {
        if (! is_array($ids) || ! array_is_list($ids)) {
            throw new DomainException('VALIDATION_ERROR', ['field'=>'group_ids']);
        }
        $result = [];
        $seen = [];
        foreach ($ids as $id) {
            // Baseline accepts canonical positive decimal strings ONLY.
            if (! is_string($id) || preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1
                || filter_var($id, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) === false
                || isset($seen[$id])) {
                throw new DomainException('VALIDATION_ERROR', ['field'=>'group_ids']);
            }
            $seen[$id] = true;
            $result[] = $id;
        }
        sort($result, SORT_NUMERIC);
        return $result;
    }

    /** @param array<string,mixed> $input @param list<string> $fields */
    private static function allow(array $input, array $fields): void
    {
        foreach ($input as $field => $_value) {
            if (! in_array($field, $fields, true)) {
                throw new DomainException('VALIDATION_ERROR', ['field'=>(string) $field]);
            }
        }
    }
}
