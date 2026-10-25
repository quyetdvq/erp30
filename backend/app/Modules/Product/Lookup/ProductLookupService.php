<?php

declare(strict_types=1);

namespace App\Modules\Product\Lookup;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
use UnexpectedValueException;

/** M6.4A.1: PBAC State predicate in SQL BEFORE COUNT, page or batch resolution. */
final class ProductLookupService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;

    public function __construct(User $actor, ?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->access = new UnifiedAccessService($actor, $this->db);
    }

    /** @param array<string,mixed> $raw
     *  @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int}
     */
    public function options(array $raw): array
    {
        try { $query = ProductLookupRules::optionsQuery($raw); }
        catch (InvalidArgumentException $error) { throw new BadRequestException('Invalid Product selector query.', 0, $error); }
        $this->requireBaseView('product');
        $viewable = $this->access->viewableMasterLifecycleStatuses('product');
        if (! in_array('ACTIVE', $viewable, true)) {
            return ['data'=>[], 'page'=>$query['page'], 'page_size'=>$query['page_size'], 'total'=>0];
        }
        $plan = ProductLookupSql::options($query, $viewable, (string) $this->db->DBPrefix);
        $countRow = $this->query($plan['count_sql'], $plan['count_binds'])->getRowArray();
        $total = (int) ($countRow['total'] ?? 0);
        if ($total === 0) {
            return ['data'=>[], 'page'=>$query['page'], 'page_size'=>$query['page_size'], 'total'=>0];
        }
        $rows = $this->query($plan['rows_sql'], $plan['rows_binds'])->getResultArray();
        $data = [];
        foreach ($rows as $row) {
            $this->assertAuthorizedRow('product', $row);
            $data[] = ProductLookupRules::option(ProductLookupProjection::fromJoined($row));
        }
        return ['data'=>$data, 'page'=>$query['page'], 'page_size'=>$query['page_size'], 'total'=>$total];
    }

    /** @param array<string,mixed> $raw @return list<array<string,mixed>> */
    public function resolveProducts(array $raw): array
    {
        $ids = $this->readIds($raw, 'product_ids');
        $this->requireBaseView('product');
        if ($ids === []) return [];
        $viewable = $this->access->viewableMasterLifecycleStatuses('product');
        if ($viewable === []) return [];
        $plan = ProductLookupSql::productsByIds($ids, $viewable, (string) $this->db->DBPrefix);
        $visible = [];
        foreach ($this->query($plan['sql'], $plan['binds'])->getResultArray() as $row) {
            $this->assertAuthorizedRow('product', $row);
            $visible[(string)$row['id']] = ProductLookupProjection::fromJoined($row);
        }
        return ProductLookupRules::resolveProducts($ids, $visible);
    }

    /** @param array<string,mixed> $raw @return list<array{id:string,code:string,status:string}> */
    public function resolveUomLabels(array $raw): array
    {
        $ids = $this->readIds($raw, 'uom_ids');
        $this->requireBaseView('product_uom');
        if ($ids === []) return [];
        $viewable = $this->access->viewableMasterLifecycleStatuses('product_uom');
        if ($viewable === []) return [];
        $plan = ProductLookupSql::uomsByIds($ids, $viewable, (string) $this->db->DBPrefix);
        $visible = [];
        foreach ($this->query($plan['sql'], $plan['binds'])->getResultArray() as $row) {
            $this->assertAuthorizedRow('product_uom', $row);
            $visible[(string)$row['id']] = $row;
        }
        return ProductLookupRules::resolveUoms($ids, $visible);
    }

    /** Defense-in-depth: check only bounded result rows, never the whole catalog. */
    private function assertAuthorizedRow(string $type, array $row): void
    {
        if (! $this->access->canView($type, $row)) {
            throw new UnexpectedValueException('SQL VIEW scope and persisted PBAC decision disagree.');
        }
    }

    /** @param list<mixed> $binds */
    private function query(string $sql, array $binds): object
    {
        $result = $this->db->query($sql, $binds);
        if (! is_object($result)) {
            throw new UnexpectedValueException('Product lookup SQL failed.');
        }
        return $result;
    }

    /** @param array<string,mixed> $raw @return list<string> */
    private function readIds(array $raw, string $field): array
    {
        try { return ProductLookupRules::ids($raw, $field); }
        catch (InvalidArgumentException $error) { throw new BadRequestException('Invalid Product lookup ID list.', 0, $error); }
    }

    private function requireBaseView(string $resource): void
    {
        if (!$this->access->hasBaseViewPermission($resource)) {
            throw new DomainException(strtoupper($resource) . '_AUTHORIZATION_DENIED', ['action'=>'view']);
        }
    }
}
