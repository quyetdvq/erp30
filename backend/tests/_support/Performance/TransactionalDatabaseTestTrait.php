<?php

declare(strict_types=1);

namespace Tests\Support\Performance;

use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

/**
 * Opt-in database isolation for single-connection, InnoDB-only test classes.
 *
 * Never migrate or refresh schema. Every test runs inside
 * one transaction which is always rolled back during CIUnitTestCase tearDown.
 *
 * NOT suitable for cross-connection visibility, real lock/commit semantics,
 * migrations/DDL, auto-increment-reset assertions or spawned HTTP subprocesses.
 */
trait TransactionalDatabaseTestTrait
{
    use DatabaseTestTrait {
        setUpDatabase as private setUpFrameworkDatabase;
        tearDownDatabase as private tearDownFrameworkDatabase;
    }

    private bool $erpIsolationTransactionStarted = false;

    protected function setUpDatabase(): void
    {
        // Do not let unguarded direct PHPUnit invocations refresh test schemas.
        if (! defined('ERP_NO_DROP_DB_GUARDED') || ERP_NO_DROP_DB_GUARDED !== true) {
            throw new RuntimeException('NO-DROP test requires the least-privilege guarded ERP runner.');
        }

        // Set CIUnitTestCase-owned flags BEFORE DatabaseTestTrait setup.
        // Both migration and migration rollback must remain disabled.
        $this->migrate = false;
        $this->migrateOnce = true;
        $this->refresh = false;
        $this->setUpFrameworkDatabase();

        if ($this->db->transDepth !== 0 || ! $this->db->transBegin()) {
            throw new RuntimeException('Cannot begin an isolated test transaction.');
        }
        $this->erpIsolationTransactionStarted = true;
    }

    protected function tearDownDatabase(): void
    {
        try {
            // The framework clears its own hasInDatabase() cache while our
            // transaction still protects any resulting DELETE statements.
            $this->tearDownFrameworkDatabase();
        } finally {
            if ($this->erpIsolationTransactionStarted) {
                $depth = $this->db->transDepth;
                try {
                    if ($depth <= 0) {
                        throw new RuntimeException('Test committed its isolation transaction.');
                    }
                    // Unwind first even if an inner transaction was left open.
                    while ($this->db->transDepth > 0) {
                        if (! $this->db->transRollback()) {
                            throw new RuntimeException('Rollback of test fixture failed.');
                        }
                    }
                    if ($depth !== 1) {
                        throw new RuntimeException('Unbalanced transaction depth in isolated test.');
                    }
                } finally {
                    $this->erpIsolationTransactionStarted = false;
                    $this->db->resetTransStatus();
                }
            }
        }
    }
}
