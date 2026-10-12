<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Services\InitialCreateStateNormalizer;
use App\Services\ResourceWorkflowStateResolver;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class P1WorkflowCreateInitialStateTest extends CIUnitTestCase
{
    public function testNativeLocationDefaultsActiveWithoutClientStatus(): void
    {
        $result = InitialCreateStateNormalizer::withInitial('ward', [
            'province_id' => '42', 'code' => 'W42', 'name' => 'Sample',
        ], null);
        $this->assertSame('ACTIVE', $result['status']);
        $this->assertSame('42', $result['province_id']);
    }

    public function testLifecycleInitialOverridesMissingFormDefault(): void
    {
        $result = InitialCreateStateNormalizer::withInitial('province', [
            'code' => 'P42', 'name' => 'Sample',
        ], 'INACTIVE');
        $this->assertSame('INACTIVE', $result['status']);
    }

    public function testNativeClientExplicitStatusIsPreserved(): void
    {
        $result = InitialCreateStateNormalizer::withInitial('province', ['status' => 'CLOSED'], null);
        $this->assertSame('CLOSED', $result['status']);
    }

    public function testLifecycleClientHintCannotOverrideInitial(): void
    {
        try {
            InitialCreateStateNormalizer::withInitial('ward', ['status' => 'ACTIVE'], 'INACTIVE');
            $this->fail('A mismatching client State must not override the configured initial.');
        } catch (DomainException $exception) {
            $this->assertSame(ResourceWorkflowStateResolver::ERROR_INITIAL_STATE_INVALID, $exception->getErrorCode());
        }
    }

    public function testAnyValidLifecycleInitialStateIsAcceptedForBusinessResources(): void
    {
        foreach (['province', 'ward', 'organization', 'department', 'position',
            'employee', 'product_group', 'product_uom', 'product', 'price_list',
            'organization_contact', 'employee_contact'] as $resource) {
            $this->assertSame('DRAFT', InitialCreateStateNormalizer::withInitial(
                $resource, ['name' => 'Sample'], 'DRAFT',
            )['status'], $resource);
        }
    }

    public function testApprovedContactDraftInitialIsPreserved(): void
    {
        foreach (['organization_contact', 'employee_contact'] as $type) {
            $payload = InitialCreateStateNormalizer::withInitial($type, ['name' => 'New contact'], 'DRAFT');
            $this->assertSame('DRAFT', $payload['status']);
        }
    }

    public function testNativeDepartmentAndPositionIncludeClosed(): void
    {
        $this->assertSame('CLOSED', InitialCreateStateNormalizer::withInitial('department', ['status' => 'CLOSED'], null)['status']);
        $this->assertSame('CLOSED', InitialCreateStateNormalizer::withInitial('position', ['status' => 'CLOSED'], null)['status']);
    }
}
