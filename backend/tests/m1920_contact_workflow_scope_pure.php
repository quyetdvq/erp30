<?php

declare(strict_types=1);

/**
 * Offline reproduction of M19.20 owner-derived Workflow PBAC scope.
 * No CodeIgniter bootstrap, HTTP, real DB connection or configuration writes.
 */
namespace CodeIgniter\Database {
    class BaseConnection {}
}

namespace App\Bootstrap {
    final class AccessReadAdapters
    {
        public static ?\M1920People $people = null;
        public static function people(\CodeIgniter\Database\BaseConnection $db): \M1920People
        {
            return self::$people ?? throw new \RuntimeException('Missing fake People source.');
        }
    }
}

namespace {
    use App\Bootstrap\AccessReadAdapters;
    use App\Exceptions\DomainException;
    use App\Modules\Workflow\Execution\BusinessWorkflowContactScope as WorkflowScope;
    use CodeIgniter\Database\BaseConnection;

    final class M1920People
    {
        /** @var array<string,array<string,mixed>> */
        public array $employees = [];
        /** @var array<string,list<array<string,mixed>>> */
        public array $assignments = [];
        public function employeeById(string $id): ?array
        {
            return $this->employees[$id] ?? null;
        }
        public function effectiveAssignments(string $id, string $date, bool $activeOnly): array
        {
            if (!$activeOnly || !preg_match('/\A\d{4}-\d\d-\d\d\z/D', $date)) {
                throw new \RuntimeException('Scope failed to request active, effective assignments.');
            }
            return $this->assignments[$id] ?? [];
        }
    }

    final class M1920Result
    {
        public function __construct(private readonly ?array $row) {}
        public function getRowArray(): ?array { return $this->row; }
    }

    final class M1920Builder
    {
        private ?string $id = null;
        public function __construct(private readonly M1920Db $db, private readonly string $table) {}
        public function select(string $fields): self { return $this; }
        public function where(string $field, string $value): self
        {
            if ($field !== 'id') {
                throw new \RuntimeException('Unexpected query field.');
            }
            $this->id = (string) $value;
            return $this;
        }
        public function get(): M1920Result
        {
            if ($this->table !== 'organizations' || $this->id === null) {
                throw new \RuntimeException('Unexpected query: ' . $this->table);
            }
            return new M1920Result($this->db->organizations[$this->id] ?? null);
        }
    }

    final class M1920Db extends BaseConnection
    {
        /** @var array<string,array{id:string}> */
        public array $organizations = ['5' => ['id'=>'5'], '9' => ['id'=>'9']];
        public function table(string $table): M1920Builder { return new M1920Builder($this,$table); }
    }

    require_once __DIR__ . '/../app/Exceptions/DomainException.php';
    require_once __DIR__ . '/../app/Shared/Read/PositiveId.php';
    require_once __DIR__ . '/../app/Modules/Contact/ContactRules.php';
    require_once __DIR__ . '/../app/Modules/Contact/ContactScope.php';
    require_once __DIR__ . '/../app/Modules/Access/ResourceStateCatalog.php';
    require_once __DIR__ . '/../app/Modules/Workflow/Execution/BusinessWorkflowRules.php';
    require_once __DIR__ . '/../app/Modules/Workflow/Execution/BusinessWorkflowContactScope.php';

    $n = 0;
    $ok = static function (bool $value, string $why) use (&$n): void {
        if (!$value) throw new \RuntimeException('M19.20 CONTACT WORKFLOW: ' . $why);
        ++$n;
    };
    $deny = static function (callable $task, string $why) use ($ok): void {
        try { $task(); }
        catch (DomainException $e) {
            $ok($e->getErrorCode() === 'WF_TARGET_NOT_FOUND', $why . ' denied without a 500');
            return;
        }
        throw new \RuntimeException('M19.20 CONTACT WORKFLOW: unsupported scope accepted: ' . $why);
    };

    $db = new M1920Db();
    $people = new M1920People();
    $people->employees['8'] = ['id'=>'8'];
    $people->assignments['8'] = [[
        'organization_id'=>'9','is_primary'=>'1','status'=>'ACTIVE',
    ]];
    AccessReadAdapters::$people = $people;

    $organizationContact = ['id'=>'4', 'contact_type'=>'ORGANIZATION',
        'contact_ref_id'=>'5', 'status'=>'ACTIVE', 'organization_id'=>'98765'];
    $orgScope = WorkflowScope::fromPersisted('organization_contact', $organizationContact, $db);
    $ok($orgScope['organization_id'] === '5', 'Organization Contact uses owner org, ignores rogue scope');
    $ok($organizationContact['organization_id'] === '98765', 'persisted input is unchanged');
    $ok($orgScope['contact_ref_id'] === '5', 'Contact owner ID is preserved');

