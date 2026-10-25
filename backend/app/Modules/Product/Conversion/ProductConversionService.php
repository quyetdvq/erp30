<?php

declare(strict_types=1);

namespace App\Modules\Product\Conversion;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

/**
 * Product-owned UOM conversion reads and atomic replacements.
 *
 * GET uses an Access-compiled MASTER Lifecycle SQL scope before querying any
 * conversion rows. PUT serializes with Product Master/Image mutations using
 * their established advisory lock and locks the Product and conversion rows.
 */
final class ProductConversionService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly string $actorId;

    public function __construct(User $actor, ?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->access = new UnifiedAccessService($actor, $this->db);
        $this->actorId = (string)$actor->id;
    }

    /** @return list<array<string,mixed>> */
    public function list(string $rawId): array
    {
        $id = ProductConversionRules::productId($rawId);
        if (!$this->access->hasBaseViewPermission('product')) {
            throw new DomainException('PRODUCT_AUTHORIZATION_DENIED', ['action'=>'view']);
        }
        $states = $this->access->viewableMasterLifecycleStatuses('product');
        $plan = ProductConversionSql::authorizedProduct($id, $states, (string)$this->db->DBPrefix);
        $result = $this->db->query($plan['sql'], $plan['binds']);
        if ($result === false) throw new DomainException('PRODUCT_CONVERSION_CONNECTION_INVALID');
        $row = $result->getRowArray();
        if ($row === null) throw new PageNotFoundException('Product is not available.');
        // A disagreeing SQL scope and persisted PBAC decision must fail closed.
        if (!$this->access->canView('product', $row)) {
            throw new UnexpectedValueException('Product conversion SQL/PBAC scope mismatch.');
        }
        return $this->byProduct($id);
    }

    /**
     * PUT replaces the entire alternate-UOM set, including removal of any
     * omitted UOM. Validation and all DML share exactly one SQL transaction.
     *
     * @param array<mixed> $request
     * @return list<array<string,mixed>>
     */
    public function replace(string $rawId, array $request): array
    {
        $id = ProductConversionRules::productId($rawId);
        $parsed = ProductConversionRules::request($request); // No SQL write yet.
        return $this->transaction(function () use ($id, $parsed): array {
            $product = $this->lockedProduct($id);
            if (!$this->access->canView('product', $product)) {
                throw new PageNotFoundException('Product is not available.');
            }
            if (!$this->access->decideMutation('product', 'edit', $product)['allowed']) {
                throw new DomainException('PRODUCT_AUTHORIZATION_DENIED', ['action'=>'edit']);
            }
            $this->assertEvaluationHint($parsed['hint']);
            ProductConversionRules::assertEligible($product, $this->hasChildren($id));
            $baseId = (string)$product['base_uom_id'];

            // All trusted UOM facts are locked in canonical ID order. All new
            // conversions require an ACTIVE UOM; unchanged existing UOM rows
            // follow baseline's merge semantics, even if since inactivated.
            $old = $this->lockedConversions($id);
            $existing = [];
            foreach ($old as $row) {
                $existing[(string)$row['uom_id']] = $row;
            }
            $needed = [$baseId=>true];
            foreach ($parsed['items'] as $item) $needed[$item['uom_id']] = true;
            $uoms = $this->lockedUoms(array_keys($needed));
            if (($uoms[$baseId]['status'] ?? null) !== 'ACTIVE') {
                throw new DomainException('PRODUCT_BASE_UOM_INACTIVE', ['field'=>'base_uom_id']);
            }
            foreach ($parsed['items'] as $item) {
                $uomId = $item['uom_id'];
                if ($uomId === $baseId) {
                    throw new DomainException('PRODUCT_CONVERSION_INVALID_UOM', ['field'=>'uom_id']);
                }
                if (!isset($uoms[$uomId])) {
                    throw new DomainException('PRODUCT_CONVERSION_INVALID_UOM', ['field'=>'uom_id']);
                }
                if (!isset($existing[$uomId]) && $uoms[$uomId]['status'] !== 'ACTIVE') {
                    throw new DomainException('PRODUCT_CONVERSION_INACTIVE_UOM', ['field'=>'uom_id']);
                }
                ProductConversionRules::assertPrice(array_replace($existing[$uomId] ?? [], $item));
            }

            // The same pure DML plan is exercised by the offline atomicity fixture.
            // Database transaction still supplies the actual rollback guarantee.
            $plan = ProductConversionPlan::build($old, $parsed['items']);
            $now = gmdate('Y-m-d H:i:s');
            foreach ($plan['deletes'] as $row) {
                $this->perform('delete', fn () => $this->db->table('product_uom_conversions')
                    ->where('product_id', $id)->where('id', $row['id'])->delete());
            }
            foreach ($plan['updates'] as $step) {
                $this->perform('update', fn () => $this->db->table('product_uom_conversions')
                    ->where('product_id', $id)->where('id', $step['old']['id'])
                    ->update([...$step['new'], 'updated_by'=>$this->actorId, 'updated_at'=>$now]));
            }
            foreach ($plan['creates'] as $item) {
                $this->perform('insert', fn () => $this->db->table('product_uom_conversions')->insert([
                    ...$item, 'product_id'=>$id, 'created_by'=>$this->actorId,
                    'created_at'=>$now,
                ]));
            }
            return $this->byProduct($id);
        });
    }

    /** @return array<string,mixed> */
    private function lockedProduct(string $id): array
    {
        $sql = $this->db->table('products')->where('id', $id)->getCompiledSelect().' FOR UPDATE';
        $result = $this->db->query($sql);
        if ($result === false) throw new DomainException('PRODUCT_CONVERSION_MUTATION_FAILED');
        $row = $result->getRowArray();
        if ($row === null) throw new DomainException('PRODUCT_NOT_FOUND');
        return $row;
    }

    private function hasChildren(string $id): bool
    {
        return $this->db->table('products')->select('id')->where('parent_product_id', $id)
            ->limit(1)->get()->getRowArray() !== null;
    }

    /** @return list<array<string,mixed>> */
    private function byProduct(string $id): array
    {
        $result = $this->db->table('product_uom_conversions')->where('product_id', $id)
            ->orderBy('uom_id', 'ASC')->orderBy('id', 'ASC')->get();
        if ($result === false) throw new DomainException('PRODUCT_CONVERSION_MUTATION_FAILED');
        return array_values($result->getResultArray());
    }

    /** @return list<array<string,mixed>> */
    private function lockedConversions(string $id): array
    {
        $sql = $this->db->table('product_uom_conversions')->where('product_id', $id)
            ->orderBy('uom_id', 'ASC')->orderBy('id', 'ASC')->getCompiledSelect().' FOR UPDATE';
        $result = $this->db->query($sql);
        if ($result === false) throw new DomainException('PRODUCT_CONVERSION_MUTATION_FAILED');
        return array_values($result->getResultArray());
    }

    /** @param list<string> $ids @return array<string,array{id:string,status:string}> */
    private function lockedUoms(array $ids): array
    {
        // Lexicographic sort prevents mixed lock acquisition order for two
        // conversions referencing the same set of UOM masters.
        sort($ids, SORT_STRING);
        $sql = $this->db->table('product_uoms')->select('id,status')->whereIn('id', $ids)
            ->orderBy('id', 'ASC')->getCompiledSelect().' FOR UPDATE';
        $result = $this->db->query($sql);
        if ($result === false) throw new DomainException('PRODUCT_CONVERSION_MUTATION_FAILED');
        $out = [];
        foreach ($result->getResultArray() as $row) {
            $out[(string)$row['id']] = ['id'=>(string)$row['id'], 'status'=>(string)$row['status']];
        }
        return $out;
    }

    private function assertEvaluationHint(mixed $hint): void
    {
        if ($hint === null) return;
        if (!is_string($hint) && !is_int($hint)) {
            throw new DomainException('EVALUATION_ORGANIZATION_HINT_INVALID');
        }
        try { $id = \App\Shared\Read\PositiveId::value($hint); }
        catch (InvalidArgumentException $e) {
            throw new DomainException('EVALUATION_ORGANIZATION_HINT_INVALID', [], $e);
        }
        $roots = $this->db->table('organizations')->select('id')->where('type', 'GROUP')
            ->where('parent_id', null)->get()->getResultArray();
        if (count($roots) !== 1 || (string)$roots[0]['id'] !== $id) {
            throw new DomainException('EVALUATION_ORGANIZATION_HINT_MISMATCH');
        }
    }

    /** Fail closed: MyISAM or unverifiable transaction engines cannot be written. */
    private function assertTransactionEngines(): void
    {
        $prefix = (string)$this->db->DBPrefix;
        if ($prefix !== '' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $prefix) !== 1) {
            throw new DomainException('PRODUCT_CONVERSION_CONNECTION_INVALID');
        }
        $tables = array_map(static fn (string $name): string => $prefix.$name,
            ['products', 'product_uoms', 'product_uom_conversions']);
        try {
            $result = $this->db->query(
                'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES'
                .' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?, ?)', $tables,
            );
            if ($result === false) throw new UnexpectedValueException('Engine metadata unavailable.');
            $found = [];
            foreach ($result->getResultArray() as $row) {
                $found[strtolower((string)$row['TABLE_NAME'])] = strtoupper((string)$row['ENGINE']);
            }
            foreach ($tables as $table) {
                if (($found[strtolower($table)] ?? null) !== 'INNODB') {
                    throw new UnexpectedValueException('Required transactional table is not InnoDB.');
                }
            }
        } catch (Throwable $e) {
            throw new DomainException('PRODUCT_CONVERSION_CONNECTION_INVALID', [], $e);
        }
    }

    /** @param callable():bool $fn */
    private function perform(string $action, callable $fn): void
    {
        try {
            if (!$fn()) throw new UnexpectedValueException('SQL write was rejected.');
        } catch (Throwable $e) {
            $err = (int)($this->db->error()['code'] ?? 0);
            if ($err === 1062 || (int)$e->getCode() === 1062) {
                throw new DomainException('PRODUCT_CONVERSION_DUPLICATE', [], $e);
            }
            throw new DomainException('PRODUCT_CONVERSION_MUTATION_FAILED', ['operation'=>$action], $e);
        }
    }

    /** Same advisory mutex as ProductMutationService and ProductImageService. */
    private function lockName(): string
    {
        $name = 'dinkerp:'.(defined('ENVIRONMENT') ? ENVIRONMENT : 'development')
            .':'.$this->db->getDatabase().':product-master';
        return strlen($name) <= 64 ? $name : substr($name, 0, 30).':'.substr(hash('sha256', $name), 0, 32);
    }

    /** @template T @param callable():T $work @return T */
    private function transaction(callable $work): mixed
    {
        if ($this->db->transDepth !== 0) {
            throw new DomainException('PRODUCT_CONVERSION_MUTATION_FAILED', ['operation'=>'nested_transaction']);
        }
        $this->assertTransactionEngines();
        $name = $this->lockName();
        $lock = $this->db->query('SELECT GET_LOCK('.$this->db->escape($name).', 10) AS acquired');
        if ($lock === false || (int)($lock->getRowArray()['acquired'] ?? 0) !== 1) {
            throw new DomainException('PRODUCT_MUTATION_LOCK_FAILED');
        }
        try {
            if (!$this->db->transBegin()) throw new DomainException('PRODUCT_CONVERSION_MUTATION_FAILED', ['operation'=>'begin']);
            try {
                $value = $work();
                if (!$this->db->transStatus() || !$this->db->transCommit()) {
                    throw new DomainException('PRODUCT_CONVERSION_MUTATION_FAILED', ['operation'=>'commit']);
                }
                return $value;
            } catch (Throwable $e) {
                $this->db->transRollback();
                $this->db->resetTransStatus();
                throw $e;
            }
        } finally {
            $this->db->query('SELECT RELEASE_LOCK('.$this->db->escape($name).')');
        }
    }
}
