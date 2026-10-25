<?php

declare(strict_types=1);

/** Pure CI4 transaction-boundary fixture; no database, no network. */
namespace CodeIgniter\Database {
    class BaseConnection
    {
        public int $transDepth = 0;
        public string $DBPrefix = '';
        public bool $status = true;
        /** @var list<string> */
        public array $events = [];
        public function query(string $sql): bool { $this->events[] = 'query:' . $sql; return true; }
        public function transBegin(): bool { $this->events[] = 'begin'; ++$this->transDepth; return true; }
        public function transStatus(): bool { $this->events[] = 'status'; return $this->status; }
        public function transCommit(): bool { $this->events[] = 'commit'; --$this->transDepth; return true; }
        public function transRollback(): bool { $this->events[] = 'rollback'; --$this->transDepth; return true; }
        public function resetTransStatus(): void { $this->events[] = 'reset'; $this->status = true; }
    }
}
namespace CodeIgniter\Shield\Entities { class User {} }
namespace App\Modules\Access {
    class UnifiedAccessService
    {
        public function __construct(\CodeIgniter\Shield\Entities\User $user, ?\CodeIgniter\Database\BaseConnection $connection = null) {}
    }
}
namespace {
    require __DIR__ . '/../app/Modules/Product/Master/ProductReadService.php';
    $passed=0;
    $check=static function(bool $condition,string $why)use(&$passed): void {
        if (!$condition) throw new \RuntimeException('M8.5 SNAPSHOT: ' . $why);
        ++$passed;
    };
    $invoke=static function(\CodeIgniter\Database\BaseConnection $db, callable $work, bool $borrow): mixed {
        $service=new \App\Modules\Product\Master\ProductReadService(
            new \CodeIgniter\Shield\Entities\User(), $db
        );
        $method=new \ReflectionMethod($service, 'snapshot');
        return $method->invoke($service,$work,$borrow);
    };

    $db=new \CodeIgniter\Database\BaseConnection();
    $db->transDepth=1;
    $check($invoke($db, static fn(): string => 'committed-by-caller', true)==='committed-by-caller',
        'borrowed detail returns correct value');
    $check($db->transDepth===1 && $db->events===['status'],
        'borrowed transaction not restarted, committed, rolled back or reset');

    $db=new \CodeIgniter\Database\BaseConnection();
    $db->transDepth=1;
    try {$invoke($db, static fn(): bool=>true,false);throw new \RuntimeException('List borrowed transaction accepted');}
    catch (\UnexpectedValueException) {$check(true,'list refuses borrowing transaction');}
    $check($db->transDepth===1 && $db->events===[], 'rejected borrow never mutates caller transaction');

    $db=new \CodeIgniter\Database\BaseConnection();
    $db->transDepth=1;
    $db->status=false;
    try {$invoke($db, static fn(): bool=>true,true);throw new \RuntimeException('Failed transaction borrowed');}
    catch (\UnexpectedValueException) {$check(true,'unhealthy borrowed transaction refused');}
    $check($db->transDepth===1 && $db->events===['status'], 'unhealthy borrowed transaction untouched');

    $db=new \CodeIgniter\Database\BaseConnection();
    $check($invoke($db, static fn(): int=>42, false)===42, 'standalone scoped read returns value');
    $check($db->events===['query:SET TRANSACTION ISOLATION LEVEL REPEATABLE READ','begin','status','commit'],
        'standalone read owns isolation, begin and commit');
    $check($db->transDepth===0, 'standalone read releases transaction');

    $db=new \CodeIgniter\Database\BaseConnection();
    try {$invoke($db, static function(): void {throw new \LogicException('expected abort');},false);
        throw new \RuntimeException('Failure swallowed');}
    catch (\LogicException $error) {$check($error->getMessage()==='expected abort', 'standalone failure rethrown');}
    $check($db->events===['query:SET TRANSACTION ISOLATION LEVEL REPEATABLE READ','begin','rollback','reset'],
        'standalone failure rolled back and reset');
    $check($db->transDepth===0,'standalone rollback exits transaction');
    echo 'M8.5 PRODUCT SNAPSHOT/WRITER INTEROP: '.$passed.'/'.$passed." PASS; no DB\n";
}