    $employeeContact = ['id'=>'7','contact_type'=>'EMPLOYEE','contact_ref_id'=>'8',
        'status'=>'DRAFT','organization_id'=>'98765'];
    $employeeScope = WorkflowScope::fromPersisted('employee_contact',$employeeContact,$db);
    $ok($employeeScope['organization_id'] === '9', 'Employee Contact uses current primary assignment');
    $ok($employeeScope['contact_ref_id'] === '8', 'Employee owner remains unchanged');
    $ok($employeeContact['organization_id'] === '98765', 'Employee raw input is not mutated');

    $deny(static fn () => WorkflowScope::fromPersisted('organization_contact',
        ['id'=>'1','contact_type'=>'EMPLOYEE','contact_ref_id'=>'8'], $db), 'wrong polymorphic owner');
    $deny(static fn () => WorkflowScope::fromPersisted('employee_contact',
        ['id'=>'1','contact_type'=>'ORGANIZATION','contact_ref_id'=>'5'], $db), 'cross-owner resource type');
    $deny(static fn () => WorkflowScope::fromPersisted('organization_contact',
        ['contact_type'=>'ORGANIZATION','contact_ref_id'=>'404'], $db), 'missing Organization owner');
    $deny(static fn () => WorkflowScope::fromPersisted('organization_contact',
        ['contact_type'=>'ORGANIZATION','contact_ref_id'=>'0'], $db), 'invalid persisted Organization owner');
    $deny(static fn () => WorkflowScope::fromPersisted('employee_contact',
        ['contact_type'=>'EMPLOYEE','contact_ref_id'=>'404'], $db), 'missing Employee owner');

    $people->assignments['8'] = [];
    $deny(static fn () => WorkflowScope::fromPersisted('employee_contact', $employeeContact, $db), 'missing current primary');
    $people->assignments['8'] = [
        ['organization_id'=>'9','is_primary'=>'1','status'=>'ACTIVE'],
        ['organization_id'=>'5','is_primary'=>'1','status'=>'ACTIVE'],
    ];
    $deny(static fn () => WorkflowScope::fromPersisted('employee_contact', $employeeContact, $db), 'ambiguous primary assignments');
    $people->assignments['8'] = [['organization_id'=>'404','is_primary'=>'1','status'=>'ACTIVE']];
    $deny(static fn () => WorkflowScope::fromPersisted('employee_contact', $employeeContact, $db), 'assignment with unknown organization');
    $people->assignments['8'] = [['organization_id'=>'9','is_primary'=>'1','status'=>'ACTIVE']];
    $ok(WorkflowScope::fromPersisted('employee_contact',$employeeContact,$db)['organization_id'] === '9',
        'valid assignment restores a safe scope');

    $customerContact = ['contact_type'=>'CUSTOMER','contact_ref_id'=>'3','status'=>'DRAFT'];
    $ok(WorkflowScope::fromPersisted('customer_contact',$customerContact,$db) === $customerContact,
        'Customer Contact MASTER scope remains unchanged');
    $product = ['id'=>'17','status'=>'ACTIVE'];
    $ok(WorkflowScope::fromPersisted('product',$product,$db) === $product,
        'unrelated Workflow resource remains unchanged');

    foreach ([
        'Modules/Workflow/Execution/BusinessWorkflowService.php',
        'Modules/Notifications/WorkflowNotificationAssigneesService.php',
    ] as $path) {
        $source = (string) file_get_contents(__DIR__ . '/../app/' . $path);
        $ok(str_contains($source, 'BusinessWorkflowContactScope::fromPersisted('),
            $path . ' uses the same authoritative scope bridge');
        $ok(str_contains($source, 'BusinessWorkflowRules::matchesTargetOwner('),
            $path . ' still checks the polymorphic owner before scope');
    }
    $serviceSource = (string) file_get_contents(__DIR__ . '/../app/Modules/Workflow/Execution/BusinessWorkflowService.php');
    $ok(str_contains($serviceSource, '$this->target($type,$id,false)'), 'GET options resolves target scope');
    $ok(str_contains($serviceSource, '$this->target($type,$id,true)'), 'SETSTATE rechecks scope under row lock');
    $ok(str_contains($serviceSource, '->decideMutation($type,\'setstate\',$row)'),
        'SETSTATE PBAC is still enforced');
    $assigneeSource = (string) file_get_contents(__DIR__ . '/../app/Modules/Notifications/WorkflowNotificationAssigneesService.php');
    $ok(str_contains($assigneeSource, '$this->authorize($resource, $row)'),
        'M11 assignee read and write still use PBAC');

    echo 'M19.20 CONTACT WORKFLOW SCOPE: ' . $n . '/' . $n . " PASS\n";
}
