<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Services\BusinessWorkflowWriteBoundary;
use App\Services\InitialCreateStateNormalizer;
use App\Services\ResourceWorkflowStateResolver;
use App\Services\WorkflowBusinessStatus;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;
use Config\Database;
use Tests\Support\Foundation\RuntimeFixture;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Legacy class public reflection contract plus guarded real-runtime parity. */
final class BeReorg012WriteBoundaryCompatibilityTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;
    protected $namespace = null;
    private RuntimeFixture $fixture;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
        $this->assertSame('erp_test', $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db']);
        $this->fixture = new RuntimeFixture($this->db);
    }

    public function testBoundaryFacadeRetainsTwoStaticSignatures(): void
    {
        $reflection = new ReflectionClass(BusinessWorkflowWriteBoundary::class);
        $this->assertTrue($reflection->isFinal());
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        $this->assertSame(['create', 'assertNoDirectStateMutation'],
            array_map(static fn (ReflectionMethod $method) => $method->getName(), $methods));
        foreach (['create' => [['resource', 'data', 'db'], 'array'],
            'assertNoDirectStateMutation' => [['resource', 'current', 'changes', 'db'], 'void']] as $name => [$args, $ret]) {
            $method = $reflection->getMethod($name);
            $this->assertTrue($method->isStatic());
            $this->assertSame($args, array_map(static fn (ReflectionParameter $p) => $p->getName(), $method->getParameters()));
            $this->assertSame($ret, (string) $method->getReturnType());
            $this->assertFalse($method->getParameters()[count($args) - 1]->isOptional());
        }
    }

    public function testStatusFacadeRetainsNormalizeSignatureAndDefaults(): void
    {
        $method = new ReflectionMethod(WorkflowBusinessStatus::class, 'normalize');
        $this->assertTrue($method->isPublic() && $method->isStatic());
        $this->assertSame('string', (string) $method->getReturnType());
        $this->assertSame(['type', 'value', 'nativeCodes', 'errorCode', 'db'],
            array_map(static fn (ReflectionParameter $p) => $p->getName(), $method->getParameters()));
        $this->assertNull($method->getParameters()[4]->getDefaultValue());
        try {
            WorkflowBusinessStatus::normalize('province', 9, ['ACTIVE'], 'CUSTOM_BAD', $this->db);
            $this->fail('Expected raw input error.');
        } catch (DomainException $error) {
            $this->assertSame('CUSTOM_BAD', $error->getErrorCode());
            $this->assertSame(['status' => 9], $error->getContext());
        }
    }

    public function testInitialNormalizerRetainsConstructorTwoMethodsAndConstant(): void
    {
        $reflection = new ReflectionClass(InitialCreateStateNormalizer::class);
        $this->assertTrue($reflection->isFinal());
        $this->assertSame(['ERROR_UNSUPPORTED_INITIAL' => 'WF_INITIAL_STATE_UNSUPPORTED_FOR_RESOURCE'], $reflection->getConstants());
        $constructor = $reflection->getConstructor();
        $this->assertSame(['states'], array_map(static fn (ReflectionParameter $p) => $p->getName(), $constructor->getParameters()));
        $this->assertSame('?'.ResourceWorkflowStateResolver::class, (string) $constructor->getParameters()[0]->getType());
        $this->assertNull($constructor->getParameters()[0]->getDefaultValue());
        foreach (['normalize' => [false, ['resource', 'payload']],
            'withInitial' => [true, ['resource', 'payload', 'initial']]] as $name => [$isStatic, $args]) {
            $method = $reflection->getMethod($name);
            $this->assertSame($isStatic, $method->isStatic());
            $this->assertSame($args, array_map(static fn (ReflectionParameter $p) => $p->getName(), $method->getParameters()));
            $this->assertSame('array', (string) $method->getReturnType());
        }
    }

    public function testFacadesDelegateWithoutDuplicatedAlgorithms(): void
    {
        $data = ['name' => 'X'];
        $this->assertSame($data, BusinessWorkflowWriteBoundary::create('policy', $data, $this->db));
        $this->assertSame('ACTIVE', BusinessWorkflowWriteBoundary::create('province', $data, $this->db)['status']);
        BusinessWorkflowWriteBoundary::assertNoDirectStateMutation('policy', ['status' => 'ACTIVE'], ['status' => 'INACTIVE'], $this->db);
        $this->assertSame('ACTIVE', WorkflowBusinessStatus::normalize('province', ' active ', ['X'], 'WRONG', $this->db));
        $this->assertSame('DRAFT', InitialCreateStateNormalizer::withInitial('province', [], 'DRAFT')['status']);
        $files = [new ReflectionClass(BusinessWorkflowWriteBoundary::class),
            new ReflectionClass(WorkflowBusinessStatus::class), new ReflectionClass(InitialCreateStateNormalizer::class)];
        foreach ($files as $reflection) {
            $source = (string) file_get_contents($reflection->getFileName());
            $this->assertStringNotContainsString('->table(', $source);
            $this->assertStringNotContainsString('preg_match(', $source);
            $this->assertStringNotContainsString('ResourceNativeStateCatalog::codes(', $source);
        }
    }

    public function testExistingConsumersAndRoutesRemainUnchanged(): void
    {
        $app = dirname(__DIR__, 2) . '/app';
        foreach ([
            'Modules/Foundation/Application/LocationService.php' => 'BusinessWorkflowWriteBoundary::create(',
            'Modules/Organization/Application/OrganizationService.php' => 'BusinessWorkflowWriteBoundary::',
            'Modules/Contact/Application/ContactService.php' => 'BusinessWorkflowWriteBoundary::',
            'Services/Api010FoundationOrganizationContactService.php' => 'InitialCreateStateNormalizer(',
        ] as $path => $reference) {
            $this->assertStringContainsString($reference, (string) file_get_contents($app . '/' . $path), $path);
        }
        $routes = (string) file_get_contents($app . '/Config/Routes.php');
        $this->assertStringContainsString("$" . "routes->post($" . "path, 'Api\\\\FoundationOrganizationContact::create/", $routes);
    }
}
