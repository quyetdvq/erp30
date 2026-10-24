<?php

declare(strict_types=1);

namespace App\Modules\Product\Master;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use App\Shared\Http\AuthorizedListPage;
use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;

/** Frozen Product Master GET contract, with persisted row authorization before totals/pages. */
final class ProductReadService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;

    public function __construct(private readonly User $actor, ?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->access = new UnifiedAccessService($actor, $this->db);
    }

    /** @param array<string,mixed> $query @return array{data:list<array<string,mixed>>,page:int,page_size:int,total:int} */
    public function list(array $query): array
    {
        // Explicit base VIEW check even when the Product table is empty.
        if (! $this->access->hasBaseViewPermission('product')) {
            throw new DomainException('PRODUCT_AUTHORIZATION_DENIED', ['action' => 'view']);
        }
        // One consistent Product snapshot supplies both authority and derived roles.
        // Hidden rows may describe a child's structure but must never enter the result.
        $raw = $this->allRows();
        $all = ProductReadRules::describe($raw);
        $visible = [];
        foreach ($raw as $row) {
            if (! $this->access->canView('product', $row)) continue;
            $id = (string) $row['id'];
            $visible[] = $this->withAllowedActions($all[$id], $row);
        }
        return AuthorizedListPage::paginate('product', $visible, ProductReadRules::listQuery($query));
    }

    /** @return array<string,mixed> */
    public function show(string $rawId): array
    {
        try { $id = PositiveId::value($rawId); }
        catch (InvalidArgumentException $error) {
            throw new DomainException('PRODUCT_NOT_FOUND', ['field' => 'id'], $error);
        }
        $original = $this->persistedRow($id);
        if (! $this->access->canView('product', $original)) {
            throw new PageNotFoundException('Product is not available.');
        }
        // Derive structural role against the full catalog, never only current page.
        $catalog = ProductReadRules::describe($this->allRows());
        if (! isset($catalog[$id])) throw new DomainException('PRODUCT_NOT_FOUND');
        return $this->withAllowedActions($catalog[$id], $original);
    }

    /** @return list<array<string,mixed>> */
    private function allRows(): array
    {
        return $this->db->table('products')->orderBy('id', 'ASC')->get()->getResultArray();
    }

    /** @return array<string,mixed> */
    private function persistedRow(string $id): array
    {
        $row = $this->db->table('products')->where('id', $id)->get()->getRowArray();
        if ($row === null) throw new DomainException('PRODUCT_NOT_FOUND');
        return $row;
    }

    /** @param array<string,mixed> $dto @param array<string,mixed> $persisted @return array<string,mixed> */
    private function withAllowedActions(array $dto, array $persisted): array
    {
        $dto['allowed_actions'] = [
            'edit' => $this->access->decideMutation('product', 'edit', $persisted)['allowed'],
            'setstate' => $this->access->decideMutation('product', 'setstate', $persisted)['allowed'],
        ];
        return $dto;
    }
}
