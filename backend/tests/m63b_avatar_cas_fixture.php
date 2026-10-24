<?php

declare(strict_types=1);

namespace CodeIgniter\Database {
    class BaseConnection
    {
        public int $transDepth=0;
    }
}
namespace CodeIgniter\Shield\Entities {
    class User
    {
        public function __construct(public int $id) {}
    }
}
namespace CodeIgniter\HTTP\Files {
    class UploadedFile {}
}
namespace App\Modules\Access {
    class UnifiedAccessService
    {
        public static bool $view = true;
        public static bool $edit = true;
        public static int $calls = 0;
        public function __construct(mixed $actor, mixed $db) {self::$calls++;}
        public function canView(string $type, array $row): bool
        {
            if ($type !== 'user' || !isset($row['id'])) throw new \RuntimeException('Wrong persisted user target');
            return self::$view;
        }
        public function decideMutation(string $type, string $action, array $row): array
        {
            if ($type !== 'user' || $action !== 'edit' || !isset($row['id'])) throw new \RuntimeException('Wrong target edit');
            return ['allowed'=>self::$edit];
        }
    }
}
namespace {
    use App\Exceptions\DomainException;
    use App\Modules\Access\UnifiedAccessService;
    use App\Modules\Identity\Media\UserAvatarService;
    use App\Shared\Media\PublicImageStorage;
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\Shield\Entities\User;

    function config(string $name): object
    {
        if ($name !== 'Auth') throw new \RuntimeException('Bad config name');
        return (object)['tables'=>['users'=>'users']];
    }
    function log_message(string $severity, string $message, array $details=[]): void {}

    final class AvatarFakeResult
    {
        public function __construct(private readonly ?array $row) {}
        public function getRowArray(): ?array {return $this->row;}
    }
    final class AvatarFakeBuilder
    {
        private array $wheres=[];
        public function __construct(private readonly AvatarFakeDb $db, string $name)
        {
            if ($name !== 'users') throw new \RuntimeException('Unexpected table: '.$name);
        }
        public function select(string $fields): self {return $this;}
        public function where(string $field, mixed $value): self {$this->wheres[$field]=$value; return $this;}
        private function matches(array $row): bool
        {
            foreach ($this->wheres as $field=>$expected) {
                if (($row[$field]??null) !== $expected) return false;
            }
            return true;
        }
        public function get(): AvatarFakeResult
        {
            foreach ($this->db->rows as $row) if($this->matches($row)) return new AvatarFakeResult($row);
            return new AvatarFakeResult(null);
        }
        public function countAllResults(): int
        {
            $count=0;
            foreach ($this->db->rows as $row) if($this->matches($row)) $count++;
            return $count;
        }
        public function update(array $changes): bool
        {
            if ($this->db->race !== null) {
                $race=$this->db->race;
                $this->db->race=null;
                $race($this->db);
            }
            $this->db->affected=0;
            if ($this->db->failUpdate) return false;
            foreach ($this->db->rows as $id=>$row) {
                if (!$this->matches($row)) continue;
                $this->db->rows[$id]=array_replace($row,$changes);
                $this->db->affected++;
            }
            return true;
        }
    }
    final class AvatarFakeDb extends BaseConnection
    {
        public array $rows=[];
        public int $affected=0;
        public bool $failUpdate=false;
        public bool $failCommit=false;
        public mixed $race=null;
        private array $before=[];
        public function __construct(array $rows) {$this->rows=$rows;}
        public function table(string $name): AvatarFakeBuilder {return new AvatarFakeBuilder($this,$name);}
        public function transBegin(): bool {$this->before=$this->rows; $this->transDepth++; return true;}
        public function transStatus(): bool {return true;}
        public function transCommit(): bool
        {
            if ($this->failCommit) return false;
            $this->transDepth--; $this->before=[];
            return true;
        }
        public function transRollback(): void
        {
            // This fixture models only the current transaction's changes;
            // the concurrent update (if any) is a separate committed writer.
            if ($this->race===null && $this->before!==[] && !$this->failUpdate) {
                // For CAS conflicts the second writer's value must be retained.
                if ($this->affected>0) $this->rows=$this->before;
            }
            $this->transDepth=0;
            $this->before=[];
        }
        public function resetTransStatus(): void {}
        public function affectedRows(): int {return $this->affected;}
    }
    require_once __DIR__.'/../app/Exceptions/DomainException.php';
    require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
    require_once __DIR__.'/../app/Shared/Media/ImageUrl.php';
    require_once __DIR__.'/../app/Shared/Media/UploadPaths.php';
    require_once __DIR__.'/../app/Shared/Media/PublicImageStorage.php';
    require_once __DIR__.'/../app/Modules/Identity/Media/UserAvatarRules.php';
    require_once __DIR__.'/../app/Modules/Identity/Media/UserAvatarService.php';

