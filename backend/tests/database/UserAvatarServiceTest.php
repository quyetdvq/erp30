<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Services\UserAvatarService;
use App\Services\UserAvatarStorage;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal Avatar DB tests operate exclusively on erp_test. */
final class UserAvatarServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;
    protected $refresh = false;
    protected $migrateOnce = true;

    /** @var list<int> */
    private array $testUserIds = [];
    private ?int $createdGroupRootId = null;
    private string $avatarDirectory = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', ENVIRONMENT);
        $this->assertSame('erp_test', $this->db->database);
        $this->avatarDirectory = sys_get_temp_dir() . '/erp-avatar-service-' . bin2hex(random_bytes(7));
        $this->assertTrue(mkdir($this->avatarDirectory, 0700));
    }

    protected function tearDown(): void
    {
        try {
            // Do not run these tests inside an outer DB transaction: deleting a
            // replaced file before the OUTER commit would invalidate rollback.
            if ($this->testUserIds !== []) {
                $this->db->table(config('Auth')->tables['users'])
                    ->whereIn('id', $this->testUserIds)->delete();
            }
            if ($this->createdGroupRootId !== null) {
                $this->db->table('organizations')->where('id', $this->createdGroupRootId)->delete();
            }
            if ($this->avatarDirectory !== '') {
                foreach (glob($this->avatarDirectory . '/*') ?: [] as $filename) {
                    @unlink($filename);
                }
                @rmdir($this->avatarDirectory);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testOwnerCanUploadReadReplaceAndDeleteWithoutDanglingMetadata(): void
    {
        $owner = $this->newUser();
        $service = $this->service($owner);
        $firstSource = $this->pngSource();
        $secondSource = $this->pngSource();
        try {
            $this->assertSame(['user_id' => (string) $owner, 'has_avatar' => true], $service->upload($owner, $firstSource));
            $first = $service->read($owner);
            $firstName = (string) $this->avatarFor($owner);
            $this->assertSame('image/png', $first['mime']);
            $this->assertFileExists($first['path']);

            $this->assertSame(['user_id' => (string) $owner, 'has_avatar' => true], $service->upload($owner, $secondSource));
            $second = $service->read($owner);
            $this->assertNotSame($first['path'], $second['path']);
            $this->assertFileDoesNotExist($first['path']);
            $this->assertNotSame($firstName, $this->avatarFor($owner));
            $this->assertFileExists($second['path']);

            $this->assertSame(['user_id' => (string) $owner, 'has_avatar' => false], $service->remove($owner));
            $this->assertNull($this->avatarFor($owner));
            $this->assertFileDoesNotExist($second['path']);
            $this->assertDomainCode('USER_AVATAR_NOT_FOUND', fn () => $service->read($owner));
            // A repeated DELETE does not resurrect files or touch other Users.
            $this->assertFalse($service->remove($owner)['has_avatar']);
        } finally {
            @unlink($firstSource);
            @unlink($secondSource);
        }
    }

    public function testInvalidUploadPreservesExistingImageAndLeavesNoNewFile(): void
    {
        $owner = $this->newUser();
        $service = $this->service($owner);
        $source = $this->pngSource();
        $invalid = tempnam(sys_get_temp_dir(), 'erp-av-invalid-');
        $this->assertIsString($invalid);
        file_put_contents($invalid, 'not an image');
        try {
            $service->upload($owner, $source);
            $previous = $this->avatarFor($owner);
            $this->assertDomainCode('USER_AVATAR_INVALID_CONTENT', fn () => $service->upload($owner, $invalid));
            $this->assertSame($previous, $this->avatarFor($owner));
            $this->assertCount(1, glob($this->avatarDirectory . '/*') ?: []);
        } finally {
            @unlink($source);
            @unlink($invalid);
        }
    }

    public function testAnotherUserWithoutPbacVisibilityCannotProbeOrMutateTarget(): void
    {
        $actor = $this->newUser();
        $target = $this->newUser();
        $service = $this->service($actor);
        $this->ensurePbacRoot();
        $source = $this->pngSource();
        try {
            // No Employee, Group permission or Organization assignment exists for this actor.
            $this->assertDomainCode('USER_NOT_FOUND', fn () => $service->read($target));
            $this->assertDomainCode('USER_NOT_FOUND', fn () => $service->upload($target, $source));
            $this->assertDomainCode('USER_NOT_FOUND', fn () => $service->remove($target));
            $this->assertNull($this->avatarFor($target));
            $this->assertSame([], glob($this->avatarDirectory . '/*') ?: []);
        } finally {
            @unlink($source);
        }
    }

    public function testOuterTransactionRollbackKeepsPreviouslyReferencedFile(): void
    {
        $owner = $this->newUser();
        $service = $this->service($owner);
        $firstSource = $this->pngSource();
        $secondSource = $this->pngSource();
        try {
            $service->upload($owner, $firstSource);
            $oldPath = $service->read($owner)['path'];
            $oldName = $this->avatarFor($owner);
            $this->assertTrue($this->db->transBegin());
            try {
                $service->upload($owner, $secondSource);
                $this->assertFileExists($oldPath, 'No cleanup before enclosing transaction commits.');
            } finally {
                $this->db->transRollback();
                $this->db->resetTransStatus();
            }
            $this->assertSame($oldName, $this->avatarFor($owner));
            $this->assertSame($oldPath, $service->read($owner)['path']);
            $this->assertFileExists($oldPath);
        } finally {
            @unlink($firstSource);
            @unlink($secondSource);
        }
    }

    public function testOptimisticMetadataUpdateRejectsStaleConcurrentWriter(): void
    {
        $actor = $this->newUser();
        $service = $this->service($actor);
        $row = $this->db->table(config('Auth')->tables['users'])->where('id', $actor)->get()->getRowArray();
        $this->assertIsArray($row);
        $first = str_repeat('a', 40) . '.png';
        $second = str_repeat('b', 40) . '.png';
        // Simulate two requests holding the same initial NULL before either writes.
        $cas = new ReflectionMethod(UserAvatarService::class, 'persistAvatarFilename');
        $cas->invoke($service, $actor, null, $first, $row);
        $this->assertSame($first, $this->avatarFor($actor));
        $this->assertDomainCode('USER_AVATAR_CONCURRENT_UPDATE',
            fn () => $cas->invoke($service, $actor, null, $second, $row));
        $this->assertSame($first, $this->avatarFor($actor));
    }

    private function newUser(): int
    {
        $this->db->table(config('Auth')->tables['users'])->insert([
            'username' => 'avatar-v12-' . bin2hex(random_bytes(8)),
            'active' => 1,
            'status' => 'active',
            'created_at' => '2026-10-15 00:00:00',
        ]);
        $id = (int) $this->db->insertID();
        $this->testUserIds[] = $id;
        return $id;
    }

    private function ensurePbacRoot(): void
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $roots = $this->db->table('organizations')->select('id')
            ->where('type', 'GROUP')->where('parent_id', null)->get()->getResultArray();
        $this->assertLessThanOrEqual(1, count($roots));
        if ($roots !== []) {
            return;
        }
        $this->db->table('organizations')->insert([
            'type' => 'GROUP',
            'code' => 'AVATAR-TEST-ROOT-' . bin2hex(random_bytes(5)),
            'name' => 'Avatar test root',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-15 00:00:00',
        ]);
        $this->createdGroupRootId = (int) $this->db->insertID();
    }

    private function service(int $actor): UserAvatarService
    {
        $clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-15T00:00:00+00:00');
            }
        };
        return new UserAvatarService(
            db: $this->db,
            storage: new UserAvatarStorage($this->avatarDirectory),
            mutationContext: new MutationContext($clock, $actor, 'avatar-test', false),
        );
    }

    private function pngSource(): string
    {
        $image = imagecreatetruecolor(32, 24);
        $this->assertInstanceOf(GdImage::class, $image);
        $path = tempnam(sys_get_temp_dir(), 'erp-avatar-png-');
        $this->assertIsString($path);
        $this->assertTrue(imagepng($image, $path));
        imagedestroy($image);
        return $path;
    }

    private function avatarFor(int $userId): ?string
    {
        $row = $this->db->table(config('Auth')->tables['users'])
            ->select('avatar_filename')->where('id', $userId)->get()->getRowArray();
        return $row['avatar_filename'] ?? null;
    }

    private function assertDomainCode(string $code, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected ' . $code);
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }
}
