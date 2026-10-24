<?php

declare(strict_types=1);

namespace App\Infrastructure\Settings;

use InvalidArgumentException;

final class DisplaySettingsContext
{
    private const MAX_SIGNED_BIGINT = '9223372036854775807';

    private function __construct(
        private readonly string $value,
        private readonly ?string $organizationId,
    ) {
    }

    public static function system(): self
    {
        return new self('system', null);
    }

    public static function organization(string|int $id): self
    {
        $normalized = self::normalizeOrganizationId($id);

        return new self('organization:' . $normalized, $normalized);
    }

    public static function parse(string $context): self
    {
        if ($context === 'system') {
            return self::system();
        }

        if (preg_match('/^organization:([1-9][0-9]*)$/', $context, $matches) !== 1) {
            throw new InvalidArgumentException('Invalid Display Settings context.');
        }

        return self::organization($matches[1]);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function organizationId(): ?string
    {
        return $this->organizationId;
    }

    public function isSystem(): bool
    {
        return $this->organizationId === null;
    }

    private static function normalizeOrganizationId(string|int $id): string
    {
        if (is_int($id)) {
            if ($id < 1) {
                throw new InvalidArgumentException('Organization ID must be positive.');
            }

            return (string) $id;
        }

        if (preg_match('/^[1-9][0-9]*$/', $id) !== 1) {
            throw new InvalidArgumentException('Organization ID must be a canonical positive BIGINT.');
        }

        if (strlen($id) > strlen(self::MAX_SIGNED_BIGINT)
            || (strlen($id) === strlen(self::MAX_SIGNED_BIGINT) && strcmp($id, self::MAX_SIGNED_BIGINT) > 0)) {
            throw new InvalidArgumentException('Organization ID exceeds signed BIGINT range.');
        }

        return $id;
    }
}
