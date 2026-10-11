<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Repositories\ResourceCatalogRepository;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Compiles only *proven row-equivalent* State/Organization buckets.
 * AuthorizationService remains the single source of PBAC decisions.
 *
 * MASTER rows have GROUP evaluation scope regardless of row identity.
 * Employee Assignments have authoritative organization_id scope. In both
 * cases stateForAuthorization reads only the persisted State. No other
 * resource is accepted by this compiler.
 */
final class EffectiveListBucketCompiler
{
    /** @var array<string, array{table:string,domain:string,scoped:bool}> */
    private const RESOURCES = [
        'province' => ['table' => 'provinces', 'domain' => 'MASTER', 'scoped' => false],
        'ward' => ['table' => 'wards', 'domain' => 'MASTER', 'scoped' => false],
        'department' => ['table' => 'departments', 'domain' => 'MASTER', 'scoped' => false],
        'position' => ['table' => 'positions', 'domain' => 'MASTER', 'scoped' => false],
        'employee' => ['table' => 'employees', 'domain' => 'MASTER', 'scoped' => false],
        'employee_assignment' => ['table' => 'employee_assignments', 'domain' => 'ORGANIZATION', 'scoped' => true],
        'product' => ['table' => 'products', 'domain' => 'MASTER', 'scoped' => false],
    ];

    public const MAX_BUCKETS = 64;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly AuthorizationService $authorization,
    ) {
    }

    /**
     * @param callable(DomainException):bool $isListDenied
     * @return list<array{status:string, organization_id?:int}>|null
     *   null means not provably safe: call the original per-row path.
     */
    public function compile(string $resource, OperationContext $operation, callable $isListDenied): ?array
    {
        $config = self::RESOURCES[$resource] ?? null;
        if ($config === null) {
            return null;
        }
        // Fail closed by delegating when the catalog was customized to a
        // different domain. Do NOT silently apply MASTER scope assumptions.
        $metadata = (new ResourceCatalogRepository($this->db))->findResourceByType($resource);
        if ($metadata === null || ($metadata['domain'] ?? null) !== $config['domain']) {
            return null;
        }

        $builder = $this->db->table($config['table'])
            ->select('MIN(id) AS sample_id, HEX(status) AS status_hex', false)
            ->groupBy('HEX(status)', false)
            ->orderBy('status_hex', 'ASC')
            ->limit(self::MAX_BUCKETS + 1);
        if ($config['scoped']) {
            $builder->select('organization_id')->groupBy('organization_id')->orderBy('organization_id', 'ASC');
        }
        $result = $builder->get();
        if ($result === false) {
            throw new \RuntimeException('PBAC state bucket projection failed.');
        }
        $buckets = $result->getResultArray();
        if (count($buckets) > self::MAX_BUCKETS) {
            return null;
        }
        $approved = [];
        foreach ($buckets as $bucket) {
            $sample = $this->db->table($config['table'])->where('id', (int) $bucket['sample_id'])->get();
            if ($sample === false) {
                throw new \RuntimeException('PBAC state bucket sample failed.');
            }
            $target = $sample->getRowArray();
            if ($target === null || ! array_key_exists('status', $target)
                || strtoupper(bin2hex((string) $target['status'])) !== strtoupper((string) ($bucket['status_hex'] ?? ''))) {
                return null; // concurrent deletion / changed group membership
            }
            if ($config['scoped'] && (string) ($target['organization_id'] ?? '') !== (string) ($bucket['organization_id'] ?? '')) {
                return null;
            }
            try {
                $decision = $this->authorization->authorize(
                    $operation,
                    $resource,
                    'view',
                    new EvaluationOrganizationRequest(persistedTarget: $target),
                );
                if (! $decision->isAllowed()) {
                    continue;
                }
            } catch (DomainException $exception) {
                if ($isListDenied($exception)) {
                    continue;
                }
                throw $exception;
            }
            $one = ['status' => (string) $target['status']];
            if ($config['scoped']) {
                $one['organization_id'] = (int) $target['organization_id'];
            }
            $approved[] = $one;
        }
        return $approved;
    }
}
