<?php

declare(strict_types=1);

/** One independent Shield session / one multipart Avatar POST. No SQL authority. */
require_once dirname(__DIR__).'/m63b1/M63B1AvatarPlan.php';
require_once dirname(__DIR__).'/m63b1/M63B1AvatarTransport.php';

try {
    if (count($argv) !== 4 || !in_array($argv[1], ['a','b'], true)
        || preg_match('/\A[a-f0-9]{24}\z/D', (string)$argv[3]) !== 1) {
        throw new InvalidArgumentException('Invalid worker arguments.');
    }
    $role = $argv[1];
    $target = M63B1AvatarPlan::userId($argv[2]);
    $nonce = $argv[3];
    $root = dirname(__DIR__, 2);
    $dir = $root.'/writable/m63c/run_'.$nonce;
    if (!is_dir($dir) || is_link($dir)
        || (getenv('M63C_RUN_NONCE') ?: '') !== $nonce
        || (getenv('M63C_TARGET_ID') ?: '') !== $target) {
        throw new RuntimeException('Unattested local race run.');
    }
    $username = getenv($role === 'a' ? 'M63C_ADMIN_USER' : 'M63C_TARGET_USER');
    $password = getenv($role === 'a' ? 'M63C_ADMIN_PASSWORD' : 'M63C_TARGET_PASSWORD');
    if (!is_string($username) || $username === '' || !is_string($password) || $password === '') {
        throw new RuntimeException('Missing independent authenticated actor.');
    }
    $http = new M63B1AvatarTransport($target);
    $me = $http->login($username, $password);
    $actor = $me['json']['data']['user'] ?? null;
    if (!is_array($actor) || ($actor['active'] ?? null) !== true
        || ($role === 'b' && (string)($actor['id'] ?? '') !== $target)
        || ($role === 'a' && (string)($actor['id'] ?? '') === $target)) {
        throw new RuntimeException('Race HTTP actor was not the attested login.');
    }
    $sample = M63B1AvatarPlan::sample($role === 'a' ? 1 : 2);
    if (file_put_contents($dir.'/race_'.$role.'_ready', "READY\n", LOCK_EX) === false) {
        throw new RuntimeException('Race worker ready marker failed.');
    }
    $deadline = microtime(true) + 25;
    while (!is_file($dir.'/race_go')) {
        if (microtime(true) >= $deadline) throw new RuntimeException('Race barrier timed out.');
        usleep(50000);
    }
    if (file_put_contents($dir.'/race_'.$role.'_requesting', "REQUESTING\n", LOCK_EX) === false) {
        throw new RuntimeException('Race worker requesting marker failed.');
    }
    $reply = $http->multipart($sample['bytes']);
    $status = (int)($reply['status'] ?? 0);
    $payload = ['status' => $status, 'error_code' => $reply['json']['error']['code'] ?? null,
        'avatar_url' => null, 'user_id' => null];
    if ($status === 200) {
        $payload['avatar_url'] = M63B1AvatarPlan::responseUrl($reply, $target);
        $payload['user_id'] = $target;
    } elseif ($status !== 409 || $payload['error_code'] !== 'USER_AVATAR_CONCURRENT_UPDATE') {
        throw new RuntimeException('Unexpected HTTP race response.');
    }
    if (file_put_contents($dir.'/race_'.$role.'_result.json',
        json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
        throw new RuntimeException('Race result cannot be recorded.');
    }
    exit(0);
} catch (Throwable) {
    // No credentials, exception messages, HTTP bodies, or SQL appear in logs.
    if (isset($dir, $role) && is_dir($dir) && in_array($role, ['a','b'], true)) {
        @file_put_contents($dir.'/race_'.$role.'_error', "WORKER_FAILED\n", LOCK_EX);
    }
    fwrite(STDERR, "M6.3C worker failed; inspect safe phase markers.\n");
    exit(1);
}
