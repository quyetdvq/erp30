<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Modules\Workflow\WriteBoundary\Application\InitialStateRules;
use App\Services\InitialCreateStateNormalizer;
use PHPUnit\Framework\TestCase;

/** @internal Pure create-state rules, no DB or state-resolution fixture. */
final class BeReorg012InitialStateRulesTest extends TestCase
{
    /** @param array<string,mixed> $context */
    private function fails(string $code, array $context, callable $call): void
    {
        try {
            $call();
            $this->fail('Expected ' . $code);
        } catch (DomainException $error) {
            $this->assertSame($code, $error->getErrorCode());
            $this->assertSame($context, $error->getContext());
        }
    }

    public function testNativeMissingStatusDefaultsActiveWhenCatalogPermits(): void
    {
        $value = ['code' => 'P42', 'name' => 'Sample'];
        $this->assertSame($value + ['status' => 'ACTIVE'], InitialStateRules::withInitial('province', $value, null));
        $this->assertSame(InitialCreateStateNormalizer::withInitial('province', $value, null),
            InitialStateRules::withInitial('province', $value, null));
    }

    public function testNativeWithoutActiveDefaultLeavesPayloadUnchanged(): void
    {
        foreach (['import_job', 'display_setting', 'authorization_log'] as $resource) {
            $payload = ['name' => 'X'];
            $this->assertSame($payload, InitialStateRules::withInitial($resource, $payload, null));
        }
    }

    public function testNativeExplicitStatusIsPreservedVerbatim(): void
    {
        foreach (['closed', null, 0, ['surprising']] as $hint) {
            $payload = ['status' => $hint, 'marker' => 42];
            $this->assertSame($payload, InitialStateRules::withInitial('province', $payload, null));
        }
    }

    public function testLifecycleInitialAcceptsArbitraryValidCode(): void
    {
        foreach (['DRAFT', 'A' . str_repeat('B', 49), 'APPROVED:1', 'Q_Z-1'] as $initial) {
            $this->assertSame($initial, InitialStateRules::withInitial('province', ['name' => 'X'], $initial)['status']);
            $this->assertSame($initial, InitialCreateStateNormalizer::withInitial('province', [], $initial)['status']);
        }
    }

    public function testMatchingClientHintIsCanonicalizedToInitial(): void
    {
        $payload = ['name' => 'X', 'status' => ' inactive '];
        $this->assertSame(['name' => 'X', 'status' => 'INACTIVE'], InitialStateRules::withInitial('province', $payload, 'INACTIVE'));
    }

    public function testMismatchingOrNonStringClientHintIsRejected(): void
    {
        foreach (['ACTIVE', null, 0, []] as $hint) {
            $this->fails('WF_INITIAL_STATE_INVALID', ['resource' => 'province', 'initial_status' => 'INACTIVE'],
                fn () => InitialStateRules::withInitial('province', ['status' => $hint], 'INACTIVE'));
        }
    }

    public function testUnsupportedInitialCodeIsRejectedWithExistingContext(): void
    {
        foreach (['draft', '1VALID', 'A' . str_repeat('B', 50), 'A ', ''] as $initial) {
            $this->fails(InitialCreateStateNormalizer::ERROR_UNSUPPORTED_INITIAL,
                ['resource' => 'province', 'initial_status' => $initial],
                fn () => InitialStateRules::withInitial('province', ['name' => 'X'], $initial));
        }
    }
}
