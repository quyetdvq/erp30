<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Repositories\ResourceCatalogRepository;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Compile exact persisted (owner Organization, raw State) Price List buckets.
 *
 * PriceList's registered scope resolver reads ONLY the persisted
 * organization_id and State authorization reads ONLY the persisted status.
 * Therefore all rows with both values identical have the same PBAC inputs.
 * No policy decision is reimplemented in SQL; AuthorizationService evaluates
 * one real persisted representative per bucket. Unproven cases fall back.
 */
final class PriceListAuthorizedBucketCompiler
{
    public const MAX_BUCKETS = 256;

    /** @param ConnectionInterface<mixed, mixed> $db */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly AuthorizationService $authorization,
    ) {
    }

    /** @return list<array{organization_id:int,status:string}>|null */
    public function compile(OperationContext $operation): ?array
    {
        if ($operation->actorUserId === null || $operation->actorUserId < 1) {
            return null;
        }
        $resource = (new ResourceCatalogRepository($this->db))->findResourceByType('price_list');
        if ($resource === null || ($resource['domain'] ?? null) !== 'ORGANIZATION') {
            return null;
        }

        $result = $this->db->table('price_lists')
            ->select('MIN(id) AS sample_id, organization_id, HEX(status) AS state_hex', false)
            ->groupBy('organization_id')
            ->groupBy('HEX(status)', false)
            ->orderBy('sample_id', 'ASC')
            ->limit(self::MAX_BUCKETS + 1)
            ->get();
        if ($result === false) {
            throw new \RuntimeException('Price List owner/State bucket projection failed.');
        }
        $buckets = $result->getResultArray();
        if (count($buckets) > self::MAX_BUCKETS) {
            return null;
        }
        $approved = [];
        foreach ($buckets as $bucket) {
            $owner = filter_var($bucket['organization_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $sampleId = filter_var($bucket['sample_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $hex = $bucket['state_hex'] ?? null;
            if (! is_int($owner) || ! is_int($sampleId) || ! is_string($hex)
                || $hex === '' || preg_match('/\A(?:[0-9A-Fa-f]{2})+\z/', $hex) !== 1) {
                return null;
            }
            $status = hex2bin($hex);
            if (! is_string($status)) {
                return null;
            }
            $representative = $this->db->table('price_lists')->where('id', $sampleId)->get();
            if ($representative === false) {
                throw new \RuntimeException('Price List PBAC representative lookup failed.');
            }
            $target = $representative->getRowArray();
            if ($target === null || (int) ($target['organization_id'] ?? 0) !== $owner
                || ! is_string($target['status'] ?? null) || $target['status'] !== $status) {
                return null;
            }
            try {
                $decision = $this->authorization->authorize($operation, 'price_list', 'view',
                    new EvaluationOrganizationRequest(persistedTarget: $target));
            } catch (DomainException $_) {
                // Preserve legacy error/visibility behavior by rerunning its
                // per-row path; never interpret unknown context errors as ALLOW.
                return null;
            }
            if ($decision->isAllowed()) {
                $approved[] = ['organization_id' => $owner, 'status' => $status];
            }
        }
        return $approved;
    }
}
