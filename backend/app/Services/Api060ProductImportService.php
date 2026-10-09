<?php

declare(strict_types=1);

namespace App\Services;

use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\AuthorizedArrayListSource;
use App\Infrastructure\Api\CommonListQueryAdapter;
use App\Infrastructure\Api\CommonListQueryAllowlist;
use App\Infrastructure\Import\Persistence\ImportJobRepository;
use App\Infrastructure\Import\Persistence\ImportJobRowRepository;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\RequestContext;
use App\Modules\Product\Import\ProductImportAuthorizationService;
use App\Modules\Product\Import\ProductImportJobService;
use App\Modules\Product\Import\ProductImportRowMutationService;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\Shield\Entities\User;

/** Application boundary for the four CONTRACT_READY P1-API-060 operations. */
final class Api060ProductImportService
{
    public const ERROR_FILE_REQUIRED = 'IMPORT_FILE_REQUIRED';
    public const ERROR_FILE_INVALID = 'IMPORT_FILE_INVALID';
    public const ERROR_FILE_TOO_LARGE = 'IMPORT_FILE_TOO_LARGE';

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?ConnectionInterface $db = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?ProductImportAuthorizationService $imports = null,
        private readonly ?ImportJobRepository $jobs = null,
        private readonly ?ImportJobRowRepository $rows = null,
        private readonly ?CommonListQueryAdapter $queryAdapter = null,
        private readonly ?MutationContext $mutationContext = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function start(string $source, string $fileName, string $mode): array
    {
        if ($source === '') {
            throw new DomainException(self::ERROR_FILE_INVALID, ['field' => 'file']);
        }
        $fileName = trim($fileName);
        if ($fileName === '') {
            throw new DomainException(self::ERROR_FILE_REQUIRED, ['field' => 'file']);
        }
        $format = $this->format($fileName);
        $normalizedMode = strtoupper(trim($mode));
        $operation = $this->operation();
        $jobId = $this->imports()->run(
            [
                'entity_type' => ProductImportJobService::ENTITY_PRODUCT,
                'mode' => $normalizedMode,
                'source_format' => $format,
                'source_file_name' => $fileName,
                'source_file_hash' => hash('sha256', $source),
            ],
            $source,
            $operation,
        );

        return $this->jobOutput($this->requireJob($jobId));
    }

    /** @param array<string, mixed> $query
     *  @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    public function list(array $query = []): array
    {
        $this->authorizeView(['entity_type' => ProductImportJobService::ENTITY_PRODUCT]);
        [$query, $dateFilters] = $this->normalizeJobQuery($query);
        $this->validateFilterEnum($query, 'mode', [
            ProductImportJobService::MODE_CREATE_ONLY,
            ProductImportJobService::MODE_UPDATE_ONLY,
            ProductImportJobService::MODE_UPSERT,
        ]);
        $this->validateFilterEnum($query, 'status', [
            ProductImportJobService::STATUS_PENDING,
            ProductImportJobService::STATUS_PROCESSING,
            ProductImportJobService::STATUS_COMPLETED,
            ProductImportJobService::STATUS_COMPLETED_WITH_ERRORS,
            ProductImportJobService::STATUS_FAILED,
        ]);
        $rows = array_map(
            fn (array $row): array => $this->jobOutput($row),
            array_values(array_filter(
                $this->jobs()->findAll(),
                fn (array $row): bool => $this->isVisibleJob($row, $dateFilters),
            )),
        );

        return $this->paginate(
            $rows,
            $query,
            new CommonListQueryAllowlist(
                search: ['source_file_name', 'status', 'mode'],
                filters: ['mode', 'status', 'created_by'],
                sort: ['id', 'mode', 'status', 'created_by', 'created_at'],
            ),
        );
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        $job = $this->requireJob($id);
        $this->authorizeView($job);

        return $this->jobOutput($job);
    }

    /** @param array<string, mixed> $query
     *  @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    public function rows(int $id, array $query = []): array
    {
        $job = $this->requireJob($id);
        $this->authorizeView($job);
        $normalizedQuery = $query;
        $this->validateFilterEnum($normalizedQuery, 'row_status', ['SUCCESS', 'ERROR', 'SKIPPED']);
        if (! isset($normalizedQuery['sort'])) {
            $normalizedQuery['sort'] = 'row_number';
        }
        $rowData = array_map(
            fn (array $row): array => $this->rowOutput($row),
            $this->rowRepository()->findByJobId($id),
        );

        return $this->paginate(
            $rowData,
            $normalizedQuery,
            new CommonListQueryAllowlist(
                search: ['entity_key'],
                filters: ['row_status'],
                sort: ['row_number', 'row_status', 'entity_key', 'target_ref_id', 'created_at'],
            ),
        );
    }

    /** @return array<string, mixed> */
    private function requireJob(int $id): array
    {
        $job = $this->jobs()->findById($id);
        if ($job === null || strtoupper((string) ($job['entity_type'] ?? '')) !== ProductImportJobService::ENTITY_PRODUCT) {
            throw new DomainException(ProductImportJobService::ERROR_NOT_FOUND, ['import_job_id' => $id]);
        }

        return $job;
    }

    /** @param array<string, mixed> $target */
    private function authorizeView(array $target): void
    {
        $decision = $this->authorization()->authorize(
            $this->operation(),
            'import_job',
            'view',
            new EvaluationOrganizationRequest(persistedTarget: $target),
        );
        if (! $decision->isAllowed()) {
            throw new DomainException('IMPORT_JOB_AUTHORIZATION_DENIED', ['action' => 'view']);
        }
    }

