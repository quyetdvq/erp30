<?php

declare(strict_types=1);

namespace App\Modules\Product\Lookup\Application;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListResult;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Infrastructure\Api\SqlAuthorizedBucketListSource;
use App\Modules\Product\Lookup\Application\Ports\ProductMasterListSqlAuthorizationPort;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Exceptions\BadRequestException;

/**
 * Bounded SQL fast path shared by Product Group/UOM lists (same Product owner).
 * Only default/id sort + proven exact filters can avoid listAll().
 * State buckets come from the real PBAC engine, not raw Policy SQL joins.
 */
final class ProductMasterListSqlFastPath
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly string $resource,
        private readonly ProductMasterListSqlAuthorizationPort $security,
        private readonly \Closure $requiresState,
        private readonly \Closure $authorizeBase,
        private readonly ?CommonListQueryAdapter $adapter = null,
    ) {
        if (! in_array($resource, ['product_group', 'product_uom'], true)) {
            throw new \InvalidArgumentException('Unsupported SQL master list resource.');
        }
    }

    /**
     * null signals an unsafe query/bucket or a recheck mismatch: execute the
     * original per-row path, never return an unauthorized/partial page.
     * @param array<string,mixed> $query
     * @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}|null
     */
    public function list(array $query, CommonListQueryAllowlist $allowlist): ?array
    {
        // CI4 nesting is not a new read transaction; do not use a misleading
        // COUNT/PAGE snapshot inside an existing write/read transaction.
        if ($this->db->transDepth !== 0) {
            return null;
        }
        $queryAdapter = $this->adapter ?? new CommonListQueryAdapter();
        try {
            $parsed = $queryAdapter->parse($query, $allowlist);
        } catch (BadRequestException) {
            // Preserve original permission/DB-failure-before-validation order.
            return null;
        }
        if (! SqlAuthorizedBucketListSource::supports($this->resource, $parsed)) {
            return null;
        }
        return $this->snapshot(function () use ($query, $allowlist, $queryAdapter): ?array {
            if (($this->requiresState)()) {
                $operation = $this->security->operation();
                return $this->security->withReadOnlyBatch($operation, function () use ($operation, $query, $allowlist, $queryAdapter): ?array {
                    $buckets = $this->security->authorizedBuckets($operation);
                    if ($buckets === null) {
                        return null;
                    }
                    $result = $this->sqlPage($queryAdapter, $query, $allowlist, $buckets);
                    $items = [];
                    foreach ($result->data as $row) {
                        // Never trust bucket approval alone for the returned
                        // page. On mismatch, fall back to exact per-row PBAC
                        // BEFORE reporting total/page to the caller.
                        try {
                            if (! $this->security->recheckRowVisible($operation, $row)) {
                                return null;
                            }
                        } catch (DomainException) {
                            return null;
                        }
                        $items[] = $row;
                    }
                    return $this->result($result, $items);
                });
            }
            // Explicit BASE permission is mandatory even if the table is
            // empty. Null buckets are allowed only on these two resources.
            ($this->authorizeBase)();
            $result = $this->sqlPage($queryAdapter, $query, $allowlist, null);
            return $this->result($result, array_values(iterator_to_array($result->data)));
        });
    }

    /**
     * Preserve the original repository persistence machine error when an
     * optimized SQL COUNT/PAGE fails; do not turn it into generic HTTP 500.
     * @param array<string,mixed> $query
     * @param list<array{status:string}>|null $buckets
     */
    private function sqlPage(
        CommonListQueryAdapter $adapter,
        array $query,
        CommonListQueryAllowlist $allowlist,
        ?array $buckets,
    ): CommonListResult {
        try {
            return $adapter->paginate($query, $allowlist,
                new SqlAuthorizedBucketListSource($this->db, $this->resource, $buckets));
        } catch (BadRequestException|DomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $error = $this->resource === 'product_group' ? 'PRODUCT_GROUP_PERSISTENCE_FAILED' : 'PRODUCT_UOM_PERSISTENCE_FAILED';
            throw new PersistenceException($error, 'listAll', $exception);
        }
    }

    /**
     * @template T
     * @param callable():T $read
     * @return T
     */
    private function snapshot(callable $read): mixed
    {
        $error = $this->resource === 'product_group' ? 'PRODUCT_GROUP_PERSISTENCE_FAILED' : 'PRODUCT_UOM_PERSISTENCE_FAILED';
        try {
            // InnoDB consistent read for State samples, PBAC, COUNT, PAGE,
            // and the page recheck. Never change session/global isolation.
            if ($this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') !== true) {
                throw new PersistenceException($error, 'listSnapshotIsolation');
            }
            if (! $this->db->transBegin()) {
                throw new PersistenceException($error, 'listSnapshotBegin');
            }
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException($error, 'listSnapshotBegin', $exception);
        }
        try {
            $result = $read();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new PersistenceException($error, 'listSnapshotCommit');
            }
            return $result;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    private function result(\App\Infrastructure\Api\CommonListResult $result, array $items): array
    {
        return ['items' => $items, 'page' => $result->query->page,
            'page_size' => $result->query->pageSize, 'total' => $result->total];
    }
}
