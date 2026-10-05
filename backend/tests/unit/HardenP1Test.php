<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Exceptions\ConcurrencyException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Lock\MariaDbDomainLockService;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Repositories\ContactRepository;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ResultInterface;
use App\Services\ContactService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class HardenP1Test extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testOperationContextCapturesOneUtcEvaluationTime(): void
    {
        $clock = new class implements Clock {
            public int $calls = 0;

            public function now(): DateTimeImmutable
            {
                $this->calls++;

                return new DateTimeImmutable('2026-10-05T23:30:00+07:00');
            }
        };
        $operation = (new MutationContext($clock, 42, 'request-1', false))->begin();

        $this->assertSame(1, $clock->calls);
        $this->assertSame(42, $operation->actorUserId);
        $this->assertSame('2026-10-05 16:30:00', $operation->createdAudit()['created_at']);
        $this->assertSame('2026-10-05 16:30:00', $operation->updatedAudit()['updated_at']);
    }

    public function testCallerAuditFieldsAreReplacedAndPartialWardPatchUsesMergedState(): void
    {
        $organizationId = $this->insertOrganization();
        $provinceA = $this->insertProvince('Harden Province A');
        $provinceB = $this->insertProvince('Harden Province B');
        $wardA = $this->insertWard($provinceA, 'Harden Ward A');
        $wardB = $this->insertWard($provinceB, 'Harden Ward B');
        $clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-05T16:30:00+00:00');
            }
        };
        $context = new MutationContext($clock, 42, 'request-2', false);
        $service = new ContactService(new ContactRepository($this->db), $this->db, $context);
        $contactId = $service->create([
            'contact_type' => 'ORGANIZATION',
            'contact_ref_id' => $organizationId,
            'status' => 'ACTIVE',
            'name' => 'Harden Contact',
            'province_id' => $provinceA,
            'ward_id' => $wardA,
            'created_by' => 999999,
            'created_at' => '1999-01-01 00:00:00',
        ]);

        $row = (new ContactRepository($this->db))->find($contactId);
        $this->assertSame(42, (int) $row['created_by']);
        $this->assertSame('2026-10-05 16:30:00', $row['created_at']);

        try {
            $service->update($contactId, ['ward_id' => $wardB]);
            $this->fail('Expected merged Ward/Province invariant rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(ContactService::ERROR_WARD_PROVINCE_MISMATCH, $exception->getErrorCode());
        }
    }

    public function testPositiveIdRejectsNonCanonicalNumericValues(): void
    {
        $this->assertSame(12, PositiveId::normalize('12', 'id', 'INVALID_ID'));
        $this->expectException(DomainException::class);
        PositiveId::normalize('12.0', 'id', 'INVALID_ID');
    }

    public function testRepositoryQueryFailureIsNotReportedAsNotFound(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('where')->willReturnSelf();
        $builder->method('get')->willReturn(false);
        $db = $this->createMock(BaseConnection::class);
        $db->method('table')->willReturn($builder);
        $this->expectException(PersistenceException::class);

        (new ContactRepository($db))->find(1);
    }

    public function testLockTimeoutHasCanonicalConcurrencyIdentity(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('getRowArray')->willReturn(['acquired' => 0]);
        $db = $this->createMock(BaseConnection::class);
        $db->method('query')->willReturn($result);

        try {
            (new MariaDbDomainLockService($db, 0))->acquire('harden-test');
            $this->fail('Expected a concurrency exception.');
        } catch (ConcurrencyException $exception) {
            $this->assertSame(ConcurrencyException::ERROR_CODE, $exception->getErrorCode());
        }
    }

    private function insertOrganization(): int
    {
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'code' => 'HARDEN-ORG-' . uniqid(), 'name' => 'Harden Group',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProvince(string $name): int
    {
        $this->db->table('provinces')->insert([
            'code' => uniqid('HP'), 'name' => $name, 'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertWard(int $provinceId, string $name): int
    {
        $this->db->table('wards')->insert([
            'province_id' => $provinceId, 'code' => uniqid('HW'), 'name' => $name, 'status' => 'ACTIVE',
            'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }
}