    /** @param array<string,mixed> $query
     *  @return array{0:array<string,mixed>,1:array{from:?string,to:?string}}
     */
    private function normalizeJobQuery(array $query): array
    {
        $rawFilters = $query['filter'] ?? null;
        if ($rawFilters !== null && ! is_array($rawFilters)) {
            throw new BadRequestException('Malformed list query: filter.');
        }
        $filters = $rawFilters ?? [];
        $from = $this->dateFilter($filters, 'created_from');
        $to = $this->dateFilter($filters, 'created_to');
        if ($from !== null && $to !== null && $from > $to) {
            throw new BadRequestException('Malformed list query: filter.created_from.');
        }
        unset($filters['created_from'], $filters['created_to']);
        if ($filters === []) {
            unset($query['filter']);
        } else {
            $query['filter'] = $filters;
        }

        return [$query, ['from' => $from, 'to' => $to]];
    }

    /** @param array<string,mixed> $filters */
    private function dateFilter(array $filters, string $field): ?string
    {
        if (! array_key_exists($field, $filters)) {
            return null;
        }
        $value = $filters[$field];
        if (is_array($value)) {
            if (count($value) !== 1) {
                throw new BadRequestException('Malformed list query: filter.' . $field . '.');
            }
            $value = array_values($value)[0];
        }
        if (! is_scalar($value) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', (string) $value) !== 1) {
            throw new BadRequestException('Malformed list query: filter.' . $field . '.');
        }

        return (string) $value;
    }

    /** @param array<string,mixed> $query
     *  @param list<string> $allowed
     */
    private function validateFilterEnum(array $query, string $field, array $allowed): void
    {
        $filters = $query['filter'] ?? null;
        if (! is_array($filters) || ! array_key_exists($field, $filters)) {
            return;
        }
        $values = is_array($filters[$field]) ? $filters[$field] : [$filters[$field]];
        foreach ($values as $value) {
            if (! is_scalar($value) || ! in_array(strtoupper((string) $value), $allowed, true)) {
                throw new BadRequestException('Malformed list query: filter.' . $field . '.');
            }
        }
    }

    /** @param array<string,mixed> $row
     *  @param array{from:?string,to:?string} $dateFilters
     */
    private function isVisibleJob(array $row, array $dateFilters): bool
    {
        if (strtoupper((string) ($row['entity_type'] ?? '')) !== ProductImportJobService::ENTITY_PRODUCT) {
            return false;
        }
        $created = substr((string) ($row['created_at'] ?? ''), 0, 10);
        if ($dateFilters['from'] !== null && $created < $dateFilters['from']) {
            return false;
        }
        if ($dateFilters['to'] !== null && $created > $dateFilters['to']) {
            return false;
        }

        return true;
    }

    /** @param array<string,mixed> $row
     *  @return array<string,mixed>
     */
    private function jobOutput(array $row): array
    {
        foreach (['id', 'created_by', 'updated_by'] as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null) {
                $row[$field] = (string) $row[$field];
            }
        }
        foreach (['total_rows', 'success_rows', 'error_rows'] as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = (int) $row[$field];
            }
        }

        return $row;
    }

    /** @param array<string,mixed> $row
     *  @return array<string,mixed>
     */
    private function rowOutput(array $row): array
    {
        return [
            'row_number' => (int) ($row['row_number'] ?? 0),
            'row_status' => (string) ($row['row_status'] ?? ''),
            'entity_key' => $row['entity_key'] ?? null,
            'target_ref_id' => ($row['target_ref_id'] ?? null) === null ? null : (string) $row['target_ref_id'],
            'error_code' => $row['error_code'] ?? null,
            'error_message' => $row['error_message'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    /** @param list<array<string,mixed>> $rows
     *  @param array<string,mixed> $query
     *  @return array{items:list<array<string,mixed>>,page:int,page_size:int,total:int|string}
     */
    private function paginate(array $rows, array $query, CommonListQueryAllowlist $allowlist): array
    {
        $result = ($this->queryAdapter ?? new CommonListQueryAdapter())->paginate(
            $query,
            $allowlist,
            new AuthorizedArrayListSource($rows),
        );

        return [
            'items' => array_values(iterator_to_array($result->data)),
            'page' => $result->query->page,
            'page_size' => $result->query->pageSize,
            'total' => $result->total,
        ];
    }

    private function format(string $fileName): string
    {
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => ProductImportJobService::FORMAT_CSV,
            'xlsx' => ProductImportJobService::FORMAT_XLSX,
            default => throw new DomainException(ProductImportJobService::ERROR_INVALID_FORMAT, ['field' => 'file']),
        };
    }

    /** @return ConnectionInterface<mixed,mixed> */
    private function connection(): ConnectionInterface
    {
        return $this->db ?? db_connect();
    }

    private function authorization(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService(db: $this->connection());
    }

    private function imports(): ProductImportAuthorizationService
    {
        return $this->imports ?? new ProductImportAuthorizationService(
            authorization: $this->authorization(),
            jobs: new ProductImportJobService(db: $this->connection()),
            rows: new ProductImportRowMutationService(db: $this->connection()),
            jobRows: $this->rowRepository(),
            db: $this->connection(),
        );
    }

    private function jobs(): ImportJobRepository
    {
        return $this->jobs ?? new ImportJobRepository($this->connection());
    }

    private function rowRepository(): ImportJobRowRepository
    {
        return $this->rows ?? new ImportJobRowRepository($this->connection());
    }

    private function operation(): OperationContext
    {
        if ($this->mutationContext !== null) {
            return $this->mutationContext->begin();
        }
        $user = auth()->user();
        if (! $user instanceof User || ! is_numeric($user->id) || (int) $user->id < 1) {
            throw new DomainException('UNAUTHENTICATED');
        }

        return (new MutationContext(service('clock'), (int) $user->id, RequestContext::requestId(), false))->begin();
    }
}
