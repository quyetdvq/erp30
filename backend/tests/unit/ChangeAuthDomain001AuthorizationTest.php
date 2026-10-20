<?php

declare(strict_types=1);

use App\Authorization\EvaluationOrganizationRequest;
use App\Authorization\EvaluationOrganizationResolver;
use App\Authorization\ResourceDomain;
use App\Exceptions\DomainException;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;

/** @internal */
final class ChangeAuthDomain001AuthorizationTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;

    public function testResourceDomainAcceptsExactlyThreeValuesAndRejectsLegacyValues(): void
    {
        $this->assertSame(['SYSTEM', 'MASTER', 'ORGANIZATION'], array_map(static fn (ResourceDomain $domain): string => $domain->value, ResourceDomain::cases()));
        $resolver = new EvaluationOrganizationResolver(null, null, null, $this->db);
        $this->insertOrganization('GROUP');

        foreach ([[], ['domain' => ''], ['domain' => 'COMPANY'], ['domain' => 'UNKNOWN']] as $resource) {
            try {
                $resolver->resolveResource('resource', 'view', $resource, new EvaluationOrganizationRequest());
                $this->fail('Expected invalid Resource Domain to fail closed.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAllSevenOrganizationResolversAreExplicitAndFailClosedWhenMissing(): void
    {
        [$groupId, $companyId] = $this->organizationTree();
        $employeeId = $this->insertEmployee();
        $resolver = new EvaluationOrganizationResolver(null, null, null, $this->db);

        $cases = [
            ['organization', 'create', ['parent_id' => $companyId]],
            ['organization_contact', 'create', ['contact_type' => 'ORGANIZATION', 'contact_ref_id' => $companyId]],
            ['employee_assignment', 'create', ['organization_id' => $companyId]],
            ['policy', 'create', ['organization_id' => $companyId]],
            ['price_list', 'create', ['organization_id' => $companyId]],
        ];
        foreach ($cases as [$resource, $action, $input]) {
            $context = $resolver->resolveResource($resource, $action, $this->resource($resource), new EvaluationOrganizationRequest(input: $input));
            $this->assertSame($companyId, $context->organizationId, $resource);
        }

        // An employee without an effective primary assignment MUST NOT be
        // silently evaluated at GROUP or any unrelated Organization.
        try {
            $resolver->resolveResource('employee_contact', 'create', $this->resource('employee_contact'), new EvaluationOrganizationRequest(
                input: ['contact_type' => 'EMPLOYEE', 'contact_ref_id' => $employeeId],
            ));
            $this->fail('Expected missing primary assignment to fail closed.');
        } catch (DomainException $exception) {
            $this->assertSame('CONTACT_EMPLOYEE_PRIMARY_SCOPE_UNAVAILABLE', $exception->getErrorCode());
        }

        $logContext = $resolver->resolveResource('authorization_log', 'view', $this->resource('authorization_log'), new EvaluationOrganizationRequest(
            persistedTarget: ['organization_id' => null],
        ));
        $this->assertSame($groupId, $logContext->organizationId);

        $importContext = $resolver->resolveResource('import_job', 'view', $this->resource('import_job'), new EvaluationOrganizationRequest(
            persistedTarget: ['entity_type' => 'PRODUCT'],
        ));
        $this->assertSame($groupId, $importContext->organizationId);

        $this->expectException(DomainException::class);
        $resolver->resolveResource('import_job', 'view', $this->resource('import_job'), new EvaluationOrganizationRequest(
            persistedTarget: ['entity_type' => 'CONTACT'],
        ));
    }

    public function testAllSystemAndMasterDomainsResolveGroupWithoutResourceNameRules(): void
    {
        $groupId = $this->insertOrganization('GROUP');
        $resolver = new EvaluationOrganizationResolver(null, null, null, $this->db);

        foreach ([
            'province', 'ward', 'department', 'position', 'employee', 'product_group', 'product_uom', 'product',
            'user', 'authorization_group', 'resource', 'workflow_state', 'lifecycle',
        ] as $resourceType) {
            $domain = in_array($resourceType, ['user', 'authorization_group', 'resource', 'workflow_state', 'lifecycle'], true)
                ? 'SYSTEM'
                : 'MASTER';
            $context = $resolver->resolveResource($resourceType, 'view', ['id' => 1, 'type' => $resourceType, 'domain' => $domain], new EvaluationOrganizationRequest());
            $this->assertSame($groupId, $context->organizationId, $resourceType);
        }

        $this->expectException(DomainException::class);
        $resolver->resolveResource('not_registered', 'view', $this->resource('not_registered'), new EvaluationOrganizationRequest(
            persistedTarget: ['organization_id' => $groupId],
        ));
    }

    public function testInactiveOrganizationRemainsValidHistoricalContextIdentity(): void
    {
        $this->insertOrganization('GROUP');
        $inactiveId = $this->insertOrganization('COMPANY', null, 'INACTIVE');
        $resolver = new EvaluationOrganizationResolver(null, null, null, $this->db);

        $context = $resolver->resolveResource('price_list', 'view', $this->resource('price_list'), new EvaluationOrganizationRequest(
            persistedTarget: ['organization_id' => $inactiveId],
        ));
        $this->assertSame($inactiveId, $context->organizationId);
    }

    /** @return array<string, mixed> */
    private function resource(string $type): array
    {
        return ['id' => 1, 'type' => $type, 'domain' => 'ORGANIZATION'];
    }

    /** @return array{int, int} */
    private function organizationTree(): array
    {
        $group = $this->insertOrganization('GROUP');
        $company = $this->insertOrganization('COMPANY', $group);

        return [$group, $company];
    }

    private function insertOrganization(string $type, ?int $parentId = null, string $status = 'ACTIVE'): int
    {
        $this->db->table('organizations')->insert([
            'parent_id' => $parentId, 'type' => $type, 'code' => uniqid('CAD-O-', true),
            'name' => $type, 'status' => $status, 'created_at' => '2026-10-07 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function insertEmployee(): int
    {
        $this->db->table('employees')->insert([
            'user_id' => null, 'employee_code' => uniqid('CAD-E-', true),
            'employee_name' => 'Domain Employee', 'status' => 'INACTIVE', 'created_at' => '2026-10-07 00:00:00',
        ]);

        return (int) $this->db->insertID();
    }
}
