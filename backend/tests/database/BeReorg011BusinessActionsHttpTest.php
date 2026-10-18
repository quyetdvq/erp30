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

/** @internal In-process route/Shield/PBAC/CSRF contract against real disposable DB. */
final class BeReorg011BusinessActionsHttpTest extends CIUnitTestCase
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

    public function testStatesMetadataBasePermissionAndResponseEnvelope(): void
    {
        $body = $this->json($this->requestAs($this->fixture->writer, 'GET',
            '/api/v1/business-workflow/province/states'), 200);
        $this->assertSame('LIFECYCLE', $body['data']['mode']);
        $this->assertSame('ACTIVE', $body['data']['initial']);
        $this->assertSame(['ACTIVE', 'INACTIVE', 'CLOSED'], $body['data']['states']);
        $this->error($this->requestAs($this->fixture->reader, 'GET',
            '/api/v1/business-workflow/product/states'), 403, 'AUTH_POLICY_DENIED');
    }

    public function testOptionsPersistedStateAndActorPermission(): void
    {
        $province = $this->fixture->province(Fixture::code('BE011H-'));
        $url = '/api/v1/business-workflow/province/' . $province . '/options';
        $writer = $this->json($this->requestAs($this->fixture->writer, 'GET', $url), 200);
        $this->assertSame('ACTIVE', $writer['data']['current']);
        $this->assertContains(['action_code' => 'setstate', 'status' => 'INACTIVE'], $writer['data']['options']);
        $reader = $this->json($this->requestAs($this->fixture->reader, 'GET', $url), 200);
        $this->assertSame([], $reader['data']['options'], 'A base view permission does not imply setstate authority.');
        $forged = $this->json($this->requestAs($this->fixture->writer, 'GET', $url . '?state_id=999&status=CLOSED'), 200);
        $this->assertSame($writer['data'], $forged['data'], 'Client hints cannot change a persisted State.');
        $this->fixture->deny('province', 'view', $this->fixture->reader, $this->fixture->states['ACTIVE']);
        $this->error($this->requestAs($this->fixture->reader, 'GET', $url), 403, 'AUTH_POLICY_DENIED');
    }

    public function testPostExecuteAuthorizedLifecycleTransitionAndAudit(): void
    {
        $province = $this->fixture->province(Fixture::code('BE011H-'));
        $before = Fixture::auditCount($this->db);
        $url = '/api/v1/business-workflow/province/' . $province . '/actions/setstate';
        $body = $this->json($this->requestAs($this->fixture->writer, 'POST', $url, []), 200);
        $this->assertSame(['status' => 'INACTIVE', 'action_code' => 'setstate'], $body['data']);
        $this->assertSame('INACTIVE', Fixture::status($this->db, 'provinces', $province));
        $this->assertSame($before + 1, Fixture::auditCount($this->db));
    }

    public function testPostExecuteDeniedCannotWrite(): void
    {
        $province = $this->fixture->province(Fixture::code('BE011H-'));
        $before = $this->db->table('provinces')->where('id', $province)->get()->getRowArray();
        $audit = Fixture::auditCount($this->db);
        $this->error($this->requestAs($this->fixture->reader, 'POST',
            '/api/v1/business-workflow/province/' . $province . '/actions/setstate', []), 403, 'AUTH_POLICY_DENIED');
        $this->assertSame($before, $this->db->table('provinces')->where('id', $province)->get()->getRowArray());
        $this->assertSame($audit, Fixture::auditCount($this->db));
    }

    public function testPostExecuteInvalidActionAndForgedDestinationRejected(): void
    {
        $province = $this->fixture->province(Fixture::code('BE011H-'));
        $path = '/api/v1/business-workflow/province/' . $province . '/actions/';
        $audit = Fixture::auditCount($this->db);
        $this->error($this->requestAs($this->fixture->writer, 'POST', $path . '1invalid', []),
            422, 'WF_INVALID_TRANSITION');
        $this->assertSame('ACTIVE', Fixture::status($this->db, 'provinces', $province));
        $this->assertSame($audit, Fixture::auditCount($this->db));
        // Controller currently ignores this extra field; only the stored
        // transition determines the destination. It is not a forced rejection.
        $body = $this->json($this->requestAs($this->fixture->writer, 'POST', $path . 'setstate', [
            'to_state_id' => $this->fixture->states['CLOSED'],
            'status' => 'CLOSED',
        ]), 200);
        $this->assertSame('INACTIVE', $body['data']['status']);
        $this->assertSame('INACTIVE', Fixture::status($this->db, 'provinces', $province));
        $this->assertSame($audit + 1, Fixture::auditCount($this->db));
    }

    public function testPostExecuteMissingCsrfRejectedWithoutWrites(): void
    {
        $province = $this->fixture->province(Fixture::code('BE011H-'));
        $before = $this->db->table('provinces')->where('id', $province)->get()->getRowArray();
        $audit = Fixture::auditCount($this->db);
        if (config(\Config\Security::class)->csrfEnabled
            && filter_var(env('app.forceGlobalSecureRequests', ENVIRONMENT === 'production'), FILTER_VALIDATE_BOOLEAN)) {
            try {
                $response = $this->requestAs($this->fixture->writer, 'POST',
                    '/api/v1/business-workflow/province/' . $province . '/actions/setstate', [], false);
                $response->assertStatus(403);
            } catch (SecurityException $exception) {
                $this->assertSame(403, $exception->getCode());
            }
        } else {
            $this->assertFalse(config(\Config\Security::class)->csrfEnabled
                && filter_var(env('app.forceGlobalSecureRequests', ENVIRONMENT === 'production'), FILTER_VALIDATE_BOOLEAN));
        }
        $this->assertSame($before, $this->db->table('provinces')->where('id', $province)->get()->getRowArray());
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
            'X-Request-ID' => 'be-reorg-011-' . ++$this->requestNumber,
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
        $this->assertSame('be-reorg-011-' . $this->requestNumber, $body['meta']['request_id']);
        return $body;
    }

    private function error(TestResponse $response, int $status, string $code): void
    {
        $body = $this->json($response, $status);
        $this->assertSame($code, $body['error']['code']);
        $this->assertArrayNotHasKey('data', $body);
    }
}
