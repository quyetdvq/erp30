<?php

declare(strict_types=1);

namespace App\Modules\Workflow\WriteBoundary\Application\Ports;

/** Four use cases; none persists data or executes Lifecycle transitions. */
interface WorkflowWriteBoundaryPort
{
    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function create(string $resource, array $data): array;

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $changes
     */
    public function assertNoDirectStateMutation(string $resource, array $current, array $changes): void;

    /** @param list<string> $nativeCodes */
    public function normalizeStatus(string $type, mixed $value, array $nativeCodes, string $errorCode): string;

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function normalizeInitial(string $resource, array $payload): array;
}
