<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Supplier\SupplierCatalogProvisioner;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

/** Explicit opt-in; not invoked by migrations or global bootstrap. */
final class SupplierCatalogProvision extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'supplier:catalog-provision';
    protected $description = 'Create Supplier resources, actions and initial Lifecycle without grants.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        if ($params !== ['CONFIRM_SUPPLIER_CATALOG_ONLY']) {
            throw new RuntimeException('SUP_CATALOG_EXPLICIT_CONFIRMATION_REQUIRED');
        }
        (new SupplierCatalogProvisioner(db_connect()))->provision();
        CLI::write('SUP catalog provisioned. ZERO permissions or policies granted; bank endpoints remain disabled.', 'green');
    }
}
