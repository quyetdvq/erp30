<?php

declare(strict_types=1);

namespace App\Modules\Product\Catalog;

use App\Exceptions\DomainException;
use App\Modules\Access\ResourceStateRepository;
use App\Modules\Access\Query\AuthorizationSqlScopeCompiler;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use Throwable;
use UnexpectedValueException;

/**
 * Product-owned M6.1 master catalog, no dependency on Product Master or
 * Pricing internals. Every row decision uses authoritative persisted PBAC.
 */
final class ProductCatalogService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly ResourceStateRepository $states;
    private readonly string $actorId;
    private readonly string $table;

    public function __construct(private readonly User $actor, private readonly string $resource, ?BaseConnection $connection = null)
    {
        ProductCatalogRules::type($resource);
        $this->table = $resource === 'product_group' ? 'product_groups' : 'product_uoms';
        $this->db = $connection ?? db_connect();
        $this->access = new UnifiedAccessService($actor, $this->db);
        $this->states = new ResourceStateRepository($this->db);
        $this->actorId = ProductCatalogRules::id((string) $actor->id, $resource);
    }

    /** @param array<string,mixed>|null $query @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int} */
    public function list(?array $query = null): array
    {
        $parameters = ProductCatalogSql::normalize($this->resource, $query ?? []);
        return $this->readSnapshot(function (UnifiedAccessService $access) use ($parameters): array {
            // Preserve the empty-list base permission gate. The authoritative
            // SQL scope is compiled from canonical State/MASTER policy decisions.
            if (!$access->hasBaseViewPermission($this->resource)) {
                throw new DomainException('AUTH_POLICY_DENIED', ['action'=>'view']);
            }
            $scope = (new AuthorizationSqlScopeCompiler($access))
                ->compile(new ProductCatalogScopeProvider($this->resource));
            $plan = ProductCatalogSql::collection($this->resource, $parameters, $scope,
                (string)$this->db->DBPrefix);
            $countQuery = $this->db->query($plan['count_sql'], $plan['count_binds']);
            if ($countQuery === false) throw new UnexpectedValueException('Catalog scoped COUNT failed.');
            $total = self::validCount($countQuery->getRowArray()['total'] ?? null);
            if ($total === 0) {
                return ['data'=>[], 'page'=>$parameters['page'],
                    'page_size'=>$parameters['page_size'], 'total'=>0];
            }
            $result = $this->db->query($plan['rows_sql'], $plan['rows_binds']);
            if ($result === false) throw new UnexpectedValueException('Catalog scoped page query failed.');
            $data = $result->getResultArray();
            if (count($data) > $parameters['page_size']) {
                throw new UnexpectedValueException('Catalog SQL exceeded requested page size.');
            }
            foreach ($data as $row) {
                // Defense in depth. A scope/canonical decision mismatch must
                // fail the whole page, NEVER post-filter and misreport total.
                if (!$access->canView($this->resource, $row)) {
                    throw new UnexpectedValueException('AUTH_SCOPE_PBAC_MISMATCH: Product catalog page.');
                }
            }
            return ['data'=>$data, 'page'=>$parameters['page'],
                'page_size'=>$parameters['page_size'], 'total'=>$total];
        });
    }

    /** @return array<string,mixed> */
    public function show(string $id): array
    {
        $id = ProductCatalogRules::id($id, $this->resource);
        return $this->readSnapshot(function (UnifiedAccessService $access) use ($id): array {
            $scope = (new AuthorizationSqlScopeCompiler($access))
                ->compile(new ProductCatalogScopeProvider($this->resource));
            $plan = ProductCatalogSql::detail($this->resource, $id, $scope,
                (string)$this->db->DBPrefix);
            $result = $this->db->query($plan['sql'], $plan['binds']);
            if ($result === false) throw new UnexpectedValueException('Catalog scoped detail query failed.');
            $row = $result->getRowArray();
            if ($row === null) {
                // Keep the historical missing-id domain error distinct from
                // a hidden existing row, without retrieving protected columns.
                $found = $this->db->table($this->table)->select('id')->where('id', $id)
                    ->limit(1)->get()->getRowArray();
                if ($found === null) {
                    throw new DomainException(ProductCatalogRules::codePrefix($this->resource) . 'NOT_FOUND');
                }
                throw new PageNotFoundException('Product catalog entry is not available.');
            }
            if (!$access->canView($this->resource, $row)) {
                throw new UnexpectedValueException('AUTH_SCOPE_PBAC_MISMATCH: Product catalog detail.');
            }
            return $row;
        });
    }

    private static function validCount(mixed $raw): int
    {
        $text = is_string($raw) || is_int($raw) ? (string)$raw : '';
        $max = (string)PHP_INT_MAX;
        if (preg_match('/\A[0-9]+\z/D', $text) !== 1 || strlen($text) > strlen($max)
            || (strlen($text) === strlen($max) && strcmp($text, $max) > 0)) {
            throw new UnexpectedValueException('Invalid Product catalog count.');
        }
        return (int)$text;
    }

    /** A consistent read snapshot owns its transaction and never borrows writers. */
    private function readSnapshot(callable $work): mixed
    {
        if ($this->db->transDepth !== 0) {
            throw new UnexpectedValueException('Unexpected transaction in catalog GET.');
        }
        if ($this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') === false
            || !$this->db->transBegin()) {
            throw new UnexpectedValueException('Unable to open catalog consistent read snapshot.');
        }
        try {
            $access = new UnifiedAccessService($this->actor, $this->db);
            $value = $work($access);
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new UnexpectedValueException('Catalog read snapshot did not complete.');
            }
            return $value;
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $e;
        }
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(array $input): array
    {
        $payload = ProductCatalogRules::payload($this->resource, $input, true);
        $initial = $this->states->initialState($this->resource);
        if ($initial === null) throw new DomainException('WF_INITIAL_STATE_REQUIRED', ['resource'=>$this->resource]);
        if (isset($payload['status']) && $payload['status'] !== $initial) {
            throw new DomainException('WF_INITIAL_STATE_INVALID', ['field'=>'status']);
        }
        $payload['status'] = $initial;
        return $this->transaction(function () use ($payload): array {
            if ($this->resource === 'product_group') {
                ProductCatalogRules::assertAcyclicGroupParent($this->lockedGroupTree(), null, $payload['parent_id']);
            }
            $this->permit('create', null, $payload);
            $this->assertUniqueCode((string) $payload['code']);
            $id = $this->insert([...$payload, ...$this->createdAudit()]);
            return $this->visible($this->target($id));
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function update(string $id, array $input): array
    {
        $id = ProductCatalogRules::id($id, $this->resource);
        return $this->transaction(function () use ($id, $input): array {
            $old = $this->target($id, true);
            $this->assertVisible($old);
            $this->permit('edit', $old);
            $patch = ProductCatalogRules::payload($this->resource, $input, false);
            if ($this->resource === 'product_group') {
                $tree = $this->lockedGroupTree();
                if (array_key_exists('parent_id', $patch)) {
                    ProductCatalogRules::assertAcyclicGroupParent($tree, $id, $patch['parent_id']);
                }
            }
            if (isset($patch['code'])) $this->assertUniqueCode($patch['code'], $id);
            $this->updateRow($id, [...$patch, ...$this->updatedAudit()]);
            return $this->visible($this->target($id));
        });
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public function setState(string $id, array $request): array
    {
        $id = ProductCatalogRules::id($id, $this->resource);
        $requested = ProductCatalogRules::requestedState($this->resource, $request);
        return $this->transaction(function () use ($id, $requested): array {
            $old = $this->target($id, true);
            $this->assertVisible($old);
            $this->permit('setstate', $old);
            try { $next = $this->states->transitionTarget($this->resource, $old, 'setstate'); }
            catch (UnexpectedValueException $cause) {
                throw new DomainException('WF_INVALID_TRANSITION', ['field'=>'status'], $cause);
            }
            if ($requested !== $next) throw new DomainException('WF_INVALID_TRANSITION', ['field'=>'status']);
            $this->permit('setstate', $old, [], true);
            try {
                $ok = $this->db->table($this->table)->where('id', $id)
                    ->where('status', (string) $old['status'])
                    ->update(['status'=>$next, ...$this->updatedAudit()]);
            } catch (Throwable $cause) { $this->writeFailure($cause); }
            if (! $ok) $this->writeFailure();
            if ($next !== (string) $old['status'] && $this->db->affectedRows() !== 1) {
                throw new DomainException('WF_CONCURRENT_STATE_CHANGE');
            }
            return $this->visible($this->target($id));
        });
    }

    /** @return list<array<string,mixed>> */
    private function lockedGroupTree(): array
    {
        $sql = 'SELECT id, parent_id FROM ' . $this->db->prefixTable('product_groups') . ' ORDER BY id FOR UPDATE';
        $result = $this->db->query($sql);
        if ($result === false) $this->writeFailure();
        return $result->getResultArray();
    }

    private function assertUniqueCode(string $code, ?string $except = null): void
    {
        $builder = $this->db->table($this->table)->select('id')->where('code', $code);
        if ($except !== null) $builder->where('id !=', $except);
        if ($builder->limit(1)->get()->getRowArray() !== null) {
            throw new DomainException(ProductCatalogRules::codePrefix($this->resource) . 'DUPLICATE_CODE', ['field'=>'code']);
        }
    }

    /** @return array<string,mixed> */
    private function target(string $id, bool $lock = false): array
    {
        $builder = $this->db->table($this->table)->where('id', $id);
        $result = $lock ? $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE') : $builder->get();
        if ($result === false) $this->writeFailure();
        $row = $result->getRowArray();
        if ($row === null) throw new DomainException(ProductCatalogRules::codePrefix($this->resource) . 'NOT_FOUND');
        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function visible(array $row): array
    {
        $this->assertVisible($row);
        return $row;
    }

    /** @param array<string,mixed> $row */
    private function assertVisible(array $row): void
    {
        if (! $this->access->canView($this->resource, $row)) {
            throw new PageNotFoundException('Product catalog entry is not available.');
        }
    }

    /** @param array<string,mixed>|null $old @param array<string,mixed> $candidate */
    private function permit(string $action, ?array $old = null, array $candidate = [], bool $transition = false): void
    {
        $decision = $transition
            ? $this->access->decideTransitionAction($this->resource, $action, $old ?? [])
            : $this->access->decideMutation($this->resource, $action, $old, $candidate);
        if (! $decision['allowed']) {
            throw new DomainException(ProductCatalogRules::codePrefix($this->resource) . 'AUTHORIZATION_DENIED', ['action'=>$action]);
        }
    }

    /** @param array<string,mixed> $payload */
    private function insert(array $payload): string
    {
        try { $ok = $this->db->table($this->table)->insert($payload); }
        catch (Throwable $cause) { $this->writeFailure($cause); }
        if (! $ok) $this->writeFailure();
        return (string) $this->db->insertID();
    }

    /** @param array<string,mixed> $payload */
    private function updateRow(string $id, array $payload): void
    {
        try { $ok = $this->db->table($this->table)->where('id', $id)->update($payload); }
        catch (Throwable $cause) { $this->writeFailure($cause); }
        if (! $ok) $this->writeFailure();
    }

    /** @return array<string,string> */
    private function createdAudit(): array
    { return ['created_by'=>$this->actorId, 'created_at'=>gmdate('Y-m-d H:i:s')]; }

    /** @return array<string,string> */
    private function updatedAudit(): array
    { return ['updated_by'=>$this->actorId, 'updated_at'=>gmdate('Y-m-d H:i:s')]; }

    private function writeFailure(?Throwable $cause = null): never
    {
        $code = (int) ($this->db->error()['code'] ?? 0);
        if ($code === 1062 || (int) ($cause?->getCode() ?? 0) === 1062) {
            throw new DomainException(ProductCatalogRules::codePrefix($this->resource) . 'DUPLICATE_CODE', ['field'=>'code'], $cause);
        }
        if ($this->resource === 'product_group' && ($code === 1452 || (int) ($cause?->getCode() ?? 0) === 1452)) {
            throw new DomainException('PRODUCT_GROUP_PARENT_NOT_FOUND', ['field'=>'parent_id'], $cause);
        }
        throw new DomainException(ProductCatalogRules::codePrefix($this->resource) . 'PERSISTENCE_FAILED', [], $cause);
    }

    /** Same advisory lock name as the baseline's MariaDbDomainLockService. */
    private function groupLockKey(): string
    {
        $environment = defined('ENVIRONMENT') ? ENVIRONMENT : 'development';
        $name = 'dinkerp:' . $environment . ':' . $this->db->getDatabase() . ':product-group-tree';
        return strlen($name) <= 64 ? $name
            : substr($name, 0, 31) . ':' . substr(hash('sha256', $name), 0, 32);
    }

    /** @template T @param callable():T $work @return T */
    private function transaction(callable $work): mixed
    {
        // Product Group hierarchy and code changes are serialized across HTTP
        // workers; UOM retains its native UNIQUE(code) conflict guarantee.
        $lock = $this->resource === 'product_group' ? $this->groupLockKey() : null;
        if ($lock !== null) {
            $result = $this->db->query('SELECT GET_LOCK(' . $this->db->escape($lock) . ', 10) AS acquired');
            if ($result === false || (int) ($result->getRowArray()['acquired'] ?? 0) !== 1) {
                throw new DomainException('PRODUCT_GROUP_PERSISTENCE_FAILED');
            }
        }
        try {
            if (! $this->db->transBegin()) $this->writeFailure();
            try {
                $value = $work();
                if (! $this->db->transStatus() || ! $this->db->transCommit()) $this->writeFailure();
                return $value;
            } catch (Throwable $cause) {
                $this->db->transRollback();
                $this->db->resetTransStatus();
                throw $cause;
            }
        } finally {
            if ($lock !== null) $this->db->query('SELECT RELEASE_LOCK(' . $this->db->escape($lock) . ')');
        }
    }
}
