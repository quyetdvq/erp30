<?php

declare(strict_types=1);

use App\Services\BusinessLifecycleTargetAdapter;
use App\Services\InitialCreateStateNormalizer;
use App\Services\ResourceNativeStateCatalog;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal Invariant of the post-P1 Workflow architecture. */
final class BusinessWorkflowTenNativeBoundaryTest extends CIUnitTestCase
{
    public function testOnlyTenResourcesAreIndependent(): void
    {
        $this->assertSame([
            'authorization_group', 'authorization_log', 'display_setting',
            'employee_assignment', 'import_job', 'lifecycle', 'policy', 'user',
            'workflow_state', 'resource',
        ], ResourceNativeStateCatalog::INDEPENDENT_RESOURCES);
        $this->assertCount(10, ResourceNativeStateCatalog::INDEPENDENT_RESOURCES);
        $this->assertSame([], ResourceNativeStateCatalog::codes('authorization_log'));
        $this->assertSame([], ResourceNativeStateCatalog::codes('display_setting'));
        foreach (ResourceNativeStateCatalog::INDEPENDENT_RESOURCES as $type) {
            $this->assertFalse(BusinessLifecycleTargetAdapter::supports($type));
            $this->assertFalse(ResourceNativeStateCatalog::supportsLifecycleState($type, 'DRAFT'));
        }
    }

    public function testBusinessResourcesAcceptLifecycleInitialRegardlessOfLegacyVocabulary(): void
    {
        foreach (['province', 'ward', 'organization', 'department', 'position', 'employee',
            'product_group', 'product_uom', 'product', 'price_list',
            'organization_contact', 'employee_contact'] as $resource) {
            $result = InitialCreateStateNormalizer::withInitial($resource, ['code' => 'TEST'], 'DRAFT');
            $this->assertSame('DRAFT', $result['status']);
            $this->assertTrue(ResourceNativeStateCatalog::supportsLifecycleState($resource, 'DRAFT'));
        }
        foreach (['organization', 'department', 'position', 'employee',
            'product_group', 'product_uom', 'product', 'price_list'] as $resource) {
            $this->assertTrue(BusinessLifecycleTargetAdapter::supports($resource));
        }
    }
}
