<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Modules\Workflow\StateResolution\Application\ResourceStateResolutionService;
use PHPUnit\Framework\TestCase;
use Tests\Support\Workflow\BeReorg010StateReadFixture as ReadFixture;

/** @internal Per-instance state cache and provenance isolation checks. */
final class BeReorg010StateResolutionCacheTest extends TestCase
{
    public function testResourceCacheIsNormalizedAndScopedToOneResolver(): void
    {
        $reader = ReadFixture::standard();
        $a = new ResourceStateResolutionService($reader);
        $first = $a->resource(' PrOvInCe ');
        $this->assertSame($first, $a->resource('province'));
        $this->assertSame(1, count(array_filter($reader->calls,
            static fn (string $call): bool => $call === 'resourceType:province')));
        $reader->resourcesByType['province']['lifecycle_id'] = 301;
        $this->assertSame($first, $a->resource('province'));
        $this->assertSame(301, (new ResourceStateResolutionService($reader))->resource('province')['lifecycle_id']);
    }

    public function testMembershipCacheIsScopedToOneResolver(): void
    {
        $reader = ReadFixture::standard();
        $reader->resourcesByType['province']['lifecycle_id'] = 300;
        $a = new ResourceStateResolutionService($reader);
        $this->assertSame(['DRAFT', 'APPROVED'], $a->allowedPolicyStateCodes('province'));
        $reader->statesById[104]['status'] = 'INACTIVE';
        $this->assertSame(['DRAFT', 'APPROVED'], $a->allowedPolicyStateCodes('province'));
        try {
            (new ResourceStateResolutionService($reader))->allowedPolicyStateCodes('province');
            $this->fail('A fresh resolver must observe inactive Lifecycle membership.');
        } catch (DomainException $exception) {
            $this->assertSame('WF_RESOURCE_LIFECYCLE_INVALID', $exception->getErrorCode());
            $this->assertSame(['state_id' => 104], $exception->getContext());
        }
    }

    public function testAnyAndActiveCacheKeysNeverCrossContaminate(): void
    {
        $reader = ReadFixture::standard();
        $reader->statesById[102]['status'] = 'INACTIVE';
        $engine = new ResourceStateResolutionService($reader);
        $this->assertSame(102, $engine->stateIdForCode('workflow_state', 'INACTIVE'));
        try {
            $engine->stateIdForCode('policy', 'INACTIVE');
            $this->fail('A disabled INACTIVE definition must not pass policy State validation.');
        } catch (DomainException $exception) {
            $this->assertSame('WF_RESOURCE_STATE_NOT_MAPPED', $exception->getErrorCode());
        }
        $cache = (new ReflectionProperty($engine, 'nativeIds'))->getValue($engine);
        $this->assertSame(['ANY:INACTIVE' => 102], $cache);
        $this->assertContains('anyCode:INACTIVE', $reader->calls);
        $this->assertContains('activeCode:INACTIVE', $reader->calls);
    }

    public function testTrustedCatalogRowPreservesHistoricalCacheKeyAndScopeSemantics(): void
    {
        $reader = ReadFixture::standard();
        $engine = new ResourceStateResolutionService($reader);
        $trusted = ['id' => 77, 'type' => 'province', 'lifecycle_id' => null];
        $this->assertNull($engine->stateForAuthorization(' PrOvInCe ', ['organization_id' => 9], null, $trusted));
        $cache = (new ReflectionProperty($engine, 'resourceCache'))->getValue($engine);
        $this->assertArrayHasKey(' PrOvInCe ', $cache);
        $this->assertArrayHasKey('province', $cache);
        $this->assertSame($trusted, $cache[' PrOvInCe ']);
        $this->assertSame($reader->resourcesByType['province'], $cache['province']);
        try {
            $engine->stateForAuthorization('province', ['organization_id' => 9], 105);
            $this->fail('Scope-only context must not accept a client state ID.');
        } catch (DomainException $exception) {
            $this->assertSame('WF_AUTHORIZATION_STATE_MISMATCH', $exception->getErrorCode());
        }
    }
}
