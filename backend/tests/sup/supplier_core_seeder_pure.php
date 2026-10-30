<?php

declare(strict_types=1);

// Exercise the new Seeder's opt-in behavior without loading CI4 or touching a DB.
namespace CodeIgniter\Database {
    class Seeder
    {
        protected mixed $db;

        public function __construct(mixed $db = null)
        {
            $this->db = $db;
        }
    }
}

namespace App\Modules\Supplier {
    final class SupplierCatalogProvisioner
    {
        public static array $connections = [];

        public function __construct(private readonly mixed $db)
        {
        }

        public function provision(): void
        {
            self::$connections[] = $this->db;
        }
    }
}

namespace {
    use App\Database\Seeds\SupplierCoreSeeder;
    use App\Modules\Supplier\SupplierCatalogProvisioner;

    define('ENVIRONMENT', 'development');
    require dirname(__DIR__, 2) . '/app/Database/Seeds/SupplierCoreSeeder.php';

    $passes = 0;
    $check = static function (bool $result, string $label) use (&$passes): void {
        if (!$result) throw new \RuntimeException('SUP SEED FAIL: ' . $label);
        ++$passes;
    };
    $expectDenied = static function (callable $action, string $message) use ($check): void {
        try {
            $action();
        } catch (\RuntimeException $error) {
            $check($error->getMessage() === $message, 'denies with ' . $message);
            return;
        }
        $check(false, 'must deny ' . $message);
    };

    $old = getenv('DINKERP_SUP_CATALOG_SEED_CONFIRM');
    $db = new \stdClass();
    $seeder = new SupplierCoreSeeder($db);
    try {
        putenv('DINKERP_SUP_CATALOG_SEED_CONFIRM');
        $expectDenied(static fn() => $seeder->run(), 'SUP_SEED_REQUIRES_DEV_CLONE_CONFIRMATION');
        putenv('DINKERP_SUP_CATALOG_SEED_CONFIRM=WRONG');
        $expectDenied(static fn() => $seeder->run(), 'SUP_SEED_REQUIRES_DEV_CLONE_CONFIRMATION');
        $check(SupplierCatalogProvisioner::$connections === [], 'denials cause zero DB writes');

        putenv('DINKERP_SUP_CATALOG_SEED_CONFIRM=DEV_CLONE_CATALOG_ONLY');
        $seeder->run();
        $check(count(SupplierCatalogProvisioner::$connections) === 1, 'exactly one delegation');
        $check(SupplierCatalogProvisioner::$connections[0] === $db, 'seeder passes active connection');
        $seeder->run();
        $check(count(SupplierCatalogProvisioner::$connections) === 2, 'repeat delegates to idempotent provisioner');

        $root = dirname(__DIR__, 2);
        $seed = file_get_contents($root . '/app/Database/Seeds/SupplierCoreSeeder.php');
        $bootstrap = file_get_contents($root . '/app/Database/Seeds/BackendBootstrapSeeder.php');
        $provisioner = file_get_contents($root . '/app/Modules/Supplier/SupplierCatalogProvisioner.php');
        $migration = file_get_contents($root . '/app/Database/Migrations/2026-10-30-000028_CreateSupplierCoreTables.php');
        $check(is_string($seed) && str_contains($seed, "ENVIRONMENT !== 'development'"), 'production and testing environment rejected');
        $check(is_string($bootstrap) && !str_contains($bootstrap, 'SupplierCoreSeeder'), 'never added to global bootstrap');
        $check(is_string($seed) && !str_contains($seed, '->up(') && !str_contains($seed, '->query(') && !str_contains($seed, '->table('), 'no DDL or direct table writes');
        $check(is_string($provisioner) && str_contains($provisioner, 'transBegin()') && str_contains($provisioner, 'transRollback()'), 'reuses atomic catalog provisioning');
        $check(is_string($provisioner) && str_contains($provisioner, 'PREEXISTING_DIRECT_GRANT'), 'Shield stale direct grant guard remains');
        $check(is_string($migration) && str_contains($migration, "'bank_ciphertext'"), 'bank metadata still requires separate PC-03 schema review');
        $check(is_string($seed) && !str_contains($seed, 'group_permissions') && !str_contains($seed, 'policy_actions'), 'no grants in seed');
    } finally {
        if ($old === false) {
            putenv('DINKERP_SUP_CATALOG_SEED_CONFIRM');
        } else {
            putenv('DINKERP_SUP_CATALOG_SEED_CONFIRM=' . $old);
        }
    }

    echo "SUP-T07 DEV CATALOG SEED: {$passes}/{$passes} PASS (fake provisioner, no MariaDB)\n";
}
