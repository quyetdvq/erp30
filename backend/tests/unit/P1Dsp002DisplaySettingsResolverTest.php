<?php

declare(strict_types=1);

use App\Infrastructure\Settings\BusinessTimezoneResolver;
use App\Infrastructure\Settings\CodeIgniterDisplaySettingsStorage;
use App\Infrastructure\Settings\DisplaySettingRegistry;
use App\Infrastructure\Settings\DisplaySettingsResolver;
use App\Exceptions\DomainException;
use App\Services\OrganizationService;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Dsp002DisplaySettingsResolverTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testNearestOrganizationPathOverrideWinsThenFallsBackThroughGroupSystemAndDefault(): void
    {
        [$groupId, $companyId, $branchId, $plantId, $warehouseId] = $this->tree();
        $values = [
            'system' => ['Display.timezone' => 'UTC'],
            'organization:' . $groupId => ['Display.timezone' => 'Asia/Tokyo'],
            'organization:' . $companyId => ['Display.timezone' => 'Europe/London'],
            'organization:' . $branchId => ['Display.timezone' => 'America/New_York'],
            'organization:' . $plantId => ['Display.timezone' => 'Australia/Sydney'],
            'organization:' . $warehouseId => ['Display.timezone' => 'Asia/Ho_Chi_Minh'],
        ];
        $resolver = $this->resolver($values);

        $this->assertSame('Asia/Ho_Chi_Minh', $resolver->resolve('Display.timezone', $warehouseId));
        unset($values['organization:' . $warehouseId]);
        $resolver = $this->resolver($values);
        $this->assertSame('Australia/Sydney', $resolver->resolve('Display.timezone', $warehouseId));
        unset($values['organization:' . $plantId], $values['organization:' . $branchId], $values['organization:' . $companyId]);
        $resolver = $this->resolver($values);
        $this->assertSame('Asia/Tokyo', $resolver->resolve('Display.timezone', $warehouseId));
        unset($values['organization:' . $groupId]);
        $resolver = $this->resolver($values);
        $this->assertSame('UTC', $resolver->resolve('Display.timezone', $warehouseId));
        unset($values['system']);
        $resolver = $this->resolver($values);
        $this->assertSame('Asia/Ho_Chi_Minh', $resolver->resolve('Display.timezone', $warehouseId));
    }

    public function testSiblingOverrideNeverParticipates(): void
    {
        [$groupId, $companyId, $branchId, $plantId, $warehouseId] = $this->tree();
        $siblingBranchId = $this->insertOrganization('BRANCH', $companyId);
        $siblingWarehouseId = $this->insertOrganization('WAREHOUSE', $siblingBranchId);
        $resolver = $this->resolver([
            'organization:' . $siblingWarehouseId => ['Display.timezone' => 'Asia/Tokyo'],
            'system' => ['Display.timezone' => 'UTC'],
        ]);

        $this->assertSame('UTC', $resolver->resolve('Display.timezone', $warehouseId));
        $this->assertNotSame($siblingWarehouseId, $warehouseId);
        $this->assertNotSame($plantId, $siblingBranchId);
        $this->assertNotSame($groupId, $companyId);
    }

    public function testMissingOrganizationUsesSystemThenRegistryDefault(): void
    {
        $resolver = $this->resolver(['system' => ['Display.quantityDecimals' => 4]]);
        $this->assertSame(4, $resolver->resolve('Display.quantityDecimals'));

        $resolver = $this->resolver([]);
        $this->assertSame(3, $resolver->resolve('Display.quantityDecimals'));
    }

    public function testTargetMustExistAndAnUnknownKeyCannotBecomeRuntimeSetting(): void
    {
        $resolver = $this->resolver(['organization:999999' => ['Display.timezone' => 'Asia/Tokyo']]);

        try {
            $resolver->resolve('Display.timezone', 999999);
            $this->fail('Expected a missing Organization to be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame(OrganizationService::ERROR_REFERENCE_INVALID, $exception->getErrorCode());
        }

        $this->expectException(\InvalidArgumentException::class);
        $resolver->resolve('Display.unknown');
    }

    public function testInvalidStoredValueIsExcludedAndResolveAllUsesOnlyRegistryKeysAndTypes(): void
    {
        [$groupId] = $this->tree();
        $resolver = $this->resolver([
            'organization:' . $groupId => [
                'Display.timezone' => 'not/a-timezone',
                'Display.quantityDecimals' => 4,
                'Display.unknown' => 'ignored',
            ],
        ]);

        $this->assertSame('Asia/Ho_Chi_Minh', $resolver->resolve('Display.timezone', $groupId));
        $snapshot = $resolver->resolveAll($groupId);
        $this->assertArrayNotHasKey('Display.unknown', $snapshot);
        $this->assertSame(4, $snapshot['Display.quantityDecimals']);
        $this->assertIsInt($snapshot['Display.quantityDecimals']);
    }

    public function testReparentingChangesTheCanonicalRuntimePath(): void
    {
        $service = new OrganizationService(null, $this->db);
        $groupId = $service->create($this->organization('GROUP'));
        $companyA = $service->create($this->organization('COMPANY', $groupId));
        $companyB = $service->create($this->organization('COMPANY', $groupId));
        $branchId = $service->create($this->organization('BRANCH', $companyA));
        $values = [
            'organization:' . $companyA => ['Display.timezone' => 'Asia/Tokyo'],
            'organization:' . $companyB => ['Display.timezone' => 'Europe/London'],
        ];
        $resolver = $this->resolver($values, $service);

        $this->assertSame('Asia/Tokyo', $resolver->resolve('Display.timezone', $branchId));
        $this->assertTrue($service->update($branchId, ['parent_id' => $companyB]));
        $this->assertSame('Europe/London', $resolver->resolve('Display.timezone', $branchId));
    }

    public function testDisplayTimezoneDoesNotReplaceBusinessTimezone(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->method('get')->willReturnCallback(static function (string $key, ?string $context = null): mixed {
            if ($key === 'Display.timezone' && $context === 'system') {
                return 'Asia/Tokyo';
            }
            if ($key === 'App.businessTimezone' && $context === 'company:42') {
                return 'UTC';
            }

            return null;
        });
        $storage = new CodeIgniterDisplaySettingsStorage($settings);

        $this->assertSame('Asia/Tokyo', (new DisplaySettingsResolver($storage, new OrganizationService(null, $this->db)))->resolve('Display.timezone'));
        $businessTimezone = (new BusinessTimezoneResolver($settings))->resolve(42);
        $this->assertSame('UTC', $businessTimezone?->getName());
    }

    /** @param array<string, array<string, mixed>> $values */
    private function resolver(array $values, ?OrganizationService $service = null): DisplaySettingsResolver
    {
        $settings = $this->createMock(Settings::class);
        $settings->method('get')->willReturnCallback(static function (string $key, ?string $context = null) use ($values): mixed {
            foreach ($values[$context ?? ''] ?? [] as $canonicalKey => $value) {
                if ($canonicalKey === $key) {
                    return $value;
                }
                if (in_array($canonicalKey, DisplaySettingRegistry::keys(), true)
                    && DisplaySettingRegistry::storageKey($canonicalKey) === $key) {
                    return $value;
                }
            }

            return null;
        });

        return new DisplaySettingsResolver(
            new CodeIgniterDisplaySettingsStorage($settings),
            $service ?? new OrganizationService(null, $this->db),
        );
    }

    /** @return array{int, int, int, int, int} */
    private function tree(): array
    {
        $groupId = $this->insertOrganization('GROUP');
        $companyId = $this->insertOrganization('COMPANY', $groupId);
        $branchId = $this->insertOrganization('BRANCH', $companyId);
        $plantId = $this->insertOrganization('PLANT', $branchId);
        $warehouseId = $this->insertOrganization('WAREHOUSE', $plantId);

        return [$groupId, $companyId, $branchId, $plantId, $warehouseId];
    }

    /** @return array{parent_id: ?int, type: string, code: string, name: string, status: string, created_at: string} */
    private function organization(string $type, ?int $parentId = null): array
    {
        return [
            'parent_id' => $parentId,
            'type' => $type,
            'code' => 'DSP002-' . $type . '-' . uniqid(),
            'name' => $type . ' Display Settings Organization',
            'status' => 'ACTIVE',
            'created_at' => '2026-10-07 00:00:00',
        ];
    }

    private function insertOrganization(string $type, ?int $parentId = null): int
    {
        $this->db->table('organizations')->insert($this->organization($type, $parentId));

        return (int) $this->db->insertID();
    }
}
