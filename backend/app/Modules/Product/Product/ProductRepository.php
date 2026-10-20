<?php

declare(strict_types=1);

namespace App\Modules\Product\Product;

use App\Infrastructure\Persistence\ValidatesRepositoryPayload;
use App\Infrastructure\Persistence\FindsRepositoryRow;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Api\CommonListQuery;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\ConnectionInterface;

/** Persistence-only primitives for the canonical Product entity. */
final class ProductRepository
{
    use FindsRepositoryRow;
    use ValidatesRepositoryPayload;

    public const ERROR_PERSISTENCE_FAILED = 'PRODUCT_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'PRODUCT_CONNECTION_INVALID';
    public const ERROR_UPDATE_EMPTY = 'PRODUCT_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'PRODUCT_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'PRODUCT_FIELD_REQUIRED';
    public const ERROR_INVALID_ID = 'PRODUCT_INVALID_ID';
    public const ERROR_DUPLICATE_CODE = 'PRODUCT_DUPLICATE_CODE';
    public const ERROR_DUPLICATE_SKU = 'PRODUCT_DUPLICATE_SKU';
    public const ERROR_DUPLICATE_BARCODE = 'PRODUCT_DUPLICATE_BARCODE';
    public const ERROR_PARENT_FOREIGN_KEY = 'PRODUCT_PARENT_NOT_FOUND';
    public const ERROR_GROUP_FOREIGN_KEY = 'PRODUCT_GROUP_NOT_FOUND';
    public const ERROR_UOM_FOREIGN_KEY = 'PRODUCT_UOM_NOT_FOUND';

    /** @var list<string> */
    private const FIELDS = [
        'code', 'sku', 'barcode', 'parent_product_id', 'product_name',
        'product_child_name', 'product_group_id', 'product_type', 'base_uom_id',
        'price', 'net_weight_g', 'package_gross_weight_g', 'package_length_mm',
        'package_width_mm', 'package_height_mm', 'description',
        'manual_standard_cost', 'serial_management_enabled', 'status',
        'created_by', 'updated_by', 'created_at', 'updated_at',
    ];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return array<string, mixed>|null */
    public function findById(mixed $id): ?array
    {
        return $this->findRow('findById', fn () => $this->db->table('products')
            ->where('id', $this->id($id, 'product_id'))->get());
    }

    /** @return array<string, mixed>|null */
    public function find(mixed $id): ?array
    {
        return $this->findById($id);
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(mixed $id): ?array
    {
        $normalizedId = $this->id($id, 'product_id');
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'findForUpdate']);
        }
        try {
            $builder = $this->db->table('products')->where('id', $normalizedId);
            $result = $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE');
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findForUpdate', $exception);
        }
        if (! $result instanceof BaseResult) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findForUpdate');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->findRow('findByCode', fn () => $this->db->table('products')->where('code', $code)->get());
    }

    /** @return array<string, mixed>|null */
    public function findBySku(string $sku): ?array
    {
        return $this->findRow('findBySku', fn () => $this->db->table('products')->where('sku', $sku)->get());
    }

    /** @return array<string, mixed>|null */
    public function findByBarcode(string $barcode): ?array
    {
        return $this->findRow('findByBarcode', fn () => $this->db->table('products')->where('barcode', $barcode)->get());
    }

    /** @return array<string, mixed>|null */
    public function findParent(mixed $id): ?array
    {
        $child = $this->findById($id);
        if ($child === null || ($child['parent_product_id'] ?? null) === null) {
            return null;
        }

        return $this->findById($child['parent_product_id']);
    }

    /** @return list<array<string, mixed>> */
    public function listChildren(mixed $parentId): array
    {
        $parentId = $this->id($parentId, 'parent_product_id');
        try {
            $result = $this->db->table('products')
                ->where('parent_product_id', $parentId)
                ->orderBy('id', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listChildren', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listChildren');
        }

        return array_values($result->getResultArray());
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        try {
            $result = $this->db->table('products')
                ->orderBy('code', 'ASC')
                ->orderBy('id', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listAll', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listAll');
        }

        return array_values($result->getResultArray());
    }

    /**
     * Fail closed if any Product in the catalog has an invalid role graph,
     * including Products outside the requested page or SQL filter.
     *
     * The legacy list validated the entire catalog before pagination. This
     * indexed, single-result scan restores that behavior without loading
     * complete Product records. Name checks use the exact six ASCII bytes
     * removed by PHP trim(), avoiding SQL collation/Unicode differences.
     * It MUST share the list's read snapshot.
     *
     * @return array{id:int,reason:string}|null
     */
    public function firstInvalidListStructure(): ?array
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'firstInvalidListStructure']);
        }
        $table = '`' . str_replace('`', '``', $this->db->prefixTable('products')) . '`';
        $sql = <<<SQL
