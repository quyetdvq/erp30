<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Modules\Access\Query\AuthorizationSqlScopeCompiler;
use App\Modules\Access\UnifiedAccessService;
use App\Modules\History\Ports\HistoryParentViewPort;
use App\Modules\Supplier\SupplierScopeProvider;
use App\Modules\Supplier\SupplierSql;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use UnexpectedValueException;

/** A History read may see only the Supplier that canonical SQL/PBAC permits. */
final class SupplierHistoryVisibility implements HistoryParentViewPort
{
    public function assertVisible(string $resource, string $id, UnifiedAccessService $access,
        BaseConnection $db): void
    {
        if ($resource !== 'supplier' || !$access->hasBaseViewPermission('supplier')) {
            throw new PageNotFoundException('Supplier history unavailable.');
        }
        $group = $access->trustedGroupRootId(); // Never accept Company/Group from the request.
        $scope = (new AuthorizationSqlScopeCompiler($access))
            ->compile(new SupplierScopeProvider());
        $plan = SupplierSql::detail($id, $scope, $group, (string) $db->DBPrefix);
        $result = $db->query($plan['sql'], $plan['binds']);
        if ($result === false) {
            throw new UnexpectedValueException('HISTORY_PARENT_SQL_FAILED');
        }
        $parent = $result->getRowArray();
        if ($parent === null) {
            throw new PageNotFoundException('Supplier history unavailable.');
        }
        if (!$access->canView('supplier', $parent)) {
            throw new UnexpectedValueException('HISTORY_PARENT_SQL_PBAC_MISMATCH');
        }
    }
}
