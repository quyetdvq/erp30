<?php

declare(strict_types=1);

use App\Authorization\AuthorizationDecision;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\ApiTransport;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\AutoNumberingSeriesRepository;
use App\Services\Api040AutoNumberingService;
use App\Services\AutoNumberingConfigurationService;
use App\Services\CanonicalNumberingPatternValidator;
use App\Services\NumberingAuthorizationPort;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Api040AutoNumberingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testApiOperationsUseResourcePathAndTransportTypes(): void
    {
        $resourceId = $this->resource('api040-crud');
        $dbRepository = new AutoNumberingSeriesRepository($this->db);
        $configuration = new AutoNumberingConfigurationService(
            repository: $dbRepository,
            authorization: new Api040AllowAuthorization(),
            patternValidator: new CanonicalNumberingPatternValidator(),
            db: $this->db,
        );
        $service = new Api040AutoNumberingService(
            configuration: $configuration,
            repository: $dbRepository,
            mutationContext: new MutationContext(service('clock'), 41, 'api040-test', false),
            db: $this->db,
        );

        $created = $service->create($resourceId, $this->payload('API040', true, 'ACTIVE'));
        $this->assertIsString($created['start_number']);
        $this->assertSame('1', $created['start_number']);
        $this->assertTrue($created['is_default']);
        $this->assertSame($resourceId, (int) $created['resource_id']);
        $this->assertCount(1, $service->list($resourceId));
        $this->assertSame($created['id'], $service->get($resourceId, (int) $created['id'])['id']);
        $this->assertSame('INACTIVE', $service->setState($resourceId, (int) $created['id'], 'INACTIVE')['status']);
        $this->assertSame('Renamed', $service->update($resourceId, (int) $created['id'], ['name' => 'Renamed'])['name']);

        $otherResource = $this->resource('api040-other');
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(AutoNumberingConfigurationService::ERROR_NOT_FOUND);
        $service->get($otherResource, (int) $created['id']);
    }

    public function testValidateConfigDoesNotAllocateOrAdvanceCounters(): void
    {
        $resourceId = $this->resource('api040-validation');
        $dbRepository = new AutoNumberingSeriesRepository($this->db);
        $configuration = new AutoNumberingConfigurationService(
            repository: $dbRepository,
            authorization: new Api040AllowAuthorization(),
            patternValidator: new CanonicalNumberingPatternValidator(),
            db: $this->db,
        );
        $service = new Api040AutoNumberingService(
            configuration: $configuration,
            repository: $dbRepository,
            mutationContext: new MutationContext(service('clock'), 42, 'api040-validation', false),
            db: $this->db,
        );

        $before = (int) $this->db->table('auto_numbering_counters')->countAllResults();
        $result = $service->validateConfig($resourceId, $this->payload('VALIDATE', false, 'ACTIVE'));
        $after = (int) $this->db->table('auto_numbering_counters')->countAllResults();

        $this->assertTrue($result['valid']);
        $this->assertSame('V{SEQ:4}', $result['normalized_pattern']);
        $this->assertSame([], $result['validation_messages']);
        $this->assertSame($before, $after);
        $this->assertSame(0, (int) $this->db->table('auto_numbering_series')->where('resource_id', $resourceId)->countAllResults());
    }

    public function testValidationRejectsSecondActiveDefaultWithoutPersistence(): void
    {
        $resourceId = $this->resource('api040-default-validation');
        $repository = new AutoNumberingSeriesRepository($this->db);
        $configuration = new AutoNumberingConfigurationService(
            repository: $repository,
            authorization: new Api040AllowAuthorization(),
            patternValidator: new CanonicalNumberingPatternValidator(),
            db: $this->db,
        );
        $service = new Api040AutoNumberingService(
            configuration: $configuration,
            repository: $repository,
            mutationContext: new MutationContext(service('clock'), 43, 'api040-default-validation', false),
            db: $this->db,
        );
        $service->create($resourceId, $this->payload('FIRST', true, 'ACTIVE'));

        $this->expectExceptionMessage(AutoNumberingConfigurationService::ERROR_ACTIVE_DEFAULT_CONFLICT);
        $second = $this->payload('SECOND', true, 'ACTIVE');
        $second['pattern'] = 'W{SEQ:4}';
        $service->validateConfig($resourceId, $second);
        $this->assertSame(1, (int) $this->db->table('auto_numbering_series')->where('resource_id', $resourceId)->countAllResults());
    }

    public function testAuthorizationDenialIsStableAndResourceEditDoesNotSubstitute(): void
    {
        $resourceId = $this->resource('api040-denied');
        $repository = new AutoNumberingSeriesRepository($this->db);
        $denied = new Api040DenyAuthorization();
        $configuration = new AutoNumberingConfigurationService(
            repository: $repository,
            authorization: $denied,
            patternValidator: new CanonicalNumberingPatternValidator(),
            db: $this->db,
        );
        $service = new Api040AutoNumberingService(
            configuration: $configuration,
            repository: $repository,
            mutationContext: new MutationContext(service('clock'), 44, 'api040-denied', false),
            db: $this->db,
        );

        try {
            $service->create($resourceId, $this->payload('DENIED', false, 'INACTIVE'));
            $this->fail('Expected configure_numbering denial.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingConfigurationService::ERROR_AUTHORIZATION_DENIED, $exception->getErrorCode());
        }
        $this->assertSame('resource', $denied->resourceType);
        $this->assertSame('configure_numbering', $denied->action);
        $this->assertSame(0, (int) $this->db->table('auto_numbering_series')->where('resource_id', $resourceId)->countAllResults());
    }

    public function testIssuedIdentityIsImmutableAndStartNumberDoesNotRewriteCounter(): void
    {
        $resourceId = $this->resource('api040-issued');
        $repository = new AutoNumberingSeriesRepository($this->db);
        $configuration = new AutoNumberingConfigurationService(
            repository: $repository,
            authorization: new Api040AllowAuthorization(),
            patternValidator: new CanonicalNumberingPatternValidator(),
            db: $this->db,
        );
        $service = new Api040AutoNumberingService(
            configuration: $configuration,
            repository: $repository,
            mutationContext: new MutationContext(service('clock'), 45, 'api040-issued', false),
            db: $this->db,
        );
        $created = $service->create($resourceId, $this->payload('ISSUED', false, 'ACTIVE'));
        $seriesId = (int) $created['id'];
        $this->db->table('auto_numbering_counters')->insert([
            'numbering_series_id' => $seriesId,
            'scope_organization_id' => $this->groupRoot(),
            'period_key' => 'ALL',
            'last_value' => 17,
            'created_at' => '2026-10-07 00:00:00',
        ]);

        foreach (['resource_id' => $this->resource('api040-moved'), 'series_code' => 'MOVED', 'scope_type' => 'COMPANY', 'reset_policy' => 'YEARLY'] as $field => $value) {
            try {
                $service->update($resourceId, $seriesId, [$field => $value]);
                $this->fail('Expected issued identity rejection.');
            } catch (DomainException $exception) {
                $this->assertSame(AutoNumberingConfigurationService::ERROR_ISSUED_IMMUTABLE, $exception->getErrorCode());
            }
        }

        $updated = $service->update($resourceId, $seriesId, ['start_number' => '99', 'name' => 'Prospective']);
        $this->assertSame('99', $updated['start_number']);
        $counter = $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->get()->getRowArray();
        $this->assertSame('17', (string) $counter['last_value']);
        $this->assertSame(1, (int) $this->db->table('auto_numbering_series')->where('id', $seriesId)->countAllResults());
    }

    public function testTransportSerializesBigIntStartNumberAndRoutesExposeSixOperations(): void
    {
        $response = ApiTransport::success(service('response'), ['id' => 7, 'resource_id' => 8, 'start_number' => '9223372036854775807']);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('9223372036854775807', $payload['data']['start_number']);

        $routes = file_get_contents(__DIR__ . '/../../app/Config/Routes.php');
        $openapi = file_get_contents(__DIR__ . '/../../../contracts/openapi/openapi.yaml');
        $this->assertIsString($routes);
        $this->assertIsString($openapi);
        $this->assertSame(6, preg_match_all('/numbering-series/', $routes, $routeMatches));
        $this->assertSame(6, preg_match_all('/operationId: (?:list|create|get|update|set|validate)NumberingSeries/', $openapi, $operationMatches));
    }

    /** @return array<string, mixed> */
    private function payload(string $code, bool $default, string $status): array
    {
        return [
            'series_code' => $code,
            'name' => $code,
            'scope_type' => 'GROUP',
            'pattern' => 'V{SEQ:4}',
            'reset_policy' => 'NEVER',
            'generation_mode' => 'AUTO_REQUIRED',
            'start_number' => '1',
            'increment_by' => 1,
            'is_default' => $default,
            'status' => $status,
        ];
    }

    private function resource(string $suffix): int
    {
        $this->db->table('resources')->insert([
            'type' => $suffix . '-' . uniqid(),
            'name' => $suffix,
            'domain' => 'SYSTEM',
            'created_at' => '2026-10-07 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function groupRoot(): int
    {
        $row = $this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null)->get()->getRowArray();
        if ($row !== null) {
            return (int) $row['id'];
        }
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'code' => 'API040-' . uniqid(), 'name' => 'API040', 'status' => 'ACTIVE',
            'created_at' => '2026-10-07 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }
}

final class Api040AllowAuthorization implements NumberingAuthorizationPort
{
    public function authorize(OperationContext $operation, string $resourceType, string $action, EvaluationOrganizationRequest $contextRequest, mixed $stateId = null): AuthorizationDecision
    {
        return new AuthorizationDecision('ALLOW', 'AUTH_POLICY_ALLOWED', $operation->actorUserId ?? 1, $resourceType, $action, 1, null, $operation->evaluationTime, [], [], [], [], $operation->requestId);
    }
}

final class Api040DenyAuthorization implements NumberingAuthorizationPort
{
    public string $resourceType = '';
    public string $action = '';

    public function authorize(OperationContext $operation, string $resourceType, string $action, EvaluationOrganizationRequest $contextRequest, mixed $stateId = null): AuthorizationDecision
    {
        $this->resourceType = $resourceType;
        $this->action = $action;

        return new AuthorizationDecision('DENY', 'AUTH_POLICY_DENIED', $operation->actorUserId ?? 1, $resourceType, $action, 1, null, $operation->evaluationTime, [], [], [], [], $operation->requestId);
    }
}
