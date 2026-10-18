<?php

declare(strict_types=1);

namespace Tests\Support\Workflow;

use App\Exceptions\DomainException;
use App\Modules\Workflow\WriteBoundary\Application\Ports\WorkflowStateLookupPort;

/** DB-free lookup fake: explicit data, read counters and configured fail-closed exceptions. */
final class BeReorg012WriteBoundaryFixture implements WorkflowStateLookupPort
{
    /** @var array<string,?int> */
    public array $lifecycles = [];
    /** @var array<string,list<string>> */
    public array $allowed = [];
    /** @var array<string,?string> */
    public array $initials = [];
    /** @var list<array{string,string}> */
    public array $calls = [];
    /** @var array<string,DomainException> */
    public array $exceptions = [];

    private function call(string $method, string $type): void
    {
        $this->calls[] = [$method, $type];
        if (isset($this->exceptions[$method . ':' . $type])) {
            throw $this->exceptions[$method . ':' . $type];
        }
    }

    public function lifecycleId(string $type): ?int
    {
        $this->call(__FUNCTION__, $type);
        return $this->lifecycles[$type] ?? null;
    }

    /** @return list<string> */
    public function allowedPolicyStateCodes(string $type): array
    {
        $this->call(__FUNCTION__, $type);
        return $this->allowed[$type] ?? [];
    }

    public function initialCode(string $type): ?string
    {
        $this->call(__FUNCTION__, $type);
        return $this->initials[$type] ?? null;
    }
}
