<?php

declare(strict_types=1);

/** Only invokes the previously reviewed SELECT-only M63B1 Spark SQL audit. */
final class M63B2AvatarAudit
{
    public static function target(string $id, ?string $filename): void
    {
        $id = M63B1AvatarPlan::userId($id);
        if ($filename !== null && \App\Shared\Media\ImageUrl::for($filename, 'avatar') === null) {
            throw new RuntimeException('Unsafe avatar basename.');
        }
        $backend = dirname(__DIR__, 2);
        $command = [PHP_BINARY, $backend.'/spark', 'm63b1:db-audit', $id, $filename ?? 'EMPTY'];
        $process = proc_open($command,
            [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, $backend);
        if (!is_resource($process)) throw new RuntimeException('Read-only SQL audit unavailable.');
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0 || !is_string($stdout)
            || preg_match('/^M63B1_DB_JSON=(\{[^\r\n]+\})\s*$/m', $stdout, $match) !== 1) {
            throw new RuntimeException('Read-only SQL target Avatar audit failed.');
        }
        $data = json_decode($match[1], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new RuntimeException('Invalid SQL audit snapshot.');
        M63B1AvatarPlan::sqlAudit($data, $id, $filename);
        $expected = getenv('M63B2_EXPECTED_DATABASE');
        if ($expected !== false && $expected !== '') {
            self::assertDatabase($data, $expected);
        }
    }

    /** @param array<string,mixed> $snapshot */
    public static function assertDatabase(array $snapshot, string $expected): void
    {
        if (preg_match('/\A[A-Za-z0-9_]{1,64}\z/D', $expected) !== 1
            || !is_string($snapshot['database_name'] ?? null)
            || !hash_equals($expected, $snapshot['database_name'])) {
            throw new RuntimeException('Connected DB does not match the approved DEV fixture.');
        }
    }
}
