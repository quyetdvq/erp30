<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Exceptions\DomainException;
use App\Modules\Notifications\MyWorkflowCursor;
use App\Modules\Notifications\MyWorkflowQuery;
use PHPUnit\Framework\TestCase;

final class MyWorkflowQueryTest extends TestCase
{
    private const SECRET = 'nfx-b-my-work-test-key-with-adequate-entropy-32';

    public function testDefaultAndBoundedAllowlistedQueries(): void
    {
        self::assertSame(['resource_type' => 'all', 'page_size' => 10, 'cursor' => null], MyWorkflowQuery::parse([]));
        self::assertSame(['resource_type' => 'product', 'page_size' => 20, 'cursor' => null],
            MyWorkflowQuery::parse(['resource_type' => 'product', 'page_size' => '20']));
        self::assertSame(['resource_type' => 'employee_contact', 'page_size' => 1, 'cursor' => null],
            MyWorkflowQuery::parse(['resource_type' => 'employee_contact', 'page_size' => '1']));
    }

    /** @dataProvider badQueries */
    public function testRejectsArbitraryFilters(array $query): void
    {
        try {
            MyWorkflowQuery::parse($query);
            self::fail('Unsafe query accepted');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_INVALID_REQUEST', $e->getErrorCode());
        }
    }

    /** @return iterable<string,array{array<string,mixed>}> */
    public static function badQueries(): iterable
    {
        yield 'other user' => [['user_id' => '2']];
        yield 'unregistered type' => [['resource_type' => 'import_job']];
        yield 'sql input' => [['resource_type' => 'product OR 1=1']];
        yield 'mixed-case type' => [['resource_type' => 'Product']];
        yield 'array type' => [['resource_type' => ['all']]];
        yield 'null type' => [['resource_type' => null]];
        yield 'null page size' => [['page_size' => null]];
        yield 'null cursor' => [['cursor' => null]];
        yield 'zero size' => [['page_size' => '0']];
        yield 'oversized page' => [['page_size' => '21']];
        yield 'integer size' => [['page_size' => 10]];
        yield 'cursor blank' => [['cursor' => '']];
        yield 'cursor array' => [['cursor' => ['A']]];
        yield 'cursor too large' => [['cursor' => str_repeat('a', 1025)]];
    }

    public function testCursorIsBoundToActorResourceTypeAndPageSize(): void
    {
        $cursor = MyWorkflowCursor::encode('10', 'product', 5,
            '2026-10-27 08:00:00.000001', 'product', '15', self::SECRET);
        self::assertSame(['created_at' => '2026-10-27 08:00:00.000001',
            'resource_type' => 'product', 'resource_id' => '15'],
            MyWorkflowCursor::decode($cursor, '10', 'product', 5, self::SECRET));
        foreach ([['11', 'product', 5], ['10', 'all', 5], ['10', 'product', 10]] as $different) {
            try {
                MyWorkflowCursor::decode($cursor, $different[0], $different[1], $different[2], self::SECRET);
                self::fail('Wrong-context cursor accepted');
            } catch (DomainException $e) {
                self::assertSame('NOTIFICATION_INVALID_CURSOR', $e->getErrorCode());
            }
        }
    }

    public function testMalformedOrTamperedCursorIsRejected(): void
    {
        $cursor = MyWorkflowCursor::encode('10', 'all', 10,
            '2026-10-27 08:00:00.000001', 'province', '1', self::SECRET);
        foreach (['x', '', 'other.' . $cursor, $cursor . 'x'] as $candidate) {
            try {
                MyWorkflowCursor::decode($candidate, '10', 'all', 10, self::SECRET);
                self::fail('Malformed cursor accepted');
            } catch (DomainException $e) {
                self::assertSame('NOTIFICATION_INVALID_CURSOR', $e->getErrorCode());
            }
        }
    }
}
