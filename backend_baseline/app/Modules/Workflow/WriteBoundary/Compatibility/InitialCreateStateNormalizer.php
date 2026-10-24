<?php

declare(strict_types=1);

namespace App\Modules\Workflow\WriteBoundary\Compatibility;

use App\Services\ResourceWorkflowStateResolver;

use App\Bootstrap\WorkflowWriteBoundaryFactory;
use App\Modules\Workflow\WriteBoundary\Application\InitialStateRules;
use App\Modules\Workflow\WriteBoundary\Contracts\WorkflowWriteBoundaryErrors;

/** Original constructor and pure static API retained for all existing consumers. */
final class InitialCreateStateNormalizer
{
    public const ERROR_UNSUPPORTED_INITIAL = WorkflowWriteBoundaryErrors::ERROR_UNSUPPORTED_INITIAL;

    public function __construct(private readonly ?ResourceWorkflowStateResolver $states = null)
    {
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function normalize(string $resource, array $payload): array
    {
        return WorkflowWriteBoundaryFactory::create(states: $this->states)->normalizeInitial($resource, $payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function withInitial(string $resource, array $payload, ?string $initial): array
    {
        return InitialStateRules::withInitial($resource, $payload, $initial);
    }
}
