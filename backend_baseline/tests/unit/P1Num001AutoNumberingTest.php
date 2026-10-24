<?php

declare(strict_types=1);

use App\Authorization\AuthorizationDecision;
use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\AuthorizationService;
use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\AutoNumberingSeriesRepository;
use App\Repositories\PolicyRepository;
use App\Services\AutoNumberingConfigurationService;
use App\Services\NumberingAuthorizationPort;
use App\Services\NumberingPatternValidator;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Num001AutoNumberingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function setUp(): void
    {
        \Tests\Support\Standard\StandardRuntimeGuard::assertReady();
        parent::setUp();
    }

    public function testRepositoryAllowsZeroAndMultipleSeriesAndUsesTrustedAudit(): void
    {
        $resourceId = $this->resource('num001-zero');
        $repository = new AutoNumberingSeriesRepository($this->db);
        $operation = $this->operation(42);

        $this->assertSame([], $repository->findByResource($resourceId));
        $first = $repository->create($this->series($resourceId, 'FIRST', 0), $operation);
        $second = $repository->create($this->series($resourceId, 'SECOND', 0), $operation);

        $rows = $repository->findByResource((string) $resourceId);
        $this->assertCount(2, $rows);
        $this->assertSame([$first, $second], array_map(static fn (array $row): int => (int) $row['id'], $rows));
        $this->assertSame(42, (int) $rows[0]['created_by']);
        $this->assertSame('2026-10-06 04:05:06', $rows[0]['created_at']);
        $this->assertNull($repository->find(999999999));
    }

    public function testActiveDefaultInvariantAllowsNonDefaultsAndRejectsSecondDefault(): void
    {
        $resourceId = $this->resource('num001-default');
        $service = $this->service();
        $first = $service->create($this->series($resourceId, 'PRIMARY', 1, true, 'ACTIVE'));
        $nonDefault = $service->create($this->series($resourceId, 'SECONDARY', 0, false, 'ACTIVE'));

        $this->assertSame($first, (int) $service->find($first)['id']);
        $this->assertSame($nonDefault, (int) $service->find($nonDefault)['id']);
        $this->expectExceptionMessage(AutoNumberingConfigurationService::ERROR_ACTIVE_DEFAULT_CONFLICT);
        $service->create($this->series($resourceId, 'OTHER', 1, true, 'ACTIVE'));
    }

    public function testInactiveDefaultDoesNotCountAndInvalidBasicConfigurationIsRejected(): void
    {
        $resourceId = $this->resource('num001-validation');
        $service = $this->service();
        $service->create($this->series($resourceId, 'INACTIVE_DEFAULT', 1, true, 'INACTIVE'));
        $active = $service->create($this->series($resourceId, 'ACTIVE_DEFAULT', 1, true, 'ACTIVE'));
        $this->assertSame('ACTIVE', $service->find($active)['status']);

        foreach ([
            ['scope_type' => 'LOCAL'],
            ['reset_policy' => 'WEEKLY'],
            ['generation_mode' => 'MANUAL'],
            ['start_number' => -1],
            ['increment_by' => 0],
        ] as $change) {
            try {
                $service->create(array_merge($this->series($resourceId, 'INVALID-' . count($change)), $change));
                $this->fail('Expected invalid configuration rejection.');
            } catch (DomainException $exception) {
                $this->assertSame(AutoNumberingConfigurationService::ERROR_INVALID_CONFIGURATION, $exception->getErrorCode());
            }
        }
    }

    public function testPostIssuanceIdentityIsImmutableButProspectiveFieldsDoNotTouchCounter(): void
    {
        $resourceId = $this->resource('num001-issued');
        $organizationId = $this->organization('num001-scope');
        $service = $this->service();
        $seriesId = $service->create($this->series($resourceId, 'ISSUED', 10, true, 'ACTIVE'));
        $this->db->table('auto_numbering_counters')->insert([
            'numbering_series_id' => $seriesId,
            'scope_organization_id' => $organizationId,
            'period_key' => 'ALL',
            'last_value' => 17,
            'created_at' => '2026-10-06 04:05:06',
        ]);

        foreach (['resource_id' => $this->resource('num001-other'), 'series_code' => 'CHANGED', 'scope_type' => 'COMPANY', 'reset_policy' => 'YEARLY'] as $field => $value) {
            try {
                $service->update($seriesId, [$field => $value]);
                $this->fail('Expected issued identity immutability rejection.');
            } catch (DomainException $exception) {
                $this->assertSame(AutoNumberingConfigurationService::ERROR_ISSUED_IMMUTABLE, $exception->getErrorCode());
            }
        }

        $this->assertTrue($service->update($seriesId, [
            'name' => 'Prospective', 'start_number' => 99, 'increment_by' => 3,
            'generation_mode' => 'AUTO_IF_EMPTY', 'is_default' => 0, 'status' => 'INACTIVE',
        ]));
        $counter = $this->db->table('auto_numbering_counters')->where('numbering_series_id', $seriesId)->get()->getRowArray();
        $this->assertSame('17', (string) $counter['last_value']);
        $this->expectExceptionMessage(AutoNumberingConfigurationService::ERROR_ISSUED_NO_HARD_DELETE);
        $service->delete($seriesId);
    }

    public function testAuthorizationRequiresConfigureNumberingAndDeniedMutationIsAtomic(): void
    {
        $resourceId = $this->resource('num001-auth');
        $authorization = new Num001FakeAuthorization('DENY');
        $service = $this->service($authorization);

        try {
            $service->create($this->series($resourceId, 'DENIED'));
            $this->fail('Expected authorization denial.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingConfigurationService::ERROR_AUTHORIZATION_DENIED, $exception->getErrorCode());
        }
        $this->assertSame('resource', $authorization->resource);
        $this->assertSame('configure_numbering', $authorization->action);
        $this->assertSame(0, (int) $this->db->table('auto_numbering_series')->where('resource_id', $resourceId)->countAllResults());
    }

    public function testRealAuthorizationRejectsEditOnlyAndMissingBasePermission(): void
    {
        [$resourceId, $userId] = $this->authorizationFixture(['edit']);
        $service = new AutoNumberingConfigurationService(db: $this->db);
        foreach ([$userId, $this->authorizationUser(false)] as $actorId) {
            $operation = $this->operation($actorId);
            try {
                $service->create($this->series($resourceId, 'REAL-DENY-' . $actorId), $operation);
                $this->fail('Expected configure_numbering denial.');
            } catch (DomainException $exception) {
                $this->assertSame(AutoNumberingConfigurationService::ERROR_AUTHORIZATION_DENIED, $exception->getErrorCode());
            }
        }
    }

    public function testRealAuthorizationRejectsPbacDenyAbsoluteDenyAndSystemAdminNoBypass(): void
    {
        [$resourceId, $userId, $groupId, $rootId] = $this->authorizationFixture(['configure_numbering']);
        $repository = new PolicyRepository($this->db);
        $resourceCatalogId = (int) $this->db->table('resources')->where('type', 'resource')->get()->getRowArray()['id'];
        $actionId = (int) $this->db->table('resource_actions')->where(['resource_id' => $resourceCatalogId, 'code' => 'configure_numbering'])->get()->getRowArray()['id'];
        $repository->insertWithActions([
            'organization_id' => $rootId, 'resource_id' => $resourceCatalogId,
            'participant_type' => 'GROUP', 'participant_ref_id' => $groupId,
        ], [['resource_action_id' => $actionId, 'effect' => 'DENY']]);
        try {
            (new AutoNumberingConfigurationService(db: $this->db))->create($this->series($resourceId, 'PBAC-DENY'), $this->operation($userId));
            $this->fail('Expected PBAC denial.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingConfigurationService::ERROR_AUTHORIZATION_DENIED, $exception->getErrorCode());
        }

        $policyId = (int) $this->db->table('policies')->where([
            'organization_id' => $rootId, 'resource_id' => $resourceCatalogId,
            'state_id' => null, 'participant_type' => 'GROUP', 'participant_ref_id' => $groupId,
        ])->get()->getRowArray()['id'];
        $this->db->table('policy_actions')->where([
            'policy_id' => $policyId, 'resource_action_id' => $actionId,
        ])->update(['effect' => 'ABSOLUTE_DENY']);
        try {
            (new AutoNumberingConfigurationService(db: $this->db))->create($this->series($resourceId, 'ABSOLUTE-DENY'), $this->operation($userId));
            $this->fail('Expected absolute denial.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingConfigurationService::ERROR_AUTHORIZATION_DENIED, $exception->getErrorCode());
        }

        $systemAdmin = $this->authorizationUser(true);
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $systemAdmin, 'group' => 'system_admin', 'created_at' => '2026-10-06 04:05:06',
        ]);
        try {
            (new AutoNumberingConfigurationService(db: $this->db))->create($this->series($resourceId, 'SYSTEM-ADMIN'), $this->operation($systemAdmin));
            $this->fail('Expected no SYSTEM_ADMIN bypass.');
        } catch (DomainException $exception) {
            $this->assertSame(AutoNumberingConfigurationService::ERROR_AUTHORIZATION_DENIED, $exception->getErrorCode());
        }
    }

    public function testRepositoryFailureIsNotNotFoundAndTransactionRollsBack(): void
    {
        $connection = $this->createMock(CodeIgniter\Database\ConnectionInterface::class);
        $connection->method('table')->willThrowException(new RuntimeException('database unavailable'));
        try {
            (new AutoNumberingSeriesRepository($connection))->find(1);
            $this->fail('Expected persistence failure.');
        } catch (PersistenceException $exception) {
            $this->assertSame(AutoNumberingSeriesRepository::ERROR_PERSISTENCE_FAILED, $exception->getErrorCode());
        }
        $repository = new AutoNumberingSeriesRepository($this->db);
        $code = 'ROLLBACK-' . uniqid();
        $rollbackSeries = $this->series($this->resource('num001-rollback'), $code);
        try {
            $repository->transactional(function () use ($repository, $rollbackSeries): void {
                $repository->insert($rollbackSeries);
                throw new RuntimeException('forced rollback');
            }, 'num001.rollback');
            $this->fail('Expected transaction failure.');
        } catch (PersistenceException $exception) {
            $this->assertSame(AutoNumberingSeriesRepository::ERROR_MUTATION_FAILED, $exception->getErrorCode());
        }
    }

    public function testConcurrentActiveDefaultMutationsSerializeOnResourceRow(): void
    {
        $resourceId = $this->resource('num001-concurrency');
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dinkerp-num001-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $worker = realpath(__DIR__ . '/../fixtures/auto_numbering_default_concurrency_worker.php');
        $this->assertNotFalse($worker);
        $startPath = $directory . DIRECTORY_SEPARATOR . 'start';
        $processes = [];
        $resultPaths = [];
        try {
            foreach (['a', 'b'] as $workerName) {
                $readyPath = $directory . DIRECTORY_SEPARATOR . 'ready-' . $workerName;
                $resultPath = $directory . DIRECTORY_SEPARATOR . 'result-' . $workerName . '.json';
                $resultPaths[] = $resultPath;
                $command = implode(' ', array_map('escapeshellarg', [
                    PHP_BINARY, $worker, (string) $resourceId, 'CONCURRENT-' . $workerName,
                    $readyPath, $startPath, $resultPath,
                ]));
                $processes[$workerName] = proc_open($command, [
                    0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
                ], $pipes);
                $this->assertIsResource($processes[$workerName]);
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
            }
            $deadline = microtime(true) + 10.0;
            while ((! is_file($directory . DIRECTORY_SEPARATOR . 'ready-a') || ! is_file($directory . DIRECTORY_SEPARATOR . 'ready-b')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($directory . DIRECTORY_SEPARATOR . 'ready-a');
            $this->assertFileExists($directory . DIRECTORY_SEPARATOR . 'ready-b');
            file_put_contents($startPath, 'go');
            foreach ($processes as $process) {
                $this->assertSame(0, proc_close($process));
            }
            $results = array_map(static function (string $path): array {
                $contents = file_get_contents($path);
                if ($contents === false) {
                    throw new RuntimeException('Concurrency worker did not write a result.');
                }

                return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            }, $resultPaths);
            $this->assertCount(1, array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true), json_encode($results, JSON_THROW_ON_ERROR));
            $this->assertCount(1, array_filter($results, static fn (array $result): bool => ($result['code'] ?? '') === AutoNumberingConfigurationService::ERROR_ACTIVE_DEFAULT_CONFLICT), json_encode($results, JSON_THROW_ON_ERROR));
            $this->assertSame(1, (int) $this->db->table('auto_numbering_series')
                ->where('resource_id', $resourceId)->where('status', 'ACTIVE')->where('is_default', 1)->countAllResults());
        } finally {
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    @proc_terminate($process);
                    @proc_close($process);
                }
            }
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($directory);
        }
    }

    private function service(?Num001FakeAuthorization $authorization = null): AutoNumberingConfigurationService
    {
        return new AutoNumberingConfigurationService(
            authorization: $authorization ?? new Num001FakeAuthorization(),
            patternValidator: new Num001PatternValidator(),
            db: $this->db,
        );
    }

    /** @return array<string, mixed> */
    private function series(int $resourceId, string $code, int $start = 1, bool $default = false, string $status = 'INACTIVE'): array
    {
        return [
            'resource_id' => $resourceId, 'series_code' => $code, 'name' => 'Series ' . $code,
            'scope_type' => 'GROUP', 'pattern' => '{SEQ:4}', 'reset_policy' => 'NEVER',
            'generation_mode' => 'AUTO_REQUIRED', 'start_number' => $start,
            'increment_by' => 1, 'is_default' => $default, 'status' => $status,
        ];
    }

    private function resource(string $type): int
    {
        $this->db->table('resources')->insert([
            'type' => $type . '-' . uniqid(), 'name' => 'Resource ' . $type, 'domain' => 'SYSTEM',
            'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    private function organization(string $code): int
    {
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'code' => $code . '-' . uniqid(), 'name' => 'Scope',
            'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    /** @param list<string> $actions
     *  @return array{int, int, int, int}
     */
    private function authorizationFixture(array $actions): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $root = $this->db->table('organizations')->where('type', 'GROUP')->where('parent_id', null)->get()->getRowArray();
        if ($root === null) {
            $rootId = $this->organization('num001-auth-root');
        } else {
            $rootId = (int) $root['id'];
        }
        $resourceId = (int) $this->db->table('resources')->where('type', 'resource')->get()->getRowArray()['id'];
        $userId = $this->authorizationUser(true);
        $groupCode = 'NUM001-AUTH-' . uniqid();
        $this->db->table('groups')->insert(['code' => $groupCode, 'name' => 'NUM001 Auth', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 04:05:06']);
        $groupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => strtolower($groupCode), 'created_at' => '2026-10-06 04:05:06']);
        foreach ($actions as $action) {
            $actionId = (int) $this->db->table('resource_actions ra')->select('ra.id')->join('resources r', 'r.id = ra.resource_id')->where('r.type', 'resource')->where('ra.code', $action)->get()->getRowArray()['id'];
            $this->db->table('group_permissions')->insert(['group_id' => $groupId, 'resource_action_id' => $actionId, 'created_at' => '2026-10-06 04:05:06']);
        }

        return [$resourceId, $userId, $groupId, $rootId];
    }

    private function authorizationUser(bool $active): int
    {
        $this->db->table('users')->insert([
            'username' => 'num001-' . uniqid(), 'status' => $active ? 'active' : 'active',
            'active' => $active ? 1 : 1, 'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    private function operation(int $actorId): OperationContext
    {
        return new OperationContext($actorId, new EvaluationTime(new DateTimeImmutable('2026-10-06 04:05:06', new DateTimeZone('UTC'))), 'num001-test', false);
    }
}

final class Num001FakeAuthorization implements NumberingAuthorizationPort
{
    public string $resource = '';
    public string $action = '';

    public function __construct(private readonly string $decision = 'ALLOW')
    {
    }

    public function authorize(OperationContext $operation, string $resourceType, string $action, EvaluationOrganizationRequest $contextRequest, mixed $stateId = null): AuthorizationDecision
    {
        $this->resource = $resourceType;
        $this->action = $action;

        return new AuthorizationDecision(
            $this->decision, $this->decision === 'ALLOW' ? 'AUTH_POLICY_ALLOWED' : 'AUTH_POLICY_DENIED',
            $operation->actorUserId ?? 1, $resourceType, $action, 1, null, $operation->evaluationTime,
            [], [], [], [], $operation->requestId,
        );
    }
}

final class Num001PatternValidator implements NumberingPatternValidator
{
    public function validate(string $pattern, array $series): void
    {
        if ($pattern === '') {
            throw new DomainException(AutoNumberingConfigurationService::ERROR_PATTERN_INVALID, ['field' => 'pattern']);
        }
    }
}
