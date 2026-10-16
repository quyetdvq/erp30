<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
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
use Tests\Support\Authorization\EligibleActorFixture;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Real in-process API-040 route/controller coverage for BE-REORG-006. */
final class BeReorg006NumberingHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;
    private int $user;
    private int $resource;
    private int $groupOrganization;
    private int $requestNumber = 0;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
        (new ResourceActionSeeder(new Database()))->run();
        $this->groupOrganization = $this->insert('organizations', [
            'code' => 'BE006-O-' . uniqid(),
            'name' => 'BE006 numbering group root',
            'type' => 'GROUP',
            'parent_id' => null,
            'status' => 'ACTIVE',
        ]);
        $this->user = $this->authorizedUser();
        $this->resource = $this->resource('be006-' . uniqid());
    }

    protected function tearDown(): void
    {
        RequestContext::reset();
        $this->resetServices();
        parent::tearDown();
    }

    public function testListSeriesThroughRealRoute(): void
    {
        $series = $this->series('LIST');
        $body = $this->json($this->request('GET', '/api/v1/resources/' . $this->resource . '/numbering-series'), 200);
        $this->assertSame($series, (int) $body['data'][0]['id']);
        $this->assertSame(1, $body['meta']['total']);
    }

    public function testCreateSeriesThroughRealRoute(): void
    {
        $body = $this->json($this->request('POST', '/api/v1/resources/' . $this->resource . '/numbering-series', $this->payload('CREATE')), 201);
        $this->assertSame('CREATE', $body['data']['series_code']);
        $this->assertSame($this->resource, (int) $body['data']['resource_id']);
        $this->assertSame('1', $body['data']['start_number']);
    }

    public function testGetSeriesThroughRealRoute(): void
    {
        $series = $this->series('GET');
        $body = $this->json($this->request('GET', '/api/v1/resources/' . $this->resource . '/numbering-series/' . $series), 200);
        $this->assertSame($series, (int) $body['data']['id']);
        $this->assertSame('GET', $body['data']['series_code']);
    }

    public function testPatchSeriesThroughRealRoute(): void
    {
        $series = $this->series('PATCH');
        $body = $this->json($this->request('PATCH', '/api/v1/resources/' . $this->resource . '/numbering-series/' . $series, ['name' => 'Renamed']), 200);
        $this->assertSame('Renamed', $body['data']['name']);
        $this->assertSame($series, (int) $body['data']['id']);
    }

    public function testSetStateThroughRealRoute(): void
    {
        $series = $this->series('STATE');
        $body = $this->json($this->request('POST', '/api/v1/resources/' . $this->resource . '/numbering-series/' . $series . '/actions/set-state', ['status' => 'INACTIVE']), 200);
        $this->assertSame('INACTIVE', $body['data']['status']);
    }

    public function testValidateConfigThroughRealRouteWithoutPersistence(): void
    {
        $before = (int) $this->db->table('auto_numbering_series')->where('resource_id', $this->resource)->countAllResults();
        $body = $this->json($this->request('POST', '/api/v1/resources/' . $this->resource . '/numbering-series/actions/validate-config', $this->payload('VALIDATE')), 200);
        $after = (int) $this->db->table('auto_numbering_series')->where('resource_id', $this->resource)->countAllResults();
        $this->assertTrue($body['data']['valid']);
        $this->assertSame('V{SEQ:4}', $body['data']['normalized_pattern']);
        $this->assertSame($before, $after);
    }

    /** @param array<string,mixed>|null $payload */
    private function request(string $method, string $path, ?array $payload = null): TestResponse
    {
        $this->resetServices();
        $this->mockCache();
        $this->mockEmail();
        $this->mockSession();
        $this->app = $this->createApplication();
        $this->routes = null;
        $this->session = [];
        RequestContext::reset();
        $user = (new UserModel())->find($this->user);
        $this->assertInstanceOf(User::class, $user);
        $this->actingAs($user);
        $headers = [
            'Accept' => 'application/json',
            'Accept-Language' => 'en',
            'X-Request-ID' => 'be-reorg-006-' . ++$this->requestNumber,
        ];
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
            $headers[config('Security')->headerName] = service('security')->getHash();
        }
        $this->withSession();
        $this->withHeaders($headers)->withBodyFormat('')->withBody($payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR));
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

    /** @return array<string,mixed> */
    private function json(TestResponse $response, int $status): array
    {
        $response->assertStatus($status);
        $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $body = json_decode((string) $response->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);
        $this->assertSame('be-reorg-006-' . $this->requestNumber, $body['meta']['request_id']);
        return $body;
    }

    /** @return array<string,mixed> */
    private function payload(string $code): array
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
            'is_default' => false,
            'status' => 'ACTIVE',
        ];
    }

    private function series(string $code): int
    {
        if (! $this->db->table('auto_numbering_series')->insert($this->payload($code) + [
            'resource_id' => $this->resource,
            'created_at' => '2026-10-16 08:00:00',
            'updated_at' => '2026-10-16 08:00:00',
        ])) {
            $this->fail('Could not insert Numbering series fixture.');
        }
        return (int) $this->db->insertID();
    }

    private function resource(string $type): int
    {
        if (! $this->db->table('resources')->insert([
            'type' => $type,
            'name' => $type,
            'domain' => 'SYSTEM',
            'created_at' => '2026-10-16 08:00:00',
        ])) {
            $this->fail('Could not insert Numbering resource fixture.');
        }
        return (int) $this->db->insertID();
    }

    private function authorizedUser(): int
    {
        $user = $this->insert('users', ['username' => 'be006-' . uniqid(), 'status' => 'active', 'active' => 1]);
        $code = 'BE006-' . strtoupper(substr(uniqid(), -6));
        $group = $this->insert('groups', ['code' => $code, 'name' => 'BE006 numbering', 'status' => 'ACTIVE']);
        $this->insert('auth_groups_users', ['user_id' => $user, 'group' => strtolower($code)]);
        $resource = $this->db->table('resources')->where('type', 'resource')->get()->getRowArray();
        $this->assertIsArray($resource);
        $action = $this->db->table('resource_actions')->where(['resource_id' => (int) $resource['id'], 'code' => 'configure_numbering'])->get()->getRowArray();
        $this->assertIsArray($action);
        $this->insert('group_permissions', ['group_id' => $group, 'resource_action_id' => (int) $action['id']]);
        EligibleActorFixture::assignAtOrganization($this->db, $user, $this->groupOrganization);
        return $user;
    }

    /** @param array<string,mixed> $data */
    private function insert(string $table, array $data): int
    {
        if (! $this->db->table($table)->insert($data + ['created_at' => '2026-10-16 08:00:00'])) {
            $this->fail('Could not insert Numbering fixture row: ' . $table);
        }
        return (int) $this->db->insertID();
    }
}
