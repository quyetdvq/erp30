<?php

declare(strict_types=1);

use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Authentication\Authenticators\Session as ShieldSession;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use Tests\Support\Foundation\RuntimeGuard;
use Tests\Support\ProductMedia\Pm3WireHttpClient;
use Tests\Support\ProductMedia\Pm3FixtureLedger;
use Tests\Support\ProductMedia\Pm3CommittedFixture;
use Tests\Support\Workflow\BeReorg011BusinessActionsFixture;

/**
 * Real HTTP loopback/PHP multipart and session CSRF probes.
 * Do NOT describe these as Apache/HTTPS/browser acceptance: the test-only
 * router marks the request secure, but transport is plaintext 127.0.0.1.
 * Fixture rows are COMMITTED so the separate web server can see them, then
 * removed by explicit IDs in tearDown (unlike MediaTestCase's rollback).
 */
final class ProductMediaPm3WireHttpTest extends CIUnitTestCase
{
    private BaseConnection $wireDb;
    private ?Pm3CommittedFixture $fixture = null;
    private int $productId = 0;
    private string $password = '';
    private string $prefix = '';
    private ?int $lifecycleId = null;
    private ?int $rootIdBeforeFixture = null;
    /** @var resource|false|null */
    private $serverProcess = null;
    /** @var list<Pm3WireHttpClient> */
    private array $clients = [];
    private string $base = '';
    private ?string $ledgerPath = null;

    protected function setUp(): void
    {
        if (! defined('PRODUCT_MEDIA_GUARDED_RUN') || PRODUCT_MEDIA_GUARDED_RUN !== true
            || getenv('ERP_PM3_WIRE_ENABLE') !== '1') {
            throw new RuntimeException('PM-3 wire tests require tools/test-product-media-pm3-01.php.');
        }
        parent::setUp();
        RuntimeGuard::assertConfiguration(ENVIRONMENT, config(Database::class)->defaultGroup, config(Database::class)->tests);
        $write = getenv('ERP_PM_WRITEPATH');
        if (! is_string($write) || realpath($write) !== realpath(WRITEPATH)
            || ! is_file(WRITEPATH . '.product-media-test-sandbox')) {
            throw new RuntimeException('PM-3 wire test requires isolated WRITEPATH.');
        }
        $this->wireDb = Database::connect('tests', false);
        $actual = $this->wireDb->query('SELECT DATABASE() AS db, @@port AS port')->getRowArray();
        if (($actual['db'] ?? '') !== 'erp_test' || (int) ($actual['port'] ?? 0) !== 3306) {
            throw new RuntimeException('PM-3 wire fixture refuses unexpected database.');
        }
        $this->assertPrewritePrerequisites();
        $this->seedCommittedFixture();
        $this->startServer();
    }

    private function assertPrewritePrerequisites(): void
    {
        // Product/Province/Ward resources and ACTIVE/INACTIVE/CLOSED states are
        // NOT prerequisites: prior suites may have rolled back their seeds.
        // Pm3CommittedFixture creates only the missing rows transactionally and
        // records verified ownership in the crash-recovery ledger.
        foreach (['resources', 'resource_actions', 'workflow_states'] as $table) {
            if (! $this->wireDb->tableExists($table)) {
                throw new RuntimeException('PM-3 requires migrated test schema: ' . $table . '. No writes performed.');
            }
        }
        // Enforce root cardinality BEFORE any fixture writes. A disposable DB
        // may legitimately have zero roots; never create a second canonical one.
        $roots = $this->wireDb->table('organizations')->where('type', 'GROUP')
            ->where('parent_id', null)->get()->getResultArray();
        if (count($roots) > 1) {
            throw new RuntimeException('PM-3 wire test refuses multiple GROUP roots. No fixture writes performed.');
        }
        if ($roots !== []) {
            $this->rootIdBeforeFixture = (int) ($roots[0]['id'] ?? 0);
            if ($this->rootIdBeforeFixture < 1) {
                throw new RuntimeException('PM-3 wire test refuses malformed pre-existing GROUP root.');
            }
        }
    }

