<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Repositories\FoundationLocationRepository;
use App\Repositories\LifecycleRepository;
use App\Services\FoundationLocationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Fixed expectations for the original v1.1.5 entrypoints, not wrapper/module
 * equality. Run this file on the original code BEFORE applying the refactor.
 * Requires the isolated MySQL/MariaDB test database (DatabaseTestTrait refreshes).
 *
 * @internal
 */
final class BeReorg001FoundationCharacterizationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Pin the legacy unbound-resource branch unless a test explicitly binds it.
        foreach (['province', 'ward'] as $type) {
            $row = $this->db->table('resources')->where('type', $type)->get()->getRowArray();
            if ($row === null) {
                $this->db->table('resources')->insert([
                    'type' => $type, 'name' => $type, 'domain' => 'MASTER',
                    'status' => 'ACTIVE', 'created_at' => '2026-10-16 00:00:00',
                ]);
            } else {
                $this->db->table('resources')->where('id', $row['id'])->update(['lifecycle_id' => null]);
            }
        }
    }

    public function testAuditCannotBeForgedAndClockIsCapturedOnlyForMutations(): void
    {
        $clock = new class implements Clock {
            public int $calls = 0;
            public function now(): DateTimeImmutable
            {
                $this->calls++;
                return new DateTimeImmutable('2026-10-16T09:30:00+07:00');
            }
        };
        $service = new FoundationLocationService(
            repository: new FoundationLocationRepository(db: $this->db), db: $this->db,
            mutationContext: new MutationContext($clock, 42, 'be-reorg-001', false), clock: $clock,
        );
        $this->assertSame(0, $clock->calls);
        $id = $service->createProvince([
            'code' => 'BE001-AUDIT', 'name' => 'Audit province', 'status' => ' active ',
            'created_by' => 999, 'updated_by' => 999,
            'created_at' => '1999-01-01 00:00:00', 'updated_at' => '1999-01-01 00:00:00',
        ]);
        $row = $service->province($id);
        $this->assertSame('ACTIVE', $row['status']);
        $this->assertSame(42, (int) $row['created_by']);
        $this->assertSame('2026-10-16 02:30:00', $row['created_at']);
        $this->assertNull($row['updated_by']);
        $this->assertNull($row['updated_at']);
        $this->assertSame(1, $clock->calls);
        $this->assertTrue($service->updateProvince($id, ['name' => 'Updated', 'updated_by' => 999]));
        $row = $service->province($id);
        $this->assertSame(42, (int) $row['updated_by']);
        $this->assertSame('2026-10-16 02:30:00', $row['updated_at']);
        $this->assertSame(2, $clock->calls);
    }

    public function testNullEmptyAndOmittedWardFieldsAreNotCollapsed(): void
    {
        $service = $this->service();
        $province = $service->createProvince($this->provinceInput('BE001-P'));
        $ward = $service->createWard($this->wardInput($province, 'BE001-W') + ['name_full' => 'Full name']);
        $this->assertTrue($service->updateWard($ward, ['name' => 'Renamed']));
        $this->assertSame('Full name', $service->ward($ward)['name_full']);
        $this->assertTrue($service->updateWard($ward, ['name_full' => null]));
        $this->assertNull($service->ward($ward)['name_full']);
        $this->assertTrue($service->updateWard($ward, ['name_full' => '']));
        $this->assertSame('', $service->ward($ward)['name_full']);
        $this->assertError('LOCATION_UPDATE_EMPTY', ['operation' => 'update'], fn () => $service->updateWard($ward, []));
    }

    public function testRequiredUnsupportedStatusAndNotFoundErrorsKeepTheirContexts(): void
    {
        $service = $this->service();
        $this->assertError('LOCATION_FIELD_REQUIRED', ['field' => 'code'], fn () => $service->createProvince(['name' => 'Missing', 'status' => 'ACTIVE']));
        $this->assertError('LOCATION_STATUS_INVALID', ['field' => 'status'], fn () => $service->createProvince(['code' => 'BE001-BAD', 'name' => 'Bad', 'status' => null]));
        $this->assertError('LOCATION_STATUS_INVALID', ['status' => 'UNKNOWN'], fn () => $service->createProvince(['code' => 'BE001-BAD', 'name' => 'Bad', 'status' => 'unknown']));
        $this->assertError('LOCATION_FIELD_UNSUPPORTED', ['field' => 'extra'], fn () => $service->createProvince($this->provinceInput('BE001-BAD') + ['extra' => 'x']));
        $this->assertError('PROVINCE_NOT_FOUND', ['province_id' => 999999999], fn () => $service->province(999999999));
        $this->assertError('WARD_NOT_FOUND', ['field' => 'ward_id'], fn () => $service->ward(0));
        $this->assertSame(0, $this->db->table('provinces')->countAllResults());
    }

    public function testWardProvinceIdNormalizationRetainsCanonicalIntegerRules(): void
    {
        $service = $this->service();
        $province = $service->createProvince($this->provinceInput('BE001-ID'));
        $input = $this->wardInput($province, 'BE001-ID-W');
        $input['province_id'] = (string) $province;
        $ward = $service->createWard($input);
        $this->assertSame($province, (int) $service->ward($ward)['province_id']);
        $input['code'] = 'BE001-ID-BAD';
        $input['province_id'] = $province . '.0';
        $this->assertError('WARD_PROVINCE_NOT_FOUND', ['field' => 'province_id'], fn () => $service->createWard($input));
    }

    public function testHistoricalWardRemainsEditableButNewInactiveProvinceSelectionFails(): void
    {
        $service = $this->service();
        $old = $service->createProvince($this->provinceInput('BE001-HISTORY'));
        $other = $service->createProvince($this->provinceInput('BE001-INACTIVE'));
        $ward = $service->createWard($this->wardInput($old, 'BE001-HISTORY-W'));
        $this->assertTrue($service->setProvinceState($old, 'INACTIVE'));
        $this->assertTrue($service->setProvinceState($other, 'INACTIVE'));
        $this->assertTrue($service->updateWard($ward, ['name' => 'History', 'province_id' => $old]));
        $this->assertSame('History', $service->ward($ward)['name']);
        $this->assertError('WARD_PROVINCE_INACTIVE', ['province_id' => $other], fn () => $service->updateWard($ward, ['province_id' => $other]));
        $this->assertError('WARD_PROVINCE_INACTIVE', ['province_id' => $old], fn () => $service->createWard($this->wardInput($old, 'BE001-BAD-W')));
        $this->assertSame($old, (int) $service->ward($ward)['province_id']);
    }

    public function testReparentRetainsDuplicateErrorAndDoesNotPartiallyWrite(): void
    {
        $service = $this->service();
        $old = $service->createProvince($this->provinceInput('BE001-OLD'));
        $new = $service->createProvince($this->provinceInput('BE001-NEW'));
        $ward = $service->createWard($this->wardInput($old, 'BE001-ORIGINAL'));
        $service->createWard($this->wardInput($new, 'BE001-DUPLICATE'));
        $before = $service->ward($ward);
        $this->assertError('LOCATION_CODE_DUPLICATE', ['field' => 'code'], fn () => $service->updateWard($ward, [
            'province_id' => $new, 'code' => 'BE001-DUPLICATE', 'name' => 'Must not persist',
        ]));
        $this->assertSame($before, $service->ward($ward));
        $this->assertTrue($service->updateWard($ward, ['province_id' => $new, 'code' => 'BE001-MOVED']));
        $after = $service->ward($ward);
        $this->assertSame($new, (int) $after['province_id']);
        $this->assertSame('BE001-MOVED', $after['code']);
    }

    public function testBothOrganizationAndContactAddressConflictsBlockReparent(): void
    {
        $service = $this->service();
        $old = $service->createProvince($this->provinceInput('BE001-ADDR-OLD'));
        $new = $service->createProvince($this->provinceInput('BE001-ADDR-NEW'));
        $ward = $service->createWard($this->wardInput($old, 'BE001-ADDR-W'));
        $this->db->table('organizations')->insert([
            'type' => 'GROUP', 'parent_id' => null, 'code' => 'BE001-ORG', 'name' => 'Address owner',
            'status' => 'ACTIVE', 'province_id' => $old, 'ward_id' => $ward, 'created_at' => '2026-10-16 00:00:00',
        ]);
        $organization = (int) $this->db->insertID();
        $context = ['ward_id' => $ward, 'province_id' => $new];
        $this->assertError('WARD_PROVINCE_REASSIGN_REFERENCED', $context, fn () => $service->updateWard($ward, ['province_id' => $new]));
        $this->db->table('organizations')->where('id', $organization)->update(['ward_id' => null]);
        $this->db->table('contacts')->insert([
            'contact_type' => 'ORGANIZATION', 'contact_ref_id' => $organization, 'name' => 'Contact owner',
            'status' => 'ACTIVE', 'province_id' => $old, 'ward_id' => $ward, 'created_at' => '2026-10-16 00:00:00',
        ]);
        $this->assertError('WARD_PROVINCE_REASSIGN_REFERENCED', $context, fn () => $service->updateWard($ward, ['province_id' => $new]));
        $this->assertSame($old, (int) $service->ward($ward)['province_id']);
    }

    public function testBoundLifecycleUsesConfiguredInitialAndRejectsDirectStateChange(): void
    {
        $repository = new LifecycleRepository($this->db);
        $lifecycle = $repository->insertLifecycle(['code' => 'BE001-LC', 'name' => 'Location lifecycle', 'status' => 'ACTIVE']);
        foreach (['DRAFT', 'ACTIVE'] as $sequence => $code) {
            $row = $this->db->table('workflow_states')->where('code', $code)->get()->getRowArray();
            $state = $row === null
                ? $repository->insertWorkflowState(['code' => $code, 'name' => $code, 'status' => 'ACTIVE'])
                : (int) $row['id'];
            $repository->insertLifecycleState([
                'lifecycle_id' => $lifecycle, 'state_id' => $state, 'sequence' => $sequence + 1,
                'is_initial' => $sequence === 0 ? 1 : 0, 'is_final' => 0,
            ]);
        }
        $this->db->table('resources')->whereIn('type', ['province', 'ward'])->update(['lifecycle_id' => $lifecycle]);
        $service = $this->service();
        $id = $service->createProvince(['code' => 'BE001-INITIAL', 'name' => 'Initial omitted']);
        $this->assertSame('DRAFT', $service->province($id)['status']);
        $this->assertError('WF_INITIAL_STATE_INVALID', ['resource' => 'province', 'initial_status' => 'DRAFT'], fn () => $service->createProvince($this->provinceInput('BE001-WRONG-INITIAL')));
        $this->assertError('WF_TRANSITION_REQUIRED', ['resource' => 'province'], fn () => $service->setProvinceState($id, 'ACTIVE'));
        // Direct service no-op differs deliberately from the HTTP facade PATCH rule.
        $this->assertTrue($service->updateProvince($id, ['status' => 'DRAFT', 'name' => 'Still draft']));
        $this->assertSame('DRAFT', $service->province($id)['status']);
    }

    private function service(): FoundationLocationService
    {
        $clock = new class implements Clock {
            public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-10-16T02:30:00Z'); }
        };
        return new FoundationLocationService(
            repository: new FoundationLocationRepository(db: $this->db), db: $this->db,
            mutationContext: new MutationContext($clock, 42, 'be-reorg-001', false), clock: $clock,
        );
    }

    /** @return array<string,mixed> */
    private function provinceInput(string $code): array
    {
        return ['code' => $code, 'name' => 'Province ' . $code, 'status' => 'ACTIVE'];
    }

    /** @return array<string,mixed> */
    private function wardInput(int $province, string $code): array
    {
        return ['province_id' => $province, 'code' => $code, 'name' => 'Ward ' . $code, 'status' => 'ACTIVE'];
    }

    /** @param array<string,scalar|null> $context */
    private function assertError(string $code, array $context, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected DomainException: ' . $code);
        } catch (DomainException $exception) {
            $this->assertSame(DomainException::class, $exception::class);
            $this->assertSame($code, $exception->getErrorCode());
            $this->assertSame($context, $exception->getContext());
        }
    }
}
