<?php

declare(strict_types=1);

/**
 * Pure negative/self-tests for the non-executable 003E-03B planner.
 * Expects the audit-only JSON export from 03A; DOES NOT bootstrap CI4/DB.
 * php tools/test-committed-sql-fixture-dry-run.php --metadata=path
 */

require_once __DIR__ . '/_support/CommittedSqlDryRunPlanner.php';

$ok = 0;
$negative = 0;
function pass(string $label, bool $condition): void
{
    global $ok;
    if (! $condition) {
        throw new RuntimeException('003E-03B SELF-TEST FAILED: ' . $label);
    }
    $ok++;
}
function reject(string $label, callable $callback): void
{
    global $negative;
    try {
        $callback();
    } catch (RuntimeException $e) {
        $negative++;
        return;
    }
    throw new RuntimeException('003E-03B FAIL-OPEN: ' . $label);
}

try {
    if ($argc !== 2 || ! str_starts_with($argv[1], '--metadata=')) {
        throw new InvalidArgumentException('Usage: php tools/test-committed-sql-fixture-dry-run.php --metadata=path');
    }
    $metadataFile = substr($argv[1], strlen('--metadata='));
    $metadata = CommittedSqlDryRunPlanner::readJsonFile($metadataFile);
    $summary = CommittedSqlDryRunPlanner::inspect($metadata);
    pass('41 InnoDB tables with PK', $summary['table_count'] === 41 && $summary['primary_key_tables'] === 41);
    pass('50 FK constraints, 46 RESTRICT and 4 CASCADE',
        $summary['foreign_key_constraints'] === 50
        && $summary['delete_rules'] === ['RESTRICT' => 46, 'CASCADE' => 4]);
    pass('five self-FK constraints', count($summary['self_references']) === 5);
    pass('forty auto-increment tables', count($summary['auto_increment_tables']) === 40);
    pass('group_permissions composite PK',
        ($summary['composite_primary_keys']['dinktech_group_permissions'] ?? null)
        === ['group_id', 'resource_action_id']);
    pass('never grants permission', $summary['permitted_to_execute_cleanup'] === false && $summary['sql_executed'] === 0);

    $ledger = CommittedSqlDryRunPlanner::readJsonFile(__DIR__ . '/fixtures/committed-sql-ledger-EXAMPLE-ONLY.json');
    $plan = CommittedSqlDryRunPlanner::plan($metadata, $ledger);
    pass('three sample claims, all hypothetical', $plan['claimed_row_count'] === 3
        && $plan['permitted_to_execute_cleanup'] === false && $plan['sql_executed'] === 0);
    pass('FK-safe table ordering, NOT a DELETE plan',
        $plan['candidate_child_before_parent_table_order'] === [
            'dinktech_auto_numbering_counters',
            'dinktech_auto_numbering_series',
            'dinktech_resources',
        ]);
    pass('live-reference proof and identity warning stay open',
        count($plan['missing_live_proofs']) > 1
        && count($plan['identity_counter_warnings']) === 3);
    pass('never generates SQL', ! array_key_exists('delete_sql', $plan)
        && ! array_key_exists('sql', $plan)
        && $plan['status'] === 'DRY_RUN_CLAIMS_NOT_VERIFIED');

    $bad = $metadata;
    $bad['target'] = '127.0.0.1:3306/erp';
    reject('production target', static fn () => CommittedSqlDryRunPlanner::inspect($bad));
    $bad = $metadata;
    $bad['effective_account'] = 'root@localhost';
    reject('privileged account', static fn () => CommittedSqlDryRunPlanner::inspect($bad));
    $bad = $metadata;
    $bad['schema_sha256'] = str_repeat('0', 64);
    reject('schema drift', static fn () => CommittedSqlDryRunPlanner::inspect($bad));
    $bad = $metadata;
    $bad['data_sha256'] = str_repeat('0', 64);
    reject('data drift', static fn () => CommittedSqlDryRunPlanner::inspect($bad));
    $bad = $metadata;
    $bad['tables'][0]['ENGINE'] = 'MyISAM';
    reject('nontransactional engine', static fn () => CommittedSqlDryRunPlanner::inspect($bad));
    $bad = $metadata;
    array_pop($bad['primary_keys']);
    reject('missing primary key', static fn () => CommittedSqlDryRunPlanner::inspect($bad));
    $bad = $metadata;
    array_pop($bad['foreign_key_rules']);
    reject('orphan FK metadata', static fn () => CommittedSqlDryRunPlanner::inspect($bad));
    $bad = $metadata;
    $bad['tables'][] = $bad['tables'][0];
    reject('duplicate table', static fn () => CommittedSqlDryRunPlanner::inspect($bad));
    $bad = $metadata;
    array_pop($bad['foreign_keys']);
    array_pop($bad['foreign_key_rules']);
    reject('simultaneously omitted FK edge and rule', static fn () => CommittedSqlDryRunPlanner::inspect($bad));
    $bad = $metadata;
    $bad['primary_keys'][] = $bad['primary_keys'][0];
    reject('duplicate primary-key ordinal', static fn () => CommittedSqlDryRunPlanner::inspect($bad));
    $bad = $metadata;
    $bad['foreign_key_rules'][0]['DELETE_RULE'] = 'CASCADE';
    reject('unexpected cascade rule', static fn () => CommittedSqlDryRunPlanner::inspect($bad));
    $bad = $ledger;
    $bad['intent'] = 'EXECUTE';
    reject('execution intent', static fn () => CommittedSqlDryRunPlanner::plan($metadata, $bad));
    $bad = $ledger;
    $bad['target'] = 'localhost:3306/erp';
    reject('wrong ledger target', static fn () => CommittedSqlDryRunPlanner::plan($metadata, $bad));
    $bad = $ledger;
    $bad['rows'][0]['table'] = 'dinktech_unknown';
    reject('unknown table', static fn () => CommittedSqlDryRunPlanner::plan($metadata, $bad));
    $bad = $ledger;
    $bad['rows'][0]['owner_token'] = 'TST03B-OTHER-RUN';
    reject('mismatched ownership token', static fn () => CommittedSqlDryRunPlanner::plan($metadata, $bad));
    $bad = $ledger;
    $bad['rows'][0]['ownership_claim'] = 'PRE_EXISTING_BASELINE';
    reject('baseline row claim', static fn () => CommittedSqlDryRunPlanner::plan($metadata, $bad));
    $bad = $ledger;
    $bad['rows'][0]['pk'] = ['wrong_id' => '1'];
    reject('missing exact PK column', static fn () => CommittedSqlDryRunPlanner::plan($metadata, $bad));
    $bad = $ledger;
    $bad['rows'][] = $bad['rows'][0];
    reject('duplicate exact PK tuple', static fn () => CommittedSqlDryRunPlanner::plan($metadata, $bad));
    $bad = $ledger;
    $bad['rows'][0]['pk']['id'] = "1'; DROP TABLE x; --";
    reject('SQL metacharacters in PK', static fn () => CommittedSqlDryRunPlanner::plan($metadata, $bad));
    $bad = $ledger;
    $bad['rows'][0]['unreviewed'] = true;
    reject('unexpected ledger field', static fn () => CommittedSqlDryRunPlanner::plan($metadata, $bad));
    $bad = $ledger;
    $bad['rows'] = [];
    reject('empty claims', static fn () => CommittedSqlDryRunPlanner::plan($metadata, $bad));

    $bad = $ledger;
    $bad['rows'] = [[
        'table' => 'dinktech_group_permissions',
        'pk' => ['group_id' => 'NOT_A_REAL_GROUP', 'resource_action_id' => 'NOT_A_REAL_ACTION'],
        'owner_token' => $ledger['run_token'],
        'ownership_claim' => 'CREATED_FOR_THIS_RUN_UNVERIFIED',
    ]];
    $composite = CommittedSqlDryRunPlanner::plan($metadata, $bad);
    pass('composite PK accepted only as unverified',
        $composite['claimed_row_count'] === 1 && ! $composite['permitted_to_execute_cleanup']);
    unset($bad['rows'][0]['pk']['resource_action_id']);
    reject('incomplete composite key', static fn () => CommittedSqlDryRunPlanner::plan($metadata, $bad));

    $bad = $ledger;
    $bad['rows'] = [[
        'table' => 'dinktech_users', 'pk' => ['id' => 'NOT_REAL_USER'],
        'owner_token' => $ledger['run_token'],
        'ownership_claim' => 'CREATED_FOR_THIS_RUN_UNVERIFIED',
    ]];
    $users = CommittedSqlDryRunPlanner::plan($metadata, $bad);
    pass('users CASCADE exposure blocked', count($users['structural_blockers']) === 4
        && ! $users['permitted_to_execute_cleanup']);

    $bad['rows'][0]['table'] = 'dinktech_organizations';
    $org = CommittedSqlDryRunPlanner::plan($metadata, $bad);
    pass('organization self-FK blocked', in_array(
        'SELF_REFERENTIAL_FK_REQUIRES_ROW_GRAPH:dinktech_organizations',
        $org['structural_blockers'], true,
    ));

    $bad = $ledger;
    $bad['rows'][0]['pk']['id'] = ['array_not_a_pk'];
    reject('non-scalar PK', static fn () => CommittedSqlDryRunPlanner::plan($metadata, $bad));

    echo '003E-03B OFFLINE DRY-RUN SELF-TEST PASS: ' . $ok . ' positive checks, '
        . $negative . ' fail-closed negative checks; 0 DB connections; 0 SQL.' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '003E-03B SELF-TEST STOP: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}
