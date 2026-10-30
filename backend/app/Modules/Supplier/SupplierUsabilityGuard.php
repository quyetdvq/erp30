<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * SUP-T05 new-Purchase guard. A participant in the Purchase-owned write
 * transaction, NOT a transaction owner and NOT a read/list selector.
 *
 * The Purchase adapter must lock and authorize the PERSISTED Purchase before
 * returning its Company. This guard locks Supplier -> Company -> Usage facts
 * and validates Supplier VIEW PBAC using the same connection and transaction.
 * It never grants Purchase CREATE/EDIT, never uses client Company hints, and
 * never reads bank columns. No Purchase adapter exists in SUP-G1 yet.
 */
final class SupplierUsabilityGuard
{
    private readonly BaseConnection $db;

    public function __construct(private readonly User $actor, ?BaseConnection $connection = null)
    {
        $this->db = $connection ?? db_connect();
    }

    /**
     * @throws DomainException on denial, corruption, missing transaction or DB failure
     */
    public function assertUsableForNewPurchase(
        string $supplierId,
        string $persistedPurchaseId,
        AuthorizedPurchaseCompanyPort $purchase,
    ): void {
        // Row locks must survive until the Purchase mutation COMMIT. Opening a
        // second transaction/snapshot here would create a TOCTOU permission gap.
        if ($this->db->transDepth < 1) {
            throw new DomainException('SUPPLIER_PURCHASE_TRANSACTION_REQUIRED');
        }
        $supplierId = $this->trustedId($supplierId);
        $persistedPurchaseId = $this->trustedId($persistedPurchaseId);
        $companyId = $this->trustedId(
            $purchase->resolveAuthorizedLockedCompanyId($persistedPurchaseId, $this->actor, $this->db)
        );

        $access = new UnifiedAccessService(
            $this->actor,
            $this->db,
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
        $groupId = $access->trustedGroupRootId();

        $supplier = $this->lockedOne('suppliers', 'id, group_organization_id, status, all_companies', [
            'id'=>$supplierId, 'group_organization_id'=>$groupId,
        ]);
        if ($supplier === null || !$access->canView('supplier', $supplier)) {
            // Do not disclose even existence/state to an actor without VIEW.
            throw new DomainException('SUPPLIER_PURCHASE_NOT_AVAILABLE');
        }
        $company = $this->lockedOne('organizations', 'id, type, status, parent_id', [
            'id'=>$companyId,
        ]);
        if ($company === null) {
            throw new DomainException('SUPPLIER_PURCHASE_COMPANY_INELIGIBLE');
        }
        // On ALL mode, any persisted usage row is inconsistent; in list mode
        // seek only the exact authorized Purchase Company. Both queries are
        // indexed, bounded, and locked inside the Purchase write transaction.
        $all = (string)($supplier['all_companies'] ?? '');
        $where = ['supplier_id'=>$supplierId];
        if ($all === '0') $where['company_organization_id'] = $companyId;
        $usage = $this->lockedOne('supplier_company_usages', 'company_organization_id', $where);
        SupplierUsabilityPolicy::assertEligible(
            $supplier, $company, $groupId, $companyId, $usage !== null,
        );
    }

    /** @param array<string,string> $where @return array<string,mixed>|null */
    private function lockedOne(string $table, string $columns, array $where): ?array
    {
        $builder = $this->db->table($table)->select($columns);
        foreach ($where as $column => $value) $builder->where($column, $value);
        // Table/column names are hardcoded; query builder handles DBPrefix
        // and the values. Locking reads are mandatory for purchase correctness.
        $sql = $builder->limit(1)->getCompiledSelect() . ' FOR UPDATE';
        try { $result = $this->db->query($sql); }
        catch (Throwable $e) { throw new DomainException('SUPPLIER_PURCHASE_LOOKUP_FAILED', [], $e); }
        if ($result === false) {
            throw new DomainException('SUPPLIER_PURCHASE_LOOKUP_FAILED');
        }
        return $result->getRowArray();
    }

    private function trustedId(mixed $id): string
    {
        if (!is_string($id) || preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1
            || strlen($id) > 20
            || (strlen($id) === 20 && strcmp($id, '18446744073709551615') > 0)) {
            throw new DomainException('SUPPLIER_PURCHASE_TRUSTED_ID_INVALID');
        }
        return $id;
    }
}
