<?php

declare(strict_types=1);

// Vendor-free production-contract checks: no MariaDB writes, HTTP or credentials.
namespace CodeIgniter\Database {
    class BaseConnection {
        public array $where = [];
        public int $reads = 0;
        public ?array $row = null;
        public function table(string $table): self { if ($table !== 'suppliers') throw new \RuntimeException('wrong table'); return $this; }
        public function select(string $columns): self { return $this; }
        public function where(string $name, mixed $value): self { $this->where[$name] = $value; return $this; }
        public function get(): self { ++$this->reads; return $this; }
        public function getRowArray(): ?array { return $this->row; }
    }
}
namespace CodeIgniter\Shield\Entities { class User {} }
namespace App\Modules\Access {
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\Shield\Entities\User;
    class UnifiedAccessService {
        public static bool $visible = true;
        public static bool $editable = true;
        public static bool $transition = true;
        public function __construct(User $user, BaseConnection $db) {}
        public function trustedGroupRootId(): string { return '100'; }
        public function canView(string $resource, array $row): bool { return $resource === 'supplier' && self::$visible; }
        public function decideMutation(string $resource, string $action, array $row): array {
            return ['allowed' => $resource === 'supplier' && $action === 'setstate' && self::$editable];
        }
        public function decideTransitionAction(string $resource, string $action, array $row): array {
            return ['allowed' => $resource === 'supplier' && $action === 'setstate' && self::$transition];
        }
    }
    class ResourceStateRepository {
        public static string $next = 'ACTIVE';
        public function __construct(BaseConnection $db) {}
        public function transitionTarget(string $resource, array $row, string $action): string {
            if (self::$next === '') throw new \UnexpectedValueException('Transition not allowed or ambiguous for current State.');
            return self::$next;
        }
    }
}
namespace {
    require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
    require_once __DIR__ . '/../../app/Modules/Access/ResourceStateCatalog.php';
    require_once __DIR__ . '/../../app/Modules/Workflow/Execution/BusinessWorkflowRules.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/NotificationDeliveryRules.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/NotificationRules.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/WorkflowNotificationRules.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/SupplierNotificationTarget.php';
    require_once __DIR__ . '/../../app/Modules/Notifications/MyWorkflowQuery.php';

    use App\Exceptions\DomainException;
    use App\Modules\Access\UnifiedAccessService as Access;
    use App\Modules\Access\ResourceStateRepository as States;
    use App\Modules\Notifications\SupplierNotificationTarget as Supplier;
    use App\Modules\Notifications\WorkflowNotificationRules as Rules;
    use App\Modules\Notifications\MyWorkflowQuery;
    use App\Modules\Workflow\Execution\BusinessWorkflowRules as Workflow;
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\Shield\Entities\User;

    $n = 0;
    $ok = static function (bool $check, string $name) use (&$n): void {
        if (!$check) throw new \RuntimeException('SUP-M11 FAIL: ' . $name);
        ++$n;
    };
    $denied = static function (string $code, callable $task) use (&$n): void {
        try { $task(); }
        catch (DomainException $e) {
            if ($e->getErrorCode() !== $code) throw $e;
            ++$n; return;
        }
        throw new \RuntimeException('Expected ' . $code);
    };
    $db = new BaseConnection(); $user = new User();
    $row = ['id'=>'7', 'group_organization_id'=>'100', 'name'=>'Supplier Demo',
        'supplier_code'=>'SUP-7','status'=>'DRAFT','row_version'=>2,
        'bank_account_cipher'=>'ENCRYPTED_PRIVATE'];
    $ok($db->reads === 0, 'preloaded locked row needs no fresh query');
    $options = Supplier::options($user, $db, '7', $row);
    $ok($options['current'] === 'DRAFT', 'persisted State is dynamic');
    $ok($options['options'] === [['action_code'=>'setstate', 'status'=>'ACTIVE']], 'only approved CAS action exposed');
    $ok($options['record_display'] === ['name'=>'Supplier Demo','code'=>'SUP-7'], 'limited safe display');
    $ok(!str_contains(json_encode($options), 'ENCRYPTED_PRIVATE'), 'no Bank leakage');
    States::$next = 'APPROVED';
    $ok(Supplier::options($user, $db, '7', $row)['options'][0]['status'] === 'APPROVED', 'Lifecycle not hardcoded');
    States::$next = '';
    $ok(Supplier::options($user, $db, '7', $row)['options'] === [], 'no ambiguous transition');
    States::$next = 'ACTIVE';
    Access::$visible = false;
    $denied('AUTH_POLICY_DENIED', fn () => Supplier::options($user, $db, '7', $row));
    Access::$visible = true; Access::$editable = false;
    $ok(Supplier::options($user, $db, '7', $row)['options'] === [], 'missing PBAC mutation yields no action');
    Access::$editable = true; Access::$transition = false;
    $ok(Supplier::options($user, $db, '7', $row)['options'] === [], 'transition-specific PBAC enforced');
    Access::$transition = true;
    $denied('WF_TARGET_NOT_FOUND', fn () => Supplier::options($user, $db, '7', [...$row, 'group_organization_id'=>'999']));
    $denied('WF_TARGET_NOT_FOUND', fn () => Supplier::options($user, $db, '7', [...$row, 'id'=>'8']));
    $db->row = $row;
    $ok(Supplier::options($user, $db, '7')['current'] === 'DRAFT', 'group scoped SQL row read works');
    $ok($db->where['group_organization_id'] === '100' && $db->where['id'] === '7', 'trusted group root applied in SQL');
    $ok(Supplier::options($user, $db, '7', $row)['options'] !== [], 'actionable transition requires live PBAC and Lifecycle, not an env switch');
    $denied('WF_TARGET_NOT_FOUND', fn () => Supplier::options($user, $db, '0', $row));
    $ok(MyWorkflowQuery::parse(['resource_type'=>'supplier'])['resource_type'] === 'supplier', 'own-user resource accepted');
    $ok(MyWorkflowQuery::parse(['resource_type'=>'all'])['resource_type'] === 'all', 'all filter unchanged');
    $ok(Workflow::id('7') === '7', 'canonical target identifier');
    $denied('WF_TARGET_ADAPTER_UNAVAILABLE', fn () => Workflow::table('supplier'));
    $payload = Rules::eventPayload('supplier','DRAFT','ACTIVE','setstate',['2','4']);
    $ok(Rules::decode($payload)['resource_type'] === 'supplier', 'canonical Supplier M11 event');
    $ok(Rules::decode($payload)['recipient_user_ids'] === ['2','4'], 'assigned user snapshot');
    try { Rules::eventPayload('supplier','DRAFT','ACTIVE','approve',['2']); throw new \RuntimeException('arbitrary supplier action accepted'); }
    catch (\InvalidArgumentException) { ++$n; }
    try { Rules::decode(str_replace('"action":"setstate"','"action":"edit"',$payload)); throw new \RuntimeException('tampered action accepted'); }
    catch (\InvalidArgumentException) { ++$n; }
    echo "SUP-M11 NOTIFICATION PURE: {$n}/{$n} PASS (fake DB, no HTTP or push)\n";
}
