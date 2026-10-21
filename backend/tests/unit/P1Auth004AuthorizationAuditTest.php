<?php

declare(strict_types=1);

use App\Authorization\AuthorizationAuditService;
use App\Authorization\AuthorizationDecision;
use App\Authorization\AuthorizationService;
use App\Infrastructure\Time\EvaluationTime;
use App\Repositories\AuthorizationLogRepository;
use App\Repositories\PolicyRepository;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;

/** @internal */
final class P1Auth004AuthorizationAuditTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;

    public function testOnlyApproveDeleteAndStateChangeArePersisted(): void
    {
        $this->seedCatalog();
        $service = new AuthorizationAuditService(db: $this->db);
        $before = (int) $this->db->table('authorization_logs')->countAllResults();

        foreach (['approve', 'delete', 'setstate'] as $action) {
            $this->assertGreaterThan(0, $service->recordIfRequired($this->decision($action)));
        }
        $this->assertNull($service->recordIfRequired($this->decision('view')));
        $this->assertNull($service->recordIfRequired($this->decision('list')));
        $this->assertSame($before + 3, (int) $this->db->table('authorization_logs')->countAllResults());
    }

    public function testAllowAndDenyPersistAuthoritativeFieldsAndTrustedTime(): void
    {
        $this->seedCatalog();
        $organizationId = $this->insertOrganization();
        $stateId = $this->insertState();
        $policyId = $this->insertPolicy($organizationId);
        $decision = $this->decision('setstate', 'ALLOW', 55, $organizationId, $stateId, [$policyId], 'audit-004');

        $id = (new AuthorizationAuditService(db: $this->db))->recordIfRequired($decision);
        $row = $this->rowById((int) $id);

        $this->assertSame('55', (string) $row['user_id']);
        $this->assertSame((string) $organizationId, (string) $row['organization_id']);
        $this->assertSame((string) $this->resourceId('product'), (string) $row['resource_id']);
        $this->assertSame('setstate', $row['action']);
        $this->assertSame((string) $stateId, (string) $row['state_id']);
        $this->assertSame('ALLOW', $row['decision']);
        $this->assertSame((string) $policyId, (string) $row['matched_policy_id']);
        $this->assertSame(AuthorizationService::REASON_POLICY_ALLOWED, $row['reason']);
        $this->assertSame('audit-004', $row['request_id']);
        $this->assertSame('2026-10-05 12:34:56', $row['created_at']);

        $deny = $this->decision('delete', 'DENY', 55, $organizationId, null, [], 'audit-004-deny');
        $denyId = (new AuthorizationAuditService(db: $this->db))->recordIfRequired($deny);
        $denyRow = $this->rowById((int) $denyId);
        $this->assertSame('DENY', $denyRow['decision']);
        $this->assertNull($denyRow['matched_policy_id']);
        $this->assertNull($denyRow['state_id']);
    }

    public function testMultipleDecisivePoliciesUseNullRepresentativeAndBaseDenyHasNoPolicy(): void
    {
        $this->seedCatalog();
        $organizationId = $this->insertOrganization();
        $first = $this->insertPolicy($organizationId, 'USER');
        $second = $this->insertPolicy($organizationId, 'GROUP');
        $service = new AuthorizationAuditService(db: $this->db);

        $multipleId = $service->recordIfRequired($this->decision('delete', 'DENY', 55, $organizationId, null, [$second, $first]));
        $multiple = $this->rowById((int) $multipleId);
        $this->assertNull($multiple['matched_policy_id']);

        $baseDenyId = $service->recordIfRequired($this->decision('approve', 'DENY', 55, $organizationId, null, [], 'base-deny', AuthorizationService::REASON_NO_BASE_PERMISSION));
        $baseDeny = $this->rowById((int) $baseDenyId);
        $this->assertNull($baseDeny['matched_policy_id']);
        $this->assertSame(AuthorizationService::REASON_NO_BASE_PERMISSION, $baseDeny['reason']);
    }

    public function testDecisionInputCannotAddAuditFieldsAndEvidenceHasNoMutationPath(): void
    {
        $this->seedCatalog();
        $repository = new AuthorizationLogRepository($this->db);
        $this->assertFalse(in_array('update', get_class_methods($repository), true));
        $this->assertFalse(in_array('delete', get_class_methods($repository), true));

        try {
            $repository->insert([
                'user_id' => 55, 'resource_id' => $this->resourceId('product'),
                'action' => 'delete', 'decision' => 'ALLOW',
                'created_at' => '2026-10-05 12:34:56', 'secret' => 'should-reject',
            ]);
            $this->fail('Expected unsupported audit field rejection.');
        } catch (\App\Exceptions\DomainException $exception) {
            $this->assertSame(AuthorizationLogRepository::ERROR_FIELD_UNSUPPORTED, $exception->getErrorCode());
        }
    }

    public function testPersistenceFailurePropagatesAsTypedFailure(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('table')->willThrowException(new RuntimeException('database unavailable'));

        $this->expectException(\App\Exceptions\PersistenceException::class);
        (new AuthorizationAuditService(db: $connection))->recordIfRequired($this->decision('delete'));
    }

    /** @param array<int, int> $decisive */
    private function decision(
        string $action,
        string $decision = 'ALLOW',
        int $userId = 55,
        int $organizationId = 1,
        ?int $stateId = null,
        array $decisive = [],
        ?string $requestId = 'audit-004-default',
        ?string $reason = null,
    ): AuthorizationDecision {
        return new AuthorizationDecision(
            $decision,
            $reason ?? ($decision === 'DENY' ? AuthorizationService::REASON_POLICY_DENIED : AuthorizationService::REASON_POLICY_ALLOWED),
            $userId,
            'product',
            $action,
            $organizationId,
            $stateId,
            new EvaluationTime(new DateTimeImmutable('2026-10-05T12:34:56+00:00')),
            ['product.' . $action],
            $decisive,
            $decisive,
            [],
            $requestId,
        );
    }

    /** @return array<string, mixed> */
    private function rowById(int $id): array
    {
        $result = $this->db->table('authorization_logs')->where('id', $id)->get();
        $row = $result === false ? null : $result->getRowArray();
        $this->assertIsArray($row);

        return $row;
    }

    private function seedCatalog(): void
    {
        (new App\Database\Seeds\ResourceActionSeeder(new Config\Database(), $this->db))->run();
    }

    private function insertOrganization(): int
    {
        $this->db->table('organizations')->insert([
            'type' => 'COMPANY', 'code' => uniqid('AUTH004-O-', true), 'name' => 'Audit Company',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertState(): int
    {
        $this->db->table('workflow_states')->insert([
            'code' => uniqid('AUTH004-S-', true), 'name' => 'Audit State',
            'status' => 'ACTIVE', 'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertPolicy(int $organizationId, string $participantType = 'USER'): int
    {
        $this->db->table('policies')->insert([
            'organization_id' => $organizationId, 'resource_id' => $this->resourceId('product'),
            'state_id' => null, 'participant_type' => $participantType, 'participant_ref_id' => 55,
            'created_at' => '2026-10-05 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function resourceId(string $type): int
    {
        $result = $this->db->table('resources')->where('type', $type)->get();
        $row = $result === false ? null : $result->getRowArray();

        return (int) ($row['id'] ?? 0);
    }
}
