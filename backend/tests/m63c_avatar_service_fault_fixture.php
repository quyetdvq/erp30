<?php

declare(strict_types=1);

/** Deterministic failure injection against the real UserAvatarService, no GD/DB needed. */
namespace CodeIgniter\Database {
    class BaseConnection { public int $transDepth=0; }
}
namespace CodeIgniter\Shield\Entities {
    class User { public function __construct(public int $id) {} }
}
namespace CodeIgniter\HTTP\Files {
    class UploadedFile {
        public function getSize(): int {return 128;}
        public function getError(): int {return UPLOAD_ERR_OK;}
        public function isValid(): bool {return true;}
        public function getTempName(): string {return 'isolated-fixture.png';}
    }
}
namespace App\Modules\Access {
    class UnifiedAccessService {
        public function __construct(mixed $actor,mixed $db) {}
        public function canView(string $type,array $row): bool {return false;}
        public function decideMutation(string $type,string $action,array $row): array {return ['allowed'=>false];}
    }
}
namespace App\Shared\Media {
    final class PublicImageStorage {
        public const AVATAR_MAX_BYTES=2*1024*1024;
        public bool $failStore=false;
        public bool $failDelete=false;
        public array $files=[];
        public int $seq=0;
        public function store(string $source,string $type): array {
            if ($type !== 'avatar') throw new \RuntimeException('Unexpected product write');
            if ($this->failStore) throw new \RuntimeException('FIXTURE_STORAGE_FAILURE');
            $this->seq++;
            $name='20261024avatar'.sprintf('%07d',$this->seq).'.png';
            $this->files[$name]=true;
            return ['filename'=>$name,'mime'=>'image/png','size'=>128];
        }
        public function delete(string $name,string $type): void {
            if ($type !== 'avatar') throw new \RuntimeException('Unexpected product cleanup');
            if ($this->failDelete) throw new \RuntimeException('FIXTURE_UNLINK_FAILURE');
            unset($this->files[$name]);
        }
    }
}
namespace {
    use App\Exceptions\DomainException;
    use App\Modules\Identity\Media\UserAvatarService;
    use App\Shared\Media\PublicImageStorage;
    use CodeIgniter\Database\BaseConnection;
    use CodeIgniter\HTTP\Files\UploadedFile;
    use CodeIgniter\Shield\Entities\User;

