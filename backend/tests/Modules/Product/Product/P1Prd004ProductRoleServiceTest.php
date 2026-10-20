<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Modules\Product\Product\ProductRoleService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;

/** @internal */
final class P1Prd004ProductRoleServiceTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

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

    public function testDescribeListMatchesCanonicalSingleProductFields(): void
    {
        $role = new ProductRoleService(db: $this->db);
        $base = ['product_type' => 'STANDARD', 'status' => 'ACTIVE', 'created_at' => '2026-10-06 00:00:00'];
        $this->db->table('products')->insert(array_merge($base, ['code' => 'ROLE-LIST-PARENT', 'product_name' => 'T-Shirt']));
        $parentId = (int) $this->db->insertID();
        $this->db->table('products')->insert(array_merge($base, ['code' => 'ROLE-LIST-CHILD', 'parent_product_id' => $parentId, 'product_child_name' => 'Red']));
        $this->db->table('products')->insert(array_merge($base, ['code' => 'ROLE-LIST-SIMPLE', 'product_name' => 'Simple']));
        $this->db->table('products')->insert(array_merge($base, ['code' => 'ROLE-LIST-INACTIVE', 'status' => 'INACTIVE', 'product_name' => 'Inactive']));
        $this->db->table('products')->insert(array_merge($base, ['code' => 'ROLE-LIST-COMBO', 'product_type' => 'COMBO', 'product_name' => 'Combo']));

        $codes = ['ROLE-LIST-PARENT', 'ROLE-LIST-CHILD', 'ROLE-LIST-SIMPLE', 'ROLE-LIST-INACTIVE', 'ROLE-LIST-COMBO'];
        $query = $this->db->table('products')->whereIn('code', $codes)->orderBy('id', 'ASC')->get();
        $this->assertNotFalse($query);
        $rows = array_values($query->getResultArray());
        $this->assertCount(5, $rows);

        $described = $role->describeList($rows);
        $this->assertCount(5, $described);
        foreach ($rows as $index => $row) {
            $this->assertSame($role->getRole($row), $described[$index]['role']);
            $this->assertSame($role->getFullName($row), $described[$index]['full_name']);
            $this->assertSame($role->isSelectable($row), $described[$index]['selectable']);
            $this->assertSame($role->isLeafTransactionable($row), $described[$index]['leaf_transactionable']);
            $this->assertSame($row['code'], $described[$index]['code']);
        }
    }

    public function testDescribeListRejectsIncompleteAndInvalidProductGraphs(): void
    {
        $role = new ProductRoleService(db: $this->db);
        $child = [
            'id' => 90002, 'parent_product_id' => 90001, 'product_type' => 'STANDARD',
            'product_child_name' => 'Red', 'status' => 'ACTIVE',
        ];
        $this->assertDomainCode(fn () => $role->describeList([$child]), ProductRoleService::ERROR_INVALID_ROLE);

        $combo = ['id' => 90003, 'product_type' => 'COMBO', 'product_name' => 'Combo', 'status' => 'ACTIVE'];
        $this->assertDomainCode(
            fn () => $role->describeList([$combo, ['id' => 90004, 'parent_product_id' => 90003, 'product_type' => 'STANDARD', 'product_child_name' => 'Child']]),
            ProductRoleService::ERROR_INVALID_ROLE,
        );
    }

    public function testDescribeListRequiresNoDatabaseQueryForCompleteSnapshot(): void
    {
        // No constructor => no initialized connection. Any repository access
        // from describeList would fail this regression test immediately.
        $role = (new \ReflectionClass(ProductRoleService::class))->newInstanceWithoutConstructor();
        $rows = [
            ['id' => 50002, 'parent_product_id' => 50001, 'product_type' => 'STANDARD', 'product_child_name' => 'Blue', 'status' => 'ACTIVE'],
            ['id' => 50001, 'parent_product_id' => null, 'product_type' => 'STANDARD', 'product_name' => 'Jacket', 'status' => 'ACTIVE'],
        ];
        $result = $role->describeList($rows);
        $this->assertSame(ProductRoleService::ROLE_CHILD, $result[0]['role']);
        $this->assertSame('Jacket - Blue', $result[0]['full_name']);
        $this->assertTrue($result[0]['selectable']);
        $this->assertSame(ProductRoleService::ROLE_PARENT, $result[1]['role']);
        $this->assertFalse($result[1]['selectable']);
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
