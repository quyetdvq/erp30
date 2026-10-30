<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Modules\Access\Query\AuthorizationSqlScopeCompiler;
use App\Modules\Access\UnifiedAccessService;
use App\Modules\Customer\CustomerScopeProvider;
use App\Modules\Customer\CustomerSql;
use App\Modules\History\Ports\HistoryParentViewPort;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use UnexpectedValueException;

/** App composition adapter: Customer owns its canonical VIEW predicates. */
final class CustomerHistoryVisibility implements HistoryParentViewPort
{
    public function assertVisible(string $resource, string $id, UnifiedAccessService $access,
        BaseConnection $db): void
    {
        if ($resource !== 'customer' || !$access->hasBaseViewPermission('customer')) {
            throw new PageNotFoundException('Customer history unavailable.');
        }
        $scope = (new AuthorizationSqlScopeCompiler($access))
            ->compile(new CustomerScopeProvider());
        $plan = CustomerSql::detail($id, $scope, (string) $db->DBPrefix);
        $result = $db->query($plan['sql'], $plan['binds']);
        if ($result === false) {
            throw new UnexpectedValueException('HISTORY_PARENT_SQL_FAILED');
        }
        $parent = $result->getRowArray();
        if ($parent === null) {
            throw new PageNotFoundException('Customer history unavailable.');
        }
        if (!$access->canView('customer', $parent)) {
            throw new UnexpectedValueException('HISTORY_PARENT_SQL_PBAC_MISMATCH');
        }
    }
}
