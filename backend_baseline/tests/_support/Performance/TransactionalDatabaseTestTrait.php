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
    private bool $erpScratchNumberingStandalone = false;

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
        $scratchWorker = defined('ERP_SCRATCH_NUMBERING_BOOTSTRAPPED')
            && ERP_SCRATCH_NUMBERING_BOOTSTRAPPED === true;
        if ($scratchWorker) {
            // This check MUST precede the framework trait setup. A cached
            // 'tests' connection can point to erp_test even when a new probe
            // connection in the bootstrap correctly points to scratch.
            \Tests\Support\Scratch\NumberingMethodGate::assertWorker(static::class, $this->name());
            $config = config(\Config\Database::class);
            \Tests\Support\Scratch\ScratchConnectionGuard::assertConfiguration(
                $config, (string) getenv('ERP_SCRATCH_DB_USER'), (string) getenv('ERP_SCRATCH_DB_PASSWORD'),
            );
            \Tests\Support\Scratch\ScratchConnectionGuard::assertSharedConnections();
            if ($this->db !== null) {
                // Do not silently replace a wrong-target connection provided
                // by CIUnitTestCase: it must be investigated and held.
                \Tests\Support\Scratch\ScratchConnectionGuard::assertEffective($this->db);
            }
            // Bind the test's real write connection explicitly: NO shared
            // group cache, not merely a disposable read-only bootstrap probe.
            $this->db = \Config\Database::connect($config->tests, false);
            \Tests\Support\Scratch\ScratchConnectionGuard::assertEffective($this->db);
        }
        $this->setUpFrameworkDatabase();

        // The ONLY approved exception to the outer transaction is one exact,
        // source-pinned Numbering method under the dedicated scratch bootstrap.
        // Never accept a bare environment flag, class-only match, or erp_test.
        if ($scratchWorker) {
            \Tests\Support\Scratch\ScratchConnectionGuard::assertEffective($this->db);
            if ($this->db->transDepth !== 0) {
                throw new RuntimeException('03C-04B STOP: scratch method entered with an open transaction.');
            }
            $this->erpScratchNumberingStandalone = true;
            return;
        }

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
            if ($this->erpScratchNumberingStandalone) {
                $this->erpScratchNumberingStandalone = false;
                if ($this->db->transDepth !== 0) {
                    throw new RuntimeException('03C-04B STOP: scratch method left its transaction open.');
                }
                $this->db->resetTransStatus();
            }
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
