<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\RequestContext;
use App\Infrastructure\Settings\CodeIgniterDisplaySettingsStorage;
use App\Infrastructure\Settings\DisplaySettingRegistry;
use App\Infrastructure\Settings\DisplaySettingsContext;
use App\Infrastructure\Settings\DisplaySettingsResolver;
use App\Repositories\OrganizationRepository;
use App\Modules\Settings\Display\Application\DisplaySettingsInputNormalizer;
use App\Modules\Settings\Display\Application\DisplaySettingsResponsePresenter;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Settings\Config\Services as SettingsServices;
use CodeIgniter\Shield\Entities\User;

/** Application boundary for the CONTRACT_READY Display Settings API. */
final class ApiDisplaySettingsService
{
    public const ERROR_CONTEXT_INVALID = 'DISPLAY_SETTING_CONTEXT_INVALID';
    public const ERROR_NOT_FOUND = 'DISPLAY_SETTING_NOT_FOUND';
    public const ERROR_DUPLICATE = 'DISPLAY_SETTING_DUPLICATE_KEY';
    public const ERROR_INVALID = 'DISPLAY_SETTING_INVALID';
    public const ERROR_ORGANIZATION_NOT_FOUND = 'DISPLAY_SETTING_ORGANIZATION_NOT_FOUND';
    public const ERROR_DENIED = 'DISPLAY_SETTING_AUTHORIZATION_DENIED';

    /** @var BaseConnection<mixed, mixed> */
    private BaseConnection $db;

    /** @param BaseConnection<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?DisplaySettingsResolver $resolver = null,
        private readonly ?DisplaySettingsMutationService $mutation = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?OrganizationService $organizations = null,
        private readonly ?OrganizationRepository $organizationRepository = null,
        private readonly ?CodeIgniterDisplaySettingsStorage $storage = null,
        ?BaseConnection $db = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $query
     *  @return array<string, mixed>
     */
    public function runtime(array $query = [], ?OperationContext $operation = null): array
    {
        $operation ??= $this->operation();
        $organizationId = $this->optionalOrganizationId($query['organization_id'] ?? null);
        if ($organizationId !== null) {
            $context = DisplaySettingsContext::organization($organizationId);
            $this->assertOrganizationVisible($context, $operation);
        } else {
            $this->groupId();
        }

        return $this->runtimeSnapshot($this->resolver()->resolveAll($organizationId));
    }

    /** @return list<array<string, mixed>> */
    public function definitions(?OperationContext $operation = null): array
    {
        $operation ??= $this->operation();
        $this->authorizeDisplaySetting(DisplaySettingsContext::system(), 'view', $operation);

        $definitions = [];
        foreach (DisplaySettingRegistry::definitions() as $key => $definition) {
            $item = [
                'key'              => $key,
                'category'         => $this->category($key),
                'data_type'        => $definition['type'],
                'default'          => $definition['default'],
                'user_overridable' => $definition['user_overridable'],
            ];
            if ($definition['validation'] === 'non_negative_integer') {
                $item['min'] = 0;
            }
            $definitions[] = $item;
        }

        return $definitions;
    }

    /** @param array<string, mixed> $query
     *  @return list<array<string, mixed>>
     */
    public function values(array $query, ?OperationContext $operation = null): array
    {
        $operation ??= $this->operation();
        $context = $this->contextFromQuery($query);
        $this->assertContextTarget($context, $operation, true);

        return $this->valueRows($context);
    }

    /** @param array<string, mixed> $payload
     *  @return list<array<string, mixed>>
     */
    public function set(array $payload, ?OperationContext $operation = null): array
    {
        $operation ??= $this->operation();
        $context = $this->contextFromPayload($payload['context'] ?? null);
        $this->assertContextTarget($context, $operation, false);
        $changes = $this->changes($payload['changes'] ?? null);
        $this->mutation()->setBatch($context->value(), $changes, $operation);

        return $this->valueRows($context, array_keys($changes));
    }

    /** @param array<string, mixed> $payload
     *  @return list<array<string, mixed>>
     */
    public function reset(array $payload, ?OperationContext $operation = null): array
    {
        $operation ??= $this->operation();
        $context = $this->contextFromPayload($payload['context'] ?? null);
        $this->assertContextTarget($context, $operation, false);
        $keys = $this->keys($payload['keys'] ?? null);
        $this->mutation()->resetBatch($context->value(), $keys, $operation);

        return $this->valueRows($context, $keys);
    }

    /** @param array<string, mixed> $snapshot
     *  @return array<string, mixed>
     */
    private function runtimeSnapshot(array $snapshot): array
    {
        return DisplaySettingsResponsePresenter::runtimeSnapshot($snapshot);
    }

    /** @param array<string, mixed> $query */
    private function contextFromQuery(array $query): DisplaySettingsContext
    {
        return $this->inputs()->contextFromQuery($query);
    }

    private function contextFromPayload(mixed $payload): DisplaySettingsContext
    {
        return $this->inputs()->contextFromPayload($payload);
    }

    private function optionalOrganizationId(mixed $value): string|null
    {
        return $this->inputs()->optionalOrganizationId($value);
    }

    /** @return array<string, mixed> */
    private function changes(mixed $value): array
    {
        return $this->inputs()->changes($value);
    }

    /** @return list<string> */
    private function keys(mixed $value): array
    {
        return $this->inputs()->keys($value);
    }

