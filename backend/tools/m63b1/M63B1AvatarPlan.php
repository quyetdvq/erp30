<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/app/Shared/Media/ImageUrl.php';
require_once dirname(__DIR__, 2).'/app/Shared/Media/UploadPaths.php';

use App\Shared\Media\ImageUrl;
use App\Shared\Media\UploadPaths;

/** Strict DEV-only, self-service avatar QA fixture and output contracts. */
final class M63B1AvatarPlan
{
    public static function userId(string $id): string
    {
        if (preg_match('/\A[1-9][0-9]{0,17}\z/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid Shield User ID.');
        }
        return $id;
    }

    /** @param array<string,mixed> $response @return array<string,mixed> */
    public static function ownEmptyActor(array $response, string $targetId): array
    {
        $actor = $response['json']['data']['user'] ?? null;
        if (($response['status'] ?? null) !== 200 || !is_array($actor)
            || (string)($actor['id'] ?? '') !== self::userId($targetId)
            || ($actor['active'] ?? null) !== true
            || ($actor['has_avatar'] ?? null) !== false
            || ($actor['avatar_url'] ?? null) !== null
            || !is_array($actor['base_permissions'] ?? null)
            || in_array('user.edit', $actor['base_permissions'], true)) {
            throw new RuntimeException('Only an active avatar-empty SELF account without user.edit is safe.');
        }
        return $actor;
    }

    /** @param array<string,mixed> $response @return array<string,mixed> */
    public static function ownAvatar(array $response, string $id, ?string $url): array
    {
        $actor = $response['json']['data']['user'] ?? null;
        if (($response['status'] ?? null) !== 200 || !is_array($actor)
            || (string)($actor['id'] ?? '') !== self::userId($id)
            || ($actor['has_avatar'] ?? null) !== ($url !== null)
            || ($actor['avatar_url'] ?? null) !== $url) {
            throw new RuntimeException('Avatar HTTP read-after-write is inconsistent.');
        }
        return $actor;
    }

    public static function filename(string $url): string
    {
        if (preg_match('~\A/uploads/(20[0-9]{2})/([0-9]{8}avatar[0-9]{7}\.(?:png|jpg|webp))\z~D', $url, $m) !== 1
            || substr($m[2], 0, 4) !== $m[1]
            || ImageUrl::for($m[2], 'avatar') !== $url) {
            throw new RuntimeException('Unsafe avatar static media URL.');
        }
        return $m[2];
    }

    public static function physicalPath(string $url): string
    {
        $path = UploadPaths::imagePath(self::filename($url), 'avatar');
        if (!is_string($path) || is_link(UploadPaths::root()) || is_link(dirname($path)) || is_link($path)) {
            throw new RuntimeException('Unsafe avatar physical path.');
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
        if (!in_array($variant, [1, 2], true)) throw new InvalidArgumentException('Unreviewed image variant.');
        if (!self::codecsReady()) throw new RuntimeException('GD codecs unavailable.');
        $image=imagecreatetruecolor(12,12);
        if (!$image instanceof GdImage) throw new RuntimeException('Cannot build avatar sample.');
        try {
            $color=$variant===1 ? imagecolorallocate($image,37,142,202) : imagecolorallocate($image,214,91,48);
            imagefill($image,0,0,$color);
            ob_start();
            $ok=imagepng($image);
            $bytes=ob_get_clean();
            if (!$ok || !is_string($bytes) || strlen($bytes)<32 || strlen($bytes)>2*1024*1024) {
                throw new RuntimeException('Cannot encode safe avatar PNG.');
            }
            return ['bytes'=>$bytes,'mime'=>'image/png'];
        } finally {
            imagedestroy($image);
        }
    }

    /** @param array<string,mixed> $response */
    public static function responseUrl(array $response, string $id): string
    {
        $dto=$response['json']['data']??null;
        if (($response['status']??null)!==200 || !is_array($dto)
            || (string)($dto['user_id']??'')!==self::userId($id)
            || ($dto['has_avatar']??null)!==true
            || !is_string($dto['avatar_url']??null)) {
            throw new RuntimeException('Avatar upload response is invalid.');
        }
        self::filename($dto['avatar_url']);
        return $dto['avatar_url'];
    }

    /** @param array<string,mixed> $response */
    public static function deleted(array $response, string $id): void
    {
        $dto=$response['json']['data']??null;
        if (($response['status']??null)!==200 || !is_array($dto)
            || (string)($dto['user_id']??'')!==self::userId($id)
            || ($dto['has_avatar']??null)!==false || ($dto['avatar_url']??null)!==null) {
            throw new RuntimeException('Avatar DELETE response is invalid.');
        }
    }

    /** @param array<string,mixed> $response */
    public static function negative(array $response, string $code, string $locale): string
    {
        $error=$response['json']['error']??null;
        if (($response['status']??null)!==422 || !is_array($error)
            || ($error['code']??null)!==$code || ($response['language']??null)!==$locale
            || !is_string($error['message']??null) || $error['message']==='') {
            throw new RuntimeException('Unexpected avatar validation response.');
        }
        return $error['message'];
    }

    /** @param array<string,mixed> $result */
    public static function sqlAudit(array $result, string $id, ?string $expected): void
    {
        if (($result['status']??null)!=='PASS' || (string)($result['id']??'')!==self::userId($id)
            || ($result['active']??null)!==true || (!array_key_exists('avatar_filename',$result) || $result['avatar_filename']!==$expected)) {
            throw new RuntimeException('Read-only SQL avatar metadata does not match expected fixture.');
        }
    }
}
