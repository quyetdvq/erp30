<?php

declare(strict_types=1);

use App\Debug\ApiExceptionHandler;
use App\Exceptions\DomainException;
use App\Infrastructure\RequestContext;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Database;
use Config\Services;
use Tests\Support\Foundation\RuntimeFixture;
use Tests\Support\Foundation\RuntimeGuard;
use Tests\Support\Workflow\BeReorg011BusinessActionsFixture as Fixture;

require_once __DIR__ . '/../_support/Workflow/BeReorg011BusinessActionsFixture.php';

/** @internal Existing province create/PATCH route behavior, not a substitute for service-level guard tests. */
final class BeReorg012WriteBoundaryHttpTest extends CIUnitTestCase
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
        Fixture::rootWriter($this->db, $this->fixture);
        Fixture::rootActor($this->db, $this->fixture->reader, $this->fixture->group);
    }

    protected function tearDown(): void
    {
        RequestContext::reset();
        $this->resetServices();
        parent::tearDown();
    }

    public function testApiCreateUsesConfiguredInitialAndRejectsForgedStatus(): void
    {
        $before = $this->db->table('provinces')->countAllResults();
        $code = Fixture::code('BE012H-');
        $body = $this->json($this->requestAs($this->fixture->writer, 'POST', '/api/v1/provinces', [
            'code' => $code, 'name' => 'BE012 HTTP',
        ]), 201);
        $this->assertSame('ACTIVE', $body['data']['status']);
        $row = $this->db->table('provinces')->where('code', $code)->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame('ACTIVE', $row['status']);
        $this->assertSame($before + 1, $this->db->table('provinces')->countAllResults());
        $audit = Fixture::auditCount($this->db);
        $this->error($this->requestAs($this->fixture->writer, 'POST', '/api/v1/provinces', [
            'code' => Fixture::code('BE012F-'), 'name' => 'forged', 'status' => 'INACTIVE',
        ]), 422, 'WF_INITIAL_STATE_INVALID');
        $this->assertSame($before + 1, $this->db->table('provinces')->countAllResults());
        $this->assertSame($audit, Fixture::auditCount($this->db));
        $this->error($this->requestAs($this->fixture->reader, 'POST', '/api/v1/provinces', [
            'code' => Fixture::code('BE012R-'), 'name' => 'unauthorized',
        ]), 403, 'AUTH_POLICY_DENIED');
        $this->assertSame($before + 1, $this->db->table('provinces')->countAllResults());
    }

    public function testApiUpdateRejectsDirectStatusMutationWithoutAudit(): void
    {
        $id = $this->fixture->province(Fixture::code('BE012H-'));
        $before = $this->db->table('provinces')->where('id', $id)->get()->getRowArray();
        $audit = Fixture::auditCount($this->db);
        $path = '/api/v1/provinces/' . $id;
        $this->error($this->requestAs($this->fixture->writer, 'PATCH', $path,
            ['status' => 'INACTIVE']), 422, 'WF_TRANSITION_REQUIRED');
        $this->assertSame($before, $this->db->table('provinces')->where('id', $id)->get()->getRowArray());
        // Ordinary edit proves the read-only actor lacks edit PBAC authority.
        $this->error($this->requestAs($this->fixture->reader, 'PATCH', $path,
            ['name' => 'forbidden edit']), 403, 'AUTH_POLICY_DENIED');
        $this->assertSame($before, $this->db->table('provinces')->where('id', $id)->get()->getRowArray());
        // BE010's State-for-authorization guard rejects a forged status before
        // it evaluates the edit base permission (422 for any actor, no writes).
        $this->error($this->requestAs($this->fixture->reader, 'PATCH', $path,
            ['status' => 'INACTIVE']), 422, 'WF_TRANSITION_REQUIRED');
        $this->assertSame($before, $this->db->table('provinces')->where('id', $id)->get()->getRowArray());
        $this->assertSame($audit, Fixture::auditCount($this->db));
    }

    /** @param array<string,mixed>|null $payload */
    private function requestAs(?int $actor, string $method, string $path, ?array $payload = null, bool $sendCsrf = true): TestResponse
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
            'Accept' => 'application/json', 'Accept-Language' => 'en',
            'X-Request-ID' => 'be-reorg-012-' . ++$this->requestNumber,
        ];
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
            if ($sendCsrf) {
                $headers[config('Security')->headerName] = service('security')->getHash();
            }
        }
        $this->withSession();
        $this->withHeaders($headers)->withBodyFormat('')->withBody(
            $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR),
        );
        $globals = service('superglobals');
        $previousUri = $globals->server('REQUEST_URI');
        $level = ob_get_level();
        try {
            $globals->setServer('REQUEST_URI', $path);
            return $this->call($method, $path);
        } catch (DomainException|PageNotFoundException|BadRequestException $exception) {
            $response = service('response');
            ob_start();
            (new ApiExceptionHandler(config('Exceptions')))->handle($exception, service('request'), $response, 500, 1);
            return new TestResponse($response);
        } finally {
            if ($previousUri === null) {
                $globals->unsetServer('REQUEST_URI');
            } else {
                $globals->setServer('REQUEST_URI', $previousUri);
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
        $this->assertSame('be-reorg-012-' . $this->requestNumber, $body['meta']['request_id']);
        return $body;
    }

    private function error(TestResponse $response, int $status, string $code): void
    {
        $body = $this->json($response, $status);
        $this->assertSame($code, $body['error']['code']);
        $this->assertArrayNotHasKey('data', $body);
    }
}
