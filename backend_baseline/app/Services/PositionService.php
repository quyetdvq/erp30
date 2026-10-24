<?php

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap\EmployeeStructureFactory;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\EmployeeStructure\Position\Application\PositionService as ModulePositionService;
use App\Modules\EmployeeStructure\Position\Contracts\PositionErrors;
use App\Repositories\PositionRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility entrypoint. New Position behavior belongs in EmployeeStructure. */
final class PositionService
{
    public const ERROR_CODE_DUPLICATE = PositionErrors::ERROR_CODE_DUPLICATE;
    public const ERROR_REFERENCE_INVALID = PositionErrors::ERROR_REFERENCE_INVALID;
    public const ERROR_STATUS_INVALID = PositionErrors::ERROR_STATUS_INVALID;
    private readonly ModulePositionService $delegate;
    /** @param ConnectionInterface<mixed,mixed>|null $db */
    public function __construct(?PositionRepository $repository = null, ?ConnectionInterface $db = null, ?MutationContext $mutationContext = null, ?Clock $clock = null)
    {
        $this->delegate = EmployeeStructureFactory::positionService(repository: $repository, db: $db, mutationContext: $mutationContext, clock: $clock);
    }
    /** @param array<string,mixed> $data */ public function create(array $data): int { return $this->delegate->create($data); }
    /** @param array<string,mixed> $data */ public function update(int $id, array $data): bool { return $this->delegate->update($id, $data); }
}
