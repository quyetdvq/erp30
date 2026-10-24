<?php

declare(strict_types=1);

namespace App\Shared\Media;

use RuntimeException;

/**
 * Non-destructive migration from the former writable/uploads/YYYY layout.
 * No SQL is consulted or changed. Only validated Product/Avatar images pass.
 * Source files are NEVER deleted. Conflicting destination bytes are refused.
 */
final class PublicImageMigrator
{
    public function __construct(private readonly string $source, private readonly string $destination)
    {
    }

    /** @return array{eligible:int,copied:int,existing:int,invalid:int,conflicts:int,skipped:int} */
    public function run(bool $apply = false): array
    {
        $counts = ['eligible'=>0,'copied'=>0,'existing'=>0,'invalid'=>0,'conflicts'=>0,'skipped'=>0];
        if (! is_dir($this->source)) {
            return $counts;
        }
        if (is_link($this->source) || is_link($this->destination)) {
            throw new RuntimeException('MEDIA_MIGRATION_SYMLINK_ROOT');
        }
        foreach (scandir($this->source) ?: [] as $year) {
            if ($year === '.' || $year === '..') continue;
            $sourceYear = $this->source . DIRECTORY_SEPARATOR . $year;
            if (is_link($sourceYear)) { $counts['invalid']++; continue; }
            if (! preg_match('/\A20[0-9]{2}\z/D', $year) || ! is_dir($sourceYear)) {
                $counts['skipped']++;
                continue;
            }
            foreach (scandir($sourceYear) ?: [] as $file) {
                if ($file === '.' || $file === '..') continue;
                $from = $sourceYear . DIRECTORY_SEPARATOR . $file;
                if (is_link($from) || ! is_file($from)
                    || ! in_array($year, [substr($file, 0, 4)], true)
                    || (! self::valid($file, 'product') && ! self::valid($file, 'avatar'))
                    || ! self::actualImage($from, $file)) {
                    $counts['invalid']++;
                    continue;
                }
                $counts['eligible']++;
                $targetYear = $this->destination . DIRECTORY_SEPARATOR . $year;
                $to = $targetYear . DIRECTORY_SEPARATOR . $file;
                if (is_link($targetYear) || is_link($to)) {
                    $counts['conflicts']++;
                    continue;
                }
                if (is_file($to)) {
                    if (hash_file('sha256', $from) === hash_file('sha256', $to)) {
                        $counts['existing']++;
                    } else {
                        $counts['conflicts']++;
                    }
                    continue;
                }
                if ($apply) {
                    if (! is_dir($targetYear) && ! @mkdir($targetYear, 0755, true) && ! is_dir($targetYear)) {
                        throw new RuntimeException('MEDIA_MIGRATION_CREATE_DIRECTORY_FAILED');
                    }
                    if (is_link($targetYear)) throw new RuntimeException('MEDIA_MIGRATION_SYMLINK_DIRECTORY');
                    $input = @fopen($from, 'rb');
                    $output = @fopen($to, 'xb');
                    if (! is_resource($input) || ! is_resource($output)) {
                        if (is_resource($input)) fclose($input);
                        if (is_resource($output)) fclose($output);
                        throw new RuntimeException('MEDIA_MIGRATION_COPY_OPEN_FAILED');
                    }
                    try {
                        $size = filesize($from);
                        $written = stream_copy_to_stream($input, $output);
                    } finally {
                        fclose($input);
                        fclose($output);
                    }
                    if ($size === false || $written !== $size
                        || hash_file('sha256', $from) !== hash_file('sha256', $to)) {
                        @unlink($to);
                        throw new RuntimeException('MEDIA_MIGRATION_COPY_VERIFY_FAILED');
                    }
                    @chmod($to, 0644);
                    $counts['copied']++;
                }
            }
        }
        return $counts;
    }

    public static function valid(string $filename, string $type): bool
    {
        return ImageUrl::for($filename, $type) !== null;
    }

    private static function actualImage(string $source, string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $expected = match ($extension) {
            'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', default => null,
        };
        if ($expected === null) return false;
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        $info = @getimagesize($source);
        return $mime === $expected && is_array($info) && ($info['mime'] ?? null) === $expected;
    }
}