    private function seedCommittedFixture(): void
    {
        if (! $this->wireDb->transBegin()) {
            throw new RuntimeException('Cannot start PM-3 wire fixture transaction.');
        }
        $committed = false;
        try {
            $this->fixture = new Pm3CommittedFixture($this->wireDb);
            foreach ([$this->fixture->writer, $this->fixture->reader] as $actor) {
                BeReorg011BusinessActionsFixture::rootActor($this->wireDb, $actor, $this->fixture->group);
            }
            foreach (['view', 'edit'] as $action) {
                BeReorg011BusinessActionsFixture::grant($this->wireDb, 'product', $action, $this->fixture->writerGroupCode);
            }
            BeReorg011BusinessActionsFixture::grant($this->wireDb, 'product', 'view', $this->fixture->readerGroupCode);
            $this->prefix = substr($this->fixture->writerGroupCode, 0, -strlen('-WRITER'));
            $root = $this->wireDb->table('organizations')
                ->select('id, code, type, parent_id')->where('id', $this->fixture->group)->get()->getRowArray();
            if (! is_array($root) || (int) ($root['id'] ?? 0) < 1
                || ($root['type'] ?? null) !== 'GROUP' || ($root['parent_id'] ?? null) !== null
                || ! is_string($root['code'] ?? null)) {
                throw new RuntimeException('PM-3 fixture GROUP root identity is invalid.');
            }
            $rootCreated = $this->rootIdBeforeFixture === null;
            if ($rootCreated && $root['code'] !== $this->prefix . '-G') {
                throw new RuntimeException('PM-3 ephemeral GROUP root code is not owned by this fixture.');
            }
            if (! $rootCreated && (int) $root['id'] !== $this->rootIdBeforeFixture) {
                throw new RuntimeException('PM-3 fixture unexpectedly replaced a pre-existing GROUP root.');
            }
            $lifecycle = $this->wireDb->table('lifecycles')->where('code', $this->prefix . '-LC')->get()->getRowArray();
            if (! is_array($lifecycle)) { throw new RuntimeException('Synthetic lifecycle lookup failed.'); }
            $this->lifecycleId = (int) $lifecycle['id'];
            if (! $this->wireDb->table('products')->insert([
                'code' => 'PM3-' . bin2hex(random_bytes(7)),
                'product_type' => 'STANDARD', 'product_name' => 'PM-3 ephemeral wire media',
                'status' => 'ACTIVE', 'serial_management_enabled' => 0,
                'created_at' => '2026-10-19 00:00:00',
            ])) { throw new RuntimeException('Could not insert synthetic PM-3 Product.'); }
            $this->productId = (int) $this->wireDb->insertID();
            $this->password = 'Pm3-' . bin2hex(random_bytes(20)) . '-aA1!';
            $ledgerDir = getenv('ERP_PM3_LEDGER_DIR');
            if (! is_string($ledgerDir) || ! is_dir($ledgerDir)
                || ! str_ends_with(str_replace('\\', '/', $ledgerDir), '/fixtures')) {
                throw new RuntimeException('Missing PM-3 runner-owned fixture ledger directory.');
            }
            $this->ledgerPath = $ledgerDir . '/fixture-' . bin2hex(random_bytes(8)) . '.json';
            $groupRows = $this->wireDb->table('groups')->select('id, code')
                ->whereIn('code', [$this->fixture->writerGroupCode, $this->fixture->readerGroupCode])->get()->getResultArray();
            $actorRows = $this->wireDb->table('users')->select('id, username')
                ->whereIn('id', [$this->fixture->writer, $this->fixture->reader])->get()->getResultArray();
            if (count($groupRows) !== 2 || count($actorRows) !== 2) {
                throw new RuntimeException('PM-3 fixture cannot attest both actors and permission groups.');
            }
            $productRow = $this->wireDb->table('products')->select('code')->where('id', $this->productId)->get()->getRowArray();
            Pm3FixtureLedger::save($this->ledgerPath, [
                'version' => 3, 'status' => 'PENDING', 'run_token' => (string) getenv('ERP_PM_RUN_TOKEN'),
                'rows' => [
                    'product' => ['id' => $this->productId, 'code' => (string) $productRow['code']],
                    'company' => ['id' => $this->fixture->company, 'code' => $this->prefix . '-C'],
                    'group_root' => [
                        'id' => (int) $root['id'], 'code' => $root['code'],
                        'created_by_fixture' => $rootCreated,
                    ],
                    'lifecycle' => ['id' => $this->lifecycleId, 'code' => $this->prefix . '-LC'],
                    'actors' => array_map(static fn (array $row): array => [
                        'id' => (int) $row['id'], 'username' => (string) $row['username'],
                    ], $actorRows),
                    'groups' => array_map(static fn (array $row): array => [
                        'id' => (int) $row['id'], 'code' => (string) $row['code'],
                    ], $groupRows),
                    'resources' => $this->fixture->resourceSnapshots,
                    'workflow_states' => $this->fixture->workflowSnapshots,
                    'resource_actions' => $this->fixture->createdActions,
                ],
            ]);
            if ($this->wireDb->transStatus() === false || ! $this->wireDb->transCommit()) {
                throw new RuntimeException('PM-3 wire fixture commit failed.');
            }
            $committed = true;
            // Shield's model owns password identity persistence. Run it AFTER
            // the synthetic user transaction commits because Model may use its
            // own database connection; uncommitted users would be invisible.
            $model = new UserModel();
            foreach ([$this->fixture->writer, $this->fixture->reader] as $actor) {
                $user = $model->find($actor);
                if (! $user instanceof User) { throw new RuntimeException('Missing PM-3 Shield fixture user.'); }
                $email = 'pm3-' . $actor . '-' . bin2hex(random_bytes(5)) . '@example.invalid';
                $user->setEmail($email);
                $user->setPassword($this->password);
                if (! $model->save($user)) { throw new RuntimeException('Could not establish synthetic Shield credentials.'); }
                // This login is performed by a separate HTTP process. Assert
                // that Shield's email/password identity was COMMITTED first;
                // never log the password, identity hash or token in a failure.
                $identity = $this->wireDb->table('auth_identities')->select('secret, secret2')
                    ->where('user_id', $actor)
                    ->where('type', ShieldSession::ID_TYPE_EMAIL_PASSWORD)->get()->getRowArray();
                if (! is_array($identity) || ($identity['secret'] ?? null) !== $email
                    || ! is_string($identity['secret2'] ?? null)
                    || ! password_verify($this->password, $identity['secret2'])) {
                    throw new RuntimeException('PM-3 synthetic Shield email/password identity was not persisted correctly.');
                }
                $provider = auth('session')->getProvider();
                $providerUser = $provider->findByCredentials(['email' => $email]);
                if (! $providerUser instanceof User || (int) $providerUser->id !== $actor) {
                    throw new RuntimeException('PM-3 Shield user provider cannot resolve committed email identity.');
                }
                $providerHash = $providerUser->password_hash;
                if (! is_string($providerHash) || ! service('passwords')->verify($this->password, $providerHash)) {
                    throw new RuntimeException('PM-3 Shield passwords service rejects committed fixture credentials.');
                }
                if ((int) $providerUser->active !== 1 || $providerUser->isBanned()
                    || ! $providerUser->isActivated()) {
                    throw new RuntimeException('PM-3 Shield fixture user is inactive or banned after commit.');
                }
            }
        } catch (Throwable $error) {
            if (! $committed) {
                $this->wireDb->transRollback();
                $this->fixture = null;
                $this->productId = 0;
                if ($this->ledgerPath !== null && is_file($this->ledgerPath)) {
                    unlink($this->ledgerPath);
                }
            }
            // If already committed, keep the PENDING ledger for recovery.
            throw $error;
        }
    }

