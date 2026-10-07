<?php

declare(strict_types=1);

namespace App\Infrastructure\Settings;

use CodeIgniter\Settings\Settings;
use InvalidArgumentException;

/**
 * Registry-guarded adapter over the repository's CodeIgniter Settings store.
 */
final class CodeIgniterDisplaySettingsStorage
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function get(string $key, DisplaySettingsContext|string $context): mixed
    {
        $canonicalContext = $this->context($context);

        return $this->settings->get(
            DisplaySettingRegistry::storageKey($key),
            $canonicalContext->value(),
        );
    }

    public function set(string $key, mixed $value, DisplaySettingsContext|string $context): void
    {
        $canonicalContext = $this->context($context);
        DisplaySettingRegistry::validate($key, $value);

        $this->settings->set(
            DisplaySettingRegistry::storageKey($key),
            $value,
            $canonicalContext->value(),
        );
    }

    public function forget(string $key, DisplaySettingsContext|string $context): void
    {
        $canonicalContext = $this->context($context);

        $this->settings->forget(
            DisplaySettingRegistry::storageKey($key),
            $canonicalContext->value(),
        );
    }

    private function context(DisplaySettingsContext|string $context): DisplaySettingsContext
    {
        if ($context instanceof DisplaySettingsContext) {
            return $context;
        }

        if ($context === '') {
            throw new InvalidArgumentException('Display Settings context is required.');
        }

        return DisplaySettingsContext::parse($context);
    }
}
