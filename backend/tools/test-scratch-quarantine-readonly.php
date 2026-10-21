<?php

declare(strict_types=1);

/** Offline test of the forensic entrypoint and the emergency execution HOLD. */
try {
    $base = __DIR__;
    $forensic = file_get_contents($base . '/scratch-numbering-quarantine-readonly.php');
    $runner = file_get_contents($base . '/scratch-numbering-phpunit.php');
    $wrapper = file_get_contents($base . '/test-scratch-numbering-phpunit.ps1');
    $plan = file_get_contents($base . '/test-scratch-quarantine-readonly.ps1');
    foreach ([$forensic, $runner, $wrapper, $plan] as $source) {
        if (! is_string($source) || $source === '') {
            throw new RuntimeException('missing or unreadable forensic source');
        }
    }
    foreach (['--plan', '--erp-test', '--scratch', 'qMarkerCounts', 'qCounts',
        'ScratchReadinessGate::assertDmlOnlyGrants', 'NoDropDataGuard::fingerprint'] as $required) {
        if (! str_contains($forensic, $required)) {
            throw new RuntimeException('forensic allowlist or read-only proof missing');
        }
    }
    if (! str_contains($runner, "if (in_array('--execute', \$args, true))")
        || ! str_contains($runner, 'live execution disabled pending forensic review')
        || ! str_contains($wrapper, 'live scratch PHPUnit is quarantined')) {
        throw new RuntimeException('emergency execution HOLD not installed');
    }
    foreach (['--erp-test', '--scratch', 'ZeroFreeBSTR', 'Remove-Item Env:ERP_SCRATCH_DB_PASSWORD'] as $required) {
        if (! str_contains($plan, $required)) {
            throw new RuntimeException('forensic wrapper security check missing');
        }
    }
    if (preg_match('/(?:query|prepare)\s*\(\s*[\x22\x27]\s*(?:DELETE|INSERT|UPDATE|REPLACE|ALTER|TRUNCATE|DROP|CREATE)\b/i', $forensic)) {
        throw new RuntimeException('forensic source includes direct mutating SQL');
    }
    echo '03C-04C PURE FORENSIC GATE PASS: execution HOLD enabled; readonly identity/fingerprint/table/marker probes; no DB connections, no SQL.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, '03C-04C PURE FORENSIC GATE STOP: source security check failed.' . PHP_EOL);
    exit(2);
}
