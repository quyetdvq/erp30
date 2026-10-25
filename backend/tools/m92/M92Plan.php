<?php

declare(strict_types=1);

/** M9.2 bounded, deliberately read-only DEV clone journal audit contract. */
final class M92Plan
{
    /** @param list<string> $args
     *  @return array{database:string,max_jobs:int,max_rows:int}
     */
    public static function args(array $args): array
    {
        if (count($args) < 3 || count($args) > 5
            || $args[1] !== 'DEV_CLONE_ONLY' || $args[2] !== 'DEV_READ_ONLY') {
            throw new InvalidArgumentException('Usage: php spark m92:integrity-audit EXPECTED_DEV_DB DEV_CLONE_ONLY DEV_READ_ONLY [MAX_JOBS] [MAX_ROWS]');
        }
        $database = (string) $args[0];
        if (preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,63}\z/D', $database) !== 1
            || in_array(strtolower($database), ['mysql','sys','performance_schema','information_schema'], true)
            || preg_match('/(?:^|_)(prod|production|live)(?:_|$)/iD', $database) === 1) {
            throw new InvalidArgumentException('Expected DEV database name is unsafe.');
        }
        return [
            'database' => $database,
            'max_jobs' => self::bounded($args[3] ?? '1000', 5000),
            'max_rows' => self::bounded($args[4] ?? '50000', 100000),
        ];
    }

    public static function devEnvironment(string $environment): void
    {
        if (!in_array(strtolower($environment), ['development','testing'], true)) {
            throw new RuntimeException('M9.2 requires development/testing.');
        }
    }

    public static function databaseMatches(string $actual, string $expected): void
    {
        if ($actual === '' || !hash_equals($expected, $actual)) {
            throw new RuntimeException('Connected DB does not match confirmed DEV clone.');
        }
    }

    private static function bounded(string $number, int $maximum): int
    {
        if (preg_match('/\A[1-9][0-9]{0,5}\z/D', $number) !== 1
            || (int)$number > $maximum) {
            throw new InvalidArgumentException('M9.2 bounded positive audit limit required.');
        }
        return (int)$number;
    }
}
