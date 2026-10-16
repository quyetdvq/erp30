<?php

declare(strict_types=1);

namespace App\Modules\Settings\Display\Infrastructure;

use App\Infrastructure\Settings\DisplaySettingRegistry;
use App\Infrastructure\Settings\DisplaySettingsContext;
use App\Modules\Settings\Display\Application\Ports\DisplaySettingsStorageInterface;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Settings\Settings;
use InvalidArgumentException;

/**
 * Registry-guarded adapter over the repository's CodeIgniter Settings store.
 */
final class CodeIgniterDisplaySettingsStorage implements DisplaySettingsStorageInterface
{
    /** @param BaseConnection<mixed, mixed>|null $db */
    public function __construct(
        private readonly Settings $settings,
        private readonly ?BaseConnection $db = null,
    )
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

    /**
     * Read only the persisted override, without CodeIgniter Settings' fallback
     * to an unscoped config value. Mutation services use this to distinguish a
     * missing override from an inherited/default value.
     *
     * @return array{exists: bool, value: mixed}
     */
    public function persistedOverride(string $key, DisplaySettingsContext|string $context): array
    {
        $canonicalContext = $this->context($context);
        $database = $this->db;
        if ($database === null) {
            throw new \RuntimeException('A database connection is required to inspect persisted Display Settings overrides.');
        }

        [$class, $property] = $this->coordinates(DisplaySettingRegistry::storageKey($key));
        $result = $database->table('settings')
            ->select('value, type')
            ->where('class', $class)
            ->where('key', $property)
            ->where('context', $canonicalContext->value())
            ->get();
        if ($result === false) {
            throw new \RuntimeException('Unable to read persisted Display Settings override.');
        }
        $row = $result->getRowArray();

        if ($row === null) {
            return ['exists' => false, 'value' => null];
        }

        return [
            'exists' => true,
            'value'  => $this->decode((string) ($row['value'] ?? ''), (string) ($row['type'] ?? 'string')),
        ];
    }

    /** @return array{string, string} */
    private function coordinates(string $storageKey): array
    {
        $parts = explode('.', $storageKey, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Invalid Display Settings storage key.');
        }

        return [$parts[0], $parts[1]];
    }

    private function decode(string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => $value === '1',
            'integer' => (int) $value,
            'double'  => (float) $value,
            default   => $value,
        };
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
