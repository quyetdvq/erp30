<?php

declare(strict_types=1);

use App\Repositories\ResourceCatalogRepository;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Database\ResultInterface;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ResourceCatalogRepositoryBulkActionsTest extends CIUnitTestCase
{
    public function testBulkActionLookupExecutesOneQueryForAllResourceIdsInDeterministicOrder(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->once())
            ->method('whereIn')
            ->with('resource_id', [3, 7, 11])
            ->willReturnSelf();
        $orderBys = [];
        $builder->method('orderBy')->willReturnCallback(function (string $field, string $direction) use (&$orderBys, $builder): BaseBuilder {
            $orderBys[] = [$field, $direction];

            return $builder;
        });

        $result = $this->createMock(ResultInterface::class);
        $result->expects($this->once())->method('getResultArray')->willReturn([
            ['id' => '30', 'resource_id' => '3', 'code' => 'edit', 'description' => 'Edit'],
            ['id' => '31', 'resource_id' => '3', 'code' => 'view', 'description' => null],
            ['id' => '70', 'resource_id' => '7', 'code' => 'edit', 'description' => 'Edit'],
        ]);
        $builder->expects($this->once())->method('get')->willReturn($result);

        $db = $this->createMock(ConnectionInterface::class);
        $db->expects($this->once())->method('table')->with('resource_actions')->willReturn($builder);

        $rows = (new ResourceCatalogRepository($db))->findResourceActionsForResources([3, 7, 11]);

        $this->assertSame([['resource_id', 'ASC'], ['code', 'ASC'], ['id', 'ASC']], $orderBys);
        $this->assertSame([
            ['id' => '30', 'resource_id' => '3', 'code' => 'edit', 'description' => 'Edit'],
            ['id' => '31', 'resource_id' => '3', 'code' => 'view', 'description' => null],
            ['id' => '70', 'resource_id' => '7', 'code' => 'edit', 'description' => 'Edit'],
        ], $rows);
    }
}
