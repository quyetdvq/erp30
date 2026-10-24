<?php

declare(strict_types=1);

use App\Bootstrap\ContactFactory;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Contact\Application\ContactService as ModuleContactService;
use App\Modules\Contact\Infrastructure\Persistence\SqlContactRepository;
use App\Repositories\ContactRepository;
use App\Repositories\LifecycleRepository;
use App\Services\ContactService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;
use Config\Database;
use Tests\Support\Foundation\RuntimeGuard;

/** @internal Fixed Contact business expectations across legacy and module entrypoints. */
final class BeReorg003ContactCharacterizationTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;
    private int $sequence = 0;

    protected function setUp(): void
    {
        $config = config(Database::class);
        RuntimeGuard::assertConfiguration(ENVIRONMENT, $config->defaultGroup, $config->tests);
        parent::setUp();
    }

    public function testBothOwnerKindsNormalizeAndPersistExpectedFields(): void
    {
        foreach ($this->services() as $label => $service) {
            $organization = $this->organization();
            $employee = $this->employee();
            $orgId = $service->create($this->contact('organization', $organization, $label . '-org'));
            $empId = $service->create($this->contact('employee', $employee, $label . '-emp'));
            $org = (new ContactRepository($this->db))->find($orgId);
            $emp = (new ContactRepository($this->db))->find($empId);
            $this->assertSame('ORGANIZATION', $org['contact_type']);
            $this->assertSame($organization, (int) $org['contact_ref_id']);
            $this->assertSame('EMPLOYEE', $emp['contact_type']);
            $this->assertSame($employee, (int) $emp['contact_ref_id']);
        }
    }

    public function testMissingUnsupportedAndUnknownOwnersKeepCodesAndDoNotWrite(): void
    {
        foreach ($this->services() as $service) {
            $before = count((new ContactRepository($this->db))->search());
            $this->domainError('CONTACT_TYPE_REQUIRED', fn () => $service->create(['contact_ref_id' => 1, 'status' => 'ACTIVE']));
            $this->domainError('CONTACT_OWNER_REFERENCE_REQUIRED', fn () => $service->create(['contact_type' => 'ORGANIZATION', 'status' => 'ACTIVE']));
            $this->domainError('CONTACT_TYPE_UNSUPPORTED', fn () => $service->create(['contact_type' => 'VENDOR', 'contact_ref_id' => 1, 'status' => 'ACTIVE']));
            $this->domainError('CONTACT_OWNER_NOT_FOUND', fn () => $service->create($this->contact('ORGANIZATION', 999999999, 'unknown')));
            $this->assertCount($before, (new ContactRepository($this->db))->search());
        }
    }

    public function testOwnerTransferAndMissingUpdateTargetKeepEstablishedPrecedence(): void
    {
        foreach ($this->services() as $service) {
            $organization = $this->organization();
            $id = $service->create($this->contact('ORGANIZATION', $organization, 'owned'));
            $this->domainError('CONTACT_OWNER_TRANSFER_UNSUPPORTED', fn () => $service->update(999999999, ['contact_type' => 'EMPLOYEE']));
            $this->domainError('CONTACT_OWNER_TRANSFER_UNSUPPORTED', fn () => $service->update($id, ['contact_ref_id' => 123]));
            $this->domainError('CONTACT_NOT_FOUND', fn () => $service->update(999999999, ['name' => 'missing']));
            $this->assertSame($organization, (int) (new ContactRepository($this->db))->find($id)['contact_ref_id']);
        }
    }

    public function testProvinceWardMissingNullAndMismatchSemanticsAreStable(): void
    {
        foreach ($this->services() as $label => $service) {
            $owner = $this->organization();
            $provinceA = $this->province($label . '-pa');
            $provinceB = $this->province($label . '-pb');
            $wardA = $this->ward($provinceA, $label . '-wa');
            $one = $service->create($this->contact('ORGANIZATION', $owner, $label . '-missing') + ['province_id' => $provinceA]);
            $two = $service->create($this->contact('ORGANIZATION', $owner, $label . '-null') + ['province_id' => null, 'ward_id' => null]);
            $this->assertNotNull((new ContactRepository($this->db))->find($one));
            $this->assertNotNull((new ContactRepository($this->db))->find($two));
            $this->domainError('CONTACT_WARD_PROVINCE_MISMATCH', fn () => $service->create(
                $this->contact('ORGANIZATION', $owner, $label . '-bad') + ['province_id' => $provinceB, 'ward_id' => $wardA],
            ));
        }
    }

    public function testAuditFieldsAreServerOwnedAndFreshServicesUseOwnActorAndClock(): void
    {
        foreach ([false, true] as $module) {
            $owner = $this->organization();
            $clockA = $this->fixedClock('2026-10-16T05:00:00Z');
            $clockB = $this->fixedClock('2026-10-16T06:00:00Z');
            $a = $this->service($module, new MutationContext($clockA, 501, 'br003-a', false), $clockA);
            $b = $this->service($module, new MutationContext($clockB, 502, 'br003-b', false), $clockB);
            $id = $a->create($this->contact('ORGANIZATION', $owner, 'audit') + [
                'created_by' => 999, 'updated_by' => 999, 'created_at' => '2000-01-01 00:00:00', 'updated_at' => '2000-01-01 00:00:00',
            ]);
            $this->assertTrue($b->update($id, ['name' => 'audit-updated', 'updated_by' => 999, 'updated_at' => '2000-01-01 00:00:00']));
            $row = (new ContactRepository($this->db))->find($id);
            $this->assertSame(501, (int) $row['created_by']);
            $this->assertSame(502, (int) $row['updated_by']);
            $this->assertSame('2026-10-16 05:00:00', $row['created_at']);
            $this->assertSame('2026-10-16 06:00:00', $row['updated_at']);
        }
    }

    public function testRepositoryPayloadErrorsAndOwnerImmutabilityKeepExactCodes(): void
    {
        foreach ([new ContactRepository($this->db), new SqlContactRepository($this->db)] as $repository) {
            $this->domainError('CONTACT_FIELD_UNSUPPORTED', fn () => $repository->insert([
                'contact_type' => 'ORGANIZATION', 'contact_ref_id' => 1, 'status' => 'ACTIVE', 'bogus' => true,
            ]));
            $this->domainError('CONTACT_FIELD_REQUIRED', fn () => $repository->insert(['contact_type' => 'ORGANIZATION']));
            $this->domainError('CONTACT_OWNER_IMMUTABLE', fn () => $repository->update(1, ['contact_type' => 'EMPLOYEE']));
            $this->domainError('CONTACT_UPDATE_EMPTY', fn () => $repository->update(1, []));
        }
    }

    public function testOwnerListingAndSearchRetainFiltersQueryColumnsAndIdOrder(): void
    {
        foreach ($this->services() as $label => $service) {
            $owner = $this->organization();
            $first = $service->create($this->contact('ORGANIZATION', $owner, $label . '-needle-a') + ['phone' => '0900000001']);
            $second = $service->create($this->contact('ORGANIZATION', $owner, $label . '-needle-b') + ['mobile' => '0900000002']);
            $this->assertSame([$first, $second], array_map(static fn (array $r): int => (int) $r['id'], $service->listForOwner('organization', $owner, 'ACTIVE')));
            $this->assertSame([$first, $second], array_map(static fn (array $r): int => (int) $r['id'], $service->search([
                'status' => 'ACTIVE', 'contact_type' => 'ORGANIZATION', 'query' => $label . '-needle',
            ])));
        }
    }

    public function testWorkflowInitialStateAndDirectStateGuardRemainUnchanged(): void
    {
        $this->bindContactLifecycle('DRAFT');
        foreach ($this->services() as $service) {
            $owner = $this->organization();
            $payload = $this->contact('ORGANIZATION', $owner, 'workflow');
            unset($payload['status']);
            $id = $service->create($payload);
            $this->assertSame('DRAFT', (new ContactRepository($this->db))->find($id)['status']);
            $this->domainError('WF_TRANSITION_REQUIRED', fn () => $service->update($id, ['status' => 'INACTIVE']));
            $this->assertTrue($service->update($id, ['status' => 'DRAFT', 'name' => 'same-state']));
        }
    }

    public function testInactivateStatusVocabularyAndWorkflowErrorPrecedenceRemainStable(): void
    {
        $this->bindContactLifecycle('ACTIVE');
        foreach ($this->services() as $service) {
            $owner = $this->organization();
            $id = $service->create($this->contact('ORGANIZATION', $owner, 'state'));
            $this->domainError('CONTACT_STATUS_INVALID', fn () => $service->inactivate($id, 'ARCHIVED'));
            $this->domainError('WF_TRANSITION_REQUIRED', fn () => $service->inactivate($id, 'INACTIVE'));
            $this->domainError('WF_TRANSITION_REQUIRED', fn () => $service->inactivate($id, 'CLOSED'));
            $this->assertSame('ACTIVE', (new ContactRepository($this->db))->find($id)['status']);
        }
    }

    /** @return array<string,ContactService|ModuleContactService> */
    private function services(): array
    {
        return ['legacy' => $this->service(false), 'module' => $this->service(true)];
    }

    private function service(bool $module, ?MutationContext $context = null, ?Clock $clock = null): ContactService|ModuleContactService
    {
        return $module
            ? ContactFactory::service(db: $this->db, mutationContext: $context, clock: $clock)
            : new ContactService(db: $this->db, mutationContext: $context, clock: $clock);
    }

    private function fixedClock(string $instant): Clock
    {
        return new class ($instant) implements Clock {
            public function __construct(private string $instant) {}
            public function now(): \DateTimeImmutable { return new \DateTimeImmutable($this->instant); }
        };
    }

    /** @return array<string,mixed> */
    private function contact(string $type, int $ownerId, string $name): array
    {
        return ['contact_type' => $type, 'contact_ref_id' => $ownerId, 'status' => 'ACTIVE', 'name' => $name,
            'email' => str_replace(' ', '.', strtolower($name)) . '@example.test'];
    }

    private function organization(): int
    {
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'code' => 'BR003-O-' . ++$this->sequence, 'name' => 'Owner', 'status' => 'ACTIVE',
            'created_at' => '2026-10-16 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function employee(): int
    {
        $this->db->table('employees')->insert([
            'employee_code' => 'BR003-E-' . ++$this->sequence, 'employee_name' => 'Owner', 'status' => 'ACTIVE',
            'created_at' => '2026-10-16 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function province(string $suffix): int
    {
        $this->db->table('provinces')->insert([
            'code' => 'BR003-P-' . ++$this->sequence, 'name' => $suffix, 'status' => 'ACTIVE', 'created_at' => '2026-10-16 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function ward(int $provinceId, string $suffix): int
    {
        $this->db->table('wards')->insert([
            'province_id' => $provinceId, 'code' => 'BR003-W-' . ++$this->sequence, 'name' => $suffix,
            'status' => 'ACTIVE', 'created_at' => '2026-10-16 00:00:00',
        ]);
        return (int) $this->db->insertID();
    }

    private function bindContactLifecycle(string $initial): void
    {
        $repo = new LifecycleRepository($this->db);
        $lifecycle = $repo->insertLifecycle([
            'code' => 'BR003-CONTACT-LC-' . ++$this->sequence, 'name' => 'Contact test', 'status' => 'ACTIVE',
        ]);
        foreach (['DRAFT', 'ACTIVE', 'INACTIVE', 'CLOSED'] as $index => $code) {
            $row = $this->db->table('workflow_states')->where('code', $code)->get()->getRowArray();
            $stateId = $row === null
                ? $repo->insertWorkflowState(['code' => $code, 'name' => $code, 'status' => 'ACTIVE'])
                : (int) $row['id'];
            $repo->insertLifecycleState([
                'lifecycle_id' => $lifecycle, 'state_id' => $stateId, 'sequence' => $index + 1,
                'is_initial' => $code === $initial ? 1 : 0, 'is_final' => $code === 'CLOSED' ? 1 : 0,
            ]);
        }
        foreach (['organization_contact', 'employee_contact'] as $resource) {
            $this->db->table('resources')->where('type', $resource)->update(['lifecycle_id' => $lifecycle, 'status' => 'ACTIVE']);
        }
    }

    private function domainError(string $code, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected domain error: ' . $code);
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }
}
