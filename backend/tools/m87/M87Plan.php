<?php

declare(strict_types=1);

/**
 * M8.7 audit-only plan. Does not depend on Composer, CodeIgniter, HTTP or DB.
 * Its bounded full-table oracle is for DEV diagnostics, NOT production reads.
 */
final class M87Plan
{
    private const RESOURCES = [
        'product' => 'products',
        'product_group' => 'product_groups',
        'product_uom' => 'product_uoms',
        'organization' => 'organizations',
        'employee_assignment' => 'employee_assignments',
        'employee' => 'employees',
        'department' => 'departments',
        'position' => 'positions',
        'contact' => 'contacts',
    ];

    /** @return array<string,string> */
    public static function resources(): array { return self::RESOURCES; }

    /** @param list<string> $args @return array{actors:list<string>,database:string,max_rows:int,case_file:?string} */
    public static function args(array $args): array
    {
        if (count($args) < 4 || count($args) > 6) {
            throw new InvalidArgumentException('Usage: php spark m87:db-audit ACTOR_A ACTOR_B EXPECTED_DEV_DB DEV_READ_ONLY [MAX_ROWS] [CASES_JSON]');
        }
        $a = self::positive((string)$args[0]);
        $b = self::positive((string)$args[1]);
        if ($a === $b || (string)$args[3] !== 'DEV_READ_ONLY') {
            throw new InvalidArgumentException('Two distinct DEV actors and exact DEV_READ_ONLY confirmation required.');
        }
        $database = (string)$args[2];
        if (preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,63}\z/D', $database) !== 1
            || in_array(strtolower($database), ['mysql', 'information_schema', 'performance_schema', 'sys'], true)
            || preg_match('/(?:^|_)(prod|production|live)(?:_|$)/iD', $database) === 1) {
            throw new InvalidArgumentException('Expected DEV database name is invalid or production-like.');
        }
        $maxRows = isset($args[4]) ? (int)self::positive((string)$args[4]) : 1000;
        if ($maxRows > 5000) {
            throw new InvalidArgumentException('M8.7 oracle is bounded to at most 5000 source rows per resource.');
        }
        $caseFile = isset($args[5]) ? (string)$args[5] : null;
        if ($caseFile !== null && ($caseFile === '' || strlen($caseFile) > 512
                || !is_file($caseFile) || !is_readable($caseFile) || is_link($caseFile)
                || filesize($caseFile) > 65536)) {
            throw new InvalidArgumentException('Invalid small local M8.7 case file.');
        }
        return ['actors'=>[$a,$b], 'database'=>$database, 'max_rows'=>$maxRows, 'case_file'=>$caseFile];
    }

    private static function positive(string $value): string
    {
        if (preg_match('/\A[1-9][0-9]{0,17}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Invalid positive numeric actor/limit.');
        }
        return $value;
    }

    public static function devEnvironment(string $environment): void
    {
        if (!in_array(strtolower($environment), ['development', 'testing'], true)) {
            throw new RuntimeException('M8.7 requires the CI4 development/testing environment, never production.');
        }
    }

    public static function databaseMatches(string $actual, string $expected): void
    {
        if ($actual === '' || !hash_equals($expected, $actual)) {
            throw new RuntimeException('Connected database does NOT match the operator-attested DEV database.');
        }
    }

    /** @param list<string> $expected @param list<string> $actual @return array<string,mixed> */
    public static function compareIds(array $expected, array $actual): array
    {
        $mismatchIndex = null;
        for ($i=0, $n=max(count($expected),count($actual)); $i<$n; $i++) {
            if (($expected[$i] ?? null) !== ($actual[$i] ?? null)) { $mismatchIndex=$i; break; }
        }
        return [
            'match'=>$mismatchIndex === null,
            'expected_count'=>count($expected),
            'actual_count'=>count($actual),
            'first_mismatch_position'=>$mismatchIndex === null ? null : $mismatchIndex + 1,
            // Never persist record IDs, names, emails, raw SQL or PBAC bind values.
            'expected_ids_sha256'=>hash('sha256', implode(',', $expected)),
            'actual_ids_sha256'=>hash('sha256', implode(',', $actual)),
        ];
    }

    /** @param list<int|float> $milliseconds @return array{samples:int,p50_ms:float,p95_ms:float,max_ms:float} */
    public static function timings(array $milliseconds): array
    {
        if ($milliseconds === []) throw new InvalidArgumentException('No measured SQL samples.');
        sort($milliseconds, SORT_NUMERIC);
        $n = count($milliseconds);
        return ['samples'=>$n, 'p50_ms'=>round((float)$milliseconds[(int)ceil($n*.50)-1],3),
            'p95_ms'=>round((float)$milliseconds[(int)ceil($n*.95)-1],3),
            'max_ms'=>round((float)$milliseconds[$n-1],3)];
    }

    /** @return array<string,mixed> */
    public static function explainSummary(string $raw): array
    {
        $plan = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($plan)) throw new UnexpectedValueException('Invalid MariaDB EXPLAIN JSON object.');
        $tables = [];
        $walk = static function (mixed $node) use (&$walk, &$tables): void {
            if (!is_array($node)) return;
            if (is_string($node['table_name'] ?? null)) {
                $tables[] = [
                    'table'=>$node['table_name'],
                    'access_type'=>is_string($node['access_type'] ?? null) ? $node['access_type'] : null,
                    'key'=>is_string($node['key'] ?? null) ? $node['key'] : null,
                    'rows'=>is_numeric($node['rows'] ?? null) ? (int)$node['rows'] : null,
                ];
            }
            foreach ($node as $value) $walk($value);
        };
        $walk($plan);
        // MariaDB may optimize a denied scope's WHERE 1=0 without any table node.
        return ['tables'=>$tables,'optimized_away'=>$tables===[],
            'plan_sha256'=>hash('sha256',$raw)];
    }

    /**
     * Minimum cross-company fixtures before allowing the DB phase to be called
     * PASS_DEV_SQL_PARITY. Additional ABSOLUTE_DENY/UTC/concurrency evidence is
     * a separate manual gate, never inferred from this file.
     * @param array<string,mixed>|null $cases @param list<string> $actors
     */
    public static function hasMinimumCases(?array $cases, array $actors): bool
    {
        if ($cases === null) return false;
        foreach ($actors as $actor) {
            foreach (['organization','employee_assignment','contact'] as $resource) {
                foreach (['allow','deny'] as $lane) {
                    if (empty($cases['actors'][$actor][$resource][$lane])) return false;
                }
            }
        }
        return true;
    }

    /** @param array<string,mixed> $caseData @param list<string> $actors */
    public static function validateCases(array $caseData, array $actors): array
    {
        if (!isset($caseData['actors']) || !is_array($caseData['actors'])) {
            throw new InvalidArgumentException('M8.7 case file must contain actors object.');
        }
        $known = array_fill_keys(array_keys(self::RESOURCES), true);
        foreach ($actors as $actor) {
            if (!isset($caseData['actors'][$actor]) || !is_array($caseData['actors'][$actor])) {
                throw new InvalidArgumentException('Case manifest missing one requested actor.');
            }
            foreach ($caseData['actors'][$actor] as $resource => $assertions) {
                if (!isset($known[$resource]) || !is_array($assertions)) {
                    throw new InvalidArgumentException('Case manifest has an unknown resource.');
                }
                foreach ($assertions as $lane => $ids) {
                    if (!in_array($lane, ['allow','deny'], true) || !is_array($ids) || !array_is_list($ids)) {
                        throw new InvalidArgumentException('Only allow/deny lists accepted.');
                    }
                    foreach ($ids as $id) self::positive((string)$id);
                }
            }
        }
        return $caseData;
    }
}
