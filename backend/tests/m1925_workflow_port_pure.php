<?php

declare(strict_types=1);

/** M19.25: application composition contract without CI4, DB, or HTTP. */
namespace CodeIgniter\Database {
    class BaseConnection {}
}
namespace CodeIgniter\Shield\Entities {
    class User { public function __construct(public readonly int $id) {} }
}
namespace App\Modules\Customer {
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\Shield\Entities\User;
    class CustomerService
    {
        public static array $calls = [];
        public function __construct(private readonly User $user, private readonly BaseConnection $db) {}
        public function setState(string $id, array $input): array
        {
            self::$calls[] = [$this->user, $this->db, $id, $input];
            return ['id' => $id, 'status' => $input['status']];
        }
    }
}
namespace App\Modules\Notifications {
    use CodeIgniter\Database\BaseConnection;
    class NotificationFlags
    {
        public static bool $enabled = false;
        public static function workflowWriteEnabled(BaseConnection $db): bool { return self::$enabled; }
    }
    class NotificationOutboxProducer
    {
        public static array $calls = [];
        public static bool $fail = false;
        public static function enqueueWorkflowTransition(
            BaseConnection $db, string $resource, string $id, string $from, string $to, string $action,
        ): void {
            if (self::$fail) throw new \RuntimeException('OUTBOX_REJECTED');
            self::$calls[] = [$db, $resource, $id, $from, $to, $action];
        }
    }
}
namespace {
    use App\Shared\Workflow\ProductionWorkflowTransitionPort;
    use App\Shared\Workflow\WorkflowTransitionPort;
    use App\Modules\Customer\CustomerService;
    use App\Modules\Notifications\NotificationFlags;
    use App\Modules\Notifications\NotificationOutboxProducer;
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\Shield\Entities\User;

    require __DIR__.'/../app/Shared/Workflow/WorkflowTransitionPort.php';
    require __DIR__.'/../app/Shared/Workflow/ProductionWorkflowTransitionPort.php';

    $n = 0;
    function assertPort(bool $ok, string $label): void
    {
        global $n;
        if (! $ok) throw new \RuntimeException('M19.25 WORKFLOW PORT FAILED: '.$label);
        ++$n;
        echo 'PASS: '.$label.PHP_EOL;
    }
    $port = new ProductionWorkflowTransitionPort();
    $actor = new User(19);
    $db = new BaseConnection();
    assertPort($port instanceof WorkflowTransitionPort, 'Application adapter implements explicit port');
    $changed = $port->changeCustomerState($actor, $db, '38', 'ACTIVE');
    assertPort($changed === ['id'=>'38','status'=>'ACTIVE'], 'Customer state response preserved');
    assertPort(CustomerService::$calls === [[$actor,$db,'38',['status'=>'ACTIVE']]],
        'Customer owns mutation on the same actor and connection');
    assertPort($port->workflowWriteEnabled($db) === false, 'Disabled feature flag denies writes');
    NotificationFlags::$enabled = true;
    assertPort($port->workflowWriteEnabled($db) === true, 'Enabled feature flag passes through');
    $port->enqueueTransition($db,'province','7','DRAFT','ACTIVE','setstate');
    assertPort(NotificationOutboxProducer::$calls === [[$db,'province','7','DRAFT','ACTIVE','setstate']],
        'Outbox receives exact immutable transition on the owning connection');
    NotificationOutboxProducer::$fail = true;
    try {
        $port->enqueueTransition($db,'province','7','ACTIVE','INACTIVE','setstate');
        assertPort(false, 'Outbox failure propagated');
    } catch (\RuntimeException $e) {
        assertPort($e->getMessage() === 'OUTBOX_REJECTED',
            'Outbox failure propagates to owning transaction for rollback');
    }
    assertPort(count(NotificationOutboxProducer::$calls) === 1, 'Failed producer does not fake a successful enqueue');
    $service = (string) file_get_contents(__DIR__.'/../app/Modules/Workflow/Execution/BusinessWorkflowService.php');
    $controller = (string) file_get_contents(__DIR__.'/../app/Modules/Workflow/Execution/BusinessWorkflowController.php');
    assertPort(str_contains($service, '->changeCustomerState(')
        && str_contains($service, '->enqueueTransition('), 'Workflow writes use port for both operations');
    assertPort(!str_contains($service, 'ProductionWorkflowTransitionPort')
        && !str_contains($controller, 'ProductionWorkflowTransitionPort')
        && !str_contains($service, 'App\\Modules\\Notifications\\')
        && !str_contains($service, 'App\\Modules\\Customer\\')
        && !str_contains($controller, 'App\\Modules\\Notifications\\'),
        'Workflow owner cannot import the two delivery/Customer implementations');
    $services = (string) file_get_contents(__DIR__.'/../app/Config/Services.php');
    assertPort(str_contains($services, 'new ProductionWorkflowTransitionPort()')
        && str_contains($service, '\\Config\\Services::workflowTransitionPort()'),
        'Composition root owns concrete adapter, not Workflow module');
    assertPort(str_contains($controller, "'UNAUTHENTICATED'")
        && !str_contains($controller, 'ACCESS_MUTATION_NOT_ENABLED'),
        'Independent M11 notification configuration does not replace generic Workflow PBAC');
    echo "M19.25 WORKFLOW PORT: {$n}/{$n} PASS\n";
}
