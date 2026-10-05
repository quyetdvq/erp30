<?php

declare(strict_types=1);

/** Static guard for the bounded backend coding contract. */
$root = realpath(__DIR__ . '/../../../..');
if ($root === false) {
    fwrite(STDERR, "ARCHITECTURE_GUARD: FAIL\nrepository root not found\n");
    exit(1);
}

$violations = [];
$checked = [];
$scanRoots = [
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services',
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Authorization',
];

foreach ($scanRoots as $scanRoot) {
    if (!is_dir($scanRoot)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanRoot));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
        if (preg_match('/(?:Service|Resolver)\.php$/', $relative) !== 1) {
            continue;
        }
        $contents = (string) file_get_contents($path);
        $checked[] = $relative;
        foreach ([
            '/\bdate\s*\(/' => 'wall-clock date() call',
            '/\btime\s*\(/' => 'wall-clock time() call',
            '/\bnow\s*\(/' => 'wall-clock now() call',
            '/\blang\s*\(/' => 'locale lookup in domain/application code',
        ] as $pattern => $label) {
            if (preg_match($pattern, $contents, $match, PREG_OFFSET_CAPTURE) === 1) {
                $line = substr_count(substr($contents, 0, (int) $match[0][1]), "\n") + 1;
                $violations[] = "{$relative}:{$line}: {$label}";
            }
        }
        if (str_contains($relative, 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services')) {
            foreach (['/->table\s*\(/', '/->query\s*\(/'] as $pattern) {
                if (preg_match($pattern, $contents, $match, PREG_OFFSET_CAPTURE) === 1) {
                    $line = substr_count(substr($contents, 0, (int) $match[0][1]), "\n") + 1;
                    $violations[] = "{$relative}:{$line}: direct database query in service";
                }
            }
        }
    }
}

$controllerRoot = $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Controllers';
if (is_dir($controllerRoot)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllerRoot));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $contents = (string) file_get_contents($file->getPathname());
        if (preg_match('~^use\s+App\\\\Repositories\\\\~m', $contents) === 1) {
            $violations[] = "{$relative}: controller imports a repository directly";
        }
    }
}

$publicAdapterRoots = ['Commands', 'Filters', 'Adapters', 'Import'];
foreach ($publicAdapterRoots as $adapterRoot) {
    $rootPath = $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $adapterRoot;
    if (!is_dir($rootPath)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $contents = (string) file_get_contents($file->getPathname());
        if (preg_match('~^use\s+App\\\\Repositories\\\\~m', $contents) === 1) {
            $violations[] = "{$relative}: public adapter imports a repository directly";
        }
    }
}

$serviceRoot = $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services';
$localeKeys = ['vi' => [], 'en' => []];
foreach (glob($serviceRoot . DIRECTORY_SEPARATOR . '*.php') ?: [] as $servicePath) {
    $contents = (string) file_get_contents($servicePath);
    if (preg_match('/final class\s+(\w+)/', $contents, $classMatch) !== 1) {
        continue;
    }
    $language = preg_replace('/Service$/', '', $classMatch[1]) ?: $classMatch[1];
    foreach (['vi', 'en'] as $locale) {
        $languagePath = $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Language' . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $language . '.php';
        $keys = [];
        if (is_file($languagePath)) {
            preg_match_all("/'([A-Z][A-Z0-9_]+)'\s*=>/", (string) file_get_contents($languagePath), $matches);
            $keys = $matches[1] ?? [];
            if (count($keys) !== count(array_unique($keys))) {
                $violations[] = "{$languagePath}: duplicate translation keys";
            }
        }
        $localeKeys[$locale] = array_merge($localeKeys[$locale], $keys);
    }
    preg_match_all('/public const ERROR_[A-Z0-9_]+\s*=\s*\'([A-Z0-9_]+)\'/m', $contents, $errorMatches);
    foreach ($errorMatches[1] ?? [] as $key) {
        foreach (['vi', 'en'] as $locale) {
            if (!in_array($key, $localeKeys[$locale], true)) {
                $violations[] = "{$language} {$locale}: missing stable error catalog entry {$key}";
            }
        }
    }
}

if ($violations !== []) {
    fwrite(STDERR, "ARCHITECTURE_GUARD: FAIL\n- " . implode("\n- ", array_unique($violations)) . "\n");
    exit(2);
}

echo "ARCHITECTURE_GUARD: PASS\n";
echo 'application_files_checked: ' . count($checked) . "\n";
echo "baseline_waivers: 0\n";
