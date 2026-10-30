<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use App\Exceptions\DomainException;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * SUP-T02 participant for the future SupplierService create transaction.
 * The parent MUST hold its Supplier-master advisory lock + physical DB tx and
 * persist Supplier + Company Usage in the callback. This class never commits.
 * No default series is seeded and no PBAC permissions are granted here.
 */
final class SupplierNumberIssuer
{
    public function __construct(private readonly BaseConnection $db) {}

    /**
     * @param callable(string,string):string $persist Receives (code, trusted GROUP id), inserts
     *     Supplier and Company Usage in the SAME transaction, and returns Supplier ID.
     */
    public function issue(?string $requestedSeries, ?string $manualCode, callable $persist): string
    {
        if ($this->db->transDepth < 1) {
            throw new DomainException('NUM_COUNTER_TRANSACTION_FAILED');
        }
        // NumberingService locks the same resource row before editing series.
        // FOR UPDATE therefore serializes config changes with issuer selection.
        $resourceQuery = $this->db->table('resources')->where('type', 'supplier');
        $resourceResult = $this->db->query($resourceQuery->getCompiledSelect() . ' FOR UPDATE');
        if ($resourceResult === false) throw new DomainException('NUM_IDENTIFIER_RESOURCE_NOT_FOUND');
        $resource = $resourceResult->getRowArray();
        if ($resource === null || ($resource['domain'] ?? null) !== 'MASTER'
            || ($resource['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException('NUM_IDENTIFIER_RESOURCE_NOT_FOUND');
        }
        $resourceId = (string)$resource['id'];
        $defaultQuery = $this->db->table('auto_numbering_series')
            ->where('resource_id', $resourceId)->where('is_default', 1);
        $defaultResult = $this->db->query($defaultQuery->getCompiledSelect() . ' FOR UPDATE');
        if ($defaultResult === false) throw new DomainException('NUM_COUNTER_CONFIGURATION_INVALID');
        $defaults = $defaultResult->getResultArray();
        $requestedCode = SupplierNumberingPolicy::requestedCode($requestedSeries);
        $requestedRow = null;
        if ($requestedCode !== null) {
            $requestedQuery = $this->db->table('auto_numbering_series')
                ->where('resource_id', $resourceId)->where('series_code', $requestedCode);
            $requestedResult = $this->db->query($requestedQuery->getCompiledSelect() . ' FOR UPDATE');
            if ($requestedResult === false) throw new DomainException('NUM_COUNTER_CONFIGURATION_INVALID');
            $requestedRow = $requestedResult->getRowArray();
        }
        $choice = SupplierNumberingPolicy::choose($defaults, $requestedCode, $manualCode, $requestedRow);

        $groupQuery = $this->db->table('organizations')->where('type', 'GROUP')
            ->where('parent_id', null);
        $groupResult = $this->db->query($groupQuery->getCompiledSelect() . ' FOR UPDATE');
        if ($groupResult === false) throw new DomainException('NUM_GROUP_ROOT_INVALID');
        $roots = $groupResult->getResultArray();
        if (count($roots) !== 1 || ($roots[0]['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException('NUM_GROUP_ROOT_INVALID');
        }
        $group = $roots[0];
        $groupId = (string)$group['id'];
        if ($choice['kind'] === 'MANUAL') {
            $this->assertFree($groupId, $choice['code']);
            return $persist($choice['code'], $groupId);
        }
        $series = $choice['series'];
        $zone = $this->businessTimezone($groupId);
        $now = new DateTimeImmutable('now', $zone);
        $period = SupplierNumberingPolicy::periodKey($series, $now);
        $counterQuery = $this->db->table('auto_numbering_counters')
            ->where('numbering_series_id', $series['id'])
            ->where('scope_organization_id', $groupId)->where('period_key', $period);
        $counterResult = $this->db->query($counterQuery->getCompiledSelect() . ' FOR UPDATE');
        if ($counterResult === false) throw new DomainException('NUM_COUNTER_TRANSACTION_FAILED');
        $counter = $counterResult->getRowArray();
        $increment = SupplierNumberingPolicy::number($series['increment_by'], 1);
        $candidate = $counter === null
            ? SupplierNumberingPolicy::number($series['start_number'], 0)
            : SupplierNumberingPolicy::next(SupplierNumberingPolicy::number($counter['last_value'] ?? null, 0), $increment);

        $issuedCode = null;
        for ($attempt = 0; $attempt < SupplierNumberingPolicy::maxAttempts(); ++$attempt) {
            $code = SupplierNumberingPolicy::render((string)$series['pattern'], $candidate, $now, (string)($group['code'] ?? ''));
            if ($this->available($groupId, $code)) {
                $issuedCode = $code;
                break;
            }
            $candidate = SupplierNumberingPolicy::next($candidate, $increment);
        }
        if ($issuedCode === null) throw new DomainException('NUM_COUNTER_CANDIDATE_EXHAUSTED');
        // The callback must execute BEFORE the counter mutation. A failure rolls
        // back the owning transaction, so there is no counter-only increment.
        $id = $persist($issuedCode, $groupId);
        $at = gmdate('Y-m-d H:i:s');
        if ($counter === null) {
            $ok = $this->db->table('auto_numbering_counters')->insert([
                'numbering_series_id' => $series['id'], 'scope_organization_id' => $groupId,
                'period_key' => $period, 'last_value' => $candidate,
                'created_at' => $at, 'updated_at' => $at,
            ]);
        } else {
            $ok = $this->db->table('auto_numbering_counters')->where('id', $counter['id'])
                ->update(['last_value' => $candidate, 'updated_at' => $at]);
        }
        if (!$ok) throw new DomainException('NUM_COUNTER_TRANSACTION_FAILED');
        return $id;
    }

    private function available(string $groupId, string $code): bool
    {
        return $this->db->table('suppliers')->select('id')
            ->where('group_organization_id', $groupId)->where('supplier_code', $code)
            ->get()->getRowArray() === null;
    }

    private function assertFree(string $groupId, string $code): void
    {
        if (!$this->available($groupId, $code)) {
            throw new DomainException('SUPPLIER_CODE_DUPLICATE', ['field' => 'supplier_code']);
        }
        // SUP-T04 must also catch DB 1062 from the Group/code UNIQUE index.
    }

    private function businessTimezone(string $groupId): DateTimeZone
    {
        try {
            $settings = \CodeIgniter\Settings\Config\Services::settings();
            try { $value = $settings->get('App.businessTimezone', 'group:' . $groupId); }
            catch (\CodeIgniter\Database\Exceptions\DatabaseException) { $value = null; }
            if (!is_string($value) || $value === '') {
                try { $value = $settings->get('App.businessTimezone'); }
                catch (\CodeIgniter\Database\Exceptions\DatabaseException) { $value = null; }
            }
            return new DateTimeZone(is_string($value) && $value !== '' ? $value : 'Asia/Ho_Chi_Minh');
        } catch (Throwable $e) {
            throw new DomainException('NUM_BUSINESS_TIMEZONE_RESOLUTION_FAILED', [], $e);
        }
    }
}
