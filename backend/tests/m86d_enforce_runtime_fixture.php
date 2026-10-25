<?php

declare(strict_types=1);

namespace CodeIgniter\Database { class BaseConnection {} }
namespace CodeIgniter\Shield\Entities { class User { public function __construct(public int $id=9) {} } }
namespace App\Modules\Access {
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\Shield\Entities\User;
    use DateTimeImmutable;
    final class UnifiedAccessService {
        public static int $constructed=0;
        public static array $calls=[];
        public function __construct(User $user, ?BaseConnection $db, DateTimeImmutable $instant) {
            self::$constructed++;
            self::$calls[]=['actor'=>$user->id,'time'=>$instant->format('c')];
        }
        public function canView(string $type, array $row): bool {
            self::$calls[]=['type'=>$type,'row'=>$row];
            if ($row['throw'] ?? false) throw new \RuntimeException('CANONICAL_DENIAL');
            return (bool)($row['permit'] ?? false);
        }
    }
}
namespace {
    require __DIR__ . '/../app/Modules/Access/ReadAccessBridge.php';
    use App\Modules\Access\ReadAccessBridge;
    use App\Modules\Access\UnifiedAccessService;
    use CodeIgniter\Shield\Entities\User;
    $n=0;
    $ok=static function(bool $pass,string $message)use(&$n):void {
        if (!$pass) throw new \RuntimeException('M8.6D BRIDGE: '.$message);
        $n++;
    };
    putenv('DINKERP_M32_READ_MODE=legacy');
    putenv('DINKERP_M86B_SQL_SCOPE_MODE=disabled');
    putenv('DINKERP_M86C_SQL_SCOPE_MODE=disabled');
    $actor=new User(9);
    $instant=new \DateTimeImmutable('2026-10-25T07:00:00+07:00');
    $bridge=new ReadAccessBridge($actor,null,$instant);
    $ok(UnifiedAccessService::$constructed===0,'lazy canonical construction');
    $ok($bridge->canView('employee',['permit'=>true]),'canonical allow');
    $ok(!$bridge->canView('employee',['permit'=>false]),'canonical deny');
    $ok(UnifiedAccessService::$constructed===1,'canonical decision reused per bridge');
    $ok(UnifiedAccessService::$calls[0]['time']==='2026-10-25T00:00:00+00:00',
        'shared UTC evaluation instant');
    $ok(UnifiedAccessService::$calls[1]['type']==='employee','original resource forwarded');
    $ok(UnifiedAccessService::$calls[1]['row']['permit']===true,'original persisted row forwarded');
    try {
        $bridge->canView('employee',['throw'=>true]);
        $ok(false,'canonical exception must propagate');
    } catch (\RuntimeException $e) {
        $ok($e->getMessage()==='CANONICAL_DENIAL','no legacy fallback after canonical failure');
    }
    foreach(['legacy','shadow','enforce','disabled'] as $override) {
        try {
            new ReadAccessBridge($actor,null,$instant,$override);
            $ok(false,'mode override must be rejected');
        } catch (\UnexpectedValueException $e) {
            $ok(true,'obsolete mode override rejected');
        }
    }
    echo 'M8.6D CANONICAL BRIDGE RUNTIME: '.$n.'/'.$n." PASS\n";
}
