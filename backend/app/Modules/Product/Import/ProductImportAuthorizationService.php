<?php

declare(strict_types=1);

namespace App\Modules\Product\Import;

use App\Authorization\AuthorizationDecision;
use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;

/** Product import application boundary for the start and row authorization gates. */
final class ProductImportAuthorizationService
{
    public const ERROR_AUTHORIZATION_DENIED = 'PRODUCT_IMPORT_AUTHORIZATION_DENIED';

    public function __construct(
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?ProductImportJobService $jobs = null,
        private readonly ?ProductImportRowMutationService $rows = null,
    ) {
    }

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
                resource: 'product',
                operation: 'import',
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
}
