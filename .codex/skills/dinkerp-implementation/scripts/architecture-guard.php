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

/** @return iterable<SplFileInfo> */
function phpFiles(string $rootPath): iterable
{
    if (!is_dir($rootPath)) {
        return [];
    }
    return new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath));
}

function hasRepositoryImport(string $contents): bool
{
    return preg_match('~^use\s+App\\\\[^;\r\n]*Repository\s*;~m', $contents) === 1;
}

$scanRoots = [
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services',
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Authorization',
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Product',
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Pricing',
];

foreach ($scanRoots as $scanRoot) {
    foreach (phpFiles($scanRoot) as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
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
        foreach (['/->table\s*\(/', '/->query\s*\(/'] as $pattern) {
            if (preg_match($pattern, $contents, $match, PREG_OFFSET_CAPTURE) === 1) {
                $line = substr_count(substr($contents, 0, (int) $match[0][1]), "\n") + 1;
                $violations[] = "{$relative}:{$line}: direct database query in service/resolver";
            }
        }
    }
}

$flatProductPatterns = [
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'Product*.php',
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Repositories' . DIRECTORY_SEPARATOR . 'Product*.php',
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'Price*.php',
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Repositories' . DIRECTORY_SEPARATOR . 'Price*.php',
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'Pricing*.php',
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Repositories' . DIRECTORY_SEPARATOR . 'Pricing*.php',
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'Combo*.php',
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Repositories' . DIRECTORY_SEPARATOR . 'Combo*.php',
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'Uom*.php',
    $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Repositories' . DIRECTORY_SEPARATOR . 'Uom*.php',
];
foreach ($flatProductPatterns as $pattern) {
    foreach (glob($pattern) ?: [] as $path) {
        $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
        $violations[] = "{$relative}: Product/Pricing class remains in flat legacy namespace";
    }
}

$moduleProductRoot = $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Product';
foreach (phpFiles($moduleProductRoot) as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
    $contents = (string) file_get_contents($file->getPathname());
    if (preg_match('/use\s+App\\\\Modules\\\\Pricing\\\\/m', $contents) === 1) {
        $violations[] = "{$relative}: Product module imports Pricing module";
    }
}

$modulePricingRoot = $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Pricing';
foreach (phpFiles($modulePricingRoot) as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
    $contents = (string) file_get_contents($file->getPathname());
    if (preg_match('/use\s+App\\\\Modules\\\\Product\\\\[^;]*Repository/m', $contents) === 1) {
        $violations[] = "{$relative}: Pricing module imports a concrete Product repository";
    }
}

$sharedImportRoot = $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Infrastructure' . DIRECTORY_SEPARATOR . 'Import';
foreach (phpFiles($sharedImportRoot) as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
    $contents = (string) file_get_contents($file->getPathname());
    if (preg_match('/use\s+App\\\\Modules\\\\/m', $contents) === 1) {
        $violations[] = "{$relative}: shared Import infrastructure imports a business module";
    }
}

$forbiddenTopLevelImportModule = $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Import';
if (is_dir($forbiddenTopLevelImportModule)) {
    $violations[] = 'backend/app/Modules/Import: forbidden top-level Import business module';
}

$backendAppRoot = $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'app';
foreach (phpFiles($backendAppRoot) as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
    $normalized = str_replace('\\', '/', $relative);
    $isController = str_contains($normalized, '/Controllers/') || str_ends_with($normalized, 'Controller.php');
    if (!$isController) {
        continue;
    }
    $contents = (string) file_get_contents($file->getPathname());
    if (hasRepositoryImport($contents)) {
        $violations[] = "{$relative}: controller imports a repository directly";
    }
}

$publicAdapterRoots = ['Commands', 'Filters', 'Adapters'];
foreach ($publicAdapterRoots as $adapterRoot) {
    $rootPath = $backendAppRoot . DIRECTORY_SEPARATOR . $adapterRoot;
    foreach (phpFiles($rootPath) as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $contents = (string) file_get_contents($file->getPathname());
        if (hasRepositoryImport($contents)) {
            $violations[] = "{$relative}: public adapter imports a repository directly";
        }
    }
}

$serviceRoot = $backendAppRoot . DIRECTORY_SEPARATOR . 'Services';
$localeKeys = ['vi' => [], 'en' => []];
foreach (glob($serviceRoot . DIRECTORY_SEPARATOR . '*.php') ?: [] as $servicePath) {
    $contents = (string) file_get_contents($servicePath);
    if (preg_match('/final class\s+(\w+)/', $contents, $classMatch) !== 1) {
        continue;
    }
    $language = preg_replace('/Service$/', '', $classMatch[1]) ?: $classMatch[1];
    foreach (['vi', 'en'] as $locale) {
        $languagePath = $backendAppRoot . DIRECTORY_SEPARATOR . 'Language' . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $language . '.php';
        $keys = [];
        if (is_file($languagePath)) {
            preg_match_all("/'([A-Z][A-Z0-9_]+)'\\s*=>/", (string) file_get_contents($languagePath), $matches);
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
