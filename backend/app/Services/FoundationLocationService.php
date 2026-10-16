<?php

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap\FoundationFactory;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Foundation\Application\LocationService;
use App\Modules\Foundation\Contracts\LocationErrors;
use App\Repositories\FoundationLocationRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility entrypoint. New behavior belongs in the Foundation module. */
final class FoundationLocationService
{
    public const ERROR_PROVINCE_NOT_FOUND = LocationErrors::ERROR_PROVINCE_NOT_FOUND;
    public const ERROR_WARD_NOT_FOUND = LocationErrors::ERROR_WARD_NOT_FOUND;
    public const ERROR_PROVINCE_REFERENCE_INVALID = LocationErrors::ERROR_PROVINCE_REFERENCE_INVALID;
    public const ERROR_PROVINCE_INACTIVE = LocationErrors::ERROR_PROVINCE_INACTIVE;
    public const ERROR_WARD_PROVINCE_MISMATCH = LocationErrors::ERROR_WARD_PROVINCE_MISMATCH;
    public const ERROR_WARD_REFERENCED = LocationErrors::ERROR_WARD_REFERENCED;
    public const ERROR_STATUS_INVALID = LocationErrors::ERROR_STATUS_INVALID;
    public const ERROR_CODE_DUPLICATE = LocationErrors::ERROR_CODE_DUPLICATE;

    private readonly LocationService $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?FoundationLocationRepository $repository = null,
        ?ConnectionInterface $db = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
        ?ResourceWorkflowStateResolver $workflowStates = null,
    ) {
        $this->delegate = FoundationFactory::service(
            repository: $repository, db: $db, mutationContext: $mutationContext,
            clock: $clock, workflowStates: $workflowStates,
        );
    }

    /** @param array<string, mixed> $data */
    public function createProvince(array $data): int
    {
        return $this->delegate->createProvince($data);
    }

    /** @param array<string, mixed> $data */
    public function updateProvince(int $id, array $data): bool
    {
        return $this->delegate->updateProvince($id, $data);
    }

    /** @param array<string, mixed> $data */
    public function createWard(array $data): int
    {
        return $this->delegate->createWard($data);
    }

    /** @param array<string, mixed> $data */
    public function updateWard(int $id, array $data): bool
    {
        return $this->delegate->updateWard($id, $data);
    }

    public function setProvinceState(int $id, string $status): bool
    {
        return $this->delegate->setProvinceState($id, $status);
    }

    public function setWardState(int $id, string $status): bool
    {
        return $this->delegate->setWardState($id, $status);
    }

    /** @return array<string, mixed> */
    public function province(int $id): array
    {
        return $this->delegate->province($id);
    }

    /** @return array<string, mixed> */
    public function ward(int $id): array
    {
        return $this->delegate->ward($id);
    }
}
