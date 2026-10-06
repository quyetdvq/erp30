<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Modules\Product\Product\ProductRoleService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Prd004ProductRoleServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testCanonicalRolesPredicatesCapabilitiesAndDynamicParentTransition(): void
    {
        $role = new ProductRoleService(db: $this->db);
        $uomless = ['product_type' => 'STANDARD', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00'];
        $this->db->table('products')->insert(array_merge($uomless, ['code' => 'ROLE-SIMPLE-004', 'product_name' => 'Simple']));
        $simpleId = (int) $this->db->insertID();
        $this->db->table('products')->insert(array_merge($uomless, ['code' => 'ROLE-LEAF-004', 'product_name' => 'Leaf']));
        $leafId = (int) $this->db->insertID();
        $this->db->table('products')->insert(array_merge($uomless, ['code' => 'ROLE-COMBO-004', 'product_type' => 'COMBO', 'product_name' => 'Combo', 'serial_management_enabled' => 0]));
        $comboId = (int) $this->db->insertID();
        $this->db->table('products')->insert(array_merge($uomless, ['code' => 'ROLE-CHILD-004', 'parent_product_id' => $simpleId, 'product_child_name' => 'Red']));
        $childId = (int) $this->db->insertID();

        $this->assertSame(ProductRoleService::ROLE_PARENT, $role->getRole($simpleId));
        $this->assertTrue($role->isParent($simpleId));
        $this->assertFalse($role->isSimple($simpleId));
        $this->assertSame(ProductRoleService::ROLE_CHILD, $role->getRole($childId));
        $this->assertTrue($role->isChild($childId));
        $this->assertTrue($role->isSimple($comboId) === false);
        $this->assertTrue($role->isCombo($comboId));
        $this->assertFalse($role->isSelectable($simpleId));
        $this->assertTrue($role->isSelectable($leafId));
        $this->assertTrue($role->isSelectable($childId));
        $this->assertTrue($role->isSelectable($comboId));
        $this->assertFalse($role->isLeafTransactionable($simpleId));
        $this->assertTrue($role->isLeafTransactionable($childId));
        $this->assertFalse($role->isLeafTransactionable($comboId));
        $this->assertTrue($role->requiresComboExpansion($comboId));
        $this->assertFalse($role->requiresComboExpansion($childId));
    }

    public function testFullNameUsesCurrentParentAndDoesNotPersistDerivedFields(): void
    {
        $role = new ProductRoleService(db: $this->db);
        $this->db->table('products')->insert(['code' => 'ROLE-NAME-PARENT-004', 'product_type' => 'STANDARD', 'product_name' => 'A', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00']);
        $parentId = (int) $this->db->insertID();
        $this->db->table('products')->insert(['code' => 'ROLE-NAME-CHILD-004', 'product_type' => 'STANDARD', 'parent_product_id' => $parentId, 'product_child_name' => 'Red', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00']);
        $childId = (int) $this->db->insertID();

        $this->assertSame('A - Red', $role->getFullName($childId));
        $childResult = $this->db->table('products')->where('id', $childId)->get();
        $childRow = $childResult === false ? null : $childResult->getRowArray();
        $this->assertNotNull($childRow);
        $this->assertNull($childRow['product_name']);
        $this->assertFalse($this->db->fieldExists('full_name', 'products'));
        $this->db->table('products')->where('id', $parentId)->update(['product_name' => 'B']);
        $this->assertSame('B - Red', $role->getFullName($childId));
        $this->assertSame(1, (int) $this->db->table('products')->where('id', $childId)->where('product_child_name', 'Red')->countAllResults());
        $this->assertSame('B', $role->getFullName($parentId));
    }

    public function testInactiveProductsRetainRoleButAreNotSelectable(): void
    {
        $role = new ProductRoleService(db: $this->db);
        $this->db->table('products')->insert(['code' => 'ROLE-INACTIVE-004', 'product_type' => 'STANDARD', 'product_name' => 'Inactive', 'status' => 'INACTIVE', 'created_at' => '2026-10-06 00:00:00']);
        $id = (int) $this->db->insertID();
        $this->assertSame(ProductRoleService::ROLE_SIMPLE, $role->getRole($id));
        $this->assertTrue($role->isSimple($id));
        $this->assertFalse($role->isSelectable($id));
    }

    public function testCorruptShapesFailClosed(): void
    {
        $role = new ProductRoleService(db: $this->db);
        $this->db->table('products')->insert(['code' => 'ROLE-BAD-TYPE-004', 'product_type' => 'BROKEN', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00']);
        $badType = (int) $this->db->insertID();
        $this->db->table('products')->insert(['code' => 'ROLE-BAD-COMBO-004', 'product_type' => 'COMBO', 'parent_product_id' => $badType, 'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00']);
        $badCombo = (int) $this->db->insertID();
        $this->assertDomainCode(fn () => $role->getRole($badType), ProductRoleService::ERROR_INVALID_ROLE);
        $this->assertDomainCode(fn () => $role->getRole($badCombo), ProductRoleService::ERROR_INVALID_ROLE);
        $this->assertDomainCode(fn () => $role->getFullName(987654321), ProductRoleService::ERROR_NOT_FOUND);
    }

    private function assertDomainCode(callable $callback, string $code): void
    {
        try {
            $callback();
            $this->fail('Expected Product role failure: ' . $code);
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getErrorCode());
        }
    }
}
