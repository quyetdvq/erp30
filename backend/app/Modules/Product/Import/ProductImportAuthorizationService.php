<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

use App\Authorization\AuthorizationDecision;
use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Import\Persistence\ImportJobRepository;
use App\Infrastructure\Import\Persistence\ImportJobRowRepository;
use App\Infrastructure\Import\Reader\CsvImportReader;
use App\Infrastructure\Import\Reader\XlsxImportReader;
use App\Infrastructure\Mutation\OperationContext;
use CodeIgniter\Database\ConnectionInterface;

/** Product import application boundary for the start and row authorization gates. */
final class ProductImportAuthorizationService
{
    public const ERROR_AUTHORIZATION_DENIED = 'PRODUCT_IMPORT_AUTHORIZATION_DENIED';

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?ProductImportJobService $jobs = null,
        private readonly ?ProductImportRowMutationService $rows = null,
        private readonly ?ImportJobRowRepository $jobRows = null,
        private readonly ?CsvImportReader $csv = null,
        private readonly ?XlsxImportReader $xlsx = null,
        private readonly ?ProductImportTemplateValidator $validator = null,
        private readonly ?ProductImportModelBuilder $builder = null,
        ?ConnectionInterface $db = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /**
     * Authorize the Product import start gate at the authoritative GROUP.
     *
     * The caller-provided hint is validated by the canonical resolver and can
     * never replace the GROUP evaluation context.
     */
    public function authorizeStart(OperationContext $operation, mixed $clientEvaluationOrganizationId = null): AuthorizationDecision
    {
        $decision = $this->auth()->authorize(
            $operation,
            'product',
            'import',
            new EvaluationOrganizationRequest(
                clientEvaluationOrganizationId: $clientEvaluationOrganizationId,
            ),
        );
        if (! $decision->isAllowed()) {
            throw new DomainException(self::ERROR_AUTHORIZATION_DENIED, [
                'action' => 'import',
                'reason' => $decision->reasonCode,
            ]);
        }

        return $decision;
    }

    /** @param array<string, mixed> $data */
    public function startImport(
        array $data,
        OperationContext $operation,
        mixed $clientEvaluationOrganizationId = null,
    ): int {
        $this->authorizeStart($operation, $clientEvaluationOrganizationId);

        return $this->jobs()->create($data, $operation);
    }

    /** @param array<string, mixed> $data */
    public function createJob(
        array $data,
        OperationContext $operation,
        mixed $clientEvaluationOrganizationId = null,
    ): int {
        return $this->startImport($data, $operation, $clientEvaluationOrganizationId);
    }

    public function executeRows(
        ProductImportPlan $plan,
        OperationContext $operation,
        mixed $clientEvaluationOrganizationId = null,
    ): ProductImportMutationResult {
        $this->authorizeStart($operation, $clientEvaluationOrganizationId);

        return $this->rows()->execute($plan, $operation);
    }

    public function execute(
        ProductImportPlan $plan,
        OperationContext $operation,
        mixed $clientEvaluationOrganizationId = null,
    ): ProductImportMutationResult {
        return $this->executeRows($plan, $operation, $clientEvaluationOrganizationId);
    }

    /**
     * Execute the Product import pipeline and return the created job ID.
     *
     * @param array<string, mixed> $jobData
     */
    public function run(
        array $jobData,
        string $source,
        OperationContext $operation,
        mixed $clientEvaluationOrganizationId = null,
    ): int {
        $this->authorizeStart($operation, $clientEvaluationOrganizationId);
        $jobId = $this->jobs()->create($jobData, $operation);
        try {
            $this->jobs()->startProcessing($jobId, $operation);
            $document = $this->reader((string) ($jobData['source_format'] ?? ''))->read($source);
            $validated = $this->validator()->validate($document, strtoupper((string) ($jobData['mode'] ?? '')));
            $plan = $this->builder()->build($validated);
            $result = $this->rows()->execute($plan, $operation);
            $this->persistRows($jobId, $result, $operation);
            $this->jobs()->reconcile($jobId, $this->jobRows(), $operation);

            return $jobId;
        } catch (\Throwable $exception) {
            try {
                $this->jobs()->markFailed($jobId, $operation);
            } catch (\Throwable) {
                // Preserve the original fatal job error.
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $jobData */
    public function process(
        array $jobData,
        string $source,
        OperationContext $operation,
        mixed $clientEvaluationOrganizationId = null,
    ): int {
        return $this->run($jobData, $source, $operation, $clientEvaluationOrganizationId);
    }

    private function auth(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService();
    }

    private function jobs(): ProductImportJobService
    {
        return $this->jobs ?? new ProductImportJobService();
    }

    private function rows(): ProductImportRowMutationService
    {
        return $this->rows ?? new ProductImportRowMutationService();
    }

    private function jobRows(): ImportJobRowRepository
    {
        return $this->jobRows ?? new ImportJobRowRepository($this->db);
    }

    private function csvReader(): CsvImportReader
    {
        return $this->csv ?? new CsvImportReader();
    }

    private function xlsxReader(): XlsxImportReader
    {
        return $this->xlsx ?? new XlsxImportReader();
    }

    private function reader(string $format): CsvImportReader|XlsxImportReader
    {
        return match (strtoupper(trim($format))) {
            ProductImportJobService::FORMAT_CSV => $this->csvReader(),
            ProductImportJobService::FORMAT_XLSX => $this->xlsxReader(),
            default => throw new DomainException(ProductImportJobService::ERROR_INVALID_FORMAT, ['field' => 'source_format']),
        };
    }

    private function validator(): ProductImportTemplateValidator
    {
        return $this->validator ?? new ProductImportTemplateValidator();
    }

    private function builder(): ProductImportModelBuilder
    {
        return $this->builder ?? new ProductImportModelBuilder(db: $this->db);
    }

    private function persistRows(int $jobId, ProductImportMutationResult $result, OperationContext $operation): void
    {
        foreach ($result->rows as $row) {
            $this->jobRows()->insert([
                'import_job_id' => $jobId,
                'row_number' => $row->rowNumber,
                'row_status' => $row->getRowStatus(),
                'entity_key' => $row->entityKey,
                'target_ref_id' => $row->targetRefId,
                'error_code' => $row->errorCode,
                'error_message' => $row->error,
            ], $operation);
        }
    }
}
