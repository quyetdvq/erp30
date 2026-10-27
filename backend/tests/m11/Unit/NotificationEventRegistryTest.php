<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Modules\Notifications\Events\NotificationEventDefinition;
use App\Modules\Notifications\Events\NotificationEventRegistry;
use App\Modules\Notifications\NotificationCatalog;
use App\Modules\Notifications\NotificationDeliveryRules;
use App\Modules\Notifications\WorkflowNotificationRules;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NotificationEventRegistryTest extends TestCase
{
    public function testRegistryExactlyMatchesTheFourExistingEvents(): void
    {
        $actual = [];
        foreach (NotificationEventRegistry::all() as $key => $event) {
            self::assertSame($key, $event->type);
            $actual[$key] = [
                $event->sourceType, $event->payloadSchemaVersion, $event->handlerId,
                $event->projectorId, $event->supportedChannels, $event->targetKind,
            ];
        }
        self::assertSame([
            'import.completed' => ['import_job', 1, 'import_terminal', 'import_inbox',
                ['in_app', 'email', 'push'], 'import_job'],
            'import.completed_with_errors' => ['import_job', 1, 'import_terminal', 'import_inbox',
                ['in_app', 'email'], 'import_job'],
            'import.failed' => ['import_job', 1, 'import_terminal', 'import_inbox',
                ['in_app', 'email'], 'import_job'],
            'workflow.action_required' => ['workflow_transition', 2, 'workflow_action_required',
                'workflow_inbox', ['in_app'], 'workflow_target'],
        ], $actual);
        self::assertCount(4, $actual);
    }

    public function testLookupRequiresExactEventAndSourceAndNeverEnablesUnavailableProducer(): void
    {
        foreach (NotificationEventRegistry::all() as $type => $definition) {
            self::assertSame($definition, NotificationEventRegistry::find($type, $definition->sourceType));
            self::assertNull(NotificationEventRegistry::find($type, 'wrong_source'));
        }
        foreach ([
            ['security.permission_changed', 'security'],
            ['import.completed', 'workflow_transition'],
            ['workflow.action_required', 'import_job'],
            ['import.completed_with_errors', 'workflow_transition'],
            ['import.failed', 'import_job OR 1=1'],
            ['import.completed|import_job', ''],
            ['', 'import_job'],
        ] as [$type, $source]) {
            self::assertNull(NotificationEventRegistry::find($type, $source));
        }
        self::assertArrayNotHasKey('security.permission_changed', NotificationEventRegistry::all());
    }

    public function testDeclaredChannelCapabilitiesAgreeWithExistingCatalogPolicyKeys(): void
    {
        $catalog = NotificationCatalog::definitions();
        self::assertCount(9, $catalog);
        $registered = [];
        foreach (NotificationEventRegistry::all() as $event) {
            foreach ($event->supportedChannels as $channel) {
                $policyKey = $event->type . '.' . $channel;
                $registered[] = $policyKey;
                self::assertArrayHasKey($policyKey, $catalog);
                self::assertSame('AVAILABLE', $catalog[$policyKey]['capability']);
            }
            self::assertTrue($event->supports('in_app'));
        }
        self::assertSame([
            'import.completed.in_app', 'import.completed.email', 'import.completed.push',
            'import.completed_with_errors.in_app', 'import.completed_with_errors.email',
            'import.failed.in_app', 'import.failed.email',
            'workflow.action_required.in_app',
        ], $registered);
        self::assertSame('UNAVAILABLE', $catalog['security.permission_changed.in_app']['capability']);
        foreach ($catalog as $policyKey => $policy) {
            if ($policy['capability'] === 'AVAILABLE') {
                self::assertContains($policyKey, $registered);
            }
        }
        self::assertTrue(NotificationEventRegistry::find('import.failed', 'import_job')->supports('email'));
        self::assertFalse(NotificationEventRegistry::find('workflow.action_required', 'workflow_transition')->supports('push'));
    }

    public function testPayloadSchemaAndTerminalMappingMatchLegacyProducerContracts(): void
    {
        foreach (['COMPLETED', 'COMPLETED_WITH_ERRORS', 'FAILED'] as $status) {
            $eventType = NotificationDeliveryRules::importEventType($status);
            $definition = NotificationEventRegistry::find($eventType, 'import_job');
            self::assertNotNull($definition);
            self::assertSame(1, $definition->payloadSchemaVersion);
            self::assertSame('import_terminal', $definition->handlerId);
        }
        $workflowJson = WorkflowNotificationRules::eventPayload(
            'province', 'M11_A', 'M11_B', 'm11_submit', ['2'],
        );
        self::assertSame(2, WorkflowNotificationRules::decode($workflowJson)['schema']);
        self::assertSame(2, NotificationEventRegistry::find('workflow.action_required',
            'workflow_transition')->payloadSchemaVersion);
    }

    public function testDefinitionRejectsNoncanonicalMetadata(): void
    {
        $valid = ['import.completed', 'import_job', 1, 'import_terminal',
            'import_inbox', ['in_app', 'email', 'push'], 'import_job'];
        $invalid = [
            [0, 'Import.Completed'], [0, 'import'], [0, 'import.completed.extra/segment'],
            [1, 'import-job'], [2, 0], [2, -1],
            [3, 'App\\Shell\\Runner'], [4, 'import-inbox'],
            [5, []], [5, ['email', 'in_app']], [5, ['in_app', 'in_app']],
            [5, ['in_app', 'webhook']], [5, ['in_app', 4]], [5, ['push', 'push']],
            [6, 'import/job'],
        ];
        foreach ($invalid as [$position, $value]) {
            $args = $valid;
            $args[$position] = $value;
            try {
                new NotificationEventDefinition(...$args);
                self::fail('Invalid static event metadata accepted: position ' . $position);
            } catch (InvalidArgumentException $e) {
                self::assertSame('NOTIFICATION_INVALID_EVENT_DEFINITION', $e->getMessage());
            }
        }
    }

    public function testDefinitionsAreReadonlyAndCannotBeModifiedByAConsumer(): void
    {
        $definition = NotificationEventRegistry::find('import.completed', 'import_job');
        self::assertNotNull($definition);
        self::assertTrue((new \ReflectionClass($definition))->isReadOnly());
        try {
            $definition->type = 'forged.event';
            self::fail('Readonly event metadata was mutated');
        } catch (\Error) {
            self::assertSame('import.completed', $definition->type);
        }
    }
}
