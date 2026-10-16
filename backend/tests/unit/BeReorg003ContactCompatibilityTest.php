<?php

declare(strict_types=1);

use App\Bootstrap\ContactFactory;
use App\Modules\Contact\Application\Ports\ContactRepositoryInterface;
use App\Modules\Contact\Contracts\ContactErrors;
use App\Repositories\ContactRepository;
use App\Services\ContactService;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ResultInterface;
use PHPUnit\Framework\TestCase;

/** @internal Compatibility surface is fixed against the accepted BE-REORG-002_FIX01 checkpoint. */
final class BeReorg003ContactCompatibilityTest extends TestCase
{
    public function testLegacyServiceContractMatchesCheckpoint(): void
    {
        $r = new ReflectionClass(ContactService::class);
        $this->assertTrue($r->isFinal());
        $this->assertSame([
            'ERROR_NOT_FOUND' => 'CONTACT_NOT_FOUND',
            'ERROR_OWNER_NOT_FOUND' => 'CONTACT_OWNER_NOT_FOUND',
            'ERROR_OWNER_REFERENCE_REQUIRED' => 'CONTACT_OWNER_REFERENCE_REQUIRED',
            'ERROR_OWNER_TRANSFER_UNSUPPORTED' => 'CONTACT_OWNER_TRANSFER_UNSUPPORTED',
            'ERROR_STATUS_INVALID' => 'CONTACT_STATUS_INVALID',
            'ERROR_TYPE_REQUIRED' => 'CONTACT_TYPE_REQUIRED',
            'ERROR_TYPE_UNSUPPORTED' => 'CONTACT_TYPE_UNSUPPORTED',
            'ERROR_WARD_PROVINCE_MISMATCH' => 'CONTACT_WARD_PROVINCE_MISMATCH',
        ], $this->publicConstants($r));
        $this->assertSame([
            '__construct' => [['repository', '?App\\Repositories\\ContactRepository', true, null], ['db', '?CodeIgniter\\Database\\ConnectionInterface', true, null], ['mutationContext', '?App\\Infrastructure\\Mutation\\MutationContext', true, null], ['clock', '?App\\Infrastructure\\Time\\Clock', true, null]],
            'create' => [['data', 'array', false, null]],
            'inactivate' => [['id', 'int', false, null], ['status', 'string', true, 'INACTIVE']],
            'listForOwner' => [['contactType', 'string', false, null], ['contactRefId', 'int', false, null], ['status', '?string', true, null]],
            'search' => [['filters', 'array', true, []]],
            'update' => [['id', 'int', false, null], ['data', 'array', false, null]],
        ], $this->publicMethodParameters($r));
        $this->assertSame(['create' => 'int', 'inactivate' => 'bool', 'listForOwner' => 'array', 'search' => 'array', 'update' => 'bool'], $this->returnTypes($r));
    }

    public function testLegacyRepositoryContractMatchesCheckpoint(): void
    {
        $r = new ReflectionClass(ContactRepository::class);
        $this->assertTrue($r->isFinal());
        $this->assertSame([
            'ERROR_CONNECTION_INVALID' => 'CONTACT_CONNECTION_INVALID',
            'ERROR_FIELD_REQUIRED' => 'CONTACT_FIELD_REQUIRED',
            'ERROR_FIELD_UNSUPPORTED' => 'CONTACT_FIELD_UNSUPPORTED',
            'ERROR_OWNER_IMMUTABLE' => 'CONTACT_OWNER_IMMUTABLE',
            'ERROR_PERSISTENCE_FAILED' => 'CONTACT_PERSISTENCE_FAILED',
            'ERROR_UPDATE_EMPTY' => 'CONTACT_UPDATE_EMPTY',
        ], $this->publicConstants($r));
        $this->assertSame(['find', 'findByOwner', 'insert', 'ownerExists', 'search', 'update', 'wardBelongsToProvince'], $this->publicBusinessMethods($r));
        $ctor = $r->getConstructor();
        $this->assertNotNull($ctor);
        $this->assertSame([['db', '?CodeIgniter\\Database\\ConnectionInterface', true, null]], $this->parameters($ctor));
    }

    public function testContactErrorsExposeExactlyFourteenCheckpointCodes(): void
    {
        $r = new ReflectionClass(ContactErrors::class);
        $this->assertCount(14, $this->publicConstants($r));
        $this->assertSame([
            'CONTACT_CONNECTION_INVALID', 'CONTACT_FIELD_REQUIRED', 'CONTACT_FIELD_UNSUPPORTED', 'CONTACT_NOT_FOUND',
            'CONTACT_OWNER_IMMUTABLE', 'CONTACT_OWNER_NOT_FOUND', 'CONTACT_OWNER_REFERENCE_REQUIRED',
            'CONTACT_OWNER_TRANSFER_UNSUPPORTED', 'CONTACT_PERSISTENCE_FAILED', 'CONTACT_STATUS_INVALID',
            'CONTACT_TYPE_REQUIRED', 'CONTACT_TYPE_UNSUPPORTED', 'CONTACT_UPDATE_EMPTY', 'CONTACT_WARD_PROVINCE_MISMATCH',
        ], array_values($this->publicConstants($r)));
    }

