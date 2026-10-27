<?php

declare(strict_types=1);

// Vendor-free NFX-C0.2 smoke: no MariaDB, SMTP, Push, or real CI4 bootstrap.
namespace CodeIgniter\Database {
    class BaseConnection
    {
        /** @var list<array{0:string,1:array}> */
        public array $queries = [];
        /** @var list<array<string,mixed>|null> */
        public array $rows = [];

        public function prefixTable(string $name): string { return 'dinktech_' . $name; }

        public function query(string $sql, array $params = []): object
        {
            $this->queries[] = [$sql, $params];
            $row = array_shift($this->rows);
            return new class($row) {
                public function __construct(private readonly ?array $row) {}
                public function getRowArray(): ?array { return $this->row; }
            };
        }
    }
}

namespace {
    require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
    require_once __DIR__ . '/../../app/Modules/Access/ResourceStateCatalog.php';
    require_once __DIR__ . '/../../app/Modules/Workflow/Execution/BusinessWorkflowRules.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/WorkflowNotificationRules.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/NotificationPermanentFailure.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/NotificationDeliveryRules.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/Events/NotificationEventDefinition.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/Events/NotificationEventRegistry.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/Events/NotificationDeliveryResult.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/Events/NotificationEventHandler.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/Events/AbstractNotificationEventHandler.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/Events/ImportTerminalHandler.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/Events/WorkflowActionRequiredHandler.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/Events/NotificationEventHandlerFactory.php';

    use App\Modules\Notifications\Events\ImportTerminalHandler;
    use App\Modules\Notifications\Events\NotificationDeliveryResult;
    use App\Modules\Notifications\Events\NotificationEventDefinition;
    use App\Modules\Notifications\Events\NotificationEventHandlerFactory;
    use App\Modules\Notifications\Events\NotificationEventRegistry;
    use App\Modules\Notifications\Events\WorkflowActionRequiredHandler;
    use App\Modules\Notifications\NotificationPermanentFailure;
    use App\Modules\Notifications\WorkflowNotificationRules;
    use CodeIgniter\Database\BaseConnection;

    $checks = 0;
    $check = static function (bool $condition) use (&$checks): void {
        if (! $condition) {
            throw new \RuntimeException('NFX-C0.2 smoke failed at check ' . ($checks + 1));
        }
        ++$checks;
    };
    $db = new BaseConnection();
    $mapping = [
        'import.completed' => ImportTerminalHandler::class,
        'import.completed_with_errors' => ImportTerminalHandler::class,
        'import.failed' => ImportTerminalHandler::class,
        'workflow.action_required' => WorkflowActionRequiredHandler::class,
    ];
    $check(array_keys(NotificationEventRegistry::all()) === array_keys($mapping));
    foreach (NotificationEventRegistry::all() as $type => $definition) {
        $handler = NotificationEventHandlerFactory::resolve($definition, $db);
        $check($handler instanceof $mapping[$type]);
        $prop = new \ReflectionProperty(get_parent_class($handler), 'db');
        $check($prop->getValue($handler) === $db);
    }
    $check(NotificationEventRegistry::find('import.failed', 'workflow_transition') === null);
    $check(NotificationEventRegistry::find('workflow.action_required', 'import_job') === null);
    $check(NotificationEventRegistry::find('security.permission_changed', 'security') === null);
    $check(NotificationDeliveryResult::processed()->status === 'PROCESSED');
    $check(NotificationDeliveryResult::processed()->reason === null);
    $check(NotificationDeliveryResult::suppressed('NOTIFICATION_NO_CREATOR')->status === 'SUPPRESSED');
    $check(NotificationDeliveryResult::suppressed('NOTIFICATION_NO_CREATOR')->reason === 'NOTIFICATION_NO_CREATOR');

    $import = new ImportTerminalHandler($db);
    $goodImport = ['source_type' => 'import_job', 'source_id' => '123',
        'event_type' => 'import.completed', 'event_key' => 'import-job:123:terminal',
        'payload_json' => '{"schema":1}'];
    foreach ([
        ['source_type' => 'unknown'], ['event_type' => 'unknown'],
        ['source_id' => '0'], ['event_key' => 'import-job:124:terminal'],
        ['payload_json' => '{"schema":2}'], ['payload_json' => '{ "schema":1 }'],
    ] as $tamper) {
        try {
            $import->deliver(array_replace($goodImport, $tamper));
            throw new \RuntimeException('bad import event accepted');
        } catch (NotificationPermanentFailure $e) {
            $check($e->reason === 'NOTIFICATION_INVALID_EVENT');
        }
    }
    $check($db->queries === []); // All malformed events fail before I/O.
    // A terminal import owned by the system is a legitimate suppression, not a retry.
    $db->rows = [['id' => 123, 'status' => 'COMPLETED', 'created_by' => null,
        'success_rows' => 3, 'error_rows' => 0]];
    $result = $import->deliver($goodImport);
    $check($result->status === 'SUPPRESSED');
    $check($result->reason === 'NOTIFICATION_NO_CREATOR');
    $check(count($db->queries) === 1);
    $check(str_contains($db->queries[0][0], 'FROM `dinktech_import_jobs`'));

    $db->queries = [];
    $workflow = new WorkflowActionRequiredHandler($db);
    $goodWorkflow = ['source_type' => 'workflow_transition', 'source_id' => '42',
        'event_type' => 'workflow.action_required',
        'event_key' => 'workflow:province:42:' . str_repeat('a', 32),
        'payload_json' => WorkflowNotificationRules::eventPayload(
            'province', 'M11_A', 'M11_B', 'm11_submit', ['2'])];
    foreach ([
        ['source_type' => 'import_job'], ['event_type' => 'import.failed'],
        ['source_id' => '0'], ['event_key' => 'workflow:province:42:invalid'],
        ['payload_json' => '{"schema":1}'],
    ] as $tamper) {
        try {
            $workflow->deliver(array_replace($goodWorkflow, $tamper));
            throw new \RuntimeException('bad workflow event accepted');
        } catch (NotificationPermanentFailure $e) {
            $check($e->reason === 'NOTIFICATION_INVALID_EVENT');
        }
    }
    $check($db->queries === []);
    $db->rows = [['id' => 42, 'status' => 'M11_C']];
    $result = $workflow->deliver($goodWorkflow);
    $check($result->status === 'SUPPRESSED');
    $check($result->reason === 'NOTIFICATION_WORKFLOW_STATE_CHANGED');
    $check(count($db->queries) === 1);
    $check(str_contains($db->queries[0][0], 'FOR UPDATE'));

    $unknown = new NotificationEventDefinition('unknown.event', 'import_job', 1,
        'unknown_id', 'import_inbox', ['in_app'], 'import_job');
    try {
        NotificationEventHandlerFactory::resolve($unknown, $db);
        throw new \RuntimeException('unknown handler accepted');
    } catch (NotificationPermanentFailure $e) {
        $check($e->reason === 'NOTIFICATION_INVALID_EVENT');
    }

    echo "NFX-C0.2 HANDLERS PURE SMOKE: {$checks} checks PASS\n";
}
