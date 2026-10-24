<?php

declare(strict_types=1);

namespace App\Modules\Organization;

use App\Shared\Read\FlatHierarchy;
use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use UnexpectedValueException;

/**
 * Private organization reads for M2/M3. These methods do NOT authorize actors.
 * Never serialize raw output from this class to an HTTP response before PBAC.
 */
final class OrganizationReadRepository
{
    public function __construct(private readonly ?BaseConnection $connection = null)
    {
    }

    private function db(): BaseConnection
    {
        return $this->connection ?? db_connect();
    }

    /** @return array<string,mixed>|null */
    public function find(int|string $id): ?array
    {
        return $this->db()->table('organizations')
            ->where('id', PositiveId::value($id))->get()->getRowArray();
    }

    /**
     * Explicitly walk parent pointers in the persisted table, as v1 did.
     * Both inactive and dated organizations are included; their effect on
     * authorization belongs to M3, not to this factual read method.
     *
     * @return list<array<string,mixed>> Ordered GROUP/root -> requested node.
     */
    public function ancestorChain(int|string $organizationId): array
    {
        $cursor = PositiveId::value($organizationId);
        $seen = [];
        $chain = [];
        while (true) {
            if (isset($seen[$cursor])) {
                throw new UnexpectedValueException('Organization parent cycle at ' . $cursor);
            }
            $seen[$cursor] = true;
            $row = $this->find($cursor);
            if ($row === null) {
                throw new UnexpectedValueException('Missing organization in ancestry: ' . $cursor);
            }
            $chain[] = $row;
            if (($row['parent_id'] ?? null) === null) {
                return array_reverse($chain);
            }
            $cursor = PositiveId::value((string) $row['parent_id']);
        }
    }

    /** @return list<array<string,mixed>> */
    public function groupRoots(): array
    {
        return array_values($this->db()->table('organizations')
            ->where('type', 'GROUP')->where('parent_id', null)
            ->orderBy('id', 'ASC')->get()->getResultArray());
    }

    /**
     * Full snapshot intended solely for internal M2/M3 hierarchy traversal.
     * For a public list/tree, authorize and paginate BEFORE FlatHierarchy::visibleRows.
     * Do not use the unfiltered snapshot for public responses.
     */
    public function treeSnapshot(): FlatHierarchy
    {
        $rows = $this->db()->table('organizations')
            ->select('id, parent_id, type, code, name, status, active_from, active_to')
            ->orderBy('id', 'ASC')->get()->getResultArray();
        return new FlatHierarchy(array_values($rows));
    }
}
