<?php

declare(strict_types=1);

namespace App\Modules\Workflow\BusinessActions\Infrastructure\Persistence;

use App\Authorization\ContactAuthorizationRegistry;
use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Modules\Workflow\BusinessActions\Application\Ports\BusinessWorkflowRecordPort;
use App\Modules\Workflow\BusinessActions\Contracts\BusinessWorkflowActionErrors as Errors;
use App\Services\ResourceNativeStateCatalog;
use CodeIgniter\Database\ConnectionInterface;

/** Read-only fixed-table adapter; never accepts a table name from the caller. */
final class SqlBusinessWorkflowRecordReader implements BusinessWorkflowRecordPort
{
    /** @var array<string,string> */
    private const TABLES = [
        'province' => 'provinces', 'ward' => 'wards', 'organization' => 'organizations',
        'department' => 'departments', 'position' => 'positions', 'employee' => 'employees',
        'product_group' => 'product_groups', 'product_uom' => 'product_uoms',
        'product' => 'products', 'price_list' => 'price_lists',
        'organization_contact' => 'contacts', 'employee_contact' => 'contacts',
    ];

    /** @param ConnectionInterface<mixed,mixed> $db */
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function assertBusinessResource(string $resource): void
    {
        if (! isset(self::TABLES[$resource]) || ResourceNativeStateCatalog::isIndependent($resource)) {
            throw new DomainException(Errors::ERROR_TARGET_ADAPTER_UNAVAILABLE, ['resource' => $resource]);
        }
    }

    /** @return array<string,mixed> */
    public function record(string $resource, int $id): array
    {
        $this->assertBusinessResource($resource);
        $id = PositiveId::normalize($id, 'target_id', Errors::ERROR_TARGET_NOT_FOUND);
        $result = $this->db->table(self::TABLES[$resource])->where('id', $id)->get();
        if ($result === false) {
            throw new \RuntimeException('Business workflow target query failed.');
        }
        $row = $result->getRowArray();
        if ($row === null || (str_ends_with($resource, '_contact') && ContactAuthorizationRegistry::resourceFor($row) !== $resource)) {
            throw new DomainException(Errors::ERROR_TARGET_NOT_FOUND, ['target_id' => $id]);
        }
        return $row;
    }
}
