<?php

declare(strict_types=1);

namespace App\Modules\Contact;

use App\Exceptions\DomainException;
use App\Shared\Read\PositiveId;
use InvalidArgumentException;

/** Immutable owner identity, strict API-010 field validation and State input. */
final class ContactRules
{
    private const OWNERS = ['ORGANIZATION' => 'organization_contact', 'EMPLOYEE' => 'employee_contact', 'CUSTOMER' => 'customer_contact'];
    private const FIELDS = ['description' => 65535, 'name' => 255, 'email' => 255,
        'phone' => 50, 'mobile' => 50, 'address' => 65535,
        'province_name' => 255, 'ward_name' => 255];
    private const AUDIT = ['created_by', 'updated_by', 'created_at', 'updated_at'];

    public static function id(mixed $value, string $field = 'id'): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new DomainException('CONTACT_OWNER_REFERENCE_REQUIRED', ['field' => $field]);
        }
        try {
            return PositiveId::value($value);
        } catch (InvalidArgumentException $exception) {
            throw new DomainException('CONTACT_OWNER_REFERENCE_REQUIRED', ['field' => $field], $exception);
        }
    }

    /** @param array<string,mixed> $row @return array{type:string,resource:string,id:string} */
    public static function owner(array $row): array
    {
        $raw = $row['contact_type'] ?? null;
        if (! is_string($raw) || trim($raw) === '') {
            throw new DomainException('CONTACT_TYPE_REQUIRED', ['field' => 'contact_type']);
        }
        $type = strtoupper(trim($raw));
        if (! isset(self::OWNERS[$type])) {
            throw new DomainException('CONTACT_TYPE_UNSUPPORTED', ['field' => 'contact_type']);
        }
        if (! array_key_exists('contact_ref_id', $row) || $row['contact_ref_id'] === null) {
            throw new DomainException('CONTACT_OWNER_REFERENCE_REQUIRED', ['field' => 'contact_ref_id']);
        }
        return ['type' => $type, 'resource' => self::OWNERS[$type],
            'id' => self::id($row['contact_ref_id'], 'contact_ref_id')];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function payload(array $input, bool $create): array
    {
        $output = [];
        foreach ($input as $field => $value) {
            if (in_array($field, self::AUDIT, true)) {
                continue; // baseline strips client audit fields; never persist them
            }
            if (in_array($field, ['contact_type', 'contact_ref_id'], true)) {
                if (! $create) {
                    throw new DomainException('CONTACT_OWNER_TRANSFER_UNSUPPORTED', ['field' => $field]);
                }
                $output[$field] = $value;
                continue;
            }
            if ($field === 'status') {
                if (! $create) {
                    throw new DomainException('CONTACT_STATE_REQUIRES_SETSTATE', ['field' => 'status']);
                }
                $output['status'] = self::status($value);
                continue;
            }
            if ($field === 'province_id' || $field === 'ward_id') {
                $output[$field] = $value === null ? null : self::id($value, $field);
                continue;
            }
            if (! isset(self::FIELDS[$field])) {
                throw new DomainException('CONTACT_FIELD_UNSUPPORTED', ['field' => (string) $field]);
            }
            if ($value !== null) {
                $length = is_string($value) ? preg_match_all('/./us', $value) : false;
                if ($length === false || $length > self::FIELDS[$field]) {
                    throw new DomainException('CONTACT_FIELD_UNSUPPORTED', ['field' => $field]);
                }
            }
            $output[$field] = $value;
        }
        if ($create) {
            $owner = self::owner($output);
            $output['contact_type'] = $owner['type'];
            $output['contact_ref_id'] = $owner['id'];
        } elseif ($output === []) {
            throw new DomainException('CONTACT_UPDATE_EMPTY');
        }
        return $output;
    }

    public static function status(mixed $value): string
    {
        $code = is_string($value) ? strtoupper(trim($value)) : '';
        if (preg_match('/\A[A-Z][A-Z0-9_:-]{0,19}\z/D', $code) !== 1) {
            throw new DomainException('CONTACT_STATUS_INVALID', ['field' => 'status']);
        }
        return $code;
    }

    /** @param array<string,mixed> $request */
    public static function requestedState(array $request): string
    {
        if (array_keys($request) !== ['status']) {
            throw new DomainException('CONTACT_STATUS_INVALID', ['field' => 'status']);
        }
        return self::status($request['status']);
    }

    /** @param list<array<string,mixed>> $rows @return array<string,mixed>|null */
    public static function uniqueActivePrimary(array $rows): ?array
    {
        if (count($rows) !== 1 || ($rows[0]['status'] ?? null) !== 'ACTIVE'
            || ! in_array($rows[0]['is_primary'] ?? null, [1, '1'], true)) {
            return null;
        }
        return $rows[0];
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $persisted @param list<array<string,mixed>> $transitions */
    public static function destination(array $definition, array $persisted, array $transitions, string $requested): string
    {
        if (($definition['mode'] ?? null) !== 'LIFECYCLE') {
            throw new DomainException('WF_TRANSITION_REQUIRED');
        }
        $from = $definition['by_code'][(string) ($persisted['status'] ?? '')] ?? null;
        $to = $definition['by_code'][$requested] ?? null;
        if ($from === null || $to === null) {
            throw new DomainException('WF_INVALID_TRANSITION', ['field' => 'status']);
        }
        $matching = [];
        foreach ($transitions as $transition) {
            if ((string) ($transition['lifecycle_id'] ?? '') === (string) $definition['lifecycle_id']
                && (string) ($transition['from_state_id'] ?? '') === (string) $from
                && strtolower((string) ($transition['action_code'] ?? '')) === 'setstate'
                && ($transition['status'] ?? null) === 'ACTIVE') {
                $matching[] = (string) ($transition['to_state_id'] ?? '');
            }
        }
        if (count($matching) !== 1 || $matching[0] !== (string) $to) {
            throw new DomainException('WF_INVALID_TRANSITION', ['field' => 'status']);
        }
        return $requested;
    }
}
