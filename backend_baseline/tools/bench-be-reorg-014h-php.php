<?php

declare(strict_types=1);

/**
 * BE-REORG-014H: synthetic PHP-only COST PROXY for ProductGroup/UOM list.
 *
 * Executes the actual canonical query services + CommonListQueryAdapter and
 * AuthorizedArrayListSource with fake repositories + PBAC. Nothing is loaded
 * from MariaDB/Shield and NO HTTP, SQL, authentication or mutation occurs.
 * The wall time is NOT an API p95 or a reliable indicator of SQL performance.
 *
 * php backend/tools/bench-be-reorg-014h-php.php --sizes=100,1000,10000 --samples=20 --warmup=3
 * php backend/tools/bench-be-reorg-014h-php.php --self-test
 */

namespace CodeIgniter\HTTP\Exceptions {
    final class BadRequestException extends \RuntimeException {}
}

namespace App\Modules\Product\ProductGroup {
    final class ProductGroupService
    {
        /** @param list<array<string,mixed>> $rows */
        public function __construct(private readonly array $rows) {}
        /** @return list<array<string,mixed>> */
        public function listAll(): array { return $this->rows; }
    }
}

namespace App\Modules\Product\Uom {
    final class ProductUomMasterService
    {
        /** @param list<array<string,mixed>> $rows */
        public function __construct(private readonly array $rows) {}
        /** @return list<array<string,mixed>> */
        public function listAll(): array { return $this->rows; }
    }
}

namespace {
    use App\Modules\Product\ProductGroup\Application\Ports\ProductGroupListAuthorizationPort;
    use App\Modules\Product\ProductGroup\Application\ProductGroupListQueryService;
    use App\Modules\Product\ProductGroup\ProductGroupService;
    use App\Modules\Product\Uom\Application\Ports\ProductUomListAuthorizationPort;
    use App\Modules\Product\Uom\Application\ProductUomListQueryService;
    use App\Modules\Product\Uom\ProductUomMasterService;

