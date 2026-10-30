<?php

declare(strict_types=1);

// Fake persisted Supplier owner lookup, no MariaDB and no real authorization.
namespace CodeIgniter\Database {
    class BaseConnection {
        public array $suppliers = [];
        public int $queries = 0;
        public function table(string $table): \T06FakeBuilder {
            if ($table !== 'suppliers') throw new \RuntimeException('Wrong table: '.$table);
            return new \T06FakeBuilder($this);
        }
    }
}
namespace App\Modules\Access {
    class UnifiedAccessService {
        public bool $view = true;
        public string $group = '1';
        public int $views = 0;
        public function trustedGroupRootId(): string { return $this->group; }
        public function canView(string $type, array $parent): bool {
            $this->views++;
            return $type === 'supplier' && $this->view && ($parent['status'] ?? '') === 'ACTIVE';
        }
    }
}
namespace {
    require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
    require_once __DIR__ . '/../../app/Shared/Read/PositiveId.php';
    require_once __DIR__ . '/../../app/Modules/Contact/ContactRules.php';
    require_once __DIR__ . '/../../app/Modules/Contact/ContactScope.php';
    require_once __DIR__ . '/../../app/Modules/Contact/SupplierContactParentView.php';

    use App\Exceptions\DomainException;
    use App\Modules\Access\UnifiedAccessService;
    use App\Modules\Contact\ContactScope;
    use App\Modules\Contact\SupplierContactParentView as ParentView;
    use CodeIgniter\Database\BaseConnection;

    final class T06FakeResult {
        public function __construct(private ?array $row) {}
        public function getRowArray(): ?array { return $this->row; }
    }
    final class T06FakeBuilder {
        private string $id='';
        public function __construct(private BaseConnection $db) {}
        public function select(string $cols): self {
            if ($cols !== 'id') throw new RuntimeException('Wrong owner existence projection');
            return $this;
        }
        public function where(string $field, string $value): self {
            if ($field !== 'id') throw new RuntimeException('Client owner SQL scope');
            $this->id=$value; return $this;
        }
        public function get(): T06FakeResult {
            $this->db->queries++;
            return new T06FakeResult($this->db->suppliers[$this->id] ?? null);
        }
    }
    $n=0;
    $ok=static function(bool $condition,string $label) use (&$n): void {
        if (!$condition) throw new RuntimeException('SUP-T06 PARENT FAILED: '.$label);
        ++$n;
    };
    $deny=static function(callable $call,string $label) use($ok): void {
        try {$call();} catch(DomainException $e) {
            $ok($e->getErrorCode()==='CONTACT_OWNER_NOT_FOUND',$label); return;
        }
        throw new RuntimeException('SUP-T06 PARENT incorrectly accepted '.$label);
    };
    $db=new BaseConnection();
    $db->suppliers['9']=['id'=>'9'];
    $resolver=new ContactScope($db);
    $row=['contact_type'=>'SUPPLIER','contact_ref_id'=>'9','status'=>'ACTIVE'];
    $scope=$resolver->resolve($row);
    $ok($scope===['resource'=>'supplier_contact','target'=>$row],'Supplier is MASTER with no synthetic Company scope');
    $ok($db->queries===1,'non-page owner existence checks DB once');
    $parents=['9'=>['id'=>'9','group_organization_id'=>'1','status'=>'ACTIVE']];
    $ok($resolver->resolve($row,$parents)===$scope,'page batch uses persisted parent');
    $ok($db->queries===1,'batch avoids per-row N+1');
    $deny(static fn()=>$resolver->resolve($row,[]),'missing page parent');
    unset($db->suppliers['9']);
    $deny(static fn()=>$resolver->resolve($row),'missing persisted Supplier');
    $actor=new UnifiedAccessService();
    $ok(ParentView::allowed($actor,$parents['9']), 'canonical Supplier VIEW and same GROUP');
    $ok($actor->views===1,'canonical canView called');
    $ok(!ParentView::allowed($actor,null),'missing parent denied');
    $ok(!ParentView::allowed($actor,['id'=>'9','status'=>'ACTIVE']),'missing group denied');
    $ok(!ParentView::allowed($actor,['id'=>'9','status'=>'ACTIVE','group_organization_id'=>'2']),'foreign group denied');
    $ok($actor->views===1,'no canView for invalid GROUP');
    $actor->view=false;
    $ok(!ParentView::allowed($actor,$parents['9']),'canonical parent VIEW denied');
    $actor->view=true;
    $ok(!ParentView::allowed($actor,['id'=>'9','status'=>'DRAFT','group_organization_id'=>'1']),'parent Lifecycle view denied by canonical evaluator');
    $actor->group='2';
    $ok(!ParentView::allowed($actor,$parents['9']),'trusted GROUP change denied');
    echo "SUP-T06 CONTACT PARENT FIXTURE: {$n}/{$n} PASS (fake DB/PBAC; NOT MariaDB)\n";
}
