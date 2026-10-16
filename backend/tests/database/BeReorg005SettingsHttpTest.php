<?php

declare(strict_types=1);

use App\Debug\ApiExceptionHandler;
use App\Exceptions\DomainException;
use App\Infrastructure\RequestContext;
use App\Models\UserModel;
use App\Repositories\ConfigurationAuditEventRepository;
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
use Tests\Support\Foundation\RuntimeGuard;
use Tests\Support\Settings\RuntimeFixture;

/** @internal In-process HTTP integration; not browser/Apache UAT. */
final class BeReorg005SettingsHttpTest extends CIUnitTestCase
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
        try {
            if (isset($this->fixture)) {
                $this->fixture->cleanupRollbackBlockers();
            }
        } finally {
            RequestContext::reset();
            $this->resetServices();
            parent::tearDown();
        }
    }

    public function testRuntimeGetRequiresAuthenticationAndRespectsOrganizationVisibility(): void
    {
        $this->error($this->requestAs(null, 'GET', '/api/v1/display-settings/runtime'), 401, 'UNAUTHENTICATED');
        $body = $this->json($this->requestAs($this->fixture->user, 'GET', '/api/v1/display-settings/runtime?organization_id=' . $this->fixture->company), 200);
        $this->assertArrayHasKey('timezone', $body['data']);
        $this->assertArrayNotHasKey('business_timezone', $body['data']);
    }

    public function testDefinitionsGetReturnsUiSafeFifteenDefinitions(): void
    {
        $body = $this->json($this->requestAs($this->fixture->user, 'GET', '/api/v1/display-settings/definitions'), 200);
        $this->assertCount(15, $body['data']);
        $this->assertSame(['key', 'category', 'data_type', 'default', 'user_overridable'], array_keys($body['data'][0]));
        $this->assertArrayNotHasKey('storage_key', $body['data'][0]);
    }

    public function testValuesGetPreservesSystemAndOrganizationShape(): void
    {
        $system = $this->json($this->requestAs($this->fixture->user, 'GET', '/api/v1/display-settings/values?context_type=SYSTEM'), 200);
        $this->assertCount(15, $system['data']);
        $query = http_build_query(['context_type' => 'ORGANIZATION', 'organization_id' => $this->fixture->company]);
        $organization = $this->json($this->requestAs($this->fixture->user, 'GET', '/api/v1/display-settings/values?' . $query), 200);
        $this->assertSame('Display.timezone', $organization['data'][0]['key']);
        $this->assertSame('SYSTEM', $organization['data'][0]['source']['type']);
        $this->assertTrue($organization['data'][0]['inherited']);
    }

    public function testPatchSetPreservesValidationEnvelopeAndChangedValues(): void
    {
        $payload = ['context' => ['type' => 'ORGANIZATION', 'organization_id' => (string) $this->fixture->company], 'changes' => [
            ['key' => 'Display.quantityDecimals', 'value' => 4],
            ['key' => 'Display.trailingZeros', 'value' => true],
        ]];
        $body = $this->json($this->requestAs($this->fixture->user, 'PATCH', '/api/v1/display-settings', $payload), 200);
        $this->assertSame(['Display.quantityDecimals', 'Display.trailingZeros'], array_column($body['data'], 'key'));
        $this->assertSame(4, $body['data'][0]['override_value']);
        $bad = ['context' => ['type' => 'SYSTEM'], 'changes' => [['key' => 'Display.unknown', 'value' => 'x']]];
        $this->error($this->requestAs($this->fixture->user, 'PATCH', '/api/v1/display-settings', $bad), 422, 'DISPLAY_SETTING_NOT_FOUND');
    }

    public function testPostResetIsIdempotentAndAuditsOnlyPersistedChange(): void
    {
        $context = ['type' => 'ORGANIZATION', 'organization_id' => (string) $this->fixture->company];
        $this->json($this->requestAs($this->fixture->user, 'PATCH', '/api/v1/display-settings', ['context' => $context, 'changes' => [['key' => 'Display.quantityDecimals', 'value' => 4]]]), 200);
        $before = count((new ConfigurationAuditEventRepository($this->db))->findAll());
        $reset = $this->json($this->requestAs($this->fixture->user, 'POST', '/api/v1/display-settings/actions/reset', ['context' => $context, 'keys' => ['Display.quantityDecimals']]), 200);
        $this->assertNull($reset['data'][0]['override_value']);
        $this->assertSame(3, $reset['data'][0]['effective_value']);
        $this->assertCount($before + 1, (new ConfigurationAuditEventRepository($this->db))->findAll());
        $this->json($this->requestAs($this->fixture->user, 'POST', '/api/v1/display-settings/actions/reset', ['context' => $context, 'keys' => ['Display.quantityDecimals']]), 200);
        $this->assertCount($before + 1, (new ConfigurationAuditEventRepository($this->db))->findAll());
    }

    /** @param array<string,mixed>|null $payload */
    private function requestAs(?int $actor, string $method, string $path, ?array $payload = null): TestResponse
    {
        $this->resetServices(); $this->mockCache(); $this->mockEmail(); $this->mockSession();
        $this->app = $this->createApplication(); $this->routes = null; $this->session = []; RequestContext::reset();
        Services::injectMock('clock', RuntimeFixture::clock());
        if ($actor !== null) {
            $user = (new UserModel())->find($actor);
            $this->assertInstanceOf(User::class, $user);
            $this->actingAs($user);
        }
        $headers = ['Accept'=>'application/json','Accept-Language'=>'en','X-Request-ID'=>'be-reorg-005-' . ++$this->requestNumber];
        if ($payload !== null) { $headers['Content-Type']='application/json'; $headers[config('Security')->headerName]=service('security')->getHash(); }
        $this->withSession();
        $this->withHeaders($headers)->withBodyFormat('')->withBody($payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR));
        $superglobals = service('superglobals'); $previousRequestUri = $superglobals->server('REQUEST_URI'); $level = ob_get_level();
        try {
            $superglobals->setServer('REQUEST_URI', $path);
            return $this->call($method, $path);
        } catch (DomainException | PageNotFoundException | BadRequestException $exception) {
            $response = service('response'); ob_start();
            (new ApiExceptionHandler(config('Exceptions')))->handle($exception, service('request'), $response, 500, 1);
            return new TestResponse($response);
        } finally {
            if ($previousRequestUri === null) { $superglobals->unsetServer('REQUEST_URI'); } else { $superglobals->setServer('REQUEST_URI', $previousRequestUri); }
            while (ob_get_level() > $level) { ob_end_clean(); }
        }
    }

    /** @return array<string,mixed> */
    private function json(TestResponse $response, int $status): array
    {
        $response->assertStatus($status); $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $body = json_decode((string) $response->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body); $this->assertSame('be-reorg-005-' . $this->requestNumber, $body['meta']['request_id']);
        return $body;
    }

    private function error(TestResponse $response, int $status, string $code): void
    {
        $body = $this->json($response, $status); $this->assertSame($code, $body['error']['code']); $this->assertArrayNotHasKey('data', $body);
    }
}
