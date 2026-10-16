<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Infrastructure\Settings\BusinessTimezoneResolver;
use App\Infrastructure\Settings\CodeIgniterDisplaySettingsStorage;
use App\Infrastructure\Settings\DisplaySettingRegistry;
use App\Infrastructure\Settings\DisplaySettingsResolver;
use App\Services\OrganizationService;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\WorkflowLifecycleFixture;

/** @internal */
final class BeReorg005SettingsStorageResolverCharacterizationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        WorkflowLifecycleFixture::bind($this->db, ['organization']);
    }

    public function testStorageSetGetForgetAndPersistedOverrideShape(): void
    {
        $storage = new CodeIgniterDisplaySettingsStorage(\CodeIgniter\Settings\Config\Services::settings(), $this->db);
        $storage->forget('Display.timezone', 'system');
        $this->assertSame(['exists' => false, 'value' => null], $storage->persistedOverride('Display.timezone', 'system'));
        $storage->set('Display.timezone', 'Asia/Tokyo', 'system');
        $this->assertSame('Asia/Tokyo', $storage->get('Display.timezone', 'system'));
        $this->assertSame(['exists' => true, 'value' => 'Asia/Tokyo'], $storage->persistedOverride('Display.timezone', 'system'));
        $storage->forget('Display.timezone', 'system');
    }

    public function testNearestAncestorWins(): void
    {
        [$group, $company, $branch] = $this->tree();
        $resolver = $this->resolver(['organization:' . $group => ['Display.timezone' => 'UTC'], 'organization:' . $company => ['Display.timezone' => 'Asia/Tokyo']]);
        $this->assertSame('Asia/Tokyo', $resolver->resolve('Display.timezone', $branch));
    }

    public function testSiblingOverrideIsExcluded(): void
    {
        [$group, $company, $branch] = $this->tree();
        $sibling = $this->insertOrganization('BRANCH', $company);
        $resolver = $this->resolver(['organization:' . $sibling => ['Display.timezone' => 'Asia/Tokyo'], 'organization:' . $group => ['Display.timezone' => 'UTC']]);
        $this->assertSame('UTC', $resolver->resolve('Display.timezone', $branch));
    }

    public function testSystemThenRegistryDefaultFallback(): void
    {
        $this->assertSame(4, $this->resolver(['system' => ['Display.quantityDecimals' => 4]])->resolve('Display.quantityDecimals'));
        $this->assertSame(3, $this->resolver([])->resolve('Display.quantityDecimals'));
    }

    public function testInvalidNearestPersistedValueIsSkipped(): void
    {
        [$group, $company, $branch] = $this->tree();
        $resolver = $this->resolver(['organization:' . $company => ['Display.timezone' => 'not/a-timezone'], 'organization:' . $group => ['Display.timezone' => 'UTC']]);
        $this->assertSame('UTC', $resolver->resolve('Display.timezone', $branch));
    }

    public function testReparentingImmediatelyChangesResolutionPath(): void
    {
        $service = new OrganizationService(null, $this->db);
        $group = $service->create($this->organization('GROUP'));
        $a = $service->create($this->organization('COMPANY', $group));
        $b = $service->create($this->organization('COMPANY', $group));
        $branch = $service->create($this->organization('BRANCH', $a));
        $resolver = $this->resolver(['organization:' . $a => ['Display.timezone' => 'Asia/Tokyo'], 'organization:' . $b => ['Display.timezone' => 'Europe/London']], $service);
        $this->assertSame('Asia/Tokyo', $resolver->resolve('Display.timezone', $branch));
        $this->assertTrue($service->update($branch, ['parent_id' => $b]));
        $this->assertSame('Europe/London', $resolver->resolve('Display.timezone', $branch));
    }

    public function testUnknownKeyAndMissingTargetFailClosed(): void
    {
        try {
            $this->resolver([])->resolve('Display.timezone', 999999999);
            $this->fail('Missing organization must fail.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(InvalidArgumentException::class);
        $this->resolver([])->resolve('Display.unknown');
    }

    public function testDisplayTimezoneDoesNotReplaceBusinessTimezone(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->method('get')->willReturnCallback(static function (string $key, ?string $context = null): mixed {
            return match ([$key, $context]) {
                ['Display.timezone', 'system'] => 'Asia/Tokyo',
                ['App.businessTimezone', 'company:42'] => 'UTC',
                default => null,
            };
        });
        $storage = new CodeIgniterDisplaySettingsStorage($settings);
        $this->assertSame('Asia/Tokyo', (new DisplaySettingsResolver($storage, new OrganizationService(null, $this->db)))->resolve('Display.timezone'));
        $this->assertSame('UTC', (new BusinessTimezoneResolver($settings))->resolve(42)?->getName());
    }

    /** @param array<string, array<string, mixed>> $values */
    private function resolver(array $values, ?OrganizationService $service = null): DisplaySettingsResolver
    {
        $settings = $this->createMock(Settings::class);
        $settings->method('get')->willReturnCallback(static function (string $key, ?string $context = null) use ($values): mixed {
            foreach ($values[$context ?? ''] ?? [] as $canonical => $value) {
                if ($canonical === $key || (in_array($canonical, DisplaySettingRegistry::keys(), true) && DisplaySettingRegistry::storageKey($canonical) === $key)) {
                    return $value;
                }
            }
            return null;
        });
        return new DisplaySettingsResolver(new CodeIgniterDisplaySettingsStorage($settings), $service ?? new OrganizationService(null, $this->db));
    }

    /** @return array{int,int,int} */
    private function tree(): array
    {
        $group = $this->insertOrganization('GROUP');
        $company = $this->insertOrganization('COMPANY', $group);
        $branch = $this->insertOrganization('BRANCH', $company);
        return [$group, $company, $branch];
    }

    /** @return array{parent_id:?int,type:string,code:string,name:string,status:string,created_at:string} */
    private function organization(string $type, ?int $parentId = null): array
    {
        return ['parent_id'=>$parentId,'type'=>$type,'code'=>'BE005-'.$type.'-'.uniqid(),'name'=>$type.' BE005','status'=>'DRAFT','created_at'=>'2026-10-16 00:00:00'];
    }

    private function insertOrganization(string $type, ?int $parentId = null): int
    {
        $this->db->table('organizations')->insert($this->organization($type, $parentId));
        return (int) $this->db->insertID();
    }
}
