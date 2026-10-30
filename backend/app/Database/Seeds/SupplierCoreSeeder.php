<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Modules\Supplier\SupplierCatalogProvisioner;
use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Explicit DEV-clone catalog seed. Reuses the reviewed SupplierCatalogProvisioner;
 * does not run migrations, grant PBAC rights or create Supplier business rows.
 * Never add this seeder to the global BackendBootstrapSeeder.
 */
final class SupplierCoreSeeder extends Seeder
{
    public function run(): void
    {
        if (!defined('ENVIRONMENT') || ENVIRONMENT !== 'development') {
            throw new RuntimeException('SUP_SEED_REQUIRES_DEVELOPMENT_ENVIRONMENT');
        }
        // This is a human acknowledgement, NOT proof of actual DB isolation.
        // The operator must separately verify the live DB identity and backup.
        if (getenv('DINKERP_SUP_CATALOG_SEED_CONFIRM') !== 'DEV_CLONE_CATALOG_ONLY') {
            throw new RuntimeException('SUP_SEED_REQUIRES_DEV_CLONE_CONFIRMATION');
        }

        (new SupplierCatalogProvisioner($this->db))->provision();
    }
}