    private function assertContextTarget(DisplaySettingsContext $context, OperationContext $operation, bool $view): void
    {
        if ($context->isSystem()) {
            $this->groupId();
        } else {
            $this->assertOrganizationExists($context);
        }
        $this->authorizeDisplaySetting($context, $view ? 'view' : 'edit', $operation);
    }

    private function assertOrganizationExists(DisplaySettingsContext $context): void
    {
        $id = $context->organizationId();
        if ($id === null || ! ctype_digit($id) || (int) $id < 1) {
            throw new DomainException(self::ERROR_CONTEXT_INVALID, ['field' => 'organization_id']);
        }
        if ($this->organizationRepo()->find((int) $id) === null) {
            throw new DomainException(self::ERROR_ORGANIZATION_NOT_FOUND, ['organization_id' => $id]);
        }
    }

    private function assertOrganizationVisible(DisplaySettingsContext $context, OperationContext $operation): void
    {
        $id = $context->organizationId();
        if ($id === null || ! ctype_digit($id) || (int) $id < 1) {
            throw new DomainException(self::ERROR_CONTEXT_INVALID, ['field' => 'organization_id']);
        }
        $row = $this->organizationRepo()->find((int) $id);
        if ($row === null) {
            throw new DomainException(self::ERROR_ORGANIZATION_NOT_FOUND, ['organization_id' => $id]);
        }
        $decision = $this->auth()->authorize(
            $operation,
            'organization',
            'view',
            new EvaluationOrganizationRequest(persistedTarget: $row),
        );
        if (! $decision->isAllowed()) {
            throw new DomainException(self::ERROR_ORGANIZATION_NOT_FOUND, ['organization_id' => $id]);
        }
    }

    private function authorizeDisplaySetting(DisplaySettingsContext $context, string $action, OperationContext $operation): void
    {
        $evaluationOrganizationId = $context->isSystem() ? $this->groupId() : (int) $context->organizationId();
        $decision = $this->auth()->authorize(
            $operation,
            'display_setting',
            $action,
            new EvaluationOrganizationRequest(persistedTarget: ['organization_id' => $evaluationOrganizationId]),
        );
        if (! $decision->isAllowed()) {
            throw new DomainException(self::ERROR_DENIED, ['action' => $action]);
        }
    }

    /** @param list<string>|null $keys
     *  @return list<array<string, mixed>>
     */
    private function valueRows(DisplaySettingsContext $context, ?array $keys = null): array
    {
        $organizationId = $context->organizationId();
        $wanted = $keys ?? DisplaySettingRegistry::keys();
        $rows = [];
        foreach ($wanted as $key) {
            $override = $this->storage()->persistedOverride($key, $context);
            $validOverride = $override['exists'];
            if ($validOverride) {
                try {
                    DisplaySettingRegistry::validate($key, $override['value']);
                } catch (\InvalidArgumentException) {
                    $validOverride = false;
                }
            }
            $source = DisplaySettingsContext::system();
            foreach ($this->resolver()->contextsFor($organizationId) as $candidate) {
                $candidateOverride = $this->storage()->persistedOverride($key, $candidate);
                if (! $candidateOverride['exists']) {
                    continue;
                }
                try {
                    DisplaySettingRegistry::validate($key, $candidateOverride['value']);
                } catch (\InvalidArgumentException) {
                    continue;
                }
                $source = $candidate;
                break;
            }
            $rows[] = [
                'key' => $key,
                'override_value' => $validOverride ? $override['value'] : null,
                'effective_value' => $this->resolver()->resolve($key, $organizationId),
                'source' => [
                    'type' => $source->isSystem() ? 'SYSTEM' : 'ORGANIZATION',
                    'organization_id' => $source->organizationId() === null ? null : (int) $source->organizationId(),
                ],
                'inherited' => ! $validOverride || $source->value() !== $context->value(),
            ];
        }

        return $rows;
    }

    private function category(string $key): string
    {
        return DisplaySettingsResponsePresenter::category($key);
    }

    private function groupId(): int
    {
        $roots = $this->organizationRepo()->findAll(['type' => 'GROUP', 'parent_id' => null]);
        if (count($roots) !== 1) {
            throw new DomainException(self::ERROR_ORGANIZATION_NOT_FOUND, ['context' => 'system']);
        }

        return (int) ($roots[0]['id'] ?? 0);
    }

    private function operation(): OperationContext
    {
        $user = auth()->user();
        if (! $user instanceof User || ! is_numeric($user->id) || (int) $user->id < 1) {
            throw new DomainException('UNAUTHENTICATED');
        }

        return (new MutationContext(service('clock'), (int) $user->id, RequestContext::requestId(), false))->begin();
    }

    private function inputs(): DisplaySettingsInputNormalizer
    {
        return new DisplaySettingsInputNormalizer();
    }

    private function organizationRepo(): OrganizationRepository
    {
        return $this->organizationRepository ?? new OrganizationRepository($this->db);
    }

    private function organizations(): OrganizationService
    {
        return $this->organizations ?? new OrganizationService(null, $this->db);
    }

    private function storage(): CodeIgniterDisplaySettingsStorage
    {
        return $this->storage ?? new CodeIgniterDisplaySettingsStorage(SettingsServices::settings(), $this->db);
    }

    private function resolver(): DisplaySettingsResolver
    {
        return $this->resolver ?? new DisplaySettingsResolver($this->storage(), $this->organizations());
    }

    private function mutation(): DisplaySettingsMutationService
    {
        return $this->mutation ?? new DisplaySettingsMutationService($this->storage(), $this->auth(), null, $this->organizations(), $this->db);
    }

    private function auth(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService(db: $this->db);
    }
}
