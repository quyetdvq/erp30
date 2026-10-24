<?php

declare(strict_types=1);

use App\Exceptions\ConcurrencyException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Integration\Foundation\LocationLifecycleAdapter;
use App\Modules\Foundation\Application\LocationService;
use App\Modules\Foundation\Infrastructure\Persistence\SqlLocationRepository;
use App\Services\LifecycleTargetSnapshot;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Database\Query;
use CodeIgniter\Database\ResultInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal No database access: exercise persistence type boundaries, not SQL locks. */
final class BeReorg001FoundationPersistenceTypesTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function nonRowResults(): array
    {
        return ['false' => ['false'], 'true' => ['true'], 'pretend-query' => ['query']];
    }

    #[DataProvider('nonRowResults')]
    public function testRepositoryLockRejectsNonRowResultsAsPersistenceFailure(string $kind): void
    {
        $db = $this->createMock(BaseConnection::class);
        $db->method('prefixTable')->with('wards')->willReturn('dinktech_wards');
        $db->expects($this->once())->method('query')
            ->with('SELECT * FROM `dinktech_wards` WHERE id = ? FOR UPDATE', [7])
            ->willReturn($this->nonRowResult($kind));

        try {
            (new SqlLocationRepository(db: $db))->lockWardForUpdate(7);
            $this->fail('A non-row result must not be treated as a locked row.');
        } catch (PersistenceException $exception) {
            $this->assertSame('LOCATION_PERSISTENCE_FAILED', $exception->getErrorCode());
            $this->assertSame(['operation' => 'lockWardForUpdate'], $exception->getContext());
        }
    }

    #[DataProvider('nonRowResults')]
    public function testAdapterLockRejectsNonRowResultsAsPersistenceFailure(string $kind): void
    {
        $db = $this->createMock(BaseConnection::class);
        $db->method('prefixTable')->with('wards')->willReturn('dinktech_wards');
        $db->expects($this->once())->method('query')
            ->with('SELECT * FROM `dinktech_wards` WHERE id = ? FOR UPDATE', [7])
            ->willReturn($this->nonRowResult($kind));
        $db->expects($this->never())->method('table');

        try {
            (new LocationLifecycleAdapter(type: 'ward', db: $db))
                ->lockAndReload($this->snapshot(), $this->operation());
            $this->fail('A non-row result must not become a workflow snapshot.');
        } catch (PersistenceException $exception) {
            $this->assertSame('LOCATION_PERSISTENCE_FAILED', $exception->getErrorCode());
            $this->assertSame(['operation' => 'workflowLock'], $exception->getContext());
        }
    }

    /** @return array<string, array{array<string, mixed>|null}> */
    public static function lockedRows(): array
    {
        return [
            'missing' => [null],
            'existing' => [['id' => 7, 'province_id' => 3, 'code' => 'WARD-7', 'status' => 'ACTIVE']],
        ];
    }

    #[DataProvider('lockedRows')]
    public function testRepositoryLockPreservesRowAndNullResults(?array $row): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->expects($this->once())->method('getRowArray')->willReturn($row);
        $db = $this->createMock(BaseConnection::class);
        $db->method('prefixTable')->willReturn('dinktech_wards');
        $db->expects($this->once())->method('query')->willReturn($result);

        $this->assertSame($row, (new SqlLocationRepository(db: $db))->lockWardForUpdate(7));
    }

    public function testAdapterMissingLockedRowStillThrowsConcurrencyException(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->expects($this->once())->method('getRowArray')->willReturn(null);
        $db = $this->createMock(BaseConnection::class);
        $db->method('prefixTable')->willReturn('dinktech_wards');
        $db->method('query')->willReturn($result);
        $db->expects($this->never())->method('table');

        try {
            (new LocationLifecycleAdapter(type: 'ward', db: $db))
                ->lockAndReload($this->snapshot(), $this->operation());
            $this->fail('A missing locked target must remain a concurrency error.');
        } catch (ConcurrencyException $exception) {
            $this->assertSame('CONCURRENT_MODIFICATION', $exception->getErrorCode());
            $this->assertSame(['operation' => 'lifecycle.target_missing'], $exception->getContext());
        }
    }

    public function testRepositoryLockPreservesTheQueryFailureAsPreviousException(): void
    {
        $failure = new RuntimeException('Injected SELECT failure');
        $db = $this->createMock(BaseConnection::class);
        $db->method('prefixTable')->willReturn('dinktech_wards');
        $db->method('query')->willThrowException($failure);

        try {
            (new SqlLocationRepository(db: $db))->lockWardForUpdate(7);
            $this->fail('The repository must propagate a persistence failure.');
        } catch (PersistenceException $exception) {
            $this->assertSame('LOCATION_PERSISTENCE_FAILED', $exception->getErrorCode());
            $this->assertSame(['operation' => 'lockWardForUpdate'], $exception->getContext());
            $this->assertSame($failure, $exception->getPrevious());
        }
    }

    public function testAdapterLockStillPropagatesQueryExceptionsUnwrapped(): void
    {
        $failure = new RuntimeException('Injected SELECT failure');
        $db = $this->createMock(BaseConnection::class);
        $db->method('prefixTable')->willReturn('dinktech_wards');
        $db->method('query')->willThrowException($failure);

        try {
            (new LocationLifecycleAdapter(type: 'ward', db: $db))
                ->lockAndReload($this->snapshot(), $this->operation());
            $this->fail('The adapter must not change the existing query exception contract.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    public function testMutateRejectsUnsupportedConnectionBeforeAnyDatabaseAccess(): void
    {
        $db = $this->createMock(ConnectionInterface::class);
        $db->expects($this->never())->method('table');
        $db->expects($this->never())->method('query');

        try {
            (new LocationLifecycleAdapter(type: 'ward', db: $db))
                ->mutate($this->snapshot(), 1, 2, $this->operation());
            $this->fail('An unsupported mutation connection must fail before querying or writing.');
        } catch (PersistenceException $exception) {
            $this->assertSame('LOCATION_PERSISTENCE_FAILED', $exception->getErrorCode());
            $this->assertSame(['operation' => 'workflowMutate'], $exception->getContext());
        }
    }

    public function testStaleSnapshotIsStillRejectedBeforeConnectionValidation(): void
    {
        $db = $this->createMock(ConnectionInterface::class);
        $db->expects($this->never())->method('table');
        $db->expects($this->never())->method('query');

        try {
            (new LocationLifecycleAdapter(type: 'ward', db: $db))
                ->mutate($this->snapshot(), 2, 1, $this->operation());
            $this->fail('A stale snapshot must retain its concurrency error.');
        } catch (ConcurrencyException $exception) {
            $this->assertSame('CONCURRENT_MODIFICATION', $exception->getErrorCode());
            $this->assertSame(['operation' => 'lifecycle.state_change'], $exception->getContext());
        }
    }

    public function testStateMutationParametersHaveSeparatePhpDocTypeTags(): void
    {
        $comment = (new ReflectionMethod(LocationService::class, 'assertStateMutationAllowed'))->getDocComment();
        $this->assertNotFalse($comment);
        foreach (['current', 'changes'] as $parameter) {
            $this->assertMatchesRegularExpression(
                '/^\s*\*\s+@param\s+array<string,\s*mixed>\s+\$' . $parameter . '\s*$/m',
                $comment,
            );
        }
    }

    private function nonRowResult(string $kind): bool|Query
    {
        return match ($kind) {
            'false' => false,
            'true' => true,
            // No constructor or SQL execution: only the concrete result type matters.
            'query' => (new ReflectionClass(Query::class))->newInstanceWithoutConstructor(),
            default => throw new InvalidArgumentException('Unknown result fixture.'),
        };
    }

    private function snapshot(): LifecycleTargetSnapshot
    {
        return new LifecycleTargetSnapshot('ward', 7, 1, 11, 3, ['id' => 7, 'status' => 'ACTIVE']);
    }

    private function operation(): OperationContext
    {
        return new OperationContext(
            42, new EvaluationTime(new DateTimeImmutable('2026-10-16T00:00:00Z')), 'be001b-types', false,
        );
    }
}
