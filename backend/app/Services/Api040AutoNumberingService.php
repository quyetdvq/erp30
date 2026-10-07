<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\RequestContext;
use App\Repositories\AutoNumberingSeriesRepository;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Shield\Entities\User;

/** Application boundary for the CONTRACT_READY P1-API-040 surface. */
final class Api040AutoNumberingService
{
    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?AutoNumberingConfigurationService $configuration = null,
        private readonly ?AutoNumberingSeriesRepository $repository = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?ConnectionInterface $db = null,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $resourceId): array
    {
        $resourceId = $this->resourceId($resourceId);
        $this->authorize($this->operation());

        return array_map(fn (array $series): array => $this->output($series), $this->configuration()->listByResource($resourceId));
    }

    /** @return array<string, mixed> */
    public function get(int $resourceId, int $seriesId): array
    {
        $resourceId = $this->resourceId($resourceId);
        $this->authorize($this->operation());
        $series = $this->configuration()->find($this->seriesId($seriesId));
        if ($series === null || (int) $series['resource_id'] !== $resourceId) {
            throw new DomainException(AutoNumberingConfigurationService::ERROR_NOT_FOUND, ['series_id' => $seriesId]);
        }

        return $this->output($series);
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function create(int $resourceId, array $payload): array
    {
        $resourceId = $this->resourceId($resourceId);
        $operation = $this->operation();
        $payload['resource_id'] = $resourceId;
        $seriesId = $this->configuration()->create($payload, $operation);

        return $this->getAfterMutation($seriesId);
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function update(int $resourceId, int $seriesId, array $payload): array
    {
        $resourceId = $this->resourceId($resourceId);
        $seriesId = $this->seriesId($seriesId);
        $operation = $this->operation();
        $this->assertSeriesResource($resourceId, $seriesId);
        $this->configuration()->update($seriesId, $payload, $operation);

        return $this->getAfterMutation($seriesId);
    }

    /** @return array<string, mixed> */
    public function setState(int $resourceId, int $seriesId, string $status): array
    {
        return $this->update($resourceId, $seriesId, ['status' => strtoupper(trim($status))]);
    }

    /** @param array<string, mixed> $payload
     *  @return array{valid: bool, normalized_pattern: string, validation_messages: list<string>}
     */
    public function validateConfig(int $resourceId, array $payload): array
    {
        $resourceId = $this->resourceId($resourceId);

        return $this->configuration()->validateConfig($resourceId, $payload, $this->operation());
    }

    private function authorize(OperationContext $operation): void
    {
        $this->configuration()->assertAuthorized($operation);
    }

    /** @return array<string, mixed> */
    private function getAfterMutation(int $seriesId): array
    {
        $series = $this->configuration()->find($seriesId);
        if ($series === null) {
            throw new DomainException(AutoNumberingConfigurationService::ERROR_NOT_FOUND, ['series_id' => $seriesId]);
        }

        return $this->output($series);
    }

    private function assertSeriesResource(int $resourceId, int $seriesId): void
    {
        $series = $this->configuration()->find($seriesId);
        if ($series === null || (int) $series['resource_id'] !== $resourceId) {
            throw new DomainException(AutoNumberingConfigurationService::ERROR_NOT_FOUND, ['series_id' => $seriesId]);
        }
    }

    /** @param array<string, mixed> $series
     *  @return array<string, mixed>
     */
    private function output(array $series): array
    {
        if (array_key_exists('start_number', $series)) {
            $series['start_number'] = (string) $series['start_number'];
        }
        if (array_key_exists('is_default', $series)) {
            $series['is_default'] = (bool) $series['is_default'];
        }

        return $series;
    }

    private function resourceId(int $resourceId): int
    {
        $resourceId = PositiveId::normalize($resourceId, 'resource_id', AutoNumberingConfigurationService::ERROR_RESOURCE_NOT_FOUND);
        if ($this->repository()->findResource($resourceId) === null) {
            throw new DomainException(AutoNumberingConfigurationService::ERROR_RESOURCE_NOT_FOUND, ['resource_id' => $resourceId]);
        }

        return $resourceId;
    }

    private function seriesId(int $seriesId): int
    {
        return PositiveId::normalize($seriesId, 'series_id', AutoNumberingConfigurationService::ERROR_NOT_FOUND);
    }

    private function operation(): OperationContext
    {
        if ($this->mutationContext !== null) {
            return $this->mutationContext->begin();
        }
        $user = auth()->user();
        if (! $user instanceof User || ! is_numeric($user->id) || (int) $user->id < 1) {
            throw new DomainException('UNAUTHENTICATED');
        }

        return (new MutationContext(service('clock'), (int) $user->id, RequestContext::requestId(), false))->begin();
    }

    private function configuration(): AutoNumberingConfigurationService
    {
        if ($this->configuration !== null) {
            return $this->configuration;
        }

        return new AutoNumberingConfigurationService(
            repository: $this->repository(),
            patternValidator: new CanonicalNumberingPatternValidator(),
            db: $this->db ?? db_connect(),
        );
    }

    private function repository(): AutoNumberingSeriesRepository
    {
        return $this->repository ?? new AutoNumberingSeriesRepository($this->db ?? db_connect());
    }
}