    $n=0;
    $ok=static function(bool $test,string $label)use(&$n):void {
        if(!$test) throw new \RuntimeException('M6.3B CAS fixture: '.$label);
        $n++;
    };
    $expect=static function (callable $call,string $code,string $label)use($ok):void {
        try {$call(); throw new \RuntimeException('Did not reject: '.$label);}
        catch(DomainException $error){$ok($error->getErrorCode()===$code,$label.' stable code');}
    };
    $root=sys_get_temp_dir().DIRECTORY_SEPARATOR.'m63b_cas_'.bin2hex(random_bytes(6));
    if(!mkdir($root,0700,true)) throw new \RuntimeException('Cannot create fixture folder');
    $storage=new PublicImageStorage($root);
    $old='20261024avatar1234567.png';
    $legacy=str_repeat('f',40).'.jpg';
    $path=$storage->path($old,'avatar');
    $year=dirname($path);
    mkdir($year,0700,true);
    $putFile=static function() use($path):void{file_put_contents($path,'PNG mocked bytes');};
    $row=static function(int $id,?string $avatar=null):array {
        return ['id'=>(string)$id,'active'=>1,'status'=>'active','deleted_at'=>null,
            'avatar_filename'=>$avatar,'updated_at'=>'2026-10-24 00:00:00'];
    };
    try {
        $putFile();
        UnifiedAccessService::$calls=0;
        $db=new AvatarFakeDb([1=>$row(1,$old)]);
        $service=new UserAvatarService(new User(1),$db,$storage);
        $result=$service->remove('1');
        $ok($result===['user_id'=>'1','has_avatar'=>false,'avatar_url'=>null],'self DELETE response');
        $ok($db->rows[1]['avatar_filename']===null,'self DELETE persists NULL');
        $ok(!is_file($path),'old avatar deleted after commit');
        $ok($db->transDepth===0,'no dangling transaction');
        $ok(UnifiedAccessService::$calls===0,'owner did not require target user.edit PBAC');

        $db=new AvatarFakeDb([1=>$row(1,null)]);
        $result=(new UserAvatarService(new User(1),$db,$storage))->remove('1');
        $ok(!$result['has_avatar'] && $db->affected===0,'idempotent DELETE on empty avatar');

        UnifiedAccessService::$view=false;
        $db=new AvatarFakeDb([2=>$row(2,$old)]);
        $expect(static fn()=>(new UserAvatarService(new User(1),$db,$storage))->remove('2'),
            'USER_NOT_FOUND','non-owner invisible target 404');
        $ok($db->rows[2]['avatar_filename']===$old,'denied user unchanged');
        UnifiedAccessService::$view=true;
        UnifiedAccessService::$edit=false;
        $expect(static fn()=>(new UserAvatarService(new User(1),$db,$storage))->remove('2'),
            'USER_AUTHORIZATION_DENIED','non-owner without user.edit 403');
        UnifiedAccessService::$edit=true;
        $putFile();
        $result=(new UserAvatarService(new User(1),$db,$storage))->remove('2');
        $ok($result['user_id']==='2' && !$result['has_avatar'],'target scoped authorized deletion');
        $ok($db->rows[2]['avatar_filename']===null,'target metadata updated');
        $ok(!is_file($path),'target orphan cleaned');

        $putFile();
        $db=new AvatarFakeDb([1=>$row(1,$old),2=>$row(2,$old)]);
        (new UserAvatarService(new User(1),$db,$storage))->remove('1');
        $ok(is_file($path),'shared image never deleted while second account references it');
        $ok($db->rows[2]['avatar_filename']===$old,'second account not touched');

        $db=new AvatarFakeDb([1=>$row(1,$legacy)]);
        $legacyPath=$root.DIRECTORY_SEPARATOR.$legacy;
        file_put_contents($legacyPath,'private old image');
        (new UserAvatarService(new User(1),$db,$storage))->remove('1');
        $ok(is_file($legacyPath),'private legacy image never unlinked');
        $ok($db->rows[1]['avatar_filename']===null,'legacy metadata cleared');

        $putFile();
        $db=new AvatarFakeDb([1=>$row(1,$old)]);
        $db->race=static function(AvatarFakeDb $db):void {
            $db->rows[1]['avatar_filename']='20261024avatar7654321.png';
        };
        $expect(static fn()=>(new UserAvatarService(new User(1),$db,$storage))->remove('1'),
            'USER_AVATAR_CONCURRENT_UPDATE','CAS blocks stale avatar removal');
        $ok(is_file($path),'old avatar not deleted on CAS conflict');
        $ok($db->rows[1]['avatar_filename']==='20261024avatar7654321.png','winner pointer not overwritten');

        $db=new AvatarFakeDb([1=>$row(1,$old)]);
        $db->failUpdate=true;
        $expect(static fn()=>(new UserAvatarService(new User(1),$db,$storage))->remove('1'),
            'USER_AVATAR_STORAGE_FAILED','SQL update failure safe');
        $ok(is_file($path),'file retained after SQL update failure');
        $ok($db->rows[1]['avatar_filename']===$old,'metadata retained after SQL update failure');

        $db=new AvatarFakeDb([1=>$row(1,$old)]);
        $db->failCommit=true;
        $expect(static fn()=>(new UserAvatarService(new User(1),$db,$storage))->remove('1'),
            'USER_AVATAR_STORAGE_FAILED','SQL commit failure safe');
        $ok(is_file($path),'file retained after failed SQL commit');
        $ok($db->rows[1]['avatar_filename']===$old,'metadata rollback on failed commit');

        $db=new AvatarFakeDb([1=>$row(1,$old)]);
        $db->transDepth=1;
        $expect(static fn()=>(new UserAvatarService(new User(1),$db,$storage))->remove('1'),
            'USER_AVATAR_STORAGE_FAILED','nested transaction not allowed');
        $ok(is_file($path) && $db->rows[1]['avatar_filename']===$old,'nested txn did not mutate');
    } finally {
        if(is_file($path))unlink($path);
        if(is_file($root.DIRECTORY_SEPARATOR.$legacy))unlink($root.DIRECTORY_SEPARATOR.$legacy);
        if(is_dir($year))rmdir($year);
        if(is_dir($root))rmdir($root);
    }
    echo 'M6.3B AVATAR CAS FIXTURE: '.$n.'/'.$n." PASS\n";
}
