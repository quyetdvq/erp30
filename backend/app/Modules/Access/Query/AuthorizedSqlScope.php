<?php

declare(strict_types=1);

namespace App\Modules\Access\Query;

use InvalidArgumentException;

/**
 * A proven, request-local VIEW scope. Data values are always bound; identifiers
 * are accepted only from reviewed repositories. The compiler cannot express
 * arbitrary client predicates and never falls back to an unscoped query.
 */
final class AuthorizedSqlScope
{
    /** @param list<string> $states @param list<array{organization_id:string,status:string}> $slots */
    private function __construct(
        public readonly string $resource,
        public readonly string $domain,
        private readonly array $states,
        private readonly array $slots,
    ) {}

    /** @param list<string> $states canonical states decided by UnifiedAccessService */
    public static function master(string $resource, array $states): self
    {
        if (!in_array($resource, ['product', 'product_group', 'product_uom', 'employee', 'department', 'position', 'customer', 'customer_contact'], true)) {
            throw new InvalidArgumentException('Unreviewed MASTER scope provider.');
        }
        return new self($resource, 'MASTER', self::canonicalStates($states), []);
    }

    /**
     * SQL predicate support for a reviewed organization-scoped provider.
     * The supplied slots MUST come from trusted organization metadata and
     * canonical PBAC decisions, never from client input or the business table.
     * @param list<array{organization_id:string,status:string}> $slots
     */
    public static function organization(string $resource, array $slots): self
    {
        if (!in_array($resource, ['price_list', 'organization', 'employee_assignment'], true)
            && !in_array($resource, ['organization_contact', 'employee_contact'], true)) {
            throw new InvalidArgumentException('Unreviewed ORGANIZATION scope provider.');
        }
        $dedup = [];
        foreach ($slots as $slot) {
            $id = $slot['organization_id'] ?? null;
            $state = $slot['status'] ?? null;
            if (!is_string($id) || preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1
                || !is_string($state)) {
                throw new InvalidArgumentException('Invalid organization scope slot.');
            }
            $state = self::canonicalStates([$state])[0];
            $dedup[$id . ':' . $state] = ['organization_id'=>$id, 'status'=>$state];
        }
        return new self($resource, 'ORGANIZATION', [], array_values($dedup));
    }

    /**
     * @return array{sql:string,binds:list<string>}
     */
    public function predicate(string $alias): array
    {
        // This is an identifier, not a SQL value. No alias supplied by HTTP.
        if (preg_match('/\A[a-z][a-z0-9_]*\z/D', $alias) !== 1) {
            throw new InvalidArgumentException('Invalid SQL scope table alias.');
        }
        $column = 'UPPER(TRIM(' . $alias . '.status)) COLLATE utf8mb4_bin';
        if ($this->domain === 'MASTER') {
            if ($this->states === []) return ['sql'=>'1 = 0', 'binds'=>[]];
            return ['sql'=>$column . ' IN (' . implode(',', array_fill(0, count($this->states), '?')) . ')',
                'binds'=>$this->states];
        }
        if ($this->slots === []) return ['sql'=>'1 = 0', 'binds'=>[]];
        $parts = [];
        $binds = [];
        foreach ($this->slots as $slot) {
            // Organization's own primary key is its PBAC scope; assignments and pricing use organization_id.
            $idColumn = $this->resource === 'organization' ? 'id' : 'organization_id';
            $parts[] = '(' . $alias . '.' . $idColumn . ' = ? AND ' . $column . ' = ?)';
            $binds[] = $slot['organization_id'];
            $binds[] = $slot['status'];
        }
        return ['sql'=>'(' . implode(' OR ', $parts) . ')', 'binds'=>$binds];
    }

    /**
     * Contact owner scope uses a persisted owner/primary-assignment organization
     * and the CONTACT Lifecycle State, never an organization row's State.
     * Table aliases are fixed by ContactSql, never supplied by HTTP.
     * @return array{sql:string,binds:list<string>}
     */
    public function contactOwnerPredicate(string $ownerAlias, string $contactAlias): array
    {
        if ($this->domain !== 'ORGANIZATION'
            || !in_array($this->resource, ['organization_contact','employee_contact'], true)) {
            throw new InvalidArgumentException('Contact owner scope requires an approved Contact resource.');
        }
        foreach ([$ownerAlias, $contactAlias] as $alias) {
            if (preg_match('/\A[a-z][a-z0-9_]*\z/D', $alias) !== 1) {
                throw new InvalidArgumentException('Unsafe Contact SQL alias.');
            }
        }
        if ($this->slots === []) return ['sql'=>'1 = 0', 'binds'=>[]];
        $idColumn = $this->resource === 'organization_contact' ? 'id' : 'organization_id';
        $state = 'UPPER(TRIM(' . $contactAlias . '.status)) COLLATE utf8mb4_bin';
        $parts = [];
        $binds = [];
        foreach ($this->slots as $slot) {
            $parts[] = '(' . $ownerAlias . '.' . $idColumn . ' = ? AND ' . $state . ' = ?)';
            $binds[] = $slot['organization_id'];
            $binds[] = $slot['status'];
        }
        return ['sql'=>'(' . implode(' OR ', $parts) . ')', 'binds'=>$binds];
    }

    /** @param list<string> $states @return list<string> */
    private static function canonicalStates(array $states): array
    {
        $set = [];
        foreach ($states as $state) {
            if (!is_string($state) || preg_match('/\A[A-Z][A-Z0-9_:-]{0,49}\z/D', $state) !== 1) {
                throw new InvalidArgumentException('Invalid canonical PBAC Lifecycle State.');
            }
            $set[$state] = true;
        }
        return array_keys($set);
    }
}
