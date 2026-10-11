<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Repositories\EmployeeAssignmentRepository;
use CodeIgniter\Database\ConnectionInterface;
use DateTimeZone;

/**
 * Compiles SQL list restrictions from authoritative persisted targets and
 * AuthorizationService decisions. Never interprets ALLOW/DENY lanes directly.
 * A grouping is valid only if Scope ancestry, effective assignments, Resource
 * type, and persisted State are identical for the authorization decision.
 */
final class OwnerAwareListBucketCompiler
{
    public const MAX_DECISION_GROUPS = 128;
    public const MAX_CONTACT_OWNER_BUCKETS = 1024;
    public const MAX_SQL_MEMBERS = 12000;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly AuthorizationService $authorization,
    ) {
    }

    /** @return list<array{id:int,status:string}>|null */
    public function organizations(OperationContext $operation): ?array
    {
        $index = EffectiveOrganizationScopeIndex::build($this->db, $operation, ['organization']);
        if ($index === null) {
            return null;
        }
        $groups = [];
        foreach ($index->nodes() as $row) {
            $signature = $index->signature($row['id']);
            if ($signature === null) {
                return null;
            }
            $key = $signature . ':' . bin2hex($row['status']);
            $groups[$key]['sample'] ??= $row;
            $groups[$key]['rows'][] = $row;
            if (count($groups) > self::MAX_DECISION_GROUPS) {
                return null;
            }
        }
        $approved = [];
        foreach ($groups as $group) {
            $sample = $group['sample'];
            if (! $this->authorized($operation, 'organization', [
                'id' => $sample['id'], 'status' => $sample['status'],
            ])) {
                continue;
            }
            foreach ($group['rows'] as $row) {
                $approved[] = ['id' => $row['id'], 'status' => $row['status']];
                if (count($approved) > self::MAX_SQL_MEMBERS) {
                    return null;
                }
            }
        }
        return $approved;
    }

    /**
     * Each authorized bucket denotes a persisted owner AND an exact raw
     * Contact type/State. A Contact can never be admitted through the other
     * owner's Base Permission, even when both share one SQL table.
     *
     * @return list<array{contact_type:string,contact_ref_id:int,status:string}>|null
     */
    public function contacts(OperationContext $operation): ?array
    {
        $index = EffectiveOrganizationScopeIndex::build($this->db, $operation,
            ['organization_contact', 'employee_contact']);
        if ($index === null) {
            return null;
        }
        $result = $this->db->table('contacts')
            ->select('MIN(id) AS sample_id, contact_ref_id, HEX(contact_type) AS type_hex, HEX(status) AS status_hex', false)
            ->groupBy('contact_ref_id')
            ->groupBy('HEX(contact_type)', false)
            ->groupBy('HEX(status)', false)
            ->orderBy('sample_id', 'ASC')
            ->limit(self::MAX_CONTACT_OWNER_BUCKETS + 1)->get();
        if ($result === false) {
            throw new \RuntimeException('Contact owner/state projection failed.');
        }
        $rows = $result->getResultArray();
        if (count($rows) > self::MAX_CONTACT_OWNER_BUCKETS) {
            return null;
        }
        $ownerBuckets = [];
        $employeeIds = [];
        foreach ($rows as $row) {
            $ownerId = filter_var($row['contact_ref_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $sampleId = filter_var($row['sample_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $type = $this->decodeHex($row['type_hex'] ?? null);
            $status = $this->decodeHex($row['status_hex'] ?? null);
            if (! is_int($ownerId) || ! is_int($sampleId) || $type === null || $status === null) {
                return null;
            }
            try {
                $resource = ContactAuthorizationRegistry::resourceFor(['contact_type' => $type]);
            } catch (DomainException $exception) {
                if ($exception->getErrorCode() === ContactAuthorizationRegistry::ERROR_TYPE_UNSUPPORTED) {
                    continue; // Same as legacy list: unsupported kinds are invisible.
                }
                throw $exception;
            }
            $ownerBuckets[] = [
                'sample_id' => $sampleId, 'resource' => $resource,
                'contact_type' => $type, 'contact_ref_id' => $ownerId, 'status' => $status,
            ];
            if ($resource === 'employee_contact') {
                $employeeIds[$ownerId] = true;
            }
        }
        $employeeScopes = $this->employeePrimaryScopes(array_keys($employeeIds), $operation);
        $groups = [];
        foreach ($ownerBuckets as $bucket) {
            $organizationId = $bucket['resource'] === 'organization_contact'
                ? $bucket['contact_ref_id']
                : ($employeeScopes[$bucket['contact_ref_id']] ?? null);
            if ($organizationId === null) {
                continue; // No owner / exactly-one-primary: legacy list hides it.
            }
            $signature = $index->signature($organizationId);
            if ($signature === null) {
                continue; // Missing owner Organization: legacy list hides it.
            }
            $key = $bucket['resource'] . ':' . $signature . ':' . bin2hex($bucket['status']);
            $groups[$key]['sample'] ??= $bucket;
            $groups[$key]['members'][] = $bucket;
            if (count($groups) > self::MAX_DECISION_GROUPS) {
                return null;
            }
        }

        $approved = [];
        foreach ($groups as $group) {
            $sample = $group['sample'];
            $persisted = $this->db->table('contacts')->where('id', $sample['sample_id'])->get();
            if ($persisted === false) {
                throw new \RuntimeException('Contact PBAC representative lookup failed.');
            }
            $target = $persisted->getRowArray();
            if ($target === null || (string) ($target['contact_type'] ?? '') !== $sample['contact_type']
                || (string) ($target['status'] ?? '') !== $sample['status']
                || (int) ($target['contact_ref_id'] ?? 0) !== $sample['contact_ref_id']) {
                return null; // Concurrent changes; use authoritative legacy path.
            }
            if (! $this->authorized($operation, $sample['resource'], $target)) {
                continue;
            }
            foreach ($group['members'] as $member) {
                $approved[] = [
                    'contact_type' => $member['contact_type'],
                    'contact_ref_id' => $member['contact_ref_id'],
                    'status' => $member['status'],
                ];
            }
        }
        return $approved;
    }

    /**
     * Reproduce EmployeeContactScopeResolver's findEffective(primaryOnly=true)
     * contract in one batch. Reject absent/ambiguous/noncanonical primaries.
     * Missing Employee records are NOT valid owners.
     *
     * @param list<int> $employeeIds
     * @return array<int,int> employee_id => primary organization_id
     */
    private function employeePrimaryScopes(array $employeeIds, OperationContext $operation): array
    {
        if ($employeeIds === []) {
            return [];
        }
        $existing = [];
        foreach (array_chunk($employeeIds, 400) as $chunk) {
            $result = $this->db->table('employees')->select('id')->whereIn('id', $chunk)->get();
            if ($result === false) {
                throw new \RuntimeException('Employee Contact owner lookup failed.');
            }
            foreach ($result->getResultArray() as $row) {
                $existing[(int) $row['id']] = true;
            }
        }
        $date = $operation->evaluationTime->businessDate(new DateTimeZone('UTC'));
        $assignments = (new EmployeeAssignmentRepository($this->db))
            ->findEffectiveForEmployees(array_keys($existing), $date);
        $scopes = [];
        foreach ($assignments as $employeeId => $rows) {
            $primaries = array_values(array_filter($rows,
                static fn (array $row): bool => in_array($row['is_primary'] ?? null, [1, '1'], true)));
            if (count($primaries) !== 1 || ($primaries[0]['status'] ?? null) !== 'ACTIVE') {
                continue;
            }
            $organizationId = filter_var($primaries[0]['organization_id'] ?? null,
                FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (is_int($organizationId)) {
                $scopes[(int) $employeeId] = $organizationId;
            }
        }
        return $scopes;
    }

    /** @param array<string,mixed> $target */
    private function authorized(OperationContext $operation, string $resource, array $target): bool
    {
        try {
            return $this->authorization->authorize($operation, $resource, 'view',
                new EvaluationOrganizationRequest(persistedTarget: $target))->isAllowed();
        } catch (DomainException $exception) {
            if (in_array($exception->getErrorCode(), [
                AuthorizationService::REASON_POLICY_DENIED,
                AuthorizationService::REASON_NO_BASE_PERMISSION,
                EvaluationOrganizationResolver::ERROR_CONTEXT_NOT_FOUND,
                EmployeeContactScopeResolver::ERROR_PRIMARY_SCOPE_UNAVAILABLE,
            ], true)) {
                return false;
            }
            throw $exception;
        }
    }

    private function decodeHex(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/\A(?:[0-9A-Fa-f]{2})*\z/', $value) !== 1) {
            return null;
        }
        $decoded = hex2bin($value);
        return $decoded === false ? null : $decoded;
    }
}
