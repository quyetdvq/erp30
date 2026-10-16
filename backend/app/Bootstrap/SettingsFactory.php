<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Authorization\AuthorizationService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Organization\Application\Ports\OrganizationRepositoryInterface;
use App\Modules\Settings\Display\Application\DisplaySettingsMutationService as ModuleMutationService;
use App\Modules\Settings\Display\Application\DisplaySettingsResolver as ModuleResolver;
use App\Modules\Settings\Display\Application\Ports\ConfigurationAuditEventWriterInterface;
use App\Modules\Settings\Display\Application\Ports\DisplaySettingsStorageInterface;
use App\Modules\Settings\Display\Infrastructure\CodeIgniterDisplaySettingsStorage as ModuleStorage;
use App\Modules\Settings\Display\Infrastructure\SqlConfigurationAuditEventRepository;
use App\Repositories\OrganizationRepository;
use App\Services\OrganizationService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Settings\Config\Services as SettingsServices;
use CodeIgniter\Settings\Settings;

/** Stateless wiring for the Display Settings module. */
final class SettingsFactory
{
    private function __construct()
    {
    }

    /** @param BaseConnection<mixed,mixed>|null $db */
    public static function storage(?Settings $settings = null, ?BaseConnection $db = null): ModuleStorage
    {
        return new ModuleStorage($settings ?? SettingsServices::settings(), $db);
    }

    /** @param BaseConnection<mixed,mixed>|null $db */
    public static function resolver(
        ?DisplaySettingsStorageInterface $storage = null,
        ?OrganizationService $organizations = null,
        ?BaseConnection $db = null,
    ): ModuleResolver {
        $connection = $db ?? db_connect();

        return new ModuleResolver(
            $storage ?? self::storage(db: $connection),
            $organizations ?? new OrganizationService(new OrganizationRepository($connection), $connection),
        );
    }

    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public static function audit(?ConnectionInterface $db = null): SqlConfigurationAuditEventRepository
    {
        return new SqlConfigurationAuditEventRepository($db);
    }

    /** @param BaseConnection<mixed,mixed>|null $db */
    public static function mutation(
        ?DisplaySettingsStorageInterface $storage = null,
        ?AuthorizationService $authorization = null,
        ?ConfigurationAuditEventWriterInterface $audit = null,
        ?OrganizationService $organizations = null,
        ?OrganizationRepositoryInterface $organizationRepository = null,
        ?BaseConnection $db = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
    ): ModuleMutationService {
        $connection = $db ?? db_connect();

        return new ModuleMutationService(
            storage: $storage ?? self::storage(db: $connection),
            authorization: $authorization ?? new AuthorizationService(db: $connection),
            audit: $audit ?? self::audit($connection),
            organizations: $organizations ?? new OrganizationService(new OrganizationRepository($connection), $connection),
            organizationRepository: $organizationRepository ?? OrganizationFactory::repository($connection),
            db: $connection,
            mutationContext: $mutationContext,
            clock: $clock,
        );
    }
}
