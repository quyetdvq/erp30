<?php

declare(strict_types=1);

namespace App\Infrastructure\Settings;

use App\Modules\Settings\Display\Application\Ports\DisplaySettingsStorageInterface;
use App\Modules\Settings\Display\Infrastructure\CodeIgniterDisplaySettingsStorage as ModuleStorage;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Settings\Settings;

/** Compatibility entrypoint. New Display Settings persistence belongs in the module. */
final class CodeIgniterDisplaySettingsStorage implements DisplaySettingsStorageInterface
{
    private readonly ModuleStorage $delegate;

    /** @param BaseConnection<mixed, mixed>|null $db */
    public function __construct(Settings $settings, ?BaseConnection $db = null)
    {
        $this->delegate = new ModuleStorage($settings, $db);
    }

    public function get(string $key, DisplaySettingsContext|string $context): mixed
    {
        return $this->delegate->get($key, $context);
    }

    public function set(string $key, mixed $value, DisplaySettingsContext|string $context): void
    {
        $this->delegate->set($key, $value, $context);
    }

    public function forget(string $key, DisplaySettingsContext|string $context): void
    {
        $this->delegate->forget($key, $context);
    }

    /** @return array{exists: bool, value: mixed} */
    public function persistedOverride(string $key, DisplaySettingsContext|string $context): array
    {
        return $this->delegate->persistedOverride($key, $context);
    }
}
