<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Execution;

use App\Exceptions\DomainException;
use App\Modules\Access\ResourceStateCatalog;

/** Physical SQL names are fixed by reviewed adapters; never route-provided. */
final class BusinessWorkflowRules
{
    private const TABLES = [
        'province'=>'provinces', 'ward'=>'wards', 'organization'=>'organizations',
        'department'=>'departments', 'position'=>'positions', 'employee'=>'employees',
        'product_group'=>'product_groups', 'product_uom'=>'product_uoms',
        'product'=>'products', 'price_list'=>'price_lists',
        'organization_contact'=>'contacts', 'employee_contact'=>'contacts',
        'customer'=>'customers', 'customer_contact'=>'contacts', 'supplier_contact'=>'contacts',
    ];

    /**
     * State definitions need only a Resource + Lifecycle binding, not a business
     * row adapter. Supplier is metadata-only here: options/execute must still
     * fail closed because Supplier writes require optimistic CAS.
     */
    public static function assertStateCatalogReadable(string $type): void
    {
        if ($type === 'supplier') return;
        self::table($type);
    }

    public static function table(string $type): string
    {
        if (!isset(self::TABLES[$type]) || ResourceStateCatalog::mode($type) !== ResourceStateCatalog::LIFECYCLE) {
            throw new DomainException('WF_TARGET_ADAPTER_UNAVAILABLE', ['resource'=>$type]);
        }
        return self::TABLES[$type];
    }

    /** Reject a contact ID belonging to a different polymorphic owner. */
    public static function matchesTargetOwner(string $type, array $row): bool
    {
        $required = match ($type) {
            'organization_contact' => 'ORGANIZATION',
            'employee_contact' => 'EMPLOYEE',
            'customer_contact' => 'CUSTOMER',
            'supplier_contact' => 'SUPPLIER',
            default => null,
        };
        return $required === null || strtoupper((string) ($row['contact_type'] ?? '')) === $required;
    }

    public static function action(string $action): string
    {
        $action = strtolower(trim($action));
        if (preg_match('/^[a-z][a-z0-9_:-]*$/D',$action)!==1 || $action==='view') {
            throw new DomainException('WF_INVALID_TRANSITION',['action_code'=>$action]);
        }
        return $action;
    }

    /**
     * Reject ambiguous configured actions rather than taking the first SQL row.
     * Never accept a destination State supplied by the client.
     * @param list<array{action_code:string,destination:string}> $transitions
     * @return array{action_code:string,destination:string}
     */
    public static function transitionForAction(array $transitions, string $action): array
    {
        $action = self::action($action);
        $found = null;
        foreach ($transitions as $transition) {
            if ($transition['action_code'] !== $action) continue;
            if ($found !== null) throw new DomainException('WF_INVALID_TRANSITION', ['action_code'=>$action]);
            $found = $transition;
        }
        if ($found === null) throw new DomainException('WF_INVALID_TRANSITION', ['action_code'=>$action]);
        return $found;
    }

    public static function id(string $id): string
    {
        if (preg_match('/^[1-9][0-9]*$/D',$id)!==1 || strlen($id)>19 ||
            (strlen($id)===19 && strcmp($id,'9223372036854775807')>0)) {
            throw new DomainException('WF_TARGET_NOT_FOUND');
        }
        return $id;
    }
}
