<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Modules\Notifications\Events\AbstractNotificationEventHandler;
use App\Modules\Notifications\Events\ImportTerminalHandler;
use App\Modules\Notifications\Events\NotificationDeliveryResult;
use App\Modules\Notifications\Events\NotificationEventDefinition;
use App\Modules\Notifications\Events\NotificationEventHandlerFactory;
use App\Modules\Notifications\Events\NotificationEventRegistry;
use App\Modules\Notifications\Events\WorkflowActionRequiredHandler;
use App\Modules\Notifications\NotificationPermanentFailure;
use App\Modules\Notifications\WorkflowNotificationRules;
use CodeIgniter\Database\BaseConnection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NotificationEventHandlerTest extends TestCase
{
    private function db(): BaseConnection
    {
        $db = $this->createMock(BaseConnection::class);
        $db->method('prefixTable')->willReturnCallback(static fn (string $table): string => 'dinktech_' . $table);
        $db->expects(self::never())->method('query');
        return $db;
    }

    public function testAllExistingRegistryEntriesResolveToDedicatedAllowlistedHandlers(): void
    {
        $db = $this->db();
        $expected = [
            'import.completed' => ImportTerminalHandler::class,
            'import.completed_with_errors' => ImportTerminalHandler::class,
            'import.failed' => ImportTerminalHandler::class,
            'workflow.action_required' => WorkflowActionRequiredHandler::class,
        ];
        self::assertSame(array_keys($expected), array_keys(NotificationEventRegistry::all()));
        foreach (NotificationEventRegistry::all() as $type => $definition) {
            $handler = NotificationEventHandlerFactory::resolve($definition, $db);
            self::assertInstanceOf($expected[$type], $handler);
            $dbProperty = new \ReflectionProperty(AbstractNotificationEventHandler::class, 'db');
            self::assertSame($db, $dbProperty->getValue($handler), 'Handler must reuse the worker connection');
        }
    }

    public function testUnknownSymbolicHandlerNeverLoadsArbitraryClass(): void
    {
        $definition = new NotificationEventDefinition(
            'malicious.example', 'import_job', 1, 'unregistered', 'import_inbox', ['in_app'], 'import_job',
        );
        try {
            NotificationEventHandlerFactory::resolve($definition, $this->db());
            self::fail('Unknown handler identifier accepted');
        } catch (NotificationPermanentFailure $e) {
            self::assertSame('NOTIFICATION_INVALID_EVENT', $e->reason);
        }
        self::assertNull(NotificationEventRegistry::find('security.permission_changed', 'security'));
        self::assertNull(NotificationEventRegistry::find('workflow.action_required', 'import_job'));
    }

    public function testTypedDeliveryOutcomesHaveLegacyTerminalStatusesAndReasons(): void
    {
        $processed = NotificationDeliveryResult::processed();
        self::assertSame('PROCESSED', $processed->status);
        self::assertNull($processed->reason);
        $suppressed = NotificationDeliveryResult::suppressed('NOTIFICATION_PREFERENCE_DISABLED');
        self::assertSame('SUPPRESSED', $suppressed->status);
        self::assertSame('NOTIFICATION_PREFERENCE_DISABLED', $suppressed->reason);
        self::assertTrue((new \ReflectionClass($processed))->isReadOnly());
    }

    public function testImportHandlerRejectsMalformedEventWithoutQueryingDatabase(): void
    {
        $handler = new ImportTerminalHandler($this->db());
        $good = [
            'source_type' => 'import_job', 'event_type' => 'import.failed',
            'source_id' => '42', 'event_key' => 'import-job:42:terminal',
            'payload_json' => '{"schema":1}',
        ];
        foreach ([
            ['source_type' => 'workflow_transition'],
            ['event_type' => 'workflow.action_required'],
            ['source_id' => '0'],
            ['event_key' => 'import-job:43:terminal'],
            ['payload_json' => '{ "schema": 1 }'],
            ['payload_json' => '{"schema":2}'],
        ] as $tamper) {
            try {
                $handler->deliver(array_replace($good, $tamper));
                self::fail('Malformed import event accepted');
            } catch (NotificationPermanentFailure $e) {
                self::assertSame('NOTIFICATION_INVALID_EVENT', $e->reason);
            }
        }
    }

    public function testWorkflowHandlerRejectsMalformedEventWithoutQueryingDatabase(): void
    {
        $handler = new WorkflowActionRequiredHandler($this->db());
        $good = [
            'source_type' => 'workflow_transition', 'event_type' => 'workflow.action_required',
            'source_id' => '42', 'event_key' => 'workflow:province:42:' . str_repeat('a', 32),
            'payload_json' => WorkflowNotificationRules::eventPayload(
                'province', 'M11_A', 'M11_B', 'm11_submit', ['2'],
            ),
        ];
        foreach ([
            ['source_type' => 'import_job'],
            ['event_type' => 'import.failed'],
            ['source_id' => '0'],
            ['event_key' => 'workflow:province:42:xyz'],
            ['payload_json' => '{"schema":1}'],
        ] as $tamper) {
            try {
                $handler->deliver(array_replace($good, $tamper));
                self::fail('Malformed workflow event accepted');
            } catch (NotificationPermanentFailure $e) {
                self::assertSame('NOTIFICATION_INVALID_EVENT', $e->reason);
            }
        }
    }

    public function testHandlerTableNamesFailClosedOnInvalidPhysicalPrefix(): void
    {
        $db = $this->createMock(BaseConnection::class);
        $db->method('prefixTable')->willReturn('bad-name');
        try {
            new ImportTerminalHandler($db);
            self::fail('Unsafe table identifier accepted');
        } catch (RuntimeException $e) {
            self::assertSame('M11_INVALID_TABLE_PREFIX', $e->getMessage());
        }
    }
}