    private function startServer(): void
    {
        if (! function_exists('proc_open') || ! extension_loaded('curl')) {
            throw new RuntimeException('PM-3 wire tests require proc_open and curl.');
        }
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if (! is_resource($socket)) { throw new RuntimeException('Cannot reserve a loopback port.'); }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if (! is_string($address) || ! preg_match('/:(\d{2,5})\z/', $address, $found)) {
            throw new RuntimeException('Invalid PM-3 loopback port.');
        }
        $port = (int) $found[1];
        $this->base = 'http://127.0.0.1:' . $port . '/api/v1/';
        $backend = dirname(__DIR__, 2);
        $serverLog = getenv('ERP_PM3_HTTP_LOG');
        if (! is_string($serverLog) || ! str_ends_with($serverLog, 'http-server.log')
            || ! is_dir(dirname($serverLog))) {
            throw new RuntimeException('PM-3 wire server needs the runner-owned evidence log.');
        }
        $env = getenv();
        if (! is_array($env)) { $env = []; }
        $env['CI_ENVIRONMENT'] = 'testing';
        $env['app.forceGlobalSecureRequests'] = 'true';
        $env['security.csrfEnabled'] = 'true';
        $env['app.baseURL'] = 'http://127.0.0.1:' . $port . '/';
        $command = [PHP_BINARY, '-S', '127.0.0.1:' . $port,
            '-t', $backend . '/public', $backend . '/tests/_support/ProductMedia/pm3-wire-router.php'];
        $this->serverProcess = proc_open($command,
            [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'ab'], 2 => ['redirect', 1]],
            $pipes, $backend, $env, ['bypass_shell' => true]);
        if (! is_resource($this->serverProcess)) {
            throw new RuntimeException('Cannot start PM-3 loopback PHP server.');
        }
        fclose($pipes[0]);
        $serverStatus = proc_get_status($this->serverProcess);
        if (isset($serverStatus['pid'])) {
            file_put_contents($serverLog . '.pid', (string) $serverStatus['pid'] . PHP_EOL);
        }
        $probe = $this->client();
        /** @var array<int, int> $healthCodes */
        $healthCodes = [];
        $transportErrors = 0;
        for ($i = 0; $i < 60; $i++) {
            usleep(100000);
            try {
                $result = $probe->call('GET', 'health');
                if ($result['status'] === 200) { return; }
                $code = $result['status'];
                $healthCodes[$code] = ($healthCodes[$code] ?? 0) + 1;
            } catch (RuntimeException) {
                // Never log cURL exception text: it can include a URL or path.
                $transportErrors++;
            }
            $status = proc_get_status($this->serverProcess);
            if (! $status['running']) { break; }
        }
        ksort($healthCodes);
        $statusSummary = [];
        foreach ($healthCodes as $code => $count) {
            $statusSummary[] = 'HTTP ' . $code . ' x' . $count;
        }
        throw new RuntimeException('PM-3 loopback health never returned HTTP 200 ('
            . (implode(', ', $statusSummary) ?: 'no HTTP response')
            . '; transport errors ' . $transportErrors
            . '). Inspect http-server.log.health-diagnostic.jsonl.');
    }

    private function client(): Pm3WireHttpClient
    {
        $path = WRITEPATH . 'session/pm3-cookies-' . bin2hex(random_bytes(8)) . '.txt';
        $client = new Pm3WireHttpClient($this->base, $path);
        $this->clients[] = $client;
        return $client;
    }

    private function actorClient(int $id): Pm3WireHttpClient
    {
        if ($this->fixture === null) { throw new RuntimeException('Missing PM-3 fixture.'); }
        $user = (new UserModel())->find($id);
        $email = $user instanceof User ? $user->getEmail() : null;
        if (! is_string($email) || ! str_starts_with($email, 'pm3-')
            || ! str_ends_with($email, '@example.invalid')) {
            throw new RuntimeException('Missing PM-3-owned synthetic Shield email identity.');
        }
        $client = $this->client();
        $client->login($email, $this->password);
        $me = $client->call('GET', 'auth/me');
        self::assertSame(200, $me['status'], 'Synthetic Shield session must persist between real requests.');
        $value = json_decode($me['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($id, (int) ($value['data']['user']['id'] ?? 0));
        return $client;
    }

    private function imageRoute(): string { return 'products/' . $this->productId . '/image'; }

    /** @param array{status:int,headers:array<string,string>,body:string} $result */
    private function errorCode(array $result, int $status, string $code): void
    {
        self::assertSame($status, $result['status']);
        self::assertStringContainsString('application/json', strtolower($result['headers']['content-type'] ?? ''));
        $body = json_decode($result['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($code, $body['error']['code'] ?? null);
        self::assertStringNotContainsString(WRITEPATH, $result['body']);
    }

    private function imageFilename(): ?string
    {
        $row = $this->wireDb->table('products')->select('image_filename')->where('id', $this->productId)->get()->getRowArray();
        return $row !== null && is_string($row['image_filename']) ? $row['image_filename'] : null;
    }

    /** @return list<string> */
    private function storedFiles(): array
    {
        $files = glob(WRITEPATH . 'uploads/product-images/*') ?: [];
        sort($files);
        return array_values($files);
    }

    public function testRealMultipartFormatsBinaryPrivateAndDelete(): void
    {
        $writer = $this->actorClient($this->fixture->writer);
        $route = $this->imageRoute();
        $this->errorCode($writer->call('GET', $route), 404, 'PRODUCT_IMAGE_NOT_FOUND');
        foreach (['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'] as $extension => $mime) {
            $path = TESTPATH . 'fixtures/product-media/pixel.' . $extension;
            $response = $writer->authorized('POST', $route, [
                'image' => new CURLFile($path, $mime, 'client.' . $extension),
                'id' => '999999', 'status' => 'INACTIVE',
                'organization_id' => '999999', 'evaluation_organization_id' => '999999',
            ]);
            self::assertSame(200, $response['status'], 'Valid real multipart image must upload.');
            $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame((string) $this->productId, (string) ($payload['data']['id'] ?? ''));
            self::assertTrue($payload['data']['image_available'] ?? false);
            $persisted = $this->wireDb->table('products')->select('updated_by, updated_at')
                ->where('id', $this->productId)->get()->getRowArray();
            self::assertSame($this->fixture->writer, (int) ($persisted['updated_by'] ?? 0),
                'Wire upload must record the actual authenticated Shield actor.');
            self::assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/',
                (string) ($persisted['updated_at'] ?? ''), 'Legacy timestamp stays process-local SQL DATETIME.');
            foreach (['image_filename', 'image_mime', 'image_size_bytes', 'code'] as $private) {
                self::assertArrayNotHasKey($private, $payload['data']);
            }
            $binary = $writer->call('GET', $route);
            self::assertSame(200, $binary['status']);
            self::assertSame($mime, $binary['headers']['content-type'] ?? null);
            self::assertSame('nosniff', $binary['headers']['x-content-type-options'] ?? null);
            self::assertStringContainsString('private', $binary['headers']['cache-control'] ?? '');
            self::assertStringContainsString('no-store', $binary['headers']['cache-control'] ?? '');
            self::assertSame(file_get_contents($path), $binary['body']);
            $filename = $this->imageFilename();
            self::assertNotNull($filename);
            self::assertFileExists(WRITEPATH . 'uploads/product-images/' . $filename);
            self::assertFileDoesNotExist(PUBLICPATH . 'uploads/product-images/' . $filename);
            self::assertCount(1, $this->storedFiles(), 'Replacing a private image removes the previous file.');
        }
        $deleted = $writer->authorized('DELETE', $route);
        self::assertSame(200, $deleted['status']);
        self::assertNull($this->imageFilename());
        self::assertSame([], $this->storedFiles());
        $this->errorCode($writer->call('GET', $route), 404, 'PRODUCT_IMAGE_NOT_FOUND');
    }

    public function testSessionCsrfRejectsMissingInvalidAndRotatedTokens(): void
    {
        $writer = $this->actorClient($this->fixture->writer);
        $route = $this->imageRoute();
        $missing = $writer->call('POST', $route, ['note' => 'no-csrf']);
        self::assertSame(403, $missing['status'], 'Secure-runtime CSRF must block absent token.');
        $invalid = $writer->call('POST', $route, ['note' => 'invalid-csrf'], null, 'wrong-token');
        self::assertSame(403, $invalid['status'], 'Secure-runtime CSRF must block incorrect token.');
        self::assertNull($this->imageFilename());
        self::assertSame([], $this->storedFiles());
        $writer->primeCsrf();
        $old = $writer->csrfToken();
        $valid = $writer->authorized('POST', $route, [
            'image' => new CURLFile(TESTPATH . 'fixtures/product-media/pixel.png', 'image/png', 'pixel.png'),
        ]);
        self::assertSame(200, $valid['status']);
        self::assertNotSame($old, $writer->csrfToken(), 'CSRF token should rotate after successful submission.');
        $stale = $writer->call('DELETE', $route, null, null, $old);
        self::assertSame(403, $stale['status'], 'Old CSRF token must not delete the uploaded image.');
        self::assertNotNull($this->imageFilename());
        $writer->primeCsrf();
        self::assertSame(200, $writer->authorized('DELETE', $route)['status']);
        self::assertNull($this->imageFilename());
    }

    public function testRealTransportRejectsBadPayloadAndPbacDeniedAccess(): void
    {
        $writer = $this->actorClient($this->fixture->writer);
        $reader = $this->actorClient($this->fixture->reader);
        $route = $this->imageRoute();
        $anonymous = $this->client();
        $anonymousResponse = $anonymous->call('GET', $route);
        self::assertSame(401, $anonymousResponse['status']);
        $this->errorCode($writer->authorized('POST', $route, ['other' => 'wrong-field']), 422, 'PRODUCT_IMAGE_INVALID_CONTENT');
        $invalidFile = WRITEPATH . 'pm3-invalid-image.bin';
        file_put_contents($invalidFile, '<!php echo "not an image";');
        $this->errorCode($writer->authorized('POST', $route, [
            'image' => new CURLFile($invalidFile, 'image/png', 'forged.png'),
        ]), 422, 'PRODUCT_IMAGE_INVALID_CONTENT');
        $oversizedFile = WRITEPATH . 'pm3-oversized.bin';
        file_put_contents($oversizedFile, str_repeat('x', 3 * 1024 * 1024 + 1));
        $sizeResult = $writer->authorized('POST', $route, [
            'image' => new CURLFile($oversizedFile, 'application/octet-stream', 'large.bin'),
        ]);
        $this->errorCode($sizeResult, 422, 'PRODUCT_IMAGE_INVALID_SIZE');
        $wideFile = WRITEPATH . 'pm3-wide.png';
        $wide = imagecreatetruecolor(5001, 1);
        if ($wide === false || ! imagepng($wide, $wideFile)) { throw new RuntimeException('Cannot create oversize-dimension PNG fixture.'); }
        imagedestroy($wide);
        $this->errorCode($writer->authorized('POST', $route, [
            'image' => new CURLFile($wideFile, 'image/png', 'wide.png'),
        ]), 422, 'PRODUCT_IMAGE_INVALID_CONTENT');
        $this->errorCode($reader->authorized('POST', $route, [
            'image' => new CURLFile(TESTPATH . 'fixtures/product-media/pixel.png', 'image/png', 'pixel.png'),
        ]), 403, 'PRODUCT_AUTHORIZATION_DENIED');
        self::assertNull($this->imageFilename());
        self::assertSame([], $this->storedFiles());
        $this->fixture->deny('product', 'view', $this->fixture->reader);
        $this->errorCode($reader->call('GET', $route), 403, 'PRODUCT_AUTHORIZATION_DENIED');
    }

    public function testPostMutationViewDenyKeepsHistoricalWriteSideEffects(): void
    {
        $writer = $this->actorClient($this->fixture->writer);
        $route = $this->imageRoute();
        self::assertSame(200, $writer->authorized('POST', $route, [
            'image' => new CURLFile(TESTPATH . 'fixtures/product-media/pixel.png', 'image/png', 'pixel.png'),
        ])['status']);
        $old = $this->imageFilename();
        self::assertNotNull($old);
        $this->fixture->deny('product', 'view', $this->fixture->writer);
        $denied = $writer->authorized('POST', $route, [
            'image' => new CURLFile(TESTPATH . 'fixtures/product-media/pixel.jpg', 'image/jpeg', 'pixel.jpg'),
        ]);
        $this->errorCode($denied, 403, 'PRODUCT_AUTHORIZATION_DENIED');
        self::assertNotSame($old, $this->imageFilename());
        self::assertFileDoesNotExist(WRITEPATH . 'uploads/product-images/' . $old);
        self::assertCount(1, $this->storedFiles());
        $this->errorCode($writer->call('GET', $route), 403, 'PRODUCT_AUTHORIZATION_DENIED');
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->clients as $client) { $client->close(); }
            if (is_resource($this->serverProcess)) {
                proc_terminate($this->serverProcess);
                proc_close($this->serverProcess);
                $this->serverProcess = null;
            }
            if ($this->fixture !== null && $this->productId > 0) { $this->cleanupCommittedFixture(); }
        } finally {
            parent::tearDown();
        }
    }

    private function cleanupCommittedFixture(): void
    {
        if ($this->ledgerPath === null) {
            throw new RuntimeException('Committed PM-3 fixture lacks recovery ledger.');
        }
        $ledger = Pm3FixtureLedger::load($this->ledgerPath);
        if (($ledger['run_token'] ?? '') !== getenv('ERP_PM_RUN_TOKEN')) {
            throw new RuntimeException('PM-3 fixture ledger token mismatch.');
        }
        Pm3FixtureLedger::cleanup($this->wireDb, $ledger);
        $ledger['status'] = 'CLEANED';
        Pm3FixtureLedger::save($this->ledgerPath, $ledger);
        $this->fixture = null;
        foreach ($this->storedFiles() as $filename) {
            if (! unlink($filename)) {
                throw new RuntimeException('Cannot delete synthetic isolated image file: ' . $filename);
            }
        }
    }
}
