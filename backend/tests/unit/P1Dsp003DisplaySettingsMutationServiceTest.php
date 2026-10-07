<?php

declare(strict_types=1);

use App\Database\Seeds\ResourceActionSeeder;
use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Settings\CodeIgniterDisplaySettingsStorage;
use App\Infrastructure\Settings\DisplaySettingsResolver;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\ConfigurationAuditEventRepository;
use App\Repositories\ConfigurationAuditEventWriter;
use App\Repositories\PolicyRepository;
use App\Services\DisplaySettingsMutationService;
use App\Services\OrganizationService;
use CodeIgniter\Settings\Config\Services as SettingsServices;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Dsp003DisplaySettingsMutationServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testAuthorizedSetResetWritesCanonicalAuditAndLeavesAuthorizationLogsUntouched(): void
    {
        [$userId, $groupId, $companyId] = $this->fixture(true);
        $operation = $this->operation($userId);
        $service = new DisplaySettingsMutationService(db: $this->db);
        $beforeLogs = (int) $this->db->table('authorization_logs')->countAllResults();

        $this->assertSame(['Display.quantityDecimals', 'Display.trailingZeros'], $service->setBatch(
            'organization:' . $companyId,
            ['Display.quantityDecimals' => 4, 'Display.trailingZeros' => true],
            $operation,
        ));
        $this->assertSame($beforeLogs, (int) $this->db->table('authorization_logs')->countAllResults());

        $events = (new ConfigurationAuditEventRepository($this->db))->findAll();
        $this->assertCount(2, $events);
        $this->assertSame('DISPLAY_SETTING', $events[0]['configuration_type']);
        $this->assertSame('ORGANIZATION', $events[0]['context_type']);
        $this->assertSame((string) $companyId, (string) $events[0]['organization_id']);
        $this->assertSame('SET', $events[0]['operation']);
        $this->assertSame('4', $events[0]['new_value_json']);
        $this->assertSame('true', $events[1]['new_value_json']);
        $this->assertSame('dsp003-test', $events[0]['request_id']);
        $this->assertSame('2026-10-07 12:00:00', $events[0]['created_at']);

        $storage = new CodeIgniterDisplaySettingsStorage(SettingsServices::settings(), $this->db);
        $this->assertSame(4, $storage->persistedOverride('Display.quantityDecimals', 'organization:' . $companyId)['value']);
        $resolver = new DisplaySettingsResolver($storage, new OrganizationService(null, $this->db));
        $this->assertSame(4, $resolver->resolve('Display.quantityDecimals', $companyId));

        $this->assertSame(['Display.quantityDecimals'], $service->resetBatch(
            'organization:' . $companyId,
            ['Display.quantityDecimals'],
            $operation,
        ));
        $events = (new ConfigurationAuditEventRepository($this->db))->findAll();
        $this->assertCount(3, $events);
        $this->assertSame('RESET', $events[2]['operation']);
        $this->assertSame('4', $events[2]['old_value_json']);
        $this->assertNull($events[2]['new_value_json']);
        $this->assertSame(3, $resolver->resolve('Display.quantityDecimals', $companyId));

        $this->assertSame([], $service->resetBatch(
            'organization:' . $companyId,
            ['Display.quantityDecimals'],
            $operation,
        ));
        $this->assertCount(3, (new ConfigurationAuditEventRepository($this->db))->findAll());
        $this->assertSame($beforeLogs, (int) $this->db->table('authorization_logs')->countAllResults());
        $this->assertSame($groupId, (int) $this->db->table('organizations')->where('type', 'GROUP')->get()->getRowArray()['id']);
    }

    public function testMissingBasePermissionAndInvalidBatchDoNotMutate(): void
    {
        [$userId, , $companyId] = $this->fixture(false);
        $service = new DisplaySettingsMutationService(db: $this->db);

        try {
            $service->setBatch('organization:' . $companyId, ['Display.quantityDecimals' => 4], $this->operation($userId));
            $this->fail('Expected PBAC denial.');
        } catch (DomainException $exception) {
            $this->assertSame(DisplaySettingsMutationService::ERROR_DENIED, $exception->getErrorCode());
        }
        $this->assertSame(0, (int) $this->db->table('configuration_audit_events')->countAllResults());

        $this->expectException(DomainException::class);
        $service->setBatch('organization:' . $companyId, ['Display.unknown' => 'x'], $this->operation($userId));
    }

    public function testAuditFailureRollsBackEverySettingsOverrideInTheBatch(): void
    {
        [$userId, , $companyId] = $this->fixture(true);
        $failingAudit = new class implements ConfigurationAuditEventWriter {
            public function insert(array $event): int
            {
                throw new RuntimeException('audit failure');
            }
        };
        $service = new DisplaySettingsMutationService(audit: $failingAudit, db: $this->db);

        try {
            $service->setBatch(
                'organization:' . $companyId,
                ['Display.quantityDecimals' => 4, 'Display.trailingZeros' => true],
                $this->operation($userId),
            );
            $this->fail('Expected audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit failure', $exception->getMessage());
        }

        $storage = new CodeIgniterDisplaySettingsStorage(new \CodeIgniter\Settings\Settings(config('Settings')), $this->db);
        $this->assertFalse($storage->persistedOverride('Display.quantityDecimals', 'organization:' . $companyId)['exists']);
        $this->assertFalse($storage->persistedOverride('Display.trailingZeros', 'organization:' . $companyId)['exists']);
        $this->assertSame(0, (int) $this->db->table('configuration_audit_events')->countAllResults());
    }

    public function testSystemContextEvaluatesAtTheCanonicalGroupAndMissingOrganizationFailsClosed(): void
    {
        [$userId, $groupId] = $this->fixture(true);
        $service = new DisplaySettingsMutationService(db: $this->db);

        $this->assertSame(['Display.dateFormat'], $service->setBatch(
            'system',
            ['Display.dateFormat' => 'YYYY-MM-DD'],
            $this->operation($userId),
        ));
        $event = (new ConfigurationAuditEventRepository($this->db))->findAll()[0];
        $this->assertSame('SYSTEM', $event['context_type']);
        $this->assertNull($event['organization_id']);
        $this->assertSame('YYYY-MM-DD', json_decode((string) $event['new_value_json'], true));

        try {
            $service->setBatch('organization:999999999', ['Display.dateFormat' => 'YYYY'], $this->operation($userId));
            $this->fail('Expected an invisible organization target to fail closed.');
        } catch (DomainException $exception) {
            $this->assertSame(DisplaySettingsMutationService::ERROR_NOT_FOUND, $exception->getErrorCode());
        }
        $this->assertSame($groupId, (int) $this->db->table('organizations')->where('type', 'GROUP')->get()->getRowArray()['id']);
    }

    public function testDenyAndAbsoluteDenyAreEnforcedByCanonicalPbacBeforeMutation(): void
    {
        [$userId, $groupId, $companyId] = $this->fixture(true);
        $resourceId = (int) $this->db->table('resources')->where('type', 'display_setting')->get()->getRowArray()['id'];
        $actionId = (int) $this->db->table('resource_actions')->where(['resource_id' => $resourceId, 'code' => 'edit'])->get()->getRowArray()['id'];
        $policies = new PolicyRepository($this->db);
        $denyId = $policies->insertWithActions([
            'organization_id' => $companyId,
            'resource_id' => $resourceId,
            'state_id' => null,
            'participant_type' => 'USER',
            'participant_ref_id' => $userId,
        ], [['resource_action_id' => $actionId, 'effect' => 'DENY']]);

        $authorization = new AuthorizationService(db: $this->db);
        $denied = $authorization->authorize(
            $this->operation($userId),
            'display_setting',
            'edit',
            new EvaluationOrganizationRequest(persistedTarget: ['organization_id' => $companyId]),
        );
        $this->assertSame('DENY', $denied->decision);
        $this->assertSame(AuthorizationService::REASON_POLICY_DENIED, $denied->reasonCode);
        $this->assertSame([$denyId], $denied->decisivePolicyIds);

        $absoluteId = $policies->insertWithActions([
            'organization_id' => $groupId,
            'resource_id' => $resourceId,
            'state_id' => null,
            'participant_type' => 'USER',
            'participant_ref_id' => $userId,
        ], [['resource_action_id' => $actionId, 'effect' => 'ABSOLUTE_DENY']]);
        $absolute = $authorization->authorize(
            $this->operation($userId),
            'display_setting',
            'edit',
            new EvaluationOrganizationRequest(persistedTarget: ['organization_id' => $companyId]),
        );
        $this->assertSame('DENY', $absolute->decision);
        $this->assertSame(AuthorizationService::REASON_ABSOLUTE_DENY, $absolute->reasonCode);
        $this->assertSame([$absoluteId], $absolute->decisivePolicyIds);

        $this->expectException(DomainException::class);
        (new DisplaySettingsMutationService(db: $this->db))->setBatch(
            'organization:' . $companyId,
            ['Display.dateFormat' => 'YYYY'],
            $this->operation($userId),
        );
    }

    /** @return array{int, int, int} */
    private function fixture(bool $basePermission): array
    {
        (new ResourceActionSeeder(new Config\Database()))->run();
        $groupId = $this->insertOrganization('GROUP');
        $companyId = $this->insertOrganization('COMPANY', $groupId);
        $this->db->table('users')->insert([
            'username' => 'dsp003-' . uniqid(), 'status' => 'active', 'active' => 1,
            'created_at' => '2026-10-07 00:00:00',
        ]);
        $userId = (int) $this->db->insertID();
        $groupCode = 'DSP003-' . uniqid();
        $this->db->table('groups')->insert([
            'code' => $groupCode, 'name' => 'DSP003', 'status' => 'ACTIVE',
            'created_at' => '2026-10-07 00:00:00',
        ]);
        $localGroupId = (int) $this->db->insertID();
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId, 'group' => strtolower($groupCode), 'created_at' => '2026-10-07 00:00:00',
        ]);
        if ($basePermission) {
            $resourceId = (int) $this->db->table('resources')->where('type', 'display_setting')->get()->getRowArray()['id'];
            $actionId = (int) $this->db->table('resource_actions')->where(['resource_id' => $resourceId, 'code' => 'edit'])->get()->getRowArray()['id'];
            $this->db->table('group_permissions')->insert([
                'group_id' => $localGroupId, 'resource_action_id' => $actionId, 'created_at' => '2026-10-07 00:00:00',
            ]);
        }

        return [$userId, $groupId, $companyId];
    }

    private function operation(int $userId): OperationContext
    {
        return new OperationContext($userId, new EvaluationTime(new DateTimeImmutable('2026-10-07T12:00:00+00:00')), 'dsp003-test', false);
    }

    private function insertOrganization(string $type, ?int $parentId = null): int
    {
        $this->db->table('organizations')->insert([
            'parent_id' => $parentId, 'type' => $type, 'code' => uniqid('DSP003-O-', true),
            'name' => $type, 'status' => 'ACTIVE', 'created_at' => '2026-10-07 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }
}
