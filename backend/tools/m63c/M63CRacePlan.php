<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/m63b1/M63B1AvatarPlan.php';

/** Strict, side-effect-free M6.3C race evidence and filename validators. */
final class M63CRacePlan
{
    public const CONFIRM = 'DEV_AVATAR_RACE_QA';

    public static function database(string $name): string
    {
        if (preg_match('/\A[A-Za-z0-9_]{1,64}\z/D', $name) !== 1) {
            throw new InvalidArgumentException('An explicitly pinned local DEV database is required.');
        }
        return $name;
    }

    public static function guardEnvironment(bool $apply, ?string $confirm): void
    {
        $ci = strtolower(trim((string) (getenv('CI_ENVIRONMENT') ?: '')));
        $app = strtolower(trim((string) (getenv('APP_ENV') ?: '')));
        if ($ci === 'production' || $app === 'production'
            || ($ci !== '' && $app !== '' && $ci !== $app)) {
            throw new InvalidArgumentException('M6.3C is forbidden in production or mixed environments.');
        }
        if (!$apply) {
            if ($confirm !== null) throw new InvalidArgumentException('Confirmation is not permitted without --apply.');
            return;
        }
        $active = $ci !== '' ? $ci : $app;
        if (!in_array($active, ['development','testing'], true)
            || getenv('M63C_DEV_QA_WRITE') !== 'YES'
            || $confirm !== self::CONFIRM) {
            throw new InvalidArgumentException('DEV/test, independent write opt-in and exact confirmation required.');
        }
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b
     * @return array{winner:string,filename:string,loser:string}
     */
    public static function results(array $a, array $b, string $targetId): array
    {
        $targetId = M63B1AvatarPlan::userId($targetId);
        $statuses = [(int)($a['status'] ?? 0), (int)($b['status'] ?? 0)];
        sort($statuses, SORT_NUMERIC);
        if ($statuses !== [200, 409]) {
            throw new RuntimeException('Expected one accepted writer and one CAS-conflicted writer.');
        }
        $winnerRole = ($a['status'] ?? null) === 200 ? 'a' : 'b';
        $winner = $winnerRole === 'a' ? $a : $b;
        $loser = $winnerRole === 'a' ? $b : $a;
        if (($loser['error_code'] ?? null) !== 'USER_AVATAR_CONCURRENT_UPDATE'
            || ($loser['avatar_url'] ?? null) !== null
            || ($winner['error_code'] ?? null) !== null
            || ($winner['user_id'] ?? null) !== $targetId
            || !is_string($winner['avatar_url'] ?? null)) {
            throw new RuntimeException('CAS conflict domain code or winner identity is inconsistent.');
        }
        return ['winner' => $winnerRole,
            'filename' => M63B1AvatarPlan::filename($winner['avatar_url']),
            'loser' => $winnerRole === 'a' ? 'b' : 'a'];
    }

    /** A read-only filesystem inventory. Never delete a path just because it is new.
     * @return array<string,true>
     */
    public static function files(): array
    {
        $root = \App\Shared\Media\UploadPaths::root();
        if (is_link($root)) throw new RuntimeException('Unsafe symlink upload root.');
        $files = glob($root.'/20[0-9][0-9]/20[0-9][0-9]*avatar*.png');
        if ($files === false) throw new RuntimeException('Cannot enumerate public Avatar files.');
        $result = [];
        foreach ($files as $path) {
            if (is_link($path) || is_link(dirname($path))) {
                throw new RuntimeException('Unsafe linked Avatar media path.');
            }
            if (is_file($path)) $result[basename($path)] = true;
        }
        return $result;
    }

    /** @param array<string,true> $before @param array<string,true> $after @return list<string> */
    public static function newFiles(array $before, array $after): array
    {
        $names = array_keys(array_diff_key($after, $before));
        sort($names, SORT_STRING);
        return $names;
    }
}