    $hasMbString = function_exists('mb_strtolower');
    if (! $hasMbString) {
        // Only ASCII benchmark data is used; this fallback is NOT Unicode parity.
        function mb_strtolower(string $value): string { return strtolower($value); }
    }
    $app = dirname(__DIR__) . '/app/';
    spl_autoload_register(static function (string $class) use ($app): void {
        if (! str_starts_with($class, 'App\\')) { return; }
        $path = $app . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) { require $path; }
    });

    final class Be014hGroupAccess implements ProductGroupListAuthorizationPort
    {
        public int $baseCalls = 0;
        public int $rowCalls = 0;
        public function __construct(private readonly bool $state) {}
        public function requiresRowStateAuthorization(): bool { return $this->state; }
        public function authorizeBaseView(): void { $this->baseCalls++; }
        public function rowVisible(array $row): bool
        {
            $this->rowCalls++;
            return (int) $row['id'] % 10 === 0;
        }
    }
    final class Be014hUomAccess implements ProductUomListAuthorizationPort
    {
        public int $baseCalls = 0;
        public int $rowCalls = 0;
        public function __construct(private readonly bool $state) {}
        public function requiresRowStateAuthorization(): bool { return $this->state; }
        public function authorizeBaseView(): void { $this->baseCalls++; }
        public function rowVisible(array $row): bool
        {
            $this->rowCalls++;
            return (int) $row['id'] % 10 === 0;
        }
    }

    /** @return list<array<string,mixed>> */
    function be014hRows(string $kind, int $count): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'id' => $i,
                'code' => sprintf('%s%07d', $kind === 'group' ? 'G' : 'U', $i),
                'name' => sprintf('%s %07d', $kind === 'group' ? 'Group' : 'UOM', $i),
                'status' => $i % 4 === 0 ? 'INACTIVE' : 'ACTIVE',
                'description' => 'Synthetic fixture; no real identities',
            ] + ($kind === 'group' ? ['parent_id' => $i > 4 ? intdiv($i, 4) : null] : []);
        }
        return $rows;
    }

    /** @param list<float> $times */
    function be014hPercentile(array $times, float $p): float
    {
        sort($times, SORT_NUMERIC);
        return round($times[max(0, (int) ceil(count($times) * $p) - 1)], 3);
    }

    /** @return array{sizes:list<int>,samples:int,warmup:int,self_test:bool,output:?string} */
    function be014hOptions(array $args): array
    {
        $options = ['sizes' => [100, 1000, 10000], 'samples' => 20, 'warmup' => 3, 'self_test' => false, 'output' => null];
        foreach (array_slice($args, 1) as $arg) {
            if ($arg === '--self-test') {
                $options['self_test'] = true;
                continue;
            }
            if (str_starts_with($arg, '--sizes=')) {
                $values = substr($arg, strlen('--sizes='));
                if (! preg_match('/\A[1-9][0-9]*(?:,[1-9][0-9]*)*\z/', $values)) { throw new \InvalidArgumentException('Invalid --sizes'); }
                $options['sizes'] = array_map('intval', explode(',', $values));
                continue;
            }
            if (preg_match('/\A--(samples|warmup)=([0-9]+)\z/', $arg, $m)) {
                $options[$m[1]] = (int) $m[2];
                continue;
            }
            if (str_starts_with($arg, '--output=')) {
                $options['output'] = substr($arg, strlen('--output='));
                continue;
            }
            throw new \InvalidArgumentException('Unknown option: ' . $arg);
        }
        if ($options['self_test']) { $options['sizes'] = [20]; $options['samples'] = 5; $options['warmup'] = 0; }
        if (count($options['sizes']) > 6 || min($options['sizes']) < 10 || max($options['sizes']) > 50000) {
            throw new \InvalidArgumentException('sizes must contain 1..6 values within 10..50000');
        }
        if ($options['samples'] < 5 || $options['samples'] > 200 || $options['warmup'] < 0 || $options['warmup'] > 20) {
            throw new \InvalidArgumentException('samples 5..200; warmup 0..20');
        }
        if ($options['output'] !== null && ($options['output'] === '' || ! is_dir(dirname($options['output'])))) {
            throw new \InvalidArgumentException('Output directory does not exist');
        }
        return $options;
    }

    /** @param list<array<string,mixed>> $rows
     *  @return array<string,mixed>
     */
    function be014hMeasure(string $kind, array $rows, bool $state, int $warmup, int $samples): array
    {
        $count = count($rows);
        $query = ['page' => '1', 'page_size' => '20', 'sort' => '-code', 'filter' => ['status' => 'ACTIVE']];
        $expected = 0;
        foreach ($rows as $row) {
            if ($row['status'] === 'ACTIVE' && (! $state || (int) $row['id'] % 10 === 0)) { $expected++; }
        }
        $times = [];
        for ($iteration = -$warmup; $iteration < $samples; $iteration++) {
            $auth = $kind === 'group' ? new Be014hGroupAccess($state) : new Be014hUomAccess($state);
            $service = $kind === 'group'
                ? new ProductGroupListQueryService(new ProductGroupService($rows), $auth)
                : new ProductUomListQueryService(new ProductUomMasterService($rows), $auth);
            $before = hrtime(true);
            $result = $service->list($query);
            $ms = (hrtime(true) - $before) / 1000000;
            if ($result['total'] !== (string) $expected || count($result['items']) !== min($expected, 20)) {
                throw new \RuntimeException('Authorized count/page mismatch in ' . $kind . ' ' . ($state ? 'state' : 'base'));
            }
            if ($state && ($auth->rowCalls !== $count || $auth->baseCalls !== 0)) {
                throw new \RuntimeException('State PBAC did not evaluate every persisted row');
            }
            if (! $state && ($auth->rowCalls !== 0 || $auth->baseCalls !== 1)) {
                throw new \RuntimeException('Base PBAC call count changed');
            }
            if ($iteration >= 0) { $times[] = $ms; }
        }
        return [
            'surface' => $kind === 'group' ? 'GET /api/v1/product-groups' : 'GET /api/v1/product-uoms',
            'mode' => $state ? 'FAKE_STATE_ALLOW_ID_MOD_10' : 'FAKE_BASE_ALLOW',
            'dataset_rows' => $count,
            'authorized_and_filtered_total' => $expected,
            'pbac_row_decisions_per_request' => $state ? $count : 0,
            'sample_count' => $samples,
            'elapsed_ms_p50' => be014hPercentile($times, .50),
            'elapsed_ms_p95' => be014hPercentile($times, .95),
            'elapsed_ms_min' => round(min($times), 3),
            'elapsed_ms_max' => round(max($times), 3),
            'process_peak_memory_bytes_upper_bound' => memory_get_peak_usage(true),
        ];
    }

    try {
        $options = be014hOptions($argv);
        $rowsOut = [];
        foreach ($options['sizes'] as $size) {
            foreach (['group', 'uom'] as $kind) {
                $rows = be014hRows($kind, $size);
                foreach ([false, true] as $state) {
                    $rowsOut[] = be014hMeasure($kind, $rows, $state, $options['warmup'], $options['samples']);
                }
                unset($rows);
            }
        }
        $result = [
            'change_id' => 'BE-REORG-014H',
            'measurement_type' => 'SYNTHETIC_PHP_COMPONENT_ONLY_NOT_HTTP_OR_DB',
            'gates' => ['synthetic_php' => 'PASS', 'authenticated_http' => 'NOT_RUN', 'mariadb_shield' => 'NOT_RUN', 'sql_explain' => 'NOT_RUN'],
            'runtime' => ['php' => PHP_VERSION, 'mbstring' => $hasMbString, 'kind' => PHP_OS_FAMILY],
            'method' => 'Canonical Group/UOM QueryService + real CommonListQueryAdapter/AuthorizedArrayListSource; fake master/authorization; ASCII fixture; status filter; descending code sort; page 20; sequential single process',
            'limitations' => 'Excludes DB I/O, real PBAC/Shield, SQL queries, HTTP, network, concurrency, effective time; process peak memory is a high-water bound, not per-request attribution.',
            'cases' => $rowsOut,
        ];
        $encoded = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if ($options['output'] !== null && file_put_contents($options['output'], $encoded) === false) {
            throw new \RuntimeException('Unable to write benchmark result');
        }
        echo $encoded;
    } catch (\Throwable $exception) {
        fwrite(STDERR, 'BE-REORG-014H PHP BENCHMARK FAIL: ' . $exception->getMessage() . "\n");
        exit(1);
    }
}
