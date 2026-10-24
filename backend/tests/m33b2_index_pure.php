<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Modules/Access/Mutation/PolicyScopeUniqueIndex.php';

use App\Modules\Access\Mutation\PolicyScopeUniqueIndex as Audit;

$assertions = 0;
$check = static function (bool $condition, string $description) use (&$assertions): void {
    if (! $condition) {
        throw new RuntimeException('FAIL: ' . $description);
    }
    $assertions++;
    echo 'PASS: ' . $description . PHP_EOL;
};
$reject = static function (array $rows, string $description) use ($check): void {
    try {
        Audit::assertValid($rows);
    } catch (UnexpectedValueException $e) {
        $check(str_contains($e->getMessage(), 'Do not enable Policy mutations'), $description);
        return;
    }
    throw new RuntimeException('FAIL: ' . $description . ' unexpectedly passed');
};

$check(str_contains(Audit::SQL, 'information_schema.STATISTICS'), 'metadata uses information_schema.STATISTICS');
$check(str_contains(Audit::SQL, 'TABLE_SCHEMA = DATABASE()'), 'metadata constrained to current database');
$check(str_contains(Audit::SQL, 'TABLE_NAME = ?') && str_contains(Audit::SQL, 'INDEX_NAME = ?'),
    'table name and index name use SQL bound parameters');
$check(str_contains(Audit::SQL, 'ORDER BY SEQ_IN_INDEX ASC'), 'index column sequence requested');

$columns = [
    'organization_id', 'resource_id', 'normalized_state_id',
    'participant_type', 'participant_ref_id',
];
$valid = [];
foreach ($columns as $position => $column) {
    $valid[] = [
        'seq_in_index' => (string) ($position + 1),
        'column_name' => $column,
        'non_unique' => '0',
        'sub_part' => null,
    ];
}
Audit::assertValid($valid);
$check(true, 'baseline five-column UNIQUE index accepted');
$reject([], 'missing index rejected');
$reject(array_slice($valid, 0, 4), 'incomplete unique index rejected');
$mutated = $valid;
$mutated[1]['column_name'] = 'participant_type';
$reject($mutated, 'wrong index column rejected');
$mutated = $valid;
$mutated[2]['seq_in_index'] = '2';
$reject($mutated, 'duplicate/wrong column sequence rejected');
$mutated = $valid;
$mutated[0]['non_unique'] = '1';
$reject($mutated, 'non-UNIQUE index rejected');
$mutated = $valid;
$mutated[3]['sub_part'] = '5';
$reject($mutated, 'prefix-only UNIQUE index rejected');
$mutated = $valid;
$mutated[] = ['seq_in_index' => '6', 'column_name' => 'extra', 'non_unique' => '0', 'sub_part' => null];
$reject($mutated, 'extra index column rejected');
$command = file_get_contents(__DIR__ . '/../app/Commands/M33B2Verify.php');
$check(is_string($command) && str_contains($command, 'PolicyScopeUniqueIndex::assertValid('),
    'runtime command invokes strict index validation');
$check(is_string($command) && ! str_contains($command, 'SHOW INDEX FROM'),
    'runtime command no longer executes SHOW INDEX');

echo 'TOTAL: ' . $assertions . ' M3.3B2 index audit assertions passed.' . PHP_EOL;
