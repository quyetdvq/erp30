<?php

declare(strict_types=1);

namespace App\Modules\Settings\Display\Application;

use App\Exceptions\DomainException;
use App\Infrastructure\Settings\DisplaySettingRegistry;
use App\Infrastructure\Settings\DisplaySettingsContext;

/** Stateless API input normalization; owns validation, not authorization or writes. */
final class DisplaySettingsInputNormalizer
{
    public const ERROR_CONTEXT_INVALID = 'DISPLAY_SETTING_CONTEXT_INVALID';
    public const ERROR_NOT_FOUND = 'DISPLAY_SETTING_NOT_FOUND';
    public const ERROR_DUPLICATE = 'DISPLAY_SETTING_DUPLICATE_KEY';
    public const ERROR_INVALID = 'DISPLAY_SETTING_INVALID';

    /** @param array<string,mixed> $query */
    public function contextFromQuery(array $query): DisplaySettingsContext
    {
        $type = $query['context_type'] ?? null;
        if (! is_string($type)) {
            throw new DomainException(self::ERROR_CONTEXT_INVALID, ['field' => 'context_type']);
        }
        $type = strtoupper(trim($type));
        if ($type === 'SYSTEM' && ! array_key_exists('organization_id', $query)) {
            return DisplaySettingsContext::system();
        }
        if ($type === 'ORGANIZATION' && array_key_exists('organization_id', $query)) {
            return $this->organizationContext($query['organization_id']);
        }

        throw new DomainException(self::ERROR_CONTEXT_INVALID, ['field' => 'context']);
    }

    public function contextFromPayload(mixed $payload): DisplaySettingsContext
    {
        if (! is_array($payload) || ! array_key_exists('type', $payload) || ! is_string($payload['type'])) {
            throw new DomainException(self::ERROR_CONTEXT_INVALID, ['field' => 'context']);
        }
        $type = strtoupper(trim($payload['type']));
        if ($type === 'SYSTEM' && ! array_key_exists('organization_id', $payload)) {
            return DisplaySettingsContext::system();
        }
        if ($type === 'ORGANIZATION' && array_key_exists('organization_id', $payload)) {
            return $this->organizationContext($payload['organization_id']);
        }

        throw new DomainException(self::ERROR_CONTEXT_INVALID, ['field' => 'context']);
    }

    private function organizationContext(mixed $value): DisplaySettingsContext
    {
        if (! is_int($value) && ! is_string($value)) {
            throw new DomainException(self::ERROR_CONTEXT_INVALID, ['field' => 'organization_id']);
        }
        try {
            return DisplaySettingsContext::organization($value);
        } catch (\InvalidArgumentException $exception) {
            throw new DomainException(self::ERROR_CONTEXT_INVALID, ['field' => 'organization_id'], $exception);
        }
    }

    public function optionalOrganizationId(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }
        if (! is_int($value) && ! is_string($value)) {
            throw new DomainException(self::ERROR_CONTEXT_INVALID, ['field' => 'organization_id']);
        }
        try {
            return DisplaySettingsContext::organization($value)->organizationId();
        } catch (\InvalidArgumentException $exception) {
            throw new DomainException(self::ERROR_CONTEXT_INVALID, ['field' => 'organization_id'], $exception);
        }
    }

    /** @return array<string,mixed> */
    public function changes(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new DomainException(self::ERROR_INVALID, ['field' => 'changes']);
        }
        $changes = [];
        foreach ($value as $change) {
            if (! is_array($change) || ! isset($change['key']) || ! is_string($change['key']) || ! array_key_exists('value', $change)) {
                throw new DomainException(self::ERROR_INVALID, ['field' => 'changes']);
            }
            $key = $change['key'];
            if (array_key_exists($key, $changes)) {
                throw new DomainException(self::ERROR_DUPLICATE, ['field' => 'key']);
            }
            try {
                DisplaySettingRegistry::validate($key, $change['value']);
            } catch (\InvalidArgumentException $exception) {
                if (! array_key_exists($key, DisplaySettingRegistry::definitions())) {
                    throw new DomainException(self::ERROR_NOT_FOUND, ['field' => 'key'], $exception);
                }
                throw new DomainException(self::ERROR_INVALID, ['field' => 'value'], $exception);
            }
            $changes[$key] = $change['value'];
        }

        return $changes;
    }

    /** @return list<string> */
    public function keys(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new DomainException(self::ERROR_INVALID, ['field' => 'keys']);
        }
        $keys = [];
        foreach ($value as $key) {
            if (! is_string($key) || $key === '') {
                throw new DomainException(self::ERROR_INVALID, ['field' => 'keys']);
            }
            if (in_array($key, $keys, true)) {
                throw new DomainException(self::ERROR_DUPLICATE, ['field' => 'keys']);
            }
            if (! array_key_exists($key, DisplaySettingRegistry::definitions())) {
                throw new DomainException(self::ERROR_NOT_FOUND, ['field' => 'key']);
            }
            $keys[] = $key;
        }

        return $keys;
    }
}
