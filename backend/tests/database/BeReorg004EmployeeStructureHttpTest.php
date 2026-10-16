<?php

declare(strict_types=1);

use App\Debug\ApiExceptionHandler;
use App\Exceptions\DomainException;
use App\Infrastructure\RequestContext;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Database;
use Config\Services;
use Tests\Support\EmployeeStructure\RuntimeFixture;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal In-process HTTP integration; not browser/Apache UAT. */
final class BeReorg004EmployeeStructureHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;
    private RuntimeFixture $fixture;
    private int $requestNumber = 0;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
        $this->fixture = new RuntimeFixture($this->db);
    }

    protected function tearDown(): void
    {
        RequestContext::reset();
        $this->resetServices();
        parent::tearDown();
    }

    public function testDepartmentCrudAndListRetainHttpWireContract(): void
    {
        $code = 'BR004-D-' . uniqid();
        $created = $this->json($this->requestAs($this->fixture->writer, 'POST', '/api/v1/departments', [
            'code' => $code, 'name' => 'BR004 Department',
        ]), 201)['data'];
        $this->assertSame($code, $created['code']);
        $shown = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/departments/' . $created['id']), 200);
        $this->assertSame($created['id'], $shown['data']['id']);
        $changed = $this->json($this->requestAs($this->fixture->writer, 'PATCH', '/api/v1/departments/' . $created['id'], ['name' => 'BR004 Department Updated']), 200);
        $this->assertSame('BR004 Department Updated', $changed['data']['name']);
        $list = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/departments?q=' . rawurlencode($code)), 200);
        $this->assertContains($created['id'], array_column($list['data'], 'id'));
    }

    public function testPositionCrudAndListRetainHttpWireContract(): void
    {
        $code = 'BR004-P-' . uniqid();
        $created = $this->json($this->requestAs($this->fixture->writer, 'POST', '/api/v1/positions', [
            'code' => $code, 'name' => 'BR004 Position', 'level' => 4,
        ]), 201)['data'];
        $this->assertSame($code, $created['code']);
        $changed = $this->json($this->requestAs($this->fixture->writer, 'PATCH', '/api/v1/positions/' . $created['id'], ['name' => 'BR004 Position Updated']), 200);
        $this->assertSame('BR004 Position Updated', $changed['data']['name']);
        $list = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/positions?q=' . rawurlencode($code)), 200);
        $this->assertContains($created['id'], array_column($list['data'], 'id'));
    }

    public function testEmployeeCrudAndListRetainCanonicalGender(): void
    {
        $code = 'BR004-E-' . uniqid();
        $created = $this->json($this->requestAs($this->fixture->writer, 'POST', '/api/v1/employees', [
            'employee_code' => $code, 'employee_name' => 'BR004 Employee', 'gender' => 'MALE',
        ]), 201)['data'];
        $this->assertSame('MALE', $created['gender']);
        $changed = $this->json($this->requestAs($this->fixture->writer, 'PATCH', '/api/v1/employees/' . $created['id'], ['employee_name' => 'BR004 Employee Updated']), 200);
        $this->assertSame('BR004 Employee Updated', $changed['data']['employee_name']);
        $list = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/employees?q=' . rawurlencode($code)), 200);
        $this->assertContains($created['id'], array_column($list['data'], 'id'));
    }

    public function testEmployeeAssignmentCrudAndListRetainOrganizationScope(): void
    {
        $created = $this->json($this->requestAs($this->fixture->writer, 'POST', '/api/v1/employee-assignments', [
            'employee_id' => $this->fixture->assignmentEmployee,
            'organization_id' => $this->fixture->company,
            'is_primary' => false,
            'effective_from' => '2026-01-01',
            'description' => 'BR004 assignment',
        ]), 201)['data'];
        $this->assertSame((string) $this->fixture->assignmentEmployee, (string) $created['employee_id']);
        $this->assertSame((string) $this->fixture->company, (string) $created['organization_id']);
        $changed = $this->json($this->requestAs($this->fixture->writer, 'PATCH', '/api/v1/employee-assignments/' . $created['id'], ['description' => 'BR004 assignment updated']), 200);
        $this->assertSame('BR004 assignment updated', $changed['data']['description']);
        $query = http_build_query(['filter' => ['employee_id' => $this->fixture->assignmentEmployee]]);
        $list = $this->json($this->requestAs($this->fixture->writer, 'GET', '/api/v1/employee-assignments?' . $query), 200);
        $this->assertContains($created['id'], array_column($list['data'], 'id'));
    }

    public function testAnonymousEmployeeMutationIsRejectedWithoutWriting(): void
    {
        $before = $this->db->table('employees')->countAllResults();
        $this->error($this->requestAs(null, 'POST', '/api/v1/employees', [
            'employee_code' => 'BR004-ANON-' . uniqid(), 'employee_name' => 'Anonymous',
        ]), 401, 'UNAUTHENTICATED');
        $this->assertSame($before, $this->db->table('employees')->countAllResults());
    }

    /** @param array<string, mixed>|null $payload */
    private function requestAs(?int $actor, string $method, string $path, ?array $payload = null): TestResponse
    {
        $this->resetServices();
        $this->mockCache();
        $this->mockEmail();
        $this->mockSession();
        $this->app = $this->createApplication();
        $this->routes = null;
        $this->session = [];
        RequestContext::reset();
        Services::injectMock('clock', RuntimeFixture::clock());
        if ($actor !== null) {
            $user = (new UserModel())->find($actor);
            $this->assertInstanceOf(User::class, $user);
            $this->actingAs($user);
        }
        $headers = [
            'Accept' => 'application/json',
            'Accept-Language' => 'en',
            'X-Request-ID' => 'be-reorg-004-' . ++$this->requestNumber,
        ];
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
            $headers[config('Security')->headerName] = service('security')->getHash();
        }
        $this->withSession();
        $this->withHeaders($headers)->withBodyFormat('')->withBody(
            $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR),
        );
        $superglobals = service('superglobals');
        $previousRequestUri = $superglobals->server('REQUEST_URI');
        $level = ob_get_level();
        try {
            $superglobals->setServer('REQUEST_URI', $path);
            return $this->call($method, $path);
        } catch (DomainException | PageNotFoundException | BadRequestException $exception) {
            $response = service('response');
            ob_start();
            (new ApiExceptionHandler(config('Exceptions')))->handle($exception, service('request'), $response, 500, 1);

            return new TestResponse($response);
        } finally {
            if ($previousRequestUri === null) {
                $superglobals->unsetServer('REQUEST_URI');
            } else {
                $superglobals->setServer('REQUEST_URI', $previousRequestUri);
            }
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    /** @return array<string, mixed> */
    private function json(TestResponse $response, int $status): array
    {
        $response->assertStatus($status);
        $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $body = json_decode((string) $response->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);
        $this->assertSame('be-reorg-004-' . $this->requestNumber, $body['meta']['request_id']);

        return $body;
    }

    private function error(TestResponse $response, int $status, string $code): void
    {
        $body = $this->json($response, $status);
        $this->assertSame($code, $body['error']['code']);
        $this->assertArrayNotHasKey('data', $body);
    }
}
