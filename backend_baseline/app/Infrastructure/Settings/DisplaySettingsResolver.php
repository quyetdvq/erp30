<?php

declare(strict_types=1);

namespace App\Infrastructure\Settings;

use App\Modules\Settings\Display\Application\DisplaySettingsResolver as ModuleResolver;
use App\Services\OrganizationService;
use CodeIgniter\Settings\Config\Services as SettingsServices;

/** Compatibility entrypoint. New Display Settings resolution belongs in the module. */
final class DisplaySettingsResolver
{
    private readonly ModuleResolver $delegate;

    public function __construct(
        ?CodeIgniterDisplaySettingsStorage $storage = null,
        ?OrganizationService $organizations = null,
    ) {
        $this->delegate = new ModuleResolver(
            $storage ?? new CodeIgniterDisplaySettingsStorage(SettingsServices::settings()),
            $organizations ?? new OrganizationService(),
        );
    }

    public function resolve(string $key, int|string|null $organizationId = null): mixed
    {
        return $this->delegate->resolve($key, $organizationId);
    }

    /** @return array<string, mixed> */
    public function resolveAll(int|string|null $organizationId = null): array
    {
        return $this->delegate->resolveAll($organizationId);
    }

    /** @return list<DisplaySettingsContext> */
    public function contextsFor(int|string|null $organizationId = null): array
    {
        return $this->delegate->contextsFor($organizationId);
    }
}
