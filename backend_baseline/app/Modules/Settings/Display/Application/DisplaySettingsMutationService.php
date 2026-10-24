<?php

declare(strict_types=1);

namespace App\Modules\Settings\Display\Application;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Settings\DisplaySettingRegistry;
use App\Infrastructure\Settings\DisplaySettingsContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Organization\Application\Ports\OrganizationRepositoryInterface;
use App\Modules\Settings\Display\Application\Ports\ConfigurationAuditEventWriterInterface;
use App\Modules\Settings\Display\Application\Ports\DisplaySettingsStorageInterface;
use App\Modules\Settings\Display\Contracts\DisplaySettingsErrors;
use App\Modules\Settings\Display\Infrastructure\SqlConfigurationAuditEventRepository;
use App\Services\OrganizationService;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

/** Authorized, atomic SET/RESET boundary for server Display Settings. */
final class DisplaySettingsMutationService
{
    public const ERROR_INVALID = DisplaySettingsErrors::ERROR_INVALID;
    public const ERROR_NOT_FOUND = DisplaySettingsErrors::ERROR_NOT_FOUND;
    public const ERROR_DENIED = DisplaySettingsErrors::ERROR_DENIED;

    /** @var BaseConnection<mixed, mixed> */
    private BaseConnection $db;

    /** @param BaseConnection<mixed, mixed> $db */
    public function __construct(
        private readonly DisplaySettingsStorageInterface $storage,
        private readonly AuthorizationService $authorization,
        private readonly ConfigurationAuditEventWriterInterface $audit,
        private readonly OrganizationService $organizations,
        private readonly OrganizationRepositoryInterface $organizationRepository,
        BaseConnection $db,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
    ) {
        $this->db = $db;
    }

    /** @param array<array-key, mixed> $values
     *  @return list<string>
     */
    public function setBatch(string|DisplaySettingsContext $context, array $values, ?OperationContext $operation = null): array
    {
        $operation ??= $this->operation();
        $target = $this->context($context);
        $this->authorize($operation, $target, 'edit');

        $validated = [];
        foreach ($values as $key => $value) {
            if (is_int($key)) {
                throw new DomainException(self::ERROR_INVALID, ['field' => 'setting_key']);
            }
            try {
                DisplaySettingRegistry::validate($key, $value);
            } catch (InvalidArgumentException $exception) {
                throw new DomainException(self::ERROR_INVALID, ['setting_key' => $key], $exception);
            }
            $validated[$key] = $value;
        }

        return $this->mutate($target, $validated, false, $operation);
    }

    /** @param array<array-key, mixed> $keys
     *  @return list<string>
     */
    public function resetBatch(string|DisplaySettingsContext $context, array $keys, ?OperationContext $operation = null): array
    {
        $operation ??= $this->operation();
        $target = $this->context($context);
        $this->authorize($operation, $target, 'edit');

        $validated = [];
        foreach ($keys as $key) {
            if (! is_string($key)) {
                throw new DomainException(self::ERROR_INVALID, ['field' => 'setting_key']);
            }
            try {
                DisplaySettingRegistry::definition($key);
            } catch (InvalidArgumentException $exception) {
                throw new DomainException(self::ERROR_INVALID, ['setting_key' => $key], $exception);
            }
            $validated[$key] = null;
        }

        return $this->mutate($target, $validated, true, $operation);
    }

    /** @param array<string, mixed> $values
     *  @return list<string>
     */
    private function mutate(DisplaySettingsContext $context, array $values, bool $reset, OperationContext $operation): array
    {
        $storage = $this->storage();
        $audit = $this->audit();
        $changed = [];
        $events = [];

        foreach ($values as $key => $value) {
            $previous = $storage->persistedOverride($key, $context);
            if ($reset) {
                if (! $previous['exists']) {
                    continue;
                }
                $changed[] = $key;
                $events[] = $this->event($context, $key, 'RESET', $previous['value'], null, $operation);
                continue;
            }
            if ($previous['exists'] && $this->sameCanonical($previous['value'], $value)) {
                continue;
            }
            $changed[] = $key;
            $events[] = $this->event($context, $key, 'SET', $previous['exists'] ? $previous['value'] : null, $value, $operation);
        }

        if ($changed === []) {
            return [];
        }

        $this->db->transBegin();
        try {
            foreach ($changed as $key) {
                if ($reset) {
                    $storage->forget($key, $context);
                } else {
                    $storage->set($key, $values[$key], $context);
                }
            }
            foreach ($events as $event) {
                $audit->insert($event);
            }
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Display Settings transaction failed.');
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return $changed;
    }

    /** @return array<string, mixed> */
    private function event(DisplaySettingsContext $context, string $key, string $operation, mixed $old, mixed $new, OperationContext $operationContext): array
    {
        return [
            'configuration_type' => 'DISPLAY_SETTING',
            'context_type'       => $context->isSystem() ? 'SYSTEM' : 'ORGANIZATION',
            'organization_id'    => $context->organizationId() === null ? null : (int) $context->organizationId(),
            'setting_key'        => $key,
            'operation'          => $operation,
            'old_value_json'     => SqlConfigurationAuditEventRepository::encodeValue($old),
            'new_value_json'     => SqlConfigurationAuditEventRepository::encodeValue($new),
            'actor_user_id'      => $operationContext->actorUserId,
            'request_id'         => $operationContext->requestId,
            'created_at'         => $operationContext->evaluationTime->utc()->format('Y-m-d H:i:s'),
        ];
    }

    private function sameCanonical(mixed $left, mixed $right): bool
    {
        return gettype($left) === gettype($right) && $left === $right;
    }

    private function context(string|DisplaySettingsContext $context): DisplaySettingsContext
    {
        try {
            $resolved = $context instanceof DisplaySettingsContext ? $context : DisplaySettingsContext::parse($context);
            if ($resolved->organizationId() !== null) {
                $this->organizations()->resolveAncestorChain($this->organizationId($resolved));
            } else {
                $this->groupId();
            }

            return $resolved;
        } catch (DomainException $exception) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['context' => $context instanceof DisplaySettingsContext ? $context->value() : $context], $exception);
        } catch (InvalidArgumentException $exception) {
            throw new DomainException(self::ERROR_INVALID, ['context' => $context instanceof DisplaySettingsContext ? $context->value() : $context], $exception);
        }
    }

    private function authorize(OperationContext $operation, DisplaySettingsContext $context, string $action): void
    {
        $evaluationOrganizationId = $context->isSystem() ? $this->groupId() : $this->organizationId($context);
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

    private function groupId(): int
    {
        $roots = $this->organizationRepository->findAll(['type' => 'GROUP', 'parent_id' => null]);
        if (count($roots) !== 1) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['context' => 'system']);
        }

        return (int) ($roots[0]['id'] ?? 0);
    }

    private function organizationId(DisplaySettingsContext $context): int
    {
        $id = $context->organizationId();
        if ($id === null || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new DomainException(self::ERROR_INVALID, ['context' => $context->value()]);
        }

        return (int) $id;
    }

    private function storage(): DisplaySettingsStorageInterface
    {
        return $this->storage;
    }

    private function audit(): ConfigurationAuditEventWriterInterface
    {
        return $this->audit;
    }

    private function organizations(): OrganizationService
    {
        return $this->organizations;
    }

    private function auth(): AuthorizationService
    {
        return $this->authorization;
    }

    private function operation(): OperationContext
    {
        return ($this->mutationContext ?? MutationContext::system($this->clock ?? service('clock')))->begin();
    }
}
