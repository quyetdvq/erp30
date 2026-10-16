<?php

declare(strict_types=1);

namespace App\Modules\Settings\Display\Application\Ports;

use App\Infrastructure\Settings\DisplaySettingsContext;

interface DisplaySettingsStorageInterface
{
    public function get(string $key, DisplaySettingsContext|string $context): mixed;

    public function set(string $key, mixed $value, DisplaySettingsContext|string $context): void;

    public function forget(string $key, DisplaySettingsContext|string $context): void;

    /** @return array{exists: bool, value: mixed} */
    public function persistedOverride(string $key, DisplaySettingsContext|string $context): array;
}
