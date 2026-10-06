<?php

declare(strict_types=1);

use App\Authorization\AuthorizationDecision;
use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class P1Auth003AuthorizationDecisionTest extends CIUnitTestCase
{
    public function testDecisionEvidenceIsDeterministicAndDeduplicated(): void
    {
        $time = new EvaluationTime(new DateTimeImmutable('2026-10-05T12:00:00+00:00'));
        $decision = new AuthorizationDecision(
            'ALLOW',
            AuthorizationService::REASON_POLICY_ALLOWED,
            7,
            'price_list',
            'view',
            20,
            null,
            $time,
            ['price_list.view', 'price_list.view'],
            [9, 3, 9],
            [9, 9],
            [12, 4, 12],
            'request-003',
        );

        $this->assertSame(['price_list.view'], $decision->basePermissionSource);
        $this->assertSame([3, 9], $decision->matchedPolicyIds);
        $this->assertSame([9], $decision->decisivePolicyIds);
        $this->assertSame([4, 12], $decision->participatingAssignmentIds);
        $this->assertSame('request-003', $decision->toArray()['request_id']);
    }

    public function testDecisionArrayContainsInternalEvidenceWithoutLocalizedText(): void
    {
        $decision = new AuthorizationDecision(
            'DENY',
            AuthorizationService::REASON_ABSOLUTE_DENY,
            7,
            'price_list',
            'view',
            20,
            4,
            new EvaluationTime(new DateTimeImmutable('2026-10-05T12:00:00+00:00')),
            ['price_list.view'],
            [11],
            [11],
            [],
            null,
        );

        $data = $decision->toArray();
        $this->assertSame('DENY', $data['decision']);
        $this->assertSame(AuthorizationService::REASON_ABSOLUTE_DENY, $data['reason_code']);
        $this->assertSame('2026-10-05T12:00:00+00:00', $data['evaluation_time']);
        $this->assertArrayNotHasKey('message', $data);
        $this->assertArrayNotHasKey('exception', $data);
    }

    public function testPersistenceFailurePropagatesInsteadOfReturningDecision(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('table')->willThrowException(new RuntimeException('database unavailable'));
        $service = new AuthorizationService(db: $connection);
        $operation = new OperationContext(
            7,
            new EvaluationTime(new DateTimeImmutable('2026-10-05T12:00:00+00:00')),
            'request-003-failure',
            false,
        );

        $this->expectException(PersistenceException::class);
        $service->authorize(
            $operation,
            'price_list',
            'view',
            new EvaluationOrganizationRequest(persistedTarget: ['organization_id' => 20]),
        );
    }
}
