<?php

declare(strict_types=1);

use App\Authorization\ReadOnlyAuthorizationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class P1Perf004ReadOnlyAuthorizationContextTest extends CIUnitTestCase
{
    public function testKeysAndNullValuesAreCachedOnlyInsideOneContext(): void
    {
        $context = new ReadOnlyAuthorizationContext($this->operation());
        $calls = 0;
        $loader = static function () use (&$calls): ?array {
            $calls++;
            return null;
        };
        $this->assertNull($context->remember('profile', 'alice', $loader));
        $this->assertNull($context->remember('profile', 'alice', $loader));
        $this->assertSame(1, $calls);
        $context->remember('profile', 'bob', $loader);
        $context->remember('groups', 'alice', $loader);
        $this->assertSame(3, $calls);
    }

    public function testOperationMatchRequiresActorRequestAndExactEvaluationInstant(): void
    {
        $operation = $this->operation();
        $context = new ReadOnlyAuthorizationContext($operation);
        $this->assertTrue($context->matches($operation));
        $this->assertTrue($context->matches($this->operation()));
        $this->assertFalse($context->matches(new OperationContext(101, $operation->evaluationTime, 'read-1', false)));
        $this->assertFalse($context->matches(new OperationContext(100, $operation->evaluationTime, 'read-2', false)));
        $this->assertFalse($context->matches(new OperationContext(100, $operation->evaluationTime, 'read-1', true)));
        $this->assertFalse($context->matches(new OperationContext(
            100, new EvaluationTime(new DateTimeImmutable('2026-10-11T04:00:00.000001+00:00')),
            'read-1', false,
        )));
    }

    public function testExceptionsAreNeverMemoized(): void
    {
        $context = new ReadOnlyAuthorizationContext($this->operation());
        $calls = 0;
        for ($i = 0; $i < 2; $i++) {
            try {
                $context->remember('policies', 'company', static function () use (&$calls): array {
                    $calls++;
                    throw new RuntimeException('db failure');
                });
                $this->fail('Expected loader failure.');
            } catch (RuntimeException $exception) {
                $this->assertSame('db failure', $exception->getMessage());
            }
        }
        $this->assertSame(2, $calls);
    }

    public function testOversizedPolicySetsDoNotRemainCached(): void
    {
        $context = new ReadOnlyAuthorizationContext($this->operation());
        $calls = 0;
        $loader = static function () use (&$calls): array {
            $calls++;
            return array_fill(0, 257, ['effect' => 'ALLOW']);
        };
        $context->remember('policies', 'large', $loader);
        $context->remember('policies', 'large', $loader);
        $this->assertSame(2, $calls);
    }

    private function operation(): OperationContext
    {
        return new OperationContext(
            100, new EvaluationTime(new DateTimeImmutable('2026-10-11T04:00:00+00:00')),
            'read-1', false,
        );
    }
}