SELECT violations.id, violations.reason
FROM (
    SELECT p.id,
        CASE
            WHEN BINARY UPPER(p.product_type) NOT IN (BINARY 'STANDARD', BINARY 'COMBO')
                THEN 'product_type'
            WHEN BINARY UPPER(p.product_type) = BINARY 'COMBO'
                AND (p.parent_product_id IS NOT NULL
                    OR EXISTS (SELECT 1 FROM {$table} AS child WHERE child.parent_product_id = p.id))
                THEN 'combo_structure'
            WHEN p.parent_product_id = p.id THEN 'self_parent'
            WHEN p.parent_product_id IS NOT NULL AND parent.id IS NULL THEN 'parent_not_found'
            WHEN p.parent_product_id IS NOT NULL
                AND (BINARY UPPER(parent.product_type) <> BINARY 'STANDARD'
                    OR parent.parent_product_id IS NOT NULL)
                THEN 'parent_not_root_standard'
            WHEN p.parent_product_id IS NOT NULL
                AND EXISTS (SELECT 1 FROM {$table} AS grandchild WHERE grandchild.parent_product_id = p.id)
                THEN 'child_has_children'
            WHEN p.parent_product_id IS NULL
                AND (p.product_name IS NULL OR HEX(p.product_name) REGEXP '^(00|09|0A|0B|0D|20)*$')
                THEN 'required_name'
            WHEN p.parent_product_id IS NOT NULL
                AND (p.product_child_name IS NULL OR HEX(p.product_child_name) REGEXP '^(00|09|0A|0B|0D|20)*$')
                THEN 'required_name'
            ELSE NULL
        END AS reason
    FROM {$table} AS p
    LEFT JOIN {$table} AS parent ON parent.id = p.parent_product_id
) AS violations
WHERE violations.reason IS NOT NULL
ORDER BY violations.id ASC
LIMIT 1
SQL;
        try {
            $result = $this->db->query($sql);
            if (! $result instanceof BaseResult) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'firstInvalidListStructure');
            }
            $row = $result->getRowArray();
            return $row === null ? null : ['id' => (int) $row['id'], 'reason' => (string) $row['reason']];
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'firstInvalidListStructure', $exception);
        }
    }

    /**
     * Read integrity, count, page and relationships from one consistent
     * InnoDB REPEATABLE READ snapshot. The isolation override applies to
     * ONLY this transaction; it never changes the session/global default.
     *
     * CI4 nested transactions do not start a new database transaction.
     * Reject them: an enclosing READ COMMITTED transaction (or a per-
     * transaction override) cannot be upgraded safely once started.
     *
     * @template T
     * @param callable():T $read
     * @return T
     */
    public function withConsistentListSnapshot(callable $read): mixed
    {
        if (! $this->db instanceof BaseConnection) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'beginProductListSnapshot');
        }
        if ($this->db->transDepth !== 0) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'nestedProductListSnapshot');
        }

        // SET TRANSACTION without SESSION applies only to the next transaction
        // (MariaDB/MySQL). This also works if the session default is READ
        // COMMITTED; merely checking @@tx_isolation after BEGIN would fail or
        // report the session default instead of this transaction's override.
        try {
            if ($this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') !== true) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'setProductListIsolation');
            }
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'setProductListIsolation', $exception);
        }

        if (! $this->db->transBegin()) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'beginProductListSnapshot');
        }
        try {
            $result = $read();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'commitProductListSnapshot');
            }
            return $result;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    /**
     * Only push down queries whose PHP list semantics are equivalent to SQL.
     *
     * The shared in-memory source performs Unicode case-folded substring
     * search and case-folded/numeric-aware string sorting. Those operations
     * cannot be replaced by a database's configurable collation without
     * changing results. Noncanonical FK filter strings (e.g. "01") also stay
     * on the legacy path because PHP compares them as exact strings.
     */
    public function supportsSqlList(CommonListQuery $query): bool
    {
        return $this->supportsSqlProjection($query)
            && $query->q === null
            && count($query->sort) === 1
            && $query->sort[0]->field === 'id';
    }

    /**
     * The database may prefilter exact fields; all remaining search and sort
     * comparisons are carried out on projected keys by the shared PHP source.
     */
    public function supportsSqlProjection(CommonListQuery $query): bool
    {
        foreach ($query->filters as $field => $values) {
            if (! in_array($field, ['base_uom_id', 'parent_product_id', 'product_group_id', 'product_type', 'status'], true)) {
                return false;
            }
            if (in_array($field, ['base_uom_id', 'parent_product_id', 'product_group_id'], true)) {
                foreach ($values as $value) {
                    if ($value !== '' && preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) !== 1) {
                        return false;
                    }
                    if ($value !== '' && (strlen($value) > strlen((string) PHP_INT_MAX)
                        || (strlen($value) === strlen((string) PHP_INT_MAX) && strcmp($value, (string) PHP_INT_MAX) > 0))) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /** Count after the same SQL predicates that will be used for the page. */
    /** @param list<string>|null $authorizedStatuses */
    public function countListMatching(CommonListQuery $query, ?array $authorizedStatuses = null): int
    {
        $this->assertSupportedSqlList($query);
        try {
            return (int) $this->listBuilder($query, $authorizedStatuses)->countAllResults();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'countListMatching', $exception);
        }
    }

    /** @param list<string>|null $authorizedStatuses
     *  @return list<array<string, mixed>>
     */
    public function listPageMatching(CommonListQuery $query, ?array $authorizedStatuses = null): array
    {
        $this->assertSupportedSqlList($query);
        try {
            $result = $this->listBuilder($query, $authorizedStatuses)
                ->orderBy('id', $query->sort[0]->direction)
                ->limit($query->pageSize, $query->offset)
                ->get();
            if ($result === false) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listPageMatching');
            }
            return array_values($result->getResultArray());
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listPageMatching', $exception);
        }
    }

    /**
     * Load just search/sort keys, not full Product rows or derived roles.
     * Initial code/id ordering matches listAll() exactly: PHP's usort may
     * otherwise behave differently for mixed numeric and textual codes.
     *
     * @param list<string>|null $authorizedStatuses
     * @return list<array<string, mixed>>
     */
    public function listProjectedKeys(CommonListQuery $query, ?array $authorizedStatuses = null): array
    {
        if (! $this->supportsSqlProjection($query)) {
            throw new \InvalidArgumentException('Unsupported SQL Product key projection.');
        }
        try {
            $result = $this->listBuilder($query, $authorizedStatuses)
                ->select(['id', 'code', 'sku', 'barcode', 'product_name', 'product_child_name', 'product_type', 'status'])
                ->orderBy('code', 'ASC')->orderBy('id', 'ASC')->get();
            if ($result === false) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listProjectedKeys');
            }
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'listProjectedKeys', $exception);
        }
        $keys = [];
        foreach ($result->getResultArray() as $row) {
            $row['product_code'] = $row['code'];
            unset($row['code']);
            $keys[] = $row;
        }
        return $keys;
    }

    /** @param list<int> $ids
     *  @return list<array<string, mixed>>
     */
    public function findByIds(array $ids): array
    {
        return $this->findByIn('id', $ids, 'findByIds');
    }

    /** @param list<int> $ids
     *  @return list<array<string, mixed>>
     */
    public function listByParentIds(array $ids): array
    {
        // Relationship validation only needs these three fields, even when a
        // requested parent has thousands of children outside the page.
        return $this->findByIn('parent_product_id', $ids, 'listByParentIds', ['id', 'parent_product_id', 'product_type']);
    }

    /** @param list<int> $ids
     *  @return list<int>
     */
    public function parentIdsWithChildren(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $found = [];
        try {
            foreach (array_chunk($ids, 500) as $chunk) {
                $result = $this->db->table('products')
                    ->select('parent_product_id')->distinct()
                    ->whereIn('parent_product_id', $chunk)->get();
                if ($result === false) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'parentIdsWithChildren');
                }
                foreach ($result->getResultArray() as $row) {
                    $found[(int) $row['parent_product_id']] = true;
                }
            }
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'parentIdsWithChildren', $exception);
        }
        return array_keys($found);
    }

    /** @param list<int> $ids
     *  @param list<string> $columns
     *  @return list<array<string,mixed>>
     */
    private function findByIn(string $field, array $ids, string $operation, array $columns = []): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $rows = [];
        try {
            foreach (array_chunk($ids, 500) as $chunk) {
                $builder = $this->db->table('products')->whereIn($field, $chunk)
                    ->orderBy('id', 'ASC');
                if ($columns !== []) {
                    $builder->select($columns);
                }
                $result = $builder->get();
                if ($result === false) {
                    throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
                }
                foreach ($result->getResultArray() as $row) {
                    $rows[] = $row;
                }
            }
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
        }
        return $rows;
    }

    private function assertSupportedSqlList(CommonListQuery $query): void
    {
        if (! $this->supportsSqlList($query)) {
            throw new \InvalidArgumentException('Unsupported SQL Product list query.');
        }
    }

    /** @param list<string>|null $authorizedStatuses
     *  @return \CodeIgniter\Database\BaseBuilder
     */
    private function listBuilder(CommonListQuery $query, ?array $authorizedStatuses = null): \CodeIgniter\Database\BaseBuilder
    {
        $builder = $this->db->table('products');
        if ($authorizedStatuses !== null) {
            // Statuses are authoritative AuthorizationService-approved State
            // buckets, never client input. HEX preserves byte-exact semantics
            // under case/accent-insensitive MariaDB collations.
            if ($authorizedStatuses === []) {
                $builder->where('1 = 0', null, false);
            } else {
                $builder->whereIn('status', $authorizedStatuses);
                $quoted = array_map(
                    fn (string $status): string => (string) $this->db->escape(strtoupper(bin2hex($status))),
                    $authorizedStatuses,
                );
                $builder->where('HEX(status) IN (' . implode(', ', $quoted) . ')', null, false);
            }
        }
        foreach ($query->filters as $field => $values) {
            if (in_array($field, ['product_type', 'status'], true)) {
                // PHP's strict string comparison is byte-exact; avoid the
                // database's potentially accent/case-insensitive collation.
                $quoted = array_map(fn (string $value): string => (string) $this->db->escape($value), $values);
                // Ordinary equality can use an index; the additional BINARY
                // predicate restores the PHP source's exact string match.
                $builder->whereIn($field, $values);
                $builder->where('BINARY `' . $field . '` IN (' . implode(', ', $quoted) . ')', null, false);
                continue;
            }
            $containsNull = in_array('', $values, true);
            $numeric = array_values(array_filter($values, static fn (string $value): bool => $value !== ''));
            $builder->groupStart();
            if ($numeric !== []) {
                $builder->whereIn($field, $numeric);
            }
            if ($containsNull) {
                if ($numeric !== []) {
                    $builder->orWhere($field . ' IS NULL', null, false);
                } else {
                    $builder->where($field . ' IS NULL', null, false);
                }
            }
            $builder->groupEnd();
        }
        return $builder;
    }

    public function hasChildren(mixed $parentId): bool
    {
        return $this->countChildren($parentId) > 0;
    }

    public function countChildren(mixed $parentId): int
    {
        $parentId = $this->id($parentId, 'parent_product_id');
        try {
            return (int) $this->db->table('products')->where('parent_product_id', $parentId)->countAllResults();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'countChildren', $exception);
        }
    }

    public function hasPriceListReference(mixed $productId): bool
    {
        return $this->existsBy('product_price_list_items', 'product_id', $productId, 'hasPriceListReference');
    }

    public function hasUomConversion(mixed $productId): bool
    {
        return $this->existsBy('product_uom_conversions', 'product_id', $productId, 'hasUomConversion');
    }

    public function isComboComponent(mixed $productId): bool
    {
        return $this->existsBy('product_combo_items', 'component_product_id', $productId, 'isComboComponent');
    }

    public function hasComboComposition(mixed $productId): bool
    {
        return $this->existsBy('product_combo_items', 'combo_product_id', $productId, 'hasComboComposition');
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        return $this->insert($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data, ?OperationContext $operation = null): int
    {
        $payload = array_merge($this->payload($data, ['code', 'product_type', 'status']), $this->createdAudit($operation));
        try {
            if (! $this->db->table('products')->insert($payload)) {
                throw $this->writeException('insert', $payload);
            }
        } catch (DomainException|PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw $this->writeException('insert', $payload, $exception);
        }
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'insert']);
        }
        $id = (int) $this->db->insertID();
        if ($id < 1) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
        }

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        $normalizedId = $this->id($id, 'product_id');
        $payload = $this->payload($data);
        unset($payload['created_by'], $payload['created_at'], $payload['updated_by'], $payload['updated_at']);
        if ($payload === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => 'update']);
        }
        if ($this->findById($normalizedId) === null) {
            return false;
        }
        try {
            $updated = $this->db->table('products')->where('id', $normalizedId)
                ->update(array_merge($payload, $this->updatedAudit($operation)));
            if ($updated === false) {
                throw $this->writeException('update', $payload);
            }
        } catch (DomainException|PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw $this->writeException('update', $payload, $exception);
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    public function updateMutable(mixed $id, array $data, ?OperationContext $operation = null): bool
    {
        return $this->update($id, $data, $operation);
    }

    public function updateStatus(mixed $id, string $status, ?OperationContext $operation = null): bool
    {
        return $this->update($id, ['status' => $status], $operation);
    }

    private function id(mixed $value, string $field): int
    {
        return PositiveId::normalize($value, $field, self::ERROR_INVALID_ID);
    }

    private function existsBy(string $table, string $field, mixed $value, string $operation): bool
    {
        $id = $this->id($value, 'product_id');
        try {
            $result = $this->db->table($table)->select('id')->where($field, $id)->limit(1)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation);
        }

        return $result->getRowArray() !== null;
    }

    /** @param array<string, mixed> $payload */
    private function writeException(string $operation, array $payload, ?\Throwable $previous = null): DomainException|PersistenceException
    {
        $error = $this->db->error();
        $message = strtolower((string) ($error['message'] ?? '') . ' ' . ($previous?->getMessage() ?? ''));
        if (str_contains($message, '1062') || str_contains($message, 'duplicate') || str_contains($message, 'unique')) {
            if (preg_match('/(?:products\.)?barcode|barcode_unique|products_barcode/i', $message) === 1) {
                return new DomainException(self::ERROR_DUPLICATE_BARCODE, ['field' => 'barcode', 'operation' => $operation], $previous);
            }
            if (preg_match('/(?:products\.)?sku|sku_unique|products_sku/i', $message) === 1) {
                return new DomainException(self::ERROR_DUPLICATE_SKU, ['field' => 'sku', 'operation' => $operation], $previous);
            }
            if (preg_match('/(?:^|[._`\' ])code(?:$|[._`\' ])|code_unique|products_code/i', $message) === 1) {
                return new DomainException(self::ERROR_DUPLICATE_CODE, ['field' => 'code', 'operation' => $operation], $previous);
            }
        }
        if (str_contains($message, '1452') || str_contains($message, 'foreign key') || str_contains($message, 'constraint fails')) {
            if (str_contains($message, 'parent_product_id') || str_contains($message, 'products_ibfk_1')) {
                return new DomainException(self::ERROR_PARENT_FOREIGN_KEY, ['field' => 'parent_product_id'], $previous);
            }
            if (str_contains($message, 'product_group_id') || str_contains($message, 'products_ibfk_2')) {
                return new DomainException(self::ERROR_GROUP_FOREIGN_KEY, ['field' => 'product_group_id'], $previous);
            }
            if (str_contains($message, 'base_uom_id') || str_contains($message, 'products_ibfk_3')) {
                return new DomainException(self::ERROR_UOM_FOREIGN_KEY, ['field' => 'base_uom_id'], $previous);
            }
        }

        return new PersistenceException(self::ERROR_PERSISTENCE_FAILED, $operation, $previous);
    }

    /** @return array{created_by: int|null, created_at: string} */
    private function createdAudit(?OperationContext $operation): array
    {
        return ($operation ?? MutationContext::system(service('clock'))->begin())->createdAudit();
    }

    /** @return array{updated_by: int|null, updated_at: string} */
    private function updatedAudit(?OperationContext $operation): array
    {
        return ($operation ?? MutationContext::system(service('clock'))->begin())->updatedAudit();
    }
}
