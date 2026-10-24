<?php

declare(strict_types=1);

require_once __DIR__.'/M62B3Fixture.php';
require_once __DIR__.'/../m62b/M62B2DevTransport.php';
require_once __DIR__.'/../m62b/M62B2DevPreflight.php';

/** Fixture-only HTTP SETSTATE probe. No Product CREATE, PATCH, DELETE or SQL writes. */
final class M62B3DevState
{
    /** @return array<string,mixed> */
    public function run(string $marker, string $id, string $username, string $password, ?string $target): array
    {
        M62B3Fixture::marker($marker);
        M62B3Fixture::id($id);
        if ($target !== null) M62B3Fixture::target($target);
        $readOnly = $target === null;
        $report = [
            'verdict' => $readOnly ? 'FAIL_DEV_STATE_PREFLIGHT' : 'FAIL_DEV_STATE_WRITE',
            'mode' => $readOnly ? 'HTTP_READ_ONLY_PREFLIGHT' : 'EXPLICIT_DEV_SETSTATE',
            'fixture_id' => $id, 'marker' => $marker,
            'phase' => 'authentication', 'fixture_verified' => false,
            'from_status' => null, 'target_status' => $target,
            'allowed_action' => false, 'initial_get_status' => null, 'initial_get_error_code' => null,
            'http_post_status' => null, 'http_error_code' => null,
            'get_after_status' => null, 'db_sql_verified' => false,
            'numbering_concurrency' => 'NOT_TESTED',
        ];
        try {
            $transport = new M62B2DevTransport();
            $transport->login($username, $password);
            $report['phase'] = 'get_before';
            $initial = $transport->request('GET', '/api/v1/products/'.$id);
            $report['initial_get_status'] = $initial['status'];
            $report['initial_get_error_code'] = M62B2DevPreflight::http($initial)['error_code'];
            $before = M62B3Fixture::readDto($initial, $marker, $id);
            $report['fixture_verified'] = true;
            $report['from_status'] = (string)$before['status'];
            $report['allowed_action'] = ($before['allowed_actions']['setstate'] ?? false) === true;
            if (!$report['allowed_action']) throw new RuntimeException('Actor lacks Product setstate permission.');
            if ($readOnly) {
                $report['phase'] = 'complete';
                $report['verdict'] = 'PASS_DEV_PRODUCT_STATE_PREFLIGHT';
                return $report;
            }
            if ($target === $report['from_status']) {
                throw new RuntimeException('Target equals persisted State: refusing an ambiguous/no-op transition.');
            }
            // Any supported transition must also be derived via php spark m62b3:plan.
            $report['phase'] = 'setstate';
            $response = $transport->request('POST', '/api/v1/products/'.$id.'/actions/set-state', ['status'=>$target]);
            $report['http_post_status'] = $response['status'];
            $report['http_error_code'] = M62B2DevPreflight::http($response)['error_code'];
            if ($response['status'] !== 200 || !is_array($response['json']['data'] ?? null)) {
                throw new RuntimeException('SETSTATE did not return HTTP 200.');
            }
            if ((string)($response['json']['data']['id'] ?? '') !== $id
                || ($response['json']['data']['status'] ?? null) !== $target) {
                throw new RuntimeException('SETSTATE response identity/State mismatch.');
            }
            $report['phase'] = 'get_after';
            $afterResponse = $transport->request('GET', '/api/v1/products/'.$id);
            $report['get_after_status'] = $afterResponse['status'];
            $after = M62B3Fixture::readDto($afterResponse, $marker, $id);
            if (($after['status'] ?? null) !== $target
                || ($after['sku'] ?? null) !== ($before['sku'] ?? null)
                || ($after['product_code'] ?? null) !== ($before['product_code'] ?? null)) {
                throw new RuntimeException('Product State not persisted or Product identity changed.');
            }
            $report['verdict'] = 'PASS_DEV_PRODUCT_SETSTATE_HTTP';
            $report['phase'] = 'complete';
        } catch (Throwable $error) {
            // No error text, raw payloads, credentials, response bodies, cookie, or token in report.
            $report['failure_class'] = get_class($error);
        }
        return $report;
    }
}
