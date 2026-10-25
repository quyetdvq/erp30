<?php

declare(strict_types=1);

namespace CodeIgniter\Database {
    class BaseConnection { public int $transDepth = 0; }
}
namespace CodeIgniter\Shield\Entities {
    class User { public function __construct(public int $id) {} }
}
namespace CodeIgniter\HTTP\Files {
    class UploadedFile {
        public function __construct(private readonly string $path) {}
        public function getTempName(): string {return $this->path;}
        public function getSize(): int {return is_file($this->path) ? (int)filesize($this->path) : 0;}
        public function getError(): int {return UPLOAD_ERR_OK;}
        public function isValid(): bool {return is_file($this->path);}
    }
}
namespace App\Modules\Access {
    class UnifiedAccessService {
        public function __construct(mixed $actor, mixed $db) {}
        public function canView(string $type, array $row): bool {return false;}
        public function decideMutation(string $type, string $action, array $row): array {return ['allowed'=>false];}
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
        if ($name !== 'Auth') throw new RuntimeException('Unsafe fixture config');
        return (object)['tables'=>['users'=>'users']];
    }
    function log_message(string $level, string $message, array $context=[]): void {}

    final class M63CFakeResult {
        public function __construct(private readonly ?array $row) {}
        public function getRowArray(): ?array {return $this->row;}
    }
    final class M63CFakeBuilder {
        private array $wheres=[];
        public function __construct(private readonly M63CFakeDb $db, string $table) {
            if ($table !== 'users') throw new RuntimeException('Unreviewed SQL table');
        }
        public function select(string $projection): self {return $this;}
        public function where(string $key, mixed $value): self {$this->wheres[$key] = $value; return $this;}
        private function matches(array $row): bool {
            foreach ($this->wheres as $key=>$value) {
                if (($row[$key] ?? null) !== $value) return false;
            }
            return true;
        }
        public function get(): M63CFakeResult {
            return new M63CFakeResult($this->matches($this->db->row) ? $this->db->row : null);
        }
        public function countAllResults(): int {return $this->matches($this->db->row) ? 1 : 0;}
        public function update(array $changes): bool {
            $this->db->affected = 0;
            if ($this->db->racingWinner !== null) {
                $this->db->row['avatar_filename'] = $this->db->racingWinner;
                $this->db->racingWinner = null;
            }
            if ($this->db->failUpdate) return false;
            if ($this->matches($this->db->row)) {
                $this->db->row = array_replace($this->db->row, $changes);
                $this->db->affected = 1;
            }
            return true;
        }
    }
    final class M63CFakeDb extends BaseConnection {
        public int $affected=0;
        public bool $failUpdate=false;
        public bool $failCommit=false;
        public ?string $racingWinner=null;
        private array $before=[];
        public function __construct(public array $row) {}
        public function table(string $table): M63CFakeBuilder {return new M63CFakeBuilder($this, $table);}
        public function transBegin(): bool {$this->before=$this->row; $this->transDepth++; return true;}
        public function transStatus(): bool {return true;}
        public function transCommit(): bool {
            if ($this->failCommit) return false;
            $this->transDepth=0; $this->before=[]; return true;
        }
        public function transRollback(): void {
            if ($this->affected > 0) $this->row=$this->before;
            $this->before=[];$this->transDepth=0;
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
    require_once __DIR__.'/../tools/m63b1/M63B1AvatarPlan.php';

    $n=0;
    $ok=static function(bool $value, string $name)use(&$n):void {
        if (!$value) throw new RuntimeException('M6.3C failure fixture: '.$name);
        $n++;
    };
    $expect=static function(callable $fn, string $code, string $name)use($ok):void {
        try {$fn();throw new RuntimeException('Failure was not injected: '.$name);}
        catch(DomainException $error) {$ok($error->getErrorCode()===$code, $name);}
    };
    $root=sys_get_temp_dir().DIRECTORY_SEPARATOR.'m63c_fail_'.bin2hex(random_bytes(8));
    if (!mkdir($root,0700,true)) throw new RuntimeException('Cannot create isolated fixture root.');
    $scratch=$root.DIRECTORY_SEPARATOR.'scratch';
    $temp=$root.DIRECTORY_SEPARATOR.'input.png';
    $row=static fn(?string $name=null):array => ['id'=>'3', 'active'=>1, 'status'=>'active',
        'deleted_at'=>null, 'avatar_filename'=>$name, 'updated_at'=>'2026-10-25 00:00:00'];
    $storage=new PublicImageStorage($scratch);
    $files=static function(string $path):array {
        $paths=glob($path.DIRECTORY_SEPARATOR.'20[0-9][0-9]'.DIRECTORY_SEPARATOR.'*avatar*.png');
        if ($paths===false) throw new RuntimeException('Cannot inspect isolated output.');
        return $paths;
    };
    $cleanup=static function(string $dir)use(&$cleanup):void {
        if (!is_dir($dir) || is_link($dir))return;
        foreach (scandir($dir) ?: [] as $item) {
            if ($item==='.' || $item==='..') continue;
            $path=$dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path) && !is_link($path)) $cleanup($path);
            else unlink($path);
        }
        rmdir($dir);
    };
    try {
        $ok(!is_dir($scratch) && is_dir($root), 'test starts outside public uploads');
        $ok($storage->path('20261024avatar1234567.png','avatar')
            ===$scratch.DIRECTORY_SEPARATOR.'2026'.DIRECTORY_SEPARATOR.'20261024avatar1234567.png',
            'storage writes only beneath injected test directory');
        $db=new M63CFakeDb($row());
        $svc=new UserAvatarService(new User(3), $db, $storage);
        $expect(static fn()=> $svc->upload('3',new UploadedFile($temp)),
            'USER_AVATAR_INVALID_CONTENT','absent upload fixture never writes SQL');
        $ok($db->row['avatar_filename']===null && $db->transDepth===0,
            'empty input leaves metadata and transaction untouched');
        $ok(!is_dir($scratch),'invalid upload cannot create a storage root');
        $codecs=M63B1AvatarPlan::codecsReady();
        if ($codecs) {
            $sample=M63B1AvatarPlan::sample(1);
            file_put_contents($temp,$sample['bytes']);
            $upload=new UploadedFile($temp);
            $block=$root.DIRECTORY_SEPARATOR.'blocked';
            mkdir($block,0700);
            file_put_contents($block.DIRECTORY_SEPARATOR.gmdate('Y'),'NOT_A_DIRECTORY');
            $db=new M63CFakeDb($row());
            $expect(static fn()=>(new UserAvatarService(new User(3),$db,new PublicImageStorage($block)))->upload('3',$upload),
                'USER_AVATAR_STORAGE_FAILED', 'forced filesystem folder conflict yields stable 500 code');
            $ok($db->row['avatar_filename']===null && $db->affected===0
                && $db->transDepth===0, 'filesystem failure occurs before CAS and preserves SQL');

            $db=new M63CFakeDb($row());
            $db->failUpdate=true;
            $expect(static fn()=>(new UserAvatarService(new User(3),$db,$storage))->upload('3',$upload),
                'USER_AVATAR_STORAGE_FAILED', 'SQL failure after staging returns stable code');
            $ok($db->row['avatar_filename']===null && $db->transDepth===0
                && $files($scratch)===[], 'failed SQL write rolls back metadata and removes staged PNG');

            $db=new M63CFakeDb($row());
            $db->failCommit=true;
            $expect(static fn()=>(new UserAvatarService(new User(3),$db,$storage))->upload('3',$upload),
                'USER_AVATAR_STORAGE_FAILED', 'failed commit after staged file is rejected');
            $ok($db->row['avatar_filename']===null && $db->transDepth===0
                && $files($scratch)===[], 'commit failure rolls back and cleans staged PNG');

            $winner='20261024avatar7654321.png';
            $winnerPath=$storage->path($winner,'avatar');
            if (!is_dir(dirname($winnerPath))) mkdir(dirname($winnerPath),0700,true);
            file_put_contents($winnerPath,'winner-pinned-file');
            $db=new M63CFakeDb($row());
            $db->racingWinner=$winner;
            $expect(static fn()=>(new UserAvatarService(new User(3),$db,$storage))->upload('3',$upload),
                'USER_AVATAR_CONCURRENT_UPDATE','stale writer rejected without replacing winner');
            $ok($db->row['avatar_filename']===$winner && is_file($winnerPath)
                && $files($scratch)===[$winnerPath] && $db->transDepth===0,
                'CAS conflict removes only loser staging; winner remains referenced');
            unlink($winnerPath);
            $db=new M63CFakeDb($row());
            $created=(new UserAvatarService(new User(3),$db,$storage))->upload('3',$upload);
            $ok($created['has_avatar']===true && is_string($db->row['avatar_filename'])
                && count($files($scratch))===1,'independent successful upload creates one referenced PNG');
            (new UserAvatarService(new User(3),$db,$storage))->remove('3');
            $ok($db->row['avatar_filename']===null && $files($scratch)===[]
                && $db->transDepth===0,'successful cleanup leaves zero QA files and null SQL');
        }
    } finally {
        $cleanup($root);
    }
    echo 'M6.3C AVATAR FS/SQL FAILURE FIXTURE: '.$n.'/'.$n.' PASS; GD_RUNTIME='.
        ($codecs ? 'EXECUTED' : 'SKIPPED_UNAVAILABLE').PHP_EOL;
}
