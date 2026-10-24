<?php

declare(strict_types=1);

require_once __DIR__.'/../m62b3/M62B3Fixture.php';
require_once dirname(__DIR__, 2).'/app/Shared/Media/ImageUrl.php';
require_once dirname(__DIR__, 2).'/app/Shared/Media/UploadPaths.php';

use App\Shared\Media\ImageUrl;
use App\Shared\Media\UploadPaths;

/** Reviewed M6.3A.1 DEV media fixture and static-file rules. No SQL writes. */
final class M63A1MediaPlan
{
    public static function marker(string $marker): string { return M62B3Fixture::marker($marker); }
    public static function id(string $id): string { return M62B3Fixture::id($id); }

    /** @param array<string,mixed> $response @return array<string,mixed> */
    public static function fixture(array $response, string $marker, string $id, bool $requireEmpty): array
    {
        $dto = M62B3Fixture::readDto($response, $marker, $id);
        if (($dto['role'] ?? null) !== 'SIMPLE' || ($dto['status'] ?? null) !== 'ACTIVE'
            || ($dto['allowed_actions']['edit'] ?? false) !== true) {
            throw new RuntimeException('Fixture is not an ACTIVE editable SIMPLE Product.');
        }
        if ($requireEmpty && (($dto['image_available'] ?? null) !== false || ($dto['image_url'] ?? null) !== null)) {
            throw new RuntimeException('DEV fixture already has an image; refusing destructive media QA.');
        }
        return $dto;
    }

    public static function filename(string $url): string
    {
        if (preg_match('~\A/uploads/(20[0-9]{2})/([0-9]{8}product[0-9]{7}\.(?:jpg|png|webp))\z~D', $url, $m) !== 1
            || substr($m[2], 0, 4) !== $m[1]
            || ImageUrl::for($m[2], 'product') !== $url) {
            throw new RuntimeException('Product media URL violates public-upload rules.');
        }
        return $m[2];
    }

    public static function physicalPath(string $url): string
    {
        $name = self::filename($url);
        $path = UploadPaths::imagePath($name, 'product');
        if ($path === null) throw new RuntimeException('Invalid public-upload filename.');
        $base = UploadPaths::root();
        if (is_link($base) || is_link(dirname($path)) || is_link($path)) {
            throw new RuntimeException('Refusing symlinked media path.');
        }
        return $path;
    }

    public static function codecsReady(): bool
    {
        return extension_loaded('gd') && extension_loaded('fileinfo')
            && function_exists('imagecreatefromstring') && function_exists('imagepng')
            && function_exists('imagewebp') && function_exists('imagetypes')
            && (imagetypes() & (IMG_JPG | IMG_PNG | IMG_WEBP)) === (IMG_JPG | IMG_PNG | IMG_WEBP);
    }

    /** @return array{bytes:string,mime:string} */
    public static function sample(int $variant): array
    {
        if (!in_array($variant, [1, 2], true)) throw new InvalidArgumentException('Unknown image variant.');
        if (!extension_loaded('gd') || !function_exists('imagepng') || !function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('GD required for DEV media upload.');
        }
        $image = imagecreatetruecolor(8, 8);
        if (!($image instanceof GdImage)) throw new RuntimeException('Cannot prepare PNG sample.');
        try {
            $color = $variant === 1 ? imagecolorallocate($image, 16, 125, 218)
                : imagecolorallocate($image, 216, 125, 32);
            imagefill($image, 0, 0, $color);
            ob_start();
            $encoded = imagepng($image);
            $bytes = ob_get_clean();
            if (!$encoded || !is_string($bytes) || strlen($bytes) < 30) {
                throw new RuntimeException('Cannot encode media QA sample.');
            }
            return ['bytes'=>$bytes, 'mime'=>'image/png'];
        } finally {
            imagedestroy($image);
        }
    }

    /** @param array<string,mixed> $response */
    public static function imageUrl(array $response, string $id): string
    {
        $dto = $response['json']['data'] ?? null;
        if (($response['status'] ?? 0) !== 200 || !is_array($dto)
            || (string)($dto['id'] ?? '') !== $id
            || ($dto['image_available'] ?? null) !== true
            || !is_string($dto['image_url'] ?? null)) {
            throw new RuntimeException('Product image POST response missing public URL.');
        }
        $url = $dto['image_url'];
        self::filename($url);
        return $url;
    }

    /** @param array<string,mixed> $response */
    public static function emptyAfterDelete(array $response, string $id): void
    {
        $dto = $response['json']['data'] ?? null;
        if (($response['status'] ?? 0) !== 200 || !is_array($dto)
            || (string)($dto['id'] ?? '') !== $id
            || ($dto['image_available'] ?? null) !== false
            || ($dto['image_url'] ?? null) !== null) {
            throw new RuntimeException('Product image DELETE response did not clear metadata.');
        }
    }

    /** @param array<string,mixed> $response */
    public static function negative(array $response, string $expectedCode, string $locale): string
    {
        $error = $response['json']['error'] ?? null;
        if (($response['status'] ?? 0) !== 422 || !is_array($error)
            || ($error['code'] ?? null) !== $expectedCode
            || ($response['language'] ?? '') !== $locale
            || !is_string($error['message'] ?? null) || $error['message'] === '') {
            throw new RuntimeException('Unexpected media validation error envelope.');
        }
        return $error['message'];
    }
}
