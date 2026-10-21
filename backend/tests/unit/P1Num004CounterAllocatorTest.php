<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Lock\DomainLockService;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\AutoNumberingCounterRepository;
use App\Repositories\AutoNumberingSeriesRepository;
use App\Services\AutoNumberingAllocationResult;
use App\Services\AutoNumberingBusinessMutation;
use App\Services\AutoNumberingCounterAllocator;
use App\Services\AutoNumberingIdentifierCollision;
use App\Services\CanonicalNumberingPatternValidator;
use App\Services\NumberingScopePeriodContext;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Num004CounterAllocatorTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function setUp(): void
    {
        \Tests\Support\Standard\StandardRuntimeGuard::assertReady();
        parent::setUp();
    }

    public function testRepositoryIdentityLockCreateUpdateAndTransactionGuard(): void
    {
        [$seriesId, $scopeId] = $this->fixture('repo', 10, 5);
        $repository = new AutoNumberingCounterRepository($this->db);

        $this->assertNull($repository->find($seriesId, $scopeId, 'ALL'));
        try {
            $repository->findForUpdate($seriesId, $scopeId, 'ALL');
            $this->fail('Expected active transaction requirement.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingCounterRepository::ERROR_TRANSACTION_REQUIRED, $exception->getErrorCode());
        }

        $this->db->transBegin();
        $counterId = $repository->insert($seriesId, $scopeId, 'ALL', 10, $this->operation());
        $locked = $repository->findForUpdate($seriesId, $scopeId, 'ALL');
        $this->assertSame($counterId, (int) ($locked['id'] ?? 0));
        $repository->updateLastValue($counterId, 15, $this->operation());
        $this->db->transCommit();
        $this->assertSame(15, (int) ($repository->find($seriesId, $scopeId, 'ALL')['last_value'] ?? 0));

        try {
            $repository->insert($seriesId, $scopeId, 'ALL', 20, $this->operation());
            $this->fail('Expected duplicate counter identity.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingCounterRepository::ERROR_DUPLICATE, $exception->getErrorCode());
        }
    }

    public function testRepositoryForeignKeyFailureAndAllocatorLockFailureAreTyped(): void
    {
        [, $scopeId] = $this->fixture('fk', 1, 1);
        try {
            (new AutoNumberingCounterRepository($this->db))->insert(999999999, $scopeId, 'ALL', 1, $this->operation());
            $this->fail('Expected counter foreign-key failure.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingCounterRepository::ERROR_FOREIGN_KEY, $exception->getErrorCode());
        }

        [$seriesId, $lockScope] = $this->fixture('lock-failure', 1, 1);
        try {
            (new AutoNumberingCounterAllocator(db: $this->db, domainLock: new Num004FailingLock()))->allocate(
                $this->series($seriesId, 1, 1),
                $this->context($lockScope),
                $this->pattern(),
                new Num004FakeBusiness(),
                $this->operation(),
            );
            $this->fail('Expected typed lock failure.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingCounterAllocator::ERROR_LOCK_FAILED, $exception->getErrorCode());
        }
        $this->assertSame(0, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->countAllResults());
    }

    public function testFirstAndExistingAllocationsUseCanonicalNextValues(): void
    {
        [$seriesId, $scopeId] = $this->fixture('first', 100, 5);
        $allocator = new AutoNumberingCounterAllocator(db: $this->db);
        $business = new Num004FakeBusiness();
        $first = $allocator->allocate($this->series($seriesId, 100, 5), $this->context($scopeId), $this->pattern(), $business, $this->operation());
        $second = $allocator->allocate($this->series($seriesId, 100, 5), $this->context($scopeId), $this->pattern(), $business, $this->operation());

        $this->assertSame(100, $first->sequenceValue);
        $this->assertSame(105, $second->sequenceValue);
        $this->assertSame('0100', $first->identifier);
        $this->assertSame('0105', $second->identifier);
        $this->assertSame(105, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->get()->getRowArray()['last_value']);
    }

    public function testRenderingUsesParsedMetadataAndRuntimeContextWithoutReparsing(): void
    {
        $allocator = new AutoNumberingCounterAllocator(db: $this->db);
        $pattern = (new CanonicalNumberingPatternValidator())->parse('INV-{YYYY}-{YY}-{MM}-{DD}-{COMPANY_CODE}-{ORG_CODE}-{RESOURCE}-{SEQ:4}');
        $context = new NumberingScopePeriodContext('ORGANIZATION', 12, '20260102', null, '2026-01-02', 7, 'COMPANY', 'ORG', 'product');

        $this->assertSame('INV-2026-26-01-02-COMPANY-ORG-product-0012', $allocator->renderCandidate($pattern, $context, 12));
        $this->assertSame('INV-2026-26-01-02-COMPANY-ORG-product-10000', $allocator->renderCandidate($pattern, $context, 10000));

        $missing = new NumberingScopePeriodContext('ORGANIZATION', 12, 'ALL', null, null, null, null, 'ORG', null);
        try {
            $allocator->renderCandidate($pattern, $missing, 1);
            $this->fail('Expected runtime context rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingCounterAllocator::ERROR_RUNTIME_CONTEXT_REQUIRED, $exception->getErrorCode());
        }
    }

    public function testOccupiedAndFinalUniqueCandidatesAdvanceOnlyActualIssuedSequence(): void
    {
        [$seriesId, $scopeId] = $this->fixture('occupied', 100, 5);
        $business = new Num004FakeBusiness(['0100', '0105'], ['0110']);
        $result = (new AutoNumberingCounterAllocator(db: $this->db))->allocate($this->series($seriesId, 100, 5), $this->context($scopeId), $this->pattern(), $business, $this->operation());

        $this->assertSame(115, $result->sequenceValue);
        $this->assertSame(['0115'], $business->persisted);
        $this->assertSame(115, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->get()->getRowArray()['last_value']);
    }

    public function testUnrelatedBusinessFailureRollsBackFirstAndExistingCountersAndAllowsReuse(): void
    {
        [$firstSeries, $firstScope] = $this->fixture('rollback-first', 100, 5);
        $allocator = new AutoNumberingCounterAllocator(db: $this->db);
        try {
            $allocator->allocate($this->series($firstSeries, 100, 5), $this->context($firstScope), $this->pattern(), new Num004FakeBusiness([], [], new RuntimeException('business failed')), $this->operation());
            $this->fail('Expected transaction failure.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingCounterAllocator::ERROR_TRANSACTION_FAILED, $exception->getErrorCode());
        }
        $this->assertSame(0, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $firstSeries)->countAllResults());
        $retry = $allocator->allocate($this->series($firstSeries, 100, 5), $this->context($firstScope), $this->pattern(), new Num004FakeBusiness(), $this->operation());
        $this->assertSame(100, $retry->sequenceValue);

        [$existingSeries, $existingScope] = $this->fixture('rollback-existing', 100, 5);
        $this->db->table('auto_numbering_counters')->insert([
            'numbering_series_id' => $existingSeries, 'scope_organization_id' => $existingScope,
            'period_key' => 'ALL', 'last_value' => 100, 'created_at' => '2026-10-06 04:05:06',
        ]);
        try {
            $allocator->allocate($this->series($existingSeries, 100, 5), $this->context($existingScope), $this->pattern(), new Num004FakeBusiness([], [], new RuntimeException('business failed')), $this->operation());
            $this->fail('Expected existing-counter transaction failure.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingCounterAllocator::ERROR_TRANSACTION_FAILED, $exception->getErrorCode());
        }
        $this->assertSame(100, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $existingSeries)->get()->getRowArray()['last_value']);
        $this->assertSame(105, $allocator->allocate($this->series($existingSeries, 100, 5), $this->context($existingScope), $this->pattern(), new Num004FakeBusiness(), $this->operation())->sequenceValue);
    }

    public function testCommittedSequenceNeverDecrementsOrReuses(): void
    {
        [$seriesId, $scopeId] = $this->fixture('committed', 1, 1);
        $allocator = new AutoNumberingCounterAllocator(db: $this->db);
        $business = new Num004FakeBusiness();
        $this->assertSame(1, $allocator->allocate($this->series($seriesId, 1, 1), $this->context($scopeId), $this->pattern(), $business, $this->operation())->sequenceValue);
        $this->assertSame(2, $allocator->allocate($this->series($seriesId, 1, 1), $this->context($scopeId), $this->pattern(), $business, $this->operation())->sequenceValue);
        $this->assertSame(2, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->get()->getRowArray()['last_value']);
    }

    public function testScopeAndPeriodIdentityPartitionsDoNotBleed(): void
    {
        [$seriesId, $scopeA] = $this->fixture('isolation-a', 1, 1);
        $this->db->table('organizations')->insert(['type' => 'GROUP', 'code' => 'NUM004-isolation-b-' . uniqid(), 'name' => 'NUM004', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $scopeB = (int) $this->db->insertID();
        $allocator = new AutoNumberingCounterAllocator(db: $this->db);
        $business = new Num004FakeBusiness();

        foreach ([$this->context($scopeA, 'ALL'), $this->context($scopeB, 'ALL'), $this->context($scopeA, '2026'), $this->context($scopeA, '202601')] as $context) {
            $this->assertSame(1, $allocator->allocate($this->series($seriesId, 1, 1), $context, $this->pattern(), $business, $this->operation())->sequenceValue);
        }
        $this->assertSame(4, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->countAllResults());
    }

    public function testRealExistingCounterRaceUsesForUpdateAndDistinctSequences(): void
    {
        [$seriesId, $scopeId] = $this->fixture('race-existing', 100, 5);
        $this->db->table('auto_numbering_counters')->insert([
            'numbering_series_id' => $seriesId, 'scope_organization_id' => $scopeId,
            'period_key' => 'ALL', 'last_value' => 100, 'created_at' => '2026-10-06 04:05:06',
        ]);
        $results = $this->runWorkers($seriesId, $scopeId, 100, 5);
        $this->assertCount(2, array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true), json_encode($results, JSON_THROW_ON_ERROR));
        $sequences = array_map(static fn (array $result): int => (int) $result['sequence'], $results);
        sort($sequences);
        $this->assertSame([105, 110], $sequences);
        $this->assertSame(110, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->get()->getRowArray()['last_value']);
    }

    public function testRealFirstCounterRaceUsesAdvisoryLockAndOneIdentity(): void
    {
        [$seriesId, $scopeId] = $this->fixture('race-first', 100, 5);
        $results = $this->runWorkers($seriesId, $scopeId, 100, 5);
        $this->assertCount(2, array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true), json_encode($results, JSON_THROW_ON_ERROR));
        $sequences = array_map(static fn (array $result): int => (int) $result['sequence'], $results);
        sort($sequences);
        $this->assertSame([100, 105], $sequences);
        $this->assertSame(1, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->countAllResults());
        $this->assertSame(105, (int) $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->get()->getRowArray()['last_value']);
    }

    /** @return list<array<string, mixed>> */
    private function runWorkers(int $seriesId, int $scopeId, int $start, int $increment): array
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dinkerp-num004-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $worker = realpath(__DIR__ . '/../fixtures/auto_numbering_counter_concurrency_worker.php');
        $this->assertNotFalse($worker);
        $startPath = $directory . DIRECTORY_SEPARATOR . 'start';
        $processes = [];
        $resultPaths = [];
        try {
            foreach (['a', 'b'] as $name) {
                $readyPath = $directory . DIRECTORY_SEPARATOR . 'ready-' . $name;
                $resultPath = $directory . DIRECTORY_SEPARATOR . 'result-' . $name . '.json';
                $resultPaths[] = $resultPath;
                $command = implode(' ', array_map('escapeshellarg', [PHP_BINARY, $worker, (string) $seriesId, (string) $scopeId, 'ALL', $readyPath, $startPath, $resultPath, (string) $start, (string) $increment]));
                $processes[$name] = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($processes[$name]);
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
            }
            $deadline = microtime(true) + 15.0;
            while ((! is_file($directory . DIRECTORY_SEPARATOR . 'ready-a') || ! is_file($directory . DIRECTORY_SEPARATOR . 'ready-b')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($directory . DIRECTORY_SEPARATOR . 'ready-a');
            $this->assertFileExists($directory . DIRECTORY_SEPARATOR . 'ready-b');
            file_put_contents($startPath, 'go');
            foreach ($processes as $process) {
                $this->assertSame(0, proc_close($process));
            }
            return array_map(static function (string $path): array {
                $contents = file_get_contents($path);
                if ($contents === false) {
                    throw new RuntimeException('Concurrency worker did not write a result.');
                }
                return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            }, $resultPaths);
        } finally {
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    @proc_terminate($process);
                    @proc_close($process);
                }
            }
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($directory);
        }
    }

    /** @return array{int, int} */
    private function fixture(string $code, int $start, int $increment): array
    {
        $this->db->table('resources')->insert(['type' => 'num004-' . $code . '-' . uniqid(), 'name' => 'NUM004', 'domain' => 'SYSTEM', 'created_at' => '2026-10-06 04:05:06']);
        $resourceId = (int) $this->db->insertID();
        $this->db->table('organizations')->insert(['type' => 'GROUP', 'code' => 'NUM004-' . $code . '-' . uniqid(), 'name' => 'NUM004', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $scopeId = (int) $this->db->insertID();
        $seriesId = (new AutoNumberingSeriesRepository($this->db))->insert($this->seriesPayload($resourceId, $start, $increment), $this->operation());
        return [$seriesId, $scopeId];
    }

    /** @return array<string, mixed> */
    private function seriesPayload(int $resourceId, int $start, int $increment): array
    {
        return ['resource_id' => $resourceId, 'series_code' => 'NUM004-' . uniqid(), 'name' => 'NUM004', 'scope_type' => 'GROUP', 'pattern' => '{SEQ:4}', 'reset_policy' => 'NEVER', 'generation_mode' => 'AUTO_REQUIRED', 'start_number' => $start, 'increment_by' => $increment, 'is_default' => 0, 'status' => 'ACTIVE'];
    }

    /** @return array<string, mixed> */
    private function series(int $id, int $start, int $increment): array
    {
        return ['id' => $id, 'start_number' => $start, 'increment_by' => $increment, 'pattern' => '{SEQ:4}'];
    }

    private function pattern(): \App\Services\ParsedNumberingPattern
    {
        return (new CanonicalNumberingPatternValidator())->parse('{SEQ:4}');
    }

    private function context(int $scopeId, string $periodKey = 'ALL'): NumberingScopePeriodContext
    {
        return new NumberingScopePeriodContext('GROUP', $scopeId, $periodKey, null, null, null, null, 'NUM004', null);
    }

    private function operation(): OperationContext
    {
        return new OperationContext(null, new EvaluationTime(new DateTimeImmutable('2026-10-06 04:05:06', new DateTimeZone('UTC'))), 'num004-test', true);
    }
}

final class Num004FakeBusiness implements AutoNumberingBusinessMutation
{
    /** @var list<string> */
    public array $persisted = [];

    /** @param list<string> $occupied
     *  @param list<string> $collisions
     */
    public function __construct(private array $occupied = [], private array $collisions = [], private ?Throwable $failure = null)
    {
    }

    public function isIdentifierOccupied(string $identifier): bool
    {
        return in_array($identifier, $this->occupied, true);
    }

    public function persist(string $identifier): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if (in_array($identifier, $this->collisions, true)) {
            $this->collisions = array_values(array_diff($this->collisions, [$identifier]));
            throw new AutoNumberingIdentifierCollision();
        }
        $this->persisted[] = $identifier;
    }
}

final class Num004FailingLock implements DomainLockService
{
    public function acquire(string $domainKey, int $timeoutSeconds = 5): void
    {
        throw new App\Exceptions\ConcurrencyException('num004.lock');
    }

    public function release(string $domainKey): void
    {
    }

    public function synchronized(string $domainKey, callable $callback, int $timeoutSeconds = 5): mixed
    {
        throw new App\Exceptions\ConcurrencyException('num004.lock');
    }
}
