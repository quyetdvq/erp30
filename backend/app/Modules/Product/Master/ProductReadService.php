<?php

declare(strict_types=1);

namespace App\Modules\Product\Master;

use App\Exceptions\DomainException;
use App\Modules\Access\Query\AuthorizationSqlScopeCompiler;
use App\Modules\Access\Query\EffectiveActionHintCache;
use App\Modules\Access\UnifiedAccessService;
use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

/** M8.5 Product READ: authorization in SQL BEFORE count/page, bounded DTO/hints. */
final class ProductReadService
{
    private readonly BaseConnection $db;
    private UnifiedAccessService $access;

    public function __construct(private readonly User $actor, ?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string,mixed> $query @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int} */
    public function list(array $query): array
    {
        $parameters = ProductReadSql::normalize(ProductReadRules::listQuery($query));
        return $this->snapshot(function () use ($parameters): array {
            if (!$this->access->hasBaseViewPermission('product')) {
                throw new DomainException('PRODUCT_AUTHORIZATION_DENIED', ['action'=>'view']);
            }
            $scope = (new AuthorizationSqlScopeCompiler($this->access))
                ->compile(new ProductScopeProvider());
            $plan = ProductReadSql::collection($parameters, $scope, (string) $this->db->DBPrefix);
            $result = $this->db->query($plan['count_sql'], $plan['count_binds']);
            if ($result === false) throw new UnexpectedValueException('Product scoped COUNT failed.');
            $total = $this->validCount($result->getRowArray()['total'] ?? null);
            if ($total === 0) {
                return ['data'=>[], 'page'=>$parameters['page'], 'page_size'=>$parameters['page_size'], 'total'=>0];
            }
            $result = $this->db->query($plan['rows_sql'], $plan['rows_binds']);
            if ($result === false) throw new UnexpectedValueException('Product scoped page query failed.');
            $hints = new EffectiveActionHintCache();
            $data = [];
            foreach ($result->getResultArray() as $row) {
                if (!$this->access->canView('product', $row)) {
                    throw new UnexpectedValueException('AUTH_SCOPE_PBAC_MISMATCH: Product page row.');
                }
                $dto = ProductReadRules::describeJoined($row);
                $dto['allowed_actions'] = $hints->forProduct($this->access, $row);
                $data[] = $dto;
            }
            return ['data'=>$data, 'page'=>$parameters['page'],
                'page_size'=>$parameters['page_size'], 'total'=>$total];
        });
    }

    /** @return array<string,mixed> */
    public function show(string $rawId): array
    {
        try { $id = PositiveId::value($rawId); }
        catch (InvalidArgumentException $error) {
            throw new DomainException('PRODUCT_NOT_FOUND', ['field'=>'id'], $error);
        }
        return $this->snapshot(function () use ($id): array {
            if (!$this->access->hasBaseViewPermission('product')) {
                throw new DomainException('PRODUCT_AUTHORIZATION_DENIED', ['action'=>'view']);
            }
            $scope = (new AuthorizationSqlScopeCompiler($this->access))
                ->compile(new ProductScopeProvider());
            $plan = ProductReadSql::detail($id, $scope, (string) $this->db->DBPrefix);
            $result = $this->db->query($plan['sql'], $plan['binds']);
            if ($result === false) throw new UnexpectedValueException('Product scoped detail query failed.');
            $row = $result->getRowArray();
            if ($row === null) throw new PageNotFoundException('Product is not available.');
            if (!$this->access->canView('product', $row)) {
                throw new UnexpectedValueException('AUTH_SCOPE_PBAC_MISMATCH: Product detail row.');
            }
            $dto = ProductReadRules::describeJoined($row);
            $dto['allowed_actions'] = (new EffectiveActionHintCache())->forProduct($this->access, $row);
            return $dto;
        }, true);
    }

    /** Guard COUNT against overflow before casting; no hidden/unauthorized total. */
    private function validCount(mixed $value): int
    {
        $text = is_string($value) || is_int($value) ? (string)$value : '';
        if (preg_match('/\A[0-9]+\z/D', $text) !== 1
            || strlen($text) > strlen((string)PHP_INT_MAX)
            || (strlen($text) === strlen((string)PHP_INT_MAX) && strcmp($text, (string)PHP_INT_MAX) > 0)) {
            throw new UnexpectedValueException('Invalid or out-of-range Product scoped COUNT.');
        }
        return (int)$text;
    }

    /**
     * COUNT, page and bounded PBAC re-check must use one DB snapshot.
     * ProductMutationService::visible() calls show() before its write transaction
     * commits. In that one reviewed case, borrow the active transaction and
     * NEVER modify its isolation level, commit it or roll it back. A standalone
     * list must never borrow an unknown transaction.
     * @template T @param callable():T $work @return T
     */
    private function snapshot(callable $work, bool $allowBorrowedDetail = false): mixed
    {
        if ($this->db->transDepth !== 0) {
            if (!$allowBorrowedDetail || !$this->db->transStatus()) {
                throw new UnexpectedValueException('Unexpected transaction in Product read.');
            }
            // The writer owns the physical transaction and all its rollback
            // responsibility. Never open/commit a nested CI4 fake transaction.
            $this->access = new UnifiedAccessService($this->actor, $this->db);
            return $work();
        }
        if ($this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') === false
            || !$this->db->transBegin()) {
            throw new UnexpectedValueException('Unable to open Product consistent read snapshot.');
        }
        try {
            // Actor, policies, COUNT, page and cross-check share one InnoDB snapshot.
            $this->access = new UnifiedAccessService($this->actor, $this->db);
            $value = $work();
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new UnexpectedValueException('Product read snapshot did not complete.');
            }
            return $value;
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $e;
        }
    }
}
