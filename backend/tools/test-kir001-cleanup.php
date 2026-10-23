<?php

declare(strict_types=1);

/** Fail-safe cleanup and residue attestation for the dedicated KIR-001 runtime lane. */
try {
    if ($argc !== 1) {
        throw new InvalidArgumentException('Usage: php tools/test-kir001-cleanup.php');
    }

    require dirname(__DIR__) . '/tests/_support/Kir001/guarded_bootstrap.php';
    \Tests\Support\Kir001\Kir001FixtureCleaner::cleanup();
    \Tests\Support\Kir001\Kir001FixtureCleaner::assertNoResidue();

    echo 'KIR-001 CLEANUP PASS: no KIR001 runtime fixture residue remains in erp_test.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'KIR-001 CLEANUP STOP: ' . $exception->getMessage() . PHP_EOL);
    exit(2);
}
