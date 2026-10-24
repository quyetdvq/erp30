<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class P1Api060ProductImportTest extends CIUnitTestCase
{
    public function testRuntimeAndOpenApiExposeExactlyFourImportOperations(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../app/Config/Routes.php');
        $openapi = file_get_contents(__DIR__ . '/../../../contracts/openapi/openapi.yaml');
        self::assertIsString($routes);
        self::assertIsString($openapi);

        self::assertSame(4, substr_count($routes, 'Api\\\\ProductImport::'));
        $expected = ['startProductImport', 'listImportJobs', 'getImportJob', 'listImportJobRows'];
        foreach ($expected as $operationId) {
            self::assertStringContainsString('operationId: ' . $operationId, $openapi);
        }
        self::assertSame(4, preg_match_all('/operationId: (?:startProductImport|listImportJobs|getImportJob|listImportJobRows)\\b/', $openapi, $matches));
    }

    public function testImportContractKeepsPendingExportAndOtherTargetsOutOfScope(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../app/Config/Routes.php');
        $openapi = file_get_contents(__DIR__ . '/../../../contracts/openapi/openapi.yaml');
        self::assertIsString($routes);
        self::assertIsString($openapi);

        self::assertStringNotContainsString("import-jobs/(:num)/export", $routes);
        self::assertStringNotContainsString('/import-jobs/{id}/export', $openapi);
        self::assertStringNotContainsString('employee-import', $routes);
        self::assertStringNotContainsString('raw_payload', $openapi);
        self::assertStringNotContainsString('source_key:', $openapi);
        self::assertStringContainsString('multipart/form-data', $openapi);
        self::assertStringContainsString('row_number', $openapi);
        self::assertStringContainsString('target_ref_id', $openapi);
    }

    public function testImportApplicationBoundaryUsesCanonicalUseCaseAndScopeAwareRepositories(): void
    {
        $service = file_get_contents(__DIR__ . '/../../app/Services/Api060ProductImportService.php');
        $controller = file_get_contents(__DIR__ . '/../../app/Controllers/Api/ProductImport.php');
        self::assertIsString($service);
        self::assertIsString($controller);

        foreach (['ProductImportAuthorizationService', 'EvaluationOrganizationRequest', 'import_job', 'AuthorizedArrayListSource', 'ProductImportJobService::ENTITY_PRODUCT'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        foreach (['getFile', 'Api060ProductImportService', 'respondData', 'ApiTransport::collection'] as $marker) {
            self::assertStringContainsString($marker, $controller);
        }
    }
}
