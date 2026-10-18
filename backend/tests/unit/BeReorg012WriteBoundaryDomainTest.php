<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Modules\Workflow\WriteBoundary\Application\WorkflowWriteBoundaryService;
use PHPUnit\Framework\TestCase;
use Tests\Support\Workflow\BeReorg012WriteBoundaryFixture as Fixture;

require_once __DIR__ . '/../_support/Workflow/BeReorg012WriteBoundaryFixture.php';

/** @internal Canonical service behavioral tests with an instrumented read-only fake. */
final class BeReorg012WriteBoundaryDomainTest extends TestCase
{
    /** @param array<string,mixed> $context */
    private function fails(string $code, array $context, callable $call): void
    {
        try {
            $call();
            $this->fail('Expected DomainException ' . $code);
        } catch (DomainException $error) {
            $this->assertSame($code, $error->getErrorCode());
            $this->assertSame($context, $error->getContext());
        }
    }

    public function testUpdateWithoutStatusReturnsBeforeStateLookup(): void
    {
        $fake = new Fixture();
        $service = new WorkflowWriteBoundaryService($fake);
        $service->assertNoDirectStateMutation('does_not_exist', ['status' => 'ACTIVE'], ['name' => 'X']);
        $this->assertSame([], $fake->calls);
    }

    public function testSameStatusCaseAndWhitespaceRequiresNoStateLookup(): void
    {
        $fake = new Fixture();
        $fake->lifecycles['province'] = 25;
        $service = new WorkflowWriteBoundaryService($fake);
        $service->assertNoDirectStateMutation('province', ['status' => 'active'], ['status' => ' ACTIVE ']);
        $this->assertSame([], $fake->calls);
        $this->fails('WF_TRANSITION_REQUIRED', ['resource' => 'province'], fn () =>
            $service->assertNoDirectStateMutation('province', ['status' => 'ACTIVE '], ['status' => 'ACTIVE']));
        $this->assertSame([['lifecycleId', 'province']], $fake->calls);
    }

    public function testBoundDirectStatusMutationThrowsTransitionRequired(): void
    {
        $fake = new Fixture();
        $fake->lifecycles['province'] = 25;
        $service = new WorkflowWriteBoundaryService($fake);
        $this->fails('WF_TRANSITION_REQUIRED', ['resource' => 'province'], fn () =>
            $service->assertNoDirectStateMutation('province', ['status' => 'ACTIVE'], ['status' => 'INACTIVE']));
        $this->assertSame([['lifecycleId', 'province']], $fake->calls);
    }

    public function testUnboundDirectStatusMutationRemainsPassThrough(): void
    {
        $fake = new Fixture();
        $service = new WorkflowWriteBoundaryService($fake);
        foreach ([null, 7, 'INACTIVE'] as $value) {
            $service->assertNoDirectStateMutation('policy', ['status' => 'ACTIVE'], ['status' => $value]);
        }
        $this->assertSame([['lifecycleId', 'policy'], ['lifecycleId', 'policy'], ['lifecycleId', 'policy']], $fake->calls);
    }

    public function testUnboundCreateReturnsPayloadUnchanged(): void
    {
        $fake = new Fixture();
        $service = new WorkflowWriteBoundaryService($fake);
        foreach ([['name' => 'X'], ['status' => null, 'name' => 'X']] as $payload) {
            $this->assertSame($payload, $service->create('policy', $payload));
        }
        $this->assertSame([['lifecycleId', 'policy'], ['lifecycleId', 'policy']], $fake->calls);
    }

    public function testBoundCreateUsesPersistedInitialAndRejectsOverride(): void
    {
        $fake = new Fixture();
        $fake->lifecycles['province'] = 25;
        $fake->initials['province'] = 'INACTIVE';
        $service = new WorkflowWriteBoundaryService($fake);
        $this->assertSame(['name' => 'X', 'status' => 'INACTIVE'], $service->create('province', ['name' => 'X']));
        $this->assertSame(['name' => 'X', 'status' => 'INACTIVE'],
            $service->create('province', ['name' => 'X', 'status' => ' inactive ']));
        foreach (['ACTIVE', null, 4] as $hint) {
            $this->fails('WF_INITIAL_STATE_INVALID', ['resource' => 'province', 'initial_status' => 'INACTIVE'],
                fn () => $service->create('province', ['status' => $hint]));
        }
        $this->assertSame([['lifecycleId', 'province'], ['initialCode', 'province']], array_slice($fake->calls, 0, 2));
        $this->assertCount(10, $fake->calls);
    }

    public function testStatusNormalizationUsesMemberOrNativeVocabulary(): void
    {
        $fake = new Fixture();
        $fake->lifecycles['province'] = 25;
        $fake->allowed['province'] = ['DRAFT'];
        $service = new WorkflowWriteBoundaryService($fake);
        $this->assertSame('DRAFT', $service->normalizeStatus('province', ' draft ', ['ACTIVE'], 'STATUS_BAD'));
        $this->fails('STATUS_BAD', ['status' => 'ACTIVE'], fn () =>
            $service->normalizeStatus('province', 'ACTIVE', ['ACTIVE'], 'STATUS_BAD'));
        $this->assertSame('INACTIVE', $service->normalizeStatus('policy', ' inactive ', ['INACTIVE'], 'STATUS_BAD'));
        $this->fails('STATUS_BAD', ['status' => 'ACTIVE'], fn () =>
            $service->normalizeStatus('policy', 'active', ['INACTIVE'], 'STATUS_BAD'));
        $before = count($fake->calls);
        foreach ([0, null, ['x']] as $invalid) {
            $this->fails('STATUS_BAD', ['status' => $invalid], fn () =>
                $service->normalizeStatus('missing', $invalid, ['ACTIVE'], 'STATUS_BAD'));
        }
        foreach ([' ', str_repeat('A', 51)] as $invalid) {
            $this->fails('STATUS_BAD', ['status' => trim($invalid)], fn () =>
                $service->normalizeStatus('missing', $invalid, ['ACTIVE'], 'STATUS_BAD'));
        }
        $this->assertCount($before, $fake->calls, 'Malformed code must not trigger catalog lookups.');
        $fake->exceptions['lifecycleId:missing'] = new DomainException('WF_RESOURCE_LIFECYCLE_INVALID', ['resource' => 'missing']);
        $this->fails('WF_RESOURCE_LIFECYCLE_INVALID', ['resource' => 'missing'], fn () =>
            $service->normalizeStatus('missing', 'ACTIVE', ['ACTIVE'], 'STATUS_BAD'));
    }
}
