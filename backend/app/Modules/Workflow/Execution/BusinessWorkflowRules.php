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
    ];

    public static function table(string $type): string
    {
        if (!isset(self::TABLES[$type]) || ResourceStateCatalog::mode($type) !== ResourceStateCatalog::LIFECYCLE) {
            throw new DomainException('WF_TARGET_ADAPTER_UNAVAILABLE', ['resource'=>$type]);
        }
        return self::TABLES[$type];
    }

    public static function action(string $action): string
    {
        $action = strtolower(trim($action));
        if (preg_match('/^[a-z][a-z0-9_:-]*$/D',$action)!==1 || $action==='view') {
            throw new DomainException('WF_INVALID_TRANSITION',['action_code'=>$action]);
        }
        return $action;
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
