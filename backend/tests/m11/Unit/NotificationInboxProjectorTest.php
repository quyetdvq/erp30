<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Exceptions\DomainException;
use App\Modules\Notifications\Events\ImportInboxProjector;
use App\Modules\Notifications\Events\NotificationEventDefinition;
use App\Modules\Notifications\Events\NotificationEventRegistry;
use App\Modules\Notifications\Events\NotificationInboxProjectorRegistry;
use App\Modules\Notifications\Events\WorkflowInboxProjector;
use PHPUnit\Framework\TestCase;

/** Golden expected DTOs are captured from the pre-C0.3 NotificationInboxService::project. */
final class NotificationInboxProjectorTest extends TestCase
{
    /** @return array<string,array{row:array<string,mixed>,expected:array<string,mixed>}> */
    private static function goldens(): array
    {
        $data = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/notification_inbox_projector_golden.json'),
            true, 32, JSON_THROW_ON_ERROR);
        self::assertCount(5, $data);
        return $data;
    }

    public function testAllRegisteredEventsMapOnlyToAllowlistedProjectors(): void
    {
        $types = [];
        foreach (NotificationEventRegistry::all() as $definition) {
            $actual = NotificationInboxProjectorRegistry::resolve($definition);
            $types[$definition->type] = $actual::class;
        }
        self::assertSame([
            'import.completed' => ImportInboxProjector::class,
            'import.completed_with_errors' => ImportInboxProjector::class,
            'import.failed' => ImportInboxProjector::class,
            'workflow.action_required' => WorkflowInboxProjector::class,
        ], $types);
    }

    public function testGoldenDtoParityPreservesEveryFieldAndJsonOrder(): void
    {
        foreach (self::goldens() as $name => $sample) {
            $row = $sample['row'];
            $actual = NotificationInboxProjectorRegistry::project($row);
            self::assertSame($sample['expected'], $actual, $name);
            self::assertSame(json_encode($sample['expected'], JSON_THROW_ON_ERROR),
                json_encode($actual, JSON_THROW_ON_ERROR), $name . ' JSON bytes');
            self::assertSame($sample['row'], $row, $name . ' row must be unmodified');
        }
    }

    public function testUnknownEventAndTargetMismatchFailClosed(): void
    {
        $row = self::goldens()['import_completed_unread']['row'];
        foreach ([
            ['type' => 'security.permission_changed'],
            ['type' => 'not_registered'],
            ['type' => 'workflow.action_required'],
            ['target_type' => 'workflow_target'],
            ['target_type' => 'import_job;DROP TABLE users'],
        ] as $change) {
            self::assertStorageFailure(static fn (): array =>
                NotificationInboxProjectorRegistry::project(array_replace($row, $change)));
        }
    }

    public function testInvalidSymbolicProjectorNeverLoadsDynamicClass(): void
    {
        $definition = new NotificationEventDefinition('unregistered.event', 'import_job', 1,
            'import_terminal', 'unregistered_projector', ['in_app'], 'import_job');
        self::assertStorageFailure(static fn (): object => NotificationInboxProjectorRegistry::resolve($definition));
    }

    public function testImportMetadataAndTargetValidationIsNotWeakened(): void
    {
        foreach (self::goldens() as $sample) {
            $row = $sample['row'];
            if (! str_starts_with($row['type'], 'import.')) {
                continue;
            }
            foreach ([
                ['title_key' => 'notifications.import.other.title'],
                ['body_key' => 'notifications.import.other.body'],
                ['target_type' => 'workflow_target'],
                ['target_id' => '0'],
                ['target_id' => '01'],
                ['target_id' => 'abc'],
            ] as $change) {
                self::assertStorageFailure(static fn (): array =>
                    NotificationInboxProjectorRegistry::project(array_replace($row, $change)));
            }
        }
    }

    public function testImportPayloadIsStillStrictlyOrderedAndIntegerTyped(): void
    {
        $row = self::goldens()['import_completed_unread']['row'];
        foreach ([
            '{"error_rows":0,"success_rows":19}',
            '{"success_rows":"19","error_rows":0}',
            '{"success_rows":19.0,"error_rows":0}',
            '{"success_rows":19,"error_rows":null}',
            '{"success_rows":19,"error_rows":0,"extra":true}',
            '[]', '{}', 'null', '{broken',
        ] as $params) {
            self::assertStorageFailure(static fn (): array =>
                NotificationInboxProjectorRegistry::project(array_replace($row, ['params_json' => $params])));
        }
    }

    public function testWorkflowMetadataStateAndResourceValidationIsNotWeakened(): void
    {
        $row = self::goldens()['workflow_action_read']['row'];
        foreach ([
            ['title_key' => 'notifications.workflow.other.title'],
            ['body_key' => 'notifications.workflow.other.body'],
            ['target_type' => 'import_job'],
            ['target_id' => '0'],
            ['target_id' => '00'],
        ] as $change) {
            self::assertStorageFailure(static fn (): array =>
                NotificationInboxProjectorRegistry::project(array_replace($row, $change)));
        }
        foreach ([
            '{"state_code":"REVIEW","resource_type":"product"}',
            '{"resource_type":"unknown_type","state_code":"REVIEW"}',
            '{"resource_type":"import_job","state_code":"REVIEW"}',
            '{"resource_type":"product","state_code":""}',
            '{"resource_type":"product","state_code":"' . str_repeat('X', 51) . '"}',
            '{"resource_type":"product","state_code":12}',
            '{"resource_type":"product","state_code":"REVIEW","extra":true}',
            '{"resource_type":"product"}', '[]', 'null', '{',
        ] as $params) {
            self::assertStorageFailure(static fn (): array =>
                NotificationInboxProjectorRegistry::project(array_replace($row, ['params_json' => $params])));
        }
    }

    public function testDirectProjectorInvocationCannotBypassEventValidation(): void
    {
        $samples = self::goldens();
        self::assertStorageFailure(static fn (): array =>
            (new ImportInboxProjector())->project($samples['workflow_action_read']['row']));
        self::assertStorageFailure(static fn (): array =>
            (new WorkflowInboxProjector())->project($samples['import_completed_unread']['row']));
    }

    /** @param \Closure():mixed $attempt */
    private static function assertStorageFailure(\Closure $attempt): void
    {
        try {
            $attempt();
            self::fail('Malformed or unknown Inbox row was accepted');
        } catch (DomainException $e) {
            self::assertSame('NOTIFICATION_STORAGE_FAILED', $e->getErrorCode());
        }
    }
}
