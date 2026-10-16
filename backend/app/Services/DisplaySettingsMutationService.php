<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationService;
use App\Bootstrap\SettingsFactory;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Settings\CodeIgniterDisplaySettingsStorage;
use App\Infrastructure\Settings\DisplaySettingsContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Settings\Display\Application\DisplaySettingsMutationService as ModuleMutationService;
use App\Modules\Settings\Display\Contracts\DisplaySettingsErrors;
use App\Repositories\ConfigurationAuditEventWriter;
use CodeIgniter\Database\BaseConnection;

/** Compatibility entrypoint. New Display Settings mutation behavior belongs in the module. */
final class DisplaySettingsMutationService
{
    public const ERROR_INVALID = DisplaySettingsErrors::ERROR_INVALID;
    public const ERROR_NOT_FOUND = DisplaySettingsErrors::ERROR_NOT_FOUND;
    public const ERROR_DENIED = DisplaySettingsErrors::ERROR_DENIED;

    private readonly ModuleMutationService $delegate;

    /** @param BaseConnection<mixed,mixed>|null $db */
    public function __construct(
        ?CodeIgniterDisplaySettingsStorage $storage = null,
        ?AuthorizationService $authorization = null,
        ?ConfigurationAuditEventWriter $audit = null,
        ?OrganizationService $organizations = null,
        ?BaseConnection $db = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
    ) {
        $this->delegate = SettingsFactory::mutation(
            storage: $storage,
            authorization: $authorization,
            audit: $audit,
            organizations: $organizations,
            db: $db,
            mutationContext: $mutationContext,
            clock: $clock,
        );
    }

    /**
     * @param array<array-key, mixed> $values
     * @return list<string>
     */
    public function setBatch(string|DisplaySettingsContext $context, array $values, ?OperationContext $operation = null): array
    {
        return $this->delegate->setBatch($context, $values, $operation);
    }

    /**
     * @param array<array-key, mixed> $keys
     * @return list<string>
     */
    public function resetBatch(string|DisplaySettingsContext $context, array $keys, ?OperationContext $operation = null): array
    {
        return $this->delegate->resetBatch($context, $keys, $operation);
    }
}