    function config(string $name): object {
        if ($name!=='Auth') throw new RuntimeException('Unsupported fixture config');
        return (object)['tables'=>['users'=>'users']];
    }
    function log_message(string $level,string $message,array $context=[]): void {}
    final class M63CResult {
        public function __construct(private readonly array $data) {}
        public function getRowArray(): array {return $this->data;}
    }
    final class M63CQuery {
        private array $predicates=[];
        public function __construct(private readonly M63CDb $db,string $table) {
            if ($table!=='users') throw new RuntimeException('Incorrect table');
        }
        public function select(string $fields): self {return $this;}
        public function where(string $key,mixed $value): self {$this->predicates[$key]=$value;return $this;}
        private function matches(): bool {
            foreach($this->predicates as $key=>$value) {
                if (($this->db->row[$key]??null)!==$value)return false;
            }
            return true;
        }
        public function get(): M63CResult {return new M63CResult($this->matches()?$this->db->row:[]);}
        public function countAllResults(): int {return $this->matches()?1:0;}
        public function update(array $changes): bool {
            $this->db->affected=0;
            if($this->db->racingWinner!==null) {
                $this->db->row['avatar_filename']=$this->db->racingWinner;
                $this->db->racingWinner=null;
            }
            if($this->db->failUpdate)return false;
            if($this->matches()) {
                $this->db->row=array_replace($this->db->row,$changes);
                $this->db->affected=1;
            }
            return true;
        }
    }
    final class M63CDb extends BaseConnection {
        public int $affected=0;
        public bool $failUpdate=false;
        public bool $failCommit=false;
        public ?string $racingWinner=null;
        private array $prior=[];
        public function __construct(public array $row) {}
        public function table(string $table): M63CQuery {return new M63CQuery($this,$table);}
        public function transBegin(): bool {$this->prior=$this->row;$this->transDepth=1;return true;}
        public function transStatus(): bool {return true;}
        public function transCommit(): bool {
            if ($this->failCommit)return false;
            $this->transDepth=0;return true;
        }
        public function transRollback(): void {
            if ($this->affected===1)$this->row=$this->prior;
            $this->transDepth=0;
        }
        public function resetTransStatus(): void {}
        public function affectedRows(): int {return $this->affected;}
    }
    require_once __DIR__.'/../app/Exceptions/DomainException.php';
    require_once __DIR__.'/../app/Shared/Read/PositiveId.php';
    require_once __DIR__.'/../app/Shared/Media/ImageUrl.php';
    require_once __DIR__.'/../app/Modules/Identity/Media/UserAvatarRules.php';
    require_once __DIR__.'/../app/Modules/Identity/Media/UserAvatarService.php';
    $n=0;
    $ok=static function(bool $pass,string $label)use(&$n):void {
        if (!$pass)throw new RuntimeException('M6.3C service fixture: '.$label);
        $n++;
    };
    $expect=static function(callable $call,string $expected,string $label)use($ok):void {
        try{$call();throw new RuntimeException('Missing failure: '.$label);}
        catch(DomainException $e){$ok($e->getErrorCode()===$expected,$label);}
    };
    $row=static fn(?string $filename=null):array=>['id'=>'3','active'=>1,'status'=>'active',
        'deleted_at'=>null,'avatar_filename'=>$filename,'updated_at'=>'2026-10-25'];
    $actor=new User(3);
    $upload=new UploadedFile();
    $store=new PublicImageStorage();
    $store->failStore=true;
    $db=new M63CDb($row());
    $expect(static fn()=>(new UserAvatarService($actor,$db,$store))->upload('3',$upload),
        'USER_AVATAR_STORAGE_FAILED','storage error mapped to stable 500 before SQL');
    $ok($db->row['avatar_filename']===null && $db->affected===0 && $store->files===[],
        'failing storage does not begin a DB write or create a file');

    $store=new PublicImageStorage();
    $db=new M63CDb($row());$db->failUpdate=true;
    $expect(static fn()=>(new UserAvatarService($actor,$db,$store))->upload('3',$upload),
        'USER_AVATAR_STORAGE_FAILED','SQL update failure after staging is handled');
    $ok($db->row['avatar_filename']===null && $db->transDepth===0 && $store->files===[],
        'SQL rollback removes staged file and preserves null pointer');

    $store=new PublicImageStorage();
    $db=new M63CDb($row());$db->failCommit=true;
    $expect(static fn()=>(new UserAvatarService($actor,$db,$store))->upload('3',$upload),
        'USER_AVATAR_STORAGE_FAILED','SQL commit failure after staging is handled');
    $ok($db->row['avatar_filename']===null && $db->transDepth===0 && $store->files===[],
        'commit failure rolls back pointer and removes staged file');

    $store=new PublicImageStorage();
    $winner='20261024avatar6543210.png';
    $store->files[$winner]=true;
    $db=new M63CDb($row());$db->racingWinner=$winner;
    $expect(static fn()=>(new UserAvatarService($actor,$db,$store))->upload('3',$upload),
        'USER_AVATAR_CONCURRENT_UPDATE','stale writer receives stable CAS conflict');
    $ok($db->row['avatar_filename']===$winner && $db->transDepth===0
        && $store->files===[$winner=>true], 'winner retained, loser staging deleted');

    $store=new PublicImageStorage();
    $db=new M63CDb($row());
    $first=(new UserAvatarService($actor,$db,$store))->upload('3',$upload);
    $ok($first['has_avatar']===true && count($store->files)===1,
        'first upload commits before old file cleanup');
    $second=(new UserAvatarService($actor,$db,$store))->upload('3',$upload);
    $ok($second['avatar_url']!==$first['avatar_url'] && count($store->files)===1,
        'replace commits new file and cleans old unreferenced image');
    $third=(new UserAvatarService($actor,$db,$store))->remove('3');
    $ok(!$third['has_avatar'] && $db->row['avatar_filename']===null && $store->files===[],
        'final DELETE leaves no file and no pointer');

    echo 'M6.3C SERVICE FAULT INJECTION: '.$n.'/'.$n.' PASS; no filesystem/DB'.PHP_EOL;
}
