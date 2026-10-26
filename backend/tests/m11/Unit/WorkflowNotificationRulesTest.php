<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Exceptions\DomainException;
use App\Modules\Notifications\WorkflowNotificationRules;
use PHPUnit\Framework\TestCase;

final class WorkflowNotificationRulesTest extends TestCase
{
    public function testAssignmentContractCanonicalizesAndRejectsInvalidInputs(): void
    {
        self::assertSame(
            ['expected_revision' => '0', 'user_ids' => ['2', '11']],
            WorkflowNotificationRules::replacement(['expected_revision' => '0', 'user_ids' => ['11', '2']]),
        );
        foreach ([
            ['expected_revision' => '00', 'user_ids' => []],
            ['expected_revision' => 0, 'user_ids' => []],
            ['expected_revision' => '1', 'user_ids' => ['2', '2']],
            ['expected_revision' => '1', 'user_ids' => ['02']],
            ['expected_revision' => '1', 'user_ids' => [2]],
            ['expected_revision' => '1', 'user_ids' => array_map('strval', range(1, 26))],
            ['expected_revision' => '1', 'user_ids' => [], 'user_id' => '1'],
        ] as $bad) {
            try {
                WorkflowNotificationRules::replacement($bad);
                self::fail('Invalid assignment accepted: ' . json_encode($bad));
            } catch (DomainException $exception) {
                self::assertSame('NOTIFICATION_INVALID_REQUEST', $exception->getErrorCode());
            }
        }
    }

    public function testPayloadIsCanonicalAndCannotBeForgedByShapeOrDuplicateRecipients(): void
    {
        $json = WorkflowNotificationRules::eventPayload('province', 'M11_A', 'M11_B', 'm11_submit', ['2','11']);
        self::assertSame(['2','11'], WorkflowNotificationRules::decode($json)['recipient_user_ids']);
        foreach ([
            '{}',
            str_replace('"schema":2', '"schema":1', $json),
            str_replace('"recipient_user_ids":["2","11"]', '"recipient_user_ids":["2","2"]', $json),
            str_replace('"resource_type":"province"', '"resource_type":"policy"', $json),
            str_replace('"from_state":"M11_A"', '"from_state":"M11_B"', $json),
        ] as $bad) {
            try {
                WorkflowNotificationRules::decode($bad);
                self::fail('Invalid payload accepted: ' . $bad);
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testUserMustBeActiveAndNotDeletedOrLocked(): void
    {
        self::assertTrue(WorkflowNotificationRules::activeUser([
            'active' => 1, 'status' => 'ACTIVE', 'deleted_at' => null,
        ]));
        foreach ([
            ['active' => 0, 'status' => 'ACTIVE', 'deleted_at' => null],
            ['active' => 1, 'status' => 'locked', 'deleted_at' => null],
            ['active' => 1, 'status' => 'ACTIVE', 'deleted_at' => '2026-01-01'],
        ] as $bad) {
            self::assertFalse(WorkflowNotificationRules::activeUser($bad));
        }
    }
}
