<?php

declare(strict_types=1);

/**
 * ARCH-06: positive/negative vendor-free fixtures for the staged architecture
 * scanner. Creates and deletes only temporary test fixtures.
 * Run (repo root): php backend/tools/test-architecture-conformance.php
 */
require_once __DIR__ . '/architecture/ModuleBoundaryScanner.php';

use DinkERP\Tools\Architecture\ModuleBoundaryScanner;

$work = sys_get_temp_dir() . '/dinkerp-arch-fixtures-' . getmypid() . '-' . bin2hex(random_bytes(4));

/** @param array<string,string> $files
 *  @param array<string,mixed> $policy
 *  @param array<string,mixed> $baseline
 *  @return array<string,mixed>
 */
function fixtureScan(string $work, array $files, array $policy, array $baseline = []): array
{
    if (is_dir($work)) {
        throw new RuntimeException('Fixture root is already in use.');
    }
    mkdir($work . '/backend/app/Modules', 0777, true);
    foreach ($files as $file => $source) {
        $target = $work . '/backend/app/' . $file;
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }
        file_put_contents($target, $source);
    }
    return (new ModuleBoundaryScanner($policy, $baseline))->scan($work);
}

function fixtureRemove(string $path): void
{
    if (! is_dir($path)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($path);
}

/** @param array<string,mixed> $result */
function assertFixture(array $result, string $name, bool $valid, ?string $rule = null): void
{
    $actual = $result['new_violations'];
    if (($actual === []) !== $valid) {
        throw new RuntimeException($name . ': unexpected violations ' . json_encode($actual));
    }
    if ($rule !== null && ! in_array($rule, array_column($actual, 'rule'), true)) {
        throw new RuntimeException($name . ': missing ' . $rule . ' in ' . json_encode($actual));
    }
    echo '  ' . $name . ': ' . ($valid ? 'PASS' : 'expected FAIL') . PHP_EOL;
}

$policy = [
    'modules' => ['A', 'B', 'C', 'Product', 'Pricing'],
    'table_owners' => ['products' => 'Product', 'import_jobs' => 'ImportInfrastructure'],
    'public_apis' => [
        ['module' => 'B', 'class' => 'App\\Modules\\B\\Contracts\\PublicRead',
            'methods' => ['get'], 'consumers' => ['A'], 'status' => 'APPROVED'],
        ['module' => 'C', 'class' => 'App\\Modules\\C\\Contracts\\PublicRead',
            'methods' => ['get'], 'consumers' => ['B'], 'status' => 'APPROVED'],
        ['module' => 'A', 'class' => 'App\\Modules\\A\\Contracts\\PublicRead',
            'methods' => ['get'], 'consumers' => ['C'], 'status' => 'APPROVED'],
    ],
];
$clean = ['observed_references' => [], 'legacy_files' => [], 'observed_write_risks' => [], 'observed_cycles' => []];
$cases = [
    [
        'approved public import', true, null,
        ['Modules/A/Worker.php' => '<?php namespace App\\Modules\\A; use App\\Modules\\B\\Contracts\\PublicRead; class Worker {}'],
        $clean,
    ],
    [
        'unapproved internal cross-module repository', false, 'ARCH-01',
        ['Modules/A/Worker.php' => '<?php namespace App\\Modules\\A; use App\\Modules\\B\\Internal\\SecretRepository; class Worker {}'],
        $clean,
    ],
    [
        'forbidden Product to Pricing edge', false, 'ARCH-02',
        ['Modules/Product/Worker.php' => '<?php namespace App\\Modules\\Product; use App\\Modules\\Pricing\\Contracts\\PublicRead; class Worker {}'],
        $clean,
    ],
    [
        'indirect A to B to C to A cycle', false, 'ARCH-02',
        [
            'Modules/A/Worker.php' => '<?php namespace App\\Modules\\A; use App\\Modules\\B\\Contracts\\PublicRead; class Worker {}',
            'Modules/B/Worker.php' => '<?php namespace App\\Modules\\B; use App\\Modules\\C\\Contracts\\PublicRead; class Worker {}',
            'Modules/C/Worker.php' => '<?php namespace App\\Modules\\C; use App\\Modules\\A\\Contracts\\PublicRead; class Worker {}',
        ],
        $clean,
    ],
    [
        'new canonical module to legacy service dependency', false, 'ARCH-04',
        ['Modules/A/Worker.php' => '<?php namespace App\\Modules\\A; use App\\Services\\OldService; class Worker {}'],
        $clean,
    ],
    [
        'exact observed legacy file/class pair unchanged', true, null,
        ['Modules/A/Worker.php' => '<?php namespace App\\Modules\\A; use App\\Services\\OldService; class Worker {}'],
        ['observed_references' => ['backend/app/Modules/A/Worker.php|App\\Services\\OldService']],
    ],
    [
        'same legacy dependency in another path is NOT whitelisted', false, 'ARCH-04',
        ['Modules/A/NewWorker.php' => '<?php namespace App\\Modules\\A; use App\\Services\\OldService; class NewWorker {}'],
        ['observed_references' => ['backend/app/Modules/A/Worker.php|App\\Services\\OldService']],
    ],
    [
        'new generic Infrastructure to business module edge', false, 'ARCH-02',
        ['Infrastructure/Import/Fake.php' => '<?php namespace App\\Infrastructure\\Import; use App\\Modules\\Product\\Product\\ProductRoleService; class Fake {}'],
        $clean,
    ],
    [
        'direct cross-owner mutation from Pricing into Product', false, 'ARCH-03',
        ['Modules/Pricing/Bad.php' => <<<'SOURCE'
<?php namespace App\Modules\Pricing;
final class Bad {
    public function save($db): void { $db->table('products')->where('id', 1)->update(['status' => 'ACTIVE']); }
}
SOURCE
        ],
        $clean,
    ],
    [
        'literal source strings do not count as imports', true, null,
        ['Modules/A/Worker.php' => <<<'SOURCE'
<?php namespace App\Modules\A;
const EXAMPLE = 'use App\Modules\Pricing\Repository;';
SOURCE
        ],
        $clean,
    ],
    [
        'new top-level legacy class', false, 'ARCH-04',
        ['Services/NewWrapper.php' => '<?php namespace App\\Services; class NewWrapper {}'],
        $clean,
    ],
    [
        'technical import owner may mutate technical import_jobs', true, null,
        ['Infrastructure/Import/Fake.php' => <<<'SOURCE'
<?php namespace App\Infrastructure\Import;
final class Fake {
    public function save($db): void { $db->table('import_jobs')->insert(['status' => 'WAITING']); }
}
SOURCE
        ],
        $clean,
    ],
    [
        'wrong consumer cannot reuse approved read class', false, 'ARCH-01',
        ['Modules/C/Worker.php' => '<?php namespace App\\Modules\\C; use App\\Modules\\B\\Contracts\\PublicRead; class Worker {}'],
        $clean,
    ],
    [
        'controller request query helper is NOT SQL access', true, null,
        ['Controllers/Api/Fake.php' => '<?php namespace App\\Controllers\\Api; class Fake { public function index(){ return $this->query(); } }'],
        $clean,
    ],
    [
        'controller direct table read is forbidden', false, 'ARCH-02',
        ['Controllers/Api/Fake.php' => '<?php namespace App\\Controllers\\Api; class Fake { public function index($db){ return $db->table(\'products\')->get(); } }'],
        $clean,
    ],
    [
        'comments cannot manufacture SQL write ownership violation', true, null,
        ['Modules/Pricing/Safe.php' => <<<'SOURCE'
<?php namespace App\Modules\Pricing;
// $db->table('products')->update(['status'=>'ACTIVE']);
final class Safe {}
SOURCE
        ],
        $clean,
    ],
    [
        'direct embedded UPDATE SQL is detected', false, 'ARCH-03',
        ['Modules/Pricing/BadSql.php' => <<<'SOURCE'
<?php namespace App\Modules\Pricing;
final class BadSql { public function update($db){ return $db->query('UPDATE products SET status = 1'); } }
SOURCE
        ],
        $clean,
    ],
    [
        'unregistered new root module is forbidden', false, 'ARCH-02',
        ['Modules/Import/Svc.php' => '<?php namespace App\\Modules\\Import; class Svc {}'],
        $clean,
    ],
    [
        'declared public class without methods does not approve dependency', false, 'ARCH-01',
        ['Modules/A/Worker.php' => '<?php namespace App\\Modules\\A; use App\\Modules\\B\\Contracts\\EmptyRead; class Worker {}'],
        $clean,
    ],
];
$count = 0;
try {
    foreach ($cases as [$name, $valid, $rule, $files, $baseline]) {
        fixtureRemove($work);
        $report = fixtureScan($work, $files, $policy, $baseline);
        assertFixture($report, $name, $valid, $rule);
        $count++;
    }
} finally {
    fixtureRemove($work);
}
echo 'ARCH-06 CONFORMANCE FIXTURES PASS: ' . $count . ' scenarios; no DB/vendor required.' . PHP_EOL;
