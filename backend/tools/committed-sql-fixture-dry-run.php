<?php

declare(strict_types=1);

/**
 * TST-PERF-003E-03B, OFFLINE ONLY. No CI4 bootstrap, no db_connect, no SQL,
 * no fixtures created/deleted. Neither mode authorizes a committed-SQL test.
 *
 * php tools/committed-sql-fixture-dry-run.php --metadata=<03A JSON> --inspect
 * php tools/committed-sql-fixture-dry-run.php --metadata=<03A JSON> --ledger=<hypothetical JSON>
 */

require_once __DIR__ . '/_support/CommittedSqlDryRunPlanner.php';

try {
    $metadataFile = null;
    $ledgerFile = null;
    $inspect = false;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--inspect' && ! $inspect) {
            $inspect = true;
        } elseif (str_starts_with($arg, '--metadata=') && $metadataFile === null) {
            $metadataFile = substr($arg, strlen('--metadata='));
        } elseif (str_starts_with($arg, '--ledger=') && $ledgerFile === null) {
            $ledgerFile = substr($arg, strlen('--ledger='));
        } else {
            throw new InvalidArgumentException('03B STOP: unexpected/duplicate CLI argument.');
        }
    }
    if (! is_string($metadataFile) || $metadataFile === '' || $inspect === ($ledgerFile !== null)) {
        throw new InvalidArgumentException('03B usage: --metadata=path (--inspect OR --ledger=path), never both.');
    }
    $metadata = CommittedSqlDryRunPlanner::readJsonFile($metadataFile);
    $result = $inspect
        ? CommittedSqlDryRunPlanner::inspect($metadata)
        : CommittedSqlDryRunPlanner::plan($metadata, CommittedSqlDryRunPlanner::readJsonFile($ledgerFile));
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '003E-03B DRY-RUN STOP: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}
