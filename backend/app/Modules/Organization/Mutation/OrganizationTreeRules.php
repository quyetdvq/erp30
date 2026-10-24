<?php

declare(strict_types=1);

namespace App\Modules\Organization\Mutation;

use App\Exceptions\DomainException;

/** Pure hierarchy decisions over a server-loaded, transactionally locked snapshot. */
final class OrganizationTreeRules
{
    private const CHILDREN = [
        'GROUP' => ['COMPANY'],
        'COMPANY' => ['BRANCH','PLANT','WAREHOUSE'],
        'BRANCH' => ['PLANT','WAREHOUSE'],
        'PLANT' => ['WAREHOUSE'],
        'WAREHOUSE' => [],
    ];

    /**
     * @param list<array<string,mixed>> $rows SQL organization snapshot, never client input
     * @param ?string $id null on CREATE
     */
    public static function assertCandidate(array $rows, ?string $id, string $type, ?string $parentId): void
    {
        $byId = [];
        foreach ($rows as $row) {
            $byId[(string)$row['id']] = $row;
        }
        if ($type === 'GROUP') {
            if ($parentId !== null) throw new DomainException('ORG_INVALID_PARENT');
            foreach ($rows as $row) {
                if (($row['type'] ?? null) === 'GROUP' && ($row['parent_id'] ?? null) === null
                    && ($id === null || (string)$row['id'] !== $id)) {
                    throw new DomainException('ORG_SECOND_GROUP_ROOT');
                }
            }
        } else {
            if ($parentId === null || !isset($byId[$parentId])) {
                throw new DomainException('ORG_INVALID_PARENT', ['field'=>'parent_id']);
            }
            if ($id !== null && $parentId === $id) {
                throw new DomainException('ORG_HIERARCHY_CYCLE');
            }
            $parent = $byId[$parentId];
            $visited = [];
            $cursor = $parentId;
            while ($cursor !== null) {
                if (isset($visited[$cursor]) || ($id !== null && $cursor === $id)) {
                    throw new DomainException('ORG_HIERARCHY_CYCLE');
                }
                $visited[$cursor] = true;
                if (!isset($byId[$cursor])) {
                    throw new DomainException('ORG_REFERENCE_INVALID', ['field'=>'parent_id']);
                }
                $next = $byId[$cursor]['parent_id'] ?? null;
                $cursor = $next === null ? null : (string)$next;
            }
            if (!in_array($type, self::CHILDREN[$parent['type'] ?? ''] ?? [], true)) {
                throw new DomainException('ORG_INVALID_PARENT', ['field'=>'parent_id']);
            }
        }
        if ($id !== null) {
            foreach ($rows as $row) {
                if (($row['parent_id'] ?? null) !== null && (string)$row['parent_id'] === $id
                    && !in_array($row['type'] ?? '', self::CHILDREN[$type] ?? [], true)) {
                    throw new DomainException('ORG_INVALID_PARENT', ['field'=>'type']);
                }
            }
        }
    }
}
