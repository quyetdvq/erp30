<?php

declare(strict_types=1);

/** Content-addressed runtime footprint for cross-run sandbox evidence. */
final class M34DSourceFingerprint
{
    public static function hash(string $root): string
    {
        $app = $root.'/backend/app';
        if (!is_dir($app)) throw new RuntimeException('Missing Backend v2 app tree');
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php' || $file->isLink()) continue;
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($app) + 1));
            $files[$path] = hash_file('sha256', $file->getPathname());
        }
        if (count($files) < 185 || in_array(false,$files,true)) {
            throw new RuntimeException('Runtime PHP files missing or unreadable');
        }
        ksort($files, SORT_STRING);
        $pairs = [];
        foreach ($files as $path=>$sha) $pairs[] = $path.' '.$sha;
        return hash('sha256', implode("\n", $pairs));
    }
}