    public function testRepositoryPortHasExactlySevenLegacyMethods(): void
    {
        $names = array_map(static fn (ReflectionMethod $m): string => $m->getName(), (new ReflectionClass(ContactRepositoryInterface::class))->getMethods());
        sort($names);
        $this->assertSame(['find', 'findByOwner', 'insert', 'ownerExists', 'search', 'update', 'wardBelongsToProvince'], $names);
        $this->assertTrue(is_a(ContactRepository::class, ContactRepositoryInterface::class, true));
        $this->assertTrue(is_a(App\Modules\Contact\Infrastructure\Persistence\SqlContactRepository::class, ContactRepositoryInterface::class, true));
    }

    public function testFactoryReturnsFreshServicesAndRepositories(): void
    {
        $db = $this->createMock(BaseConnection::class);
        $db->expects($this->never())->method('table');
        $this->assertNotSame(ContactFactory::service(db: $db), ContactFactory::service(db: $db));
        $this->assertNotSame(ContactFactory::repository(db: $db), ContactFactory::repository(db: $db));
    }

    public function testFactoryKeepsSuppliedPortWhileWorkflowUsesSuppliedDatabase(): void
    {
        $port = $this->createMock(ContactRepositoryInterface::class);
        $port->expects($this->once())->method('search')->with(['status' => 'ACTIVE'])->willReturn([['id' => 7]]);
        $db = $this->createMock(BaseConnection::class);
        $db->expects($this->never())->method('table');
        $this->assertSame([['id' => 7]], ContactFactory::service(repository: $port, db: $db)->search(['status' => 'ACTIVE']));
    }

    public function testLegacyNamedArgumentsKeepExplicitRepositoryConnection(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('getResultArray')->willReturn([['id' => 9, 'contact_type' => 'ORGANIZATION', 'contact_ref_id' => 1]]);
        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('orderBy')->willReturnSelf();
        $builder->method('get')->willReturn($result);
        $repositoryDb = $this->createMock(BaseConnection::class);
        $repositoryDb->expects($this->once())->method('table')->with('contacts')->willReturn($builder);
        $serviceDb = $this->createMock(BaseConnection::class);
        $serviceDb->expects($this->never())->method('table');
        $service = new ContactService(repository: new ContactRepository(db: $repositoryDb), db: $serviceDb);
        $this->assertCount(1, $service->search());
    }

    public function testReadFailureRemainsPersistenceFailure(): void
    {
        foreach ([ContactRepository::class, App\Modules\Contact\Infrastructure\Persistence\SqlContactRepository::class] as $class) {
            $builder = $this->createMock(BaseBuilder::class);
            $builder->method('where')->willReturnSelf();
            $builder->method('get')->willReturn(false);
            $db = $this->createMock(BaseConnection::class);
            $db->method('table')->willReturn($builder);
            $repository = new $class($db);
            try {
                $repository->find(1);
                $this->fail($class . ' must surface a persistence failure.');
            } catch (App\Exceptions\PersistenceException $exception) {
                $this->assertSame('CONTACT_PERSISTENCE_FAILED', $exception->getErrorCode());
            }
        }
    }

    public function testRepositoryValidationRunsBeforeDatabaseMutation(): void
    {
        foreach ([ContactRepository::class, App\Modules\Contact\Infrastructure\Persistence\SqlContactRepository::class] as $class) {
            $db = $this->createMock(BaseConnection::class);
            $db->expects($this->never())->method('table');
            $repository = new $class($db);
            try {
                $repository->insert(['contact_type' => 'EMPLOYEE', 'contact_ref_id' => 1, 'status' => 'ACTIVE', 'bogus' => true]);
                $this->fail($class . ' must reject unsupported fields before touching the DB.');
            } catch (App\Exceptions\DomainException $exception) {
                $this->assertSame('CONTACT_FIELD_UNSUPPORTED', $exception->getErrorCode());
            }
        }
    }

    public function testLegacyWrappersUseExplicitModuleDelegates(): void
    {
        foreach ([ContactService::class, ContactRepository::class] as $class) {
            $property = (new ReflectionClass($class))->getProperty('delegate');
            $this->assertTrue($property->isPrivate());
            $this->assertTrue($property->isReadOnly());
        }
    }

    /** @return array<string,mixed> */
    private function publicConstants(ReflectionClass $r): array
    {
        $out = [];
        foreach ($r->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $c) {
            $out[$c->getName()] = $c->getValue();
        }
        ksort($out);
        return $out;
    }

    /** @return list<string> */
    private function publicBusinessMethods(ReflectionClass $r): array
    {
        $names = [];
        foreach ($r->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->getName() !== '__construct') {
                $names[] = $m->getName();
            }
        }
        sort($names);
        return $names;
    }

    /** @return array<string,list<array{0:string,1:string,2:bool,3:mixed}>> */
    private function publicMethodParameters(ReflectionClass $r): array
    {
        $out = [];
        foreach ($r->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->getDeclaringClass()->getName() !== $r->getName()) {
                continue;
            }
            $out[$m->getName()] = $this->parameters($m);
        }
        ksort($out);
        return $out;
    }

    /** @return list<array{0:string,1:string,2:bool,3:mixed}> */
    private function parameters(ReflectionMethod $m): array
    {
        return array_map(static fn (ReflectionParameter $p): array => [
            $p->getName(), (string) $p->getType(), $p->isOptional(), $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null,
        ], $m->getParameters());
    }

    /** @return array<string,string> */
    private function returnTypes(ReflectionClass $r): array
    {
        $out = [];
        foreach (['create', 'inactivate', 'listForOwner', 'search', 'update'] as $name) {
            $out[$name] = (string) $r->getMethod($name)->getReturnType();
        }
        return $out;
    }
}
