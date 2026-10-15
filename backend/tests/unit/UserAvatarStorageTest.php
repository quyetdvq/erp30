<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Services\UserAvatarStorage;
use PHPUnit\Framework\TestCase;

/** @internal Storage security tests use real image bytes, not mocked MIME labels. */
final class UserAvatarStorageTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'erp-user-avatar-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->folder, 0700));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->folder . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->folder);
        parent::tearDown();
    }

    public function testSupportedFormatsAreDecodedAndReencodedToRandomPrivateFiles(): void
    {
        $this->requireCodecs();
        foreach (['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'] as $extension => $mime) {
            $source = $this->makeImage($extension);
            try {
                $storage = new UserAvatarStorage($this->folder);
                $inspected = $storage->inspect($source);
                self::assertSame($mime, $inspected['mime']);
                self::assertSame($extension, $inspected['extension']);
                self::assertSame(32, $inspected['width']);
                self::assertSame(16, $inspected['height']);

                $a = $storage->store($source);
                $b = $storage->store($source);
                self::assertMatchesRegularExpression('/\A[a-f0-9]{40}\.' . $extension . '\z/', $a['filename']);
                self::assertNotSame($a['filename'], $b['filename']);
                self::assertSame($mime, $a['mime']);
                self::assertFileExists($storage->path($a['filename']));
                self::assertSame($mime, (new finfo(FILEINFO_MIME_TYPE))->file($storage->path($a['filename'])));
                self::assertIsArray(@getimagesize($storage->path($a['filename'])));
                $storage->delete($a['filename']);
                $storage->delete($b['filename']);
                self::assertFileDoesNotExist($storage->path($a['filename']));
            } finally {
                @unlink($source);
            }
        }
    }

    public function testExifIsRemovedByReencodingJpeg(): void
    {
        $this->requireCodecs();
        $source = $this->makeImage('jpg');
        $plain = (string) file_get_contents($source);
        $exif = "Exif\0\0" . "MM\0*" . pack('N', 8) . pack('N', 0);
        file_put_contents($source, substr($plain, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($plain, 2));
        $storage = new UserAvatarStorage($this->folder);
        try {
            $result = $storage->store($source);
            self::assertStringContainsString("Exif\0\0", (string) file_get_contents($source));
            self::assertStringNotContainsString("Exif\0\0", (string) file_get_contents($storage->path($result['filename'])));
            $storage->delete($result['filename']);
        } finally {
            @unlink($source);
        }
    }

    public function testEmptyAndOversizedInputsAreRejected(): void
    {
        $storage = new UserAvatarStorage($this->folder);
        foreach (['', str_repeat('A', UserAvatarStorage::MAX_BYTES + 1)] as $body) {
            $file = $this->temporaryFile($body);
            try {
                $this->assertDomainError('USER_AVATAR_INVALID_SIZE', static fn () => $storage->inspect($file));
            } finally {
                @unlink($file);
            }
        }
    }

    public function testOversizedPixelsSvgGifAndFakePngAreRejected(): void
    {
        $this->requireCodecs();
        $storage = new UserAvatarStorage($this->folder);
        $oversized = $this->makeImage('png', UserAvatarStorage::MAX_DIMENSION + 1, 1);
        $gif = $this->temporaryFile((string) base64_decode('R0lGODlhAQABAIAAAP8AAP///ywAAAAAAQABAAACAUwAOw==', true));
        $svg = $this->temporaryFile('<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $fakePng = $this->temporaryFile("\x89PNG\r\n\x1A\n" . str_repeat('a', 80));
        try {
            $this->assertDomainError('USER_AVATAR_INVALID_CONTENT', static fn () => $storage->inspect($oversized));
            $this->assertDomainError('USER_AVATAR_INVALID_TYPE', static fn () => $storage->inspect($gif));
            $this->assertDomainError('USER_AVATAR_INVALID_CONTENT', static fn () => $storage->inspect($svg));
            $this->assertDomainError('USER_AVATAR_INVALID_CONTENT', static fn () => $storage->inspect($fakePng));
        } finally {
            foreach ([$oversized, $gif, $svg, $fakePng] as $file) {
                @unlink($file);
            }
        }
    }

    public function testPngAnimationControlChunkIsRejected(): void
    {
        $this->requireCodecs();
        $source = $this->makeImage('png');
        $bytes = (string) file_get_contents($source);
        $payload = pack('NN', 2, 0);
        $chunk = pack('N', strlen($payload)) . 'acTL' . $payload . pack('N', crc32('acTL' . $payload));
        // A PNG's IHDR chunk begins immediately after its 8-byte signature.
        $ihdrLength = unpack('N', substr($bytes, 8, 4))[1];
        $afterIhdr = 8 + 12 + $ihdrLength;
        file_put_contents($source, substr($bytes, 0, $afterIhdr) . $chunk . substr($bytes, $afterIhdr));
        try {
            $this->assertDomainError('USER_AVATAR_INVALID_CONTENT', static fn () => (new UserAvatarStorage())->inspect($source));
        } finally {
            @unlink($source);
        }
    }

    public function testFilenameValidationRejectsTraversalForReadAndDelete(): void
    {
        $storage = new UserAvatarStorage($this->folder);
        foreach (['../../.env', 'other.png', str_repeat('a', 40) . '.php', "x\0.jpg", ''] as $name) {
            $this->assertDomainError('USER_AVATAR_NOT_FOUND', static fn () => $storage->path($name));
            $this->assertDomainError('USER_AVATAR_NOT_FOUND', static fn () => $storage->delete($name));
        }
    }

    private function requireCodecs(): void
    {
        self::assertTrue(extension_loaded('gd'), 'PHP GD is required for secure avatar re-encoding.');
        self::assertTrue(function_exists('imagewebp') && function_exists('imagecreatefromstring'));
        self::assertSame(IMG_JPG | IMG_PNG | IMG_WEBP, imagetypes() & (IMG_JPG | IMG_PNG | IMG_WEBP));
    }

    private function makeImage(string $extension, int $width = 32, int $height = 16): string
    {
        $path = tempnam(sys_get_temp_dir(), 'erp-av-src-');
        self::assertIsString($path);
        $image = imagecreatetruecolor($width, $height);
        self::assertInstanceOf(GdImage::class, $image);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 20, 60, 120));
        $saved = match ($extension) {
            'jpg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path, 90),
        };
        imagedestroy($image);
        self::assertTrue($saved);
        return $path;
    }

    private function temporaryFile(string $body): string
    {
        $path = tempnam(sys_get_temp_dir(), 'erp-av-test-');
        self::assertIsString($path);
        file_put_contents($path, $body);
        return $path;
    }

    private function assertDomainError(string $expected, callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected DomainException code ' . $expected);
        } catch (DomainException $e) {
            self::assertSame($expected, $e->getErrorCode());
        }
    }
}
