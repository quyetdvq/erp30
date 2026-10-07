<?php

declare(strict_types=1);

namespace App\Infrastructure\Settings;

use App\Services\OrganizationService;
use CodeIgniter\Settings\Config\Services as SettingsServices;
use InvalidArgumentException;

/**
 * Resolves presentation-only Display Settings along the canonical Organization path.
 */
final class DisplaySettingsResolver
{
    private readonly CodeIgniterDisplaySettingsStorage $storage;
    private readonly OrganizationService $organizations;

    public function __construct(
        ?CodeIgniterDisplaySettingsStorage $storage = null,
        ?OrganizationService $organizations = null,
    ) {
        $this->storage = $storage ?? new CodeIgniterDisplaySettingsStorage(SettingsServices::settings());
        $this->organizations = $organizations ?? new OrganizationService();
    }

    /**
     * Resolve one registered setting for an optional current Organization.
     */
    public function resolve(string $key, int|string|null $organizationId = null): mixed
    {
        DisplaySettingRegistry::definition($key);

        foreach ($this->contexts($organizationId) as $context) {
            $value = $this->storage->get($key, $context);
            if ($value === null) {
                continue;
            }

            try {
                DisplaySettingRegistry::validate($key, $value);
            } catch (InvalidArgumentException) {
                // Invalid legacy rows never override an inherited valid value.
                continue;
            }

            return $value;
        }

        return DisplaySettingRegistry::default($key);
    }

    /**
     * Resolve the complete registered presentation snapshot.
     *
     * @return array<string, mixed>
     */
    public function resolveAll(int|string|null $organizationId = null): array
    {
        $settings = [];
        foreach (DisplaySettingRegistry::keys() as $key) {
            $settings[$key] = $this->resolve($key, $organizationId);
        }

        return $settings;
    }

    /** @return list<DisplaySettingsContext> */
    private function contexts(int|string|null $organizationId): array
    {
        $contexts = [];
        if ($organizationId !== null) {
            $organization = DisplaySettingsContext::organization($organizationId);
            $normalizedId = $organization->organizationId();
            if ($normalizedId === null || filter_var($normalizedId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new InvalidArgumentException('Organization ID is outside the runtime integer range.');
            }

            foreach (array_reverse($this->organizations->resolveAncestorChain((int) $normalizedId)) as $node) {
                $contexts[] = DisplaySettingsContext::organization($node['id'] ?? null);
            }
        }

        $contexts[] = DisplaySettingsContext::system();

        return $contexts;
    }
}
