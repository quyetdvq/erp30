<?php

declare(strict_types=1);

/**
 * NUM-HF05 optional DEV-only HTTP acceptance, modeled on M8.7 read-only checks.
 * Only POST /auth/login (session setup) and GET requests. M87Http pins the
 * network socket to 127.0.0.1 and validates reviewed virtual hosts.
 *
 * Invoke through tools/num_hf05_run_dev.ps1 to keep credentials off argv.
 */
require_once __DIR__ . '/m87/M87Http.php';

$folder = dirname(__DIR__) . '/writable/num_hf05/reports';
$report = [
    'milestone' => 'NUM-HF05-DEV-HTTP', 'timestamp_utc' => gmdate('c'),
    'status' => 'BLOCKED_ENV', 'mode' => 'DEV_LOOPBACK_AUTHENTICATED_READ_ONLY',
    'business_mutations' => 'BLOCKED', 'actual_mariadb_sql_count' => 'NOT_TESTED',
    'production_acceptance' => 'NOT_CLAIMED',
];
$exit = 2;
$stage = 'PREFLIGHT';
try {
    if (count($argv) !== 1) throw new InvalidArgumentException('Unexpected arguments');
    if (getenv('NUM_HF05_DEV_CONSENT') !== 'DEV_HTTP_READ_ONLY') {
        throw new InvalidArgumentException('Missing explicit DEV consent');
    }
    $host = (string) getenv('NUM_HF05_DEV_HOST');
    $scheme = (string) getenv('NUM_HF05_DEV_SCHEME');
    if (!in_array($host, ['api.monmin.net', 'localhost'], true)
        || !in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('Unsupported DEV loopback virtual host');
    }
    $accounts = [];
    foreach (['ALLOW', 'DENY'] as $kind) {
        $id = (string) getenv('NUM_HF05_' . $kind . '_ID');
        $user = (string) getenv('NUM_HF05_' . $kind . '_USER');
        $password = (string) getenv('NUM_HF05_' . $kind . '_PASSWORD');
        if (preg_match('/\A[1-9][0-9]{0,17}\z/D', $id) !== 1 || $user === '' || $password === '') {
            throw new InvalidArgumentException('DEV actor ID or credentials missing');
        }
        $accounts[$kind] = ['id' => $id, 'user' => $user, 'password' => $password];
    }
    if ($accounts['ALLOW']['id'] === $accounts['DENY']['id']
        || $accounts['ALLOW']['user'] === $accounts['DENY']['user']) {
        throw new InvalidArgumentException('Two distinct DEV actors are required');
    }
    $max = (int) (getenv('NUM_HF05_HTTP_MAX_RESOURCES') ?: '30');
    if ($max < 1 || $max > 100) throw new InvalidArgumentException('Invalid comparison limit');

    $stage = 'ANONYMOUS_OVERVIEW';
    $anonymous = (new M87Http($host, $scheme))->request('GET', '/api/v1/numbering-series/overview');
    if ($anonymous['status'] !== 401 || isset($anonymous['json']['data'])) {
        throw new RuntimeException('Anonymous overview did not return a data-free 401');
    }
    $report['anonymous'] = ['overview_gets' => 1, 'status' => 401, 'data_exposed' => false];

    // Authenticated read-only HTTP probe: the scoped old API is used as a
    // positive parity oracle. This traffic comes from TEST ONLY, not the UI.
    $stage = 'ALLOW_LOGIN';
    $allow = new M87Http($host, $scheme);
    $allow->login($accounts['ALLOW']['user'], $accounts['ALLOW']['password'], $accounts['ALLOW']['id']);
    $stage = 'ALLOW_OVERVIEW';
    $response = $allow->request('GET', '/api/v1/numbering-series/overview');
    if ($response['status'] !== 200) throw new RuntimeException('Positive actor overview status is not 200');
    $payload = $response['json']['data'] ?? null;
    if (!is_array($payload) || !array_is_list($payload['resources'] ?? null)
        || !array_is_list($payload['series'] ?? null)) {
        throw new RuntimeException('Invalid overview response shape');
    }
    $resources = $payload['resources'];
    $series = $payload['series'];
    $authorized = [];
    foreach ($resources as $r) {
        $id = $r['id'] ?? null;
        if (!is_array($r) || preg_match('/\A[1-9][0-9]*\z/D', (string) $id) !== 1
            || isset($authorized[(string) $id]) || ($r['allowed_actions']['configure_numbering'] ?? false) !== true) {
            throw new RuntimeException('Unauthorized or duplicated Resource in overview');
        }
        $authorized[(string) $id] = true;
    }
    $grouped = array_fill_keys(array_keys($authorized), []);
    $seenSeries = [];
    foreach ($series as $row) {
        if (!is_array($row)) throw new RuntimeException('Non-object Numbering Series');
        $owner = (string) ($row['resource_id'] ?? '');
        $id = (string) ($row['id'] ?? '');
        if (!array_key_exists($owner, $grouped)
            || preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1 || isset($seenSeries[$id])) {
            throw new RuntimeException('Series ownership or ID integrity failed');
        }
        $seenSeries[$id] = true;
        $grouped[$owner][] = $row;
    }
    if ($resources === []) throw new RuntimeException('Positive actor has no authorized Resource coverage');
    $report['allow'] = ['actor_id' => $accounts['ALLOW']['id'], 'resource_count' => count($resources),
        'series_count' => count($series), 'scoped_gets' => 0, 'parity' => 'NOT_TESTED'];
    if (count($resources) > $max) {
        $report['allow']['parity'] = 'INCONCLUSIVE_RESOURCE_LIMIT';
        $report['status'] = 'INCONCLUSIVE_RESOURCE_LIMIT';
        $exit = 3;
    } else {
        $stage = 'ALLOW_SCOPED_PARITY';
        foreach (array_keys($authorized) as $owner) {
            $scoped = $allow->request('GET', '/api/v1/resources/' . $owner . '/numbering-series');
            if ($scoped['status'] !== 200 || !array_is_list($scoped['json']['data'] ?? null)) {
                throw new RuntimeException('Existing Resource-scoped Numbering API did not return a complete list');
            }
            $items = $scoped['json']['data'];
            $meta = $scoped['json']['meta'] ?? [];
            if ((int) ($meta['total'] ?? -1) !== count($items)) {
                throw new RuntimeException('Scoped Numbering API pagination is incomplete');
            }
            // Compare exact API-visible values; JSON-decoded associative key
            // order is an implementation detail and must not affect parity.
            $normalize = static function (array $rows): array {
                $byId = [];
                foreach ($rows as $row) {
                    if (!is_array($row) || !isset($row['id'])) throw new RuntimeException('Invalid scoped series');
                    ksort($row);
                    $byId[(string) $row['id']] = $row;
                }
                ksort($byId, SORT_NATURAL);
                return $byId;
            };
            if ($normalize($items) !== $normalize($grouped[$owner])) {
                throw new RuntimeException('Overview and scoped Numbering API data are not equivalent');
            }
            $report['allow']['scoped_gets']++;
        }
        $report['allow']['parity'] = 'PASS';
    }
    unset($payload, $resources, $series, $grouped, $seenSeries);

    $stage = 'DENY_LOGIN';
    $deny = new M87Http($host, $scheme);
    $deny->login($accounts['DENY']['user'], $accounts['DENY']['password'], $accounts['DENY']['id']);
    $stage = 'DENY_OVERVIEW';
    $negative = $deny->request('GET', '/api/v1/numbering-series/overview');
    if ($negative['status'] === 200) {
        $deniedData = $negative['json']['data'] ?? null;
        if (!is_array($deniedData) || ($deniedData['resources'] ?? null) !== []
            || ($deniedData['series'] ?? null) !== []) {
            throw new RuntimeException('Denied actor received Resource or Numbering data');
        }
    } elseif ($negative['status'] !== 403 || isset($negative['json']['data'])) {
        throw new RuntimeException('Denied actor did not fail closed');
    }
    $report['deny'] = ['actor_id' => $accounts['DENY']['id'], 'overview_gets' => 1,
        'http_status' => $negative['status'], 'resource_count' => 0, 'series_count' => 0];
    if ($exit !== 3) {
        $report['status'] = 'PASS_DEV_HTTP_PBAC_AND_SCOPED_PARITY';
        $exit = 0;
    }
} catch (Throwable $error) {
    $report['status'] = $stage === 'PREFLIGHT' ? 'BLOCKED_DEV_PREFLIGHT' : 'FAIL_OR_BLOCKED_DEV_HTTP';
    $report['failed_stage'] = $stage;
    // Codes only: do not serialize exceptions/headers/body/session data.
    $report['failure_type'] = get_class($error);
    fwrite(STDERR, 'NUM-HF05 DEV HTTP failed at ' . $stage . ' (' . get_class($error) . ').' . PHP_EOL);
    $exit = 2;
} finally {
    if (!is_dir($folder)) @mkdir($folder, 0700, true);
    if (is_dir($folder) && is_writable($folder)) {
        $path = $folder . '/num_hf05_http_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
        @chmod($path, 0600);
        echo 'NUM-HF05 DEV HTTP report: ' . $path . PHP_EOL;
    }
    echo 'NUM-HF05 DEV HTTP: ' . $report['status'] . '; business writes BLOCKED.' . PHP_EOL;
}
exit($exit);
