<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Exceptions\DomainException;
use App\Infrastructure\Api\ApiErrorMapper;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Settings\CodeIgniterDisplaySettingsStorage;
use App\Infrastructure\Time\EvaluationTime;
use App\Services\ApiDisplaySettingsService;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Dsp004DisplaySettingsApiTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testRuntimeIsAuthenticatedPresentationOnlyAndOrganizationVisibilityIsEnforced(): void
    {
        [$userId, , $companyId] = $this->fixture(false, true);
        $service = $this->service();
        $runtime = $service->runtime([], $this->operation($userId));

        $this->assertArrayHasKey('timezone', $runtime);
        $this->assertArrayHasKey('date', $runtime);
        $this->assertArrayHasKey('number', $runtime);
        $this->assertArrayHasKey('money', $runtime);
        $this->assertArrayNotHasKey('locale', $runtime);
        $this->assertArrayNotHasKey('business_timezone', $runtime);
        $this->assertArrayNotHasKey('source', $runtime);

        $organizationRuntime = $service->runtime(
            ['organization_id' => (string) $companyId],
            $this->operation($userId),
        );
        $this->assertSame($runtime['timezone'], $organizationRuntime['timezone']);

        $hiddenUserId = $this->hiddenUser();
        try {
            $service->runtime(['organization_id' => (string) $companyId], $this->operation($hiddenUserId));
            $this->fail('Expected hidden Organization runtime to fail closed.');
        } catch (DomainException $exception) {
            $this->assertSame(ApiDisplaySettingsService::ERROR_ORGANIZATION_NOT_FOUND, $exception->getErrorCode());
        }
    }

    public function testDefinitionsAndValuesUseAdministrativeViewAndReturnUiSafeMetadata(): void
    {
        [$userId, , $companyId] = $this->fixture(true, false);
        $service = $this->service();
        $definitions = $service->definitions($this->operation($userId));

        $this->assertCount(15, $definitions);
        $this->assertSame([
            'key', 'category', 'data_type', 'default', 'user_overridable',
        ], array_keys($definitions[0]));
        $this->assertArrayNotHasKey('storage_key', $definitions[0]);
        $this->assertArrayNotHasKey('validation', $definitions[0]);

        $values = $service->values([
            'context_type' => 'ORGANIZATION',
            'organization_id' => (string) $companyId,
        ], $this->operation($userId));
        $this->assertCount(15, $values);
        $this->assertSame('Display.timezone', $values[0]['key']);
        $this->assertSame('SYSTEM', $values[0]['source']['type']);
        $this->assertTrue($values[0]['inherited']);
    }

    public function testInvalidContextsUnknownKeysAndDuplicateKeysAreStable422DomainErrors(): void
    {
        [$userId, , $companyId] = $this->fixture(true, false);
        $service = $this->service();
        $operation = $this->operation($userId);

        $cases = [
            [static fn () => $service->values(['context_type' => 'COMPANY', 'organization_id' => (string) $companyId], $operation), ApiDisplaySettingsService::ERROR_CONTEXT_INVALID],
            [static fn () => $service->set(['context' => ['type' => 'SYSTEM'], 'changes' => [['key' => 'Display.unknown', 'value' => 'x']]], $operation), ApiDisplaySettingsService::ERROR_NOT_FOUND],
            [static fn () => $service->set(['context' => ['type' => 'SYSTEM'], 'changes' => [['key' => 'Display.dateFormat', 'value' => 'YYYY'], ['key' => 'Display.dateFormat', 'value' => 'YYYY-MM-DD']]], $operation), ApiDisplaySettingsService::ERROR_DUPLICATE],
        ];
        foreach ($cases as [$call, $code]) {
            try {
                $call();
                $this->fail('Expected Display Settings validation failure.');
            } catch (DomainException $exception) {
                $this->assertSame($code, $exception->getErrorCode());
            }
        }
        $mapped = ApiErrorMapper::map(new DomainException(ApiDisplaySettingsService::ERROR_NOT_FOUND));
        $this->assertSame(422, $mapped['status']);
    }

    public function testSetAndResetReturnCanonicalValuesAndResetIsIdempotent(): void
    {
        [$userId, , $companyId] = $this->fixture(true, false);
        $service = $this->service();
        $context = ['type' => 'ORGANIZATION', 'organization_id' => (string) $companyId];

        $set = $service->set([
            'context' => $context,
            'changes' => [
                ['key' => 'Display.quantityDecimals', 'value' => 4],
                ['key' => 'Display.trailingZeros', 'value' => true],
            ],
        ], $this->operation($userId));
        $this->assertSame(['Display.quantityDecimals', 'Display.trailingZeros'], array_column($set, 'key'));
        $this->assertSame(4, $set[0]['override_value']);
        $this->assertSame(4, $set[0]['effective_value']);
        $this->assertFalse($set[0]['inherited']);

        $reset = $service->reset([
            'context' => $context,
            'keys' => ['Display.quantityDecimals', 'Display.trailingZeros'],
        ], $this->operation($userId));
        $this->assertNull($reset[0]['override_value']);
        $this->assertSame(3, $reset[0]['effective_value']);
        $this->assertTrue($reset[0]['inherited']);

        $idempotent = $service->reset([
            'context' => $context,
            'keys' => ['Display.quantityDecimals'],
        ], $this->operation($userId));
        $this->assertNull($idempotent[0]['override_value']);
        $this->assertSame(3, $idempotent[0]['effective_value']);
    }

    public function testRoutesAndOpenApiContainExactlyTheFiveDisplaySettingsOperations(): void
    {
        $routes = (string) file_get_contents(__DIR__ . '/../../app/Config/Routes.php');
        $openapi = (string) file_get_contents(__DIR__ . '/../../../contracts/openapi/openapi.yaml');
        foreach ([
            'GET display-settings/runtime',
            'GET display-settings/definitions',
            'GET display-settings/values',
            'PATCH display-settings',
            'POST display-settings/actions/reset',
        ] as $signature) {
            [$method, $path] = explode(' ', $signature, 2);
            $this->assertStringContainsString("\$routes->" . strtolower($method) . "('" . $path . "'", $routes);
            $this->assertStringContainsString('/api/v1/' . $path . ':', $openapi);
        }
        $this->assertStringNotContainsString('display-settings/user', strtolower($routes . $openapi));
        $this->assertStringNotContainsString('business_timezone', strtolower($openapi));
    }

    /** @return array{int, int, int} */
    private function fixture(bool $displayPermission, bool $organizationPermission): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $groupId = $this->insertOrganization('GROUP');
        $companyId = $this->insertOrganization('COMPANY', $groupId);
        $this->db->table('users')->insert([
            'username' => 'dsp004-' . uniqid(), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-07 00:00:00',
        ]);
        $userId = (int) $this->db->insertID();
        $groupCode = 'DSP004-' . uniqid();
        $this->db->table('groups')->insert([
            'code' => $groupCode, 'name' => 'DSP004', 'status' => 'ACTIVE',
            'created_at' => '2026-10-07 00:00:00',
        ]);
        $localGroupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId, 'group' => strtolower($groupCode), 'created_at' => '2026-10-07 00:00:00',
        ]);
        $permissions = [];
        if ($displayPermission) {
            $permissions[] = ['display_setting', 'view'];
            $permissions[] = ['display_setting', 'edit'];
        }
        if ($organizationPermission) {
            $permissions[] = ['organization', 'view'];
        }
        foreach ($permissions as [$resource, $action]) {
            $resourceId = (int) $this->db->table('resources')->where('type', $resource)->get()->getRowArray()['id'];
            $actionId = (int) $this->db->table('resource_actions')->where(['resource_id' => $resourceId, 'code' => $action])->get()->getRowArray()['id'];
            $this->db->table('group_permissions')->insert([
                'group_id' => $localGroupId, 'resource_action_id' => $actionId, 'created_at' => '2026-10-07 00:00:00',
            ]);
        }

        return [$userId, $groupId, $companyId];
    }

    private function operation(int $userId): OperationContext
    {
        return new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-07T12:00:00+00:00')), 'dsp004-test', false);
    }

    private function service(): ApiDisplaySettingsService
    {
        $settings = new Settings(config('Settings'));
        $storage = new CodeIgniterDisplaySettingsStorage($settings, $this->db);

        return new ApiDisplaySettingsService(storage: $storage, db: $this->db);
    }

    private function hiddenUser(): int
    {
        $this->db->table('users')->insert([
            'username' => 'dsp004-hidden-' . uniqid(), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-07 00:00:00',
        ]);
        $userId = (int) $this->db->insertID();
        $groupCode = 'DSP004-HIDDEN-' . uniqid();
        $this->db->table('groups')->insert([
            'code' => $groupCode, 'name' => 'DSP004 hidden', 'status' => 'ACTIVE',
            'created_at' => '2026-10-07 00:00:00',
        ]);
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId, 'group' => strtolower($groupCode), 'created_at' => '2026-10-07 00:00:00',
        ]);

        return $userId;
    }

    private function insertOrganization(string $type, ?int $parentId = null): int
    {
        $this->db->table('organizations')->insert([
            'parent_id' => $parentId, 'type' => $type, 'code' => uniqid('DSP004-O-', true),
            'name' => $type, 'status' => 'ACTIVE', 'created_at' => '2026-10-07 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }
}
