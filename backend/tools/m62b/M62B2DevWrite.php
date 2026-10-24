<?php

declare(strict_types=1);

require_once __DIR__ . '/M62B2DevPlan.php';
require_once __DIR__ . '/M62B2DevTransport.php';
require_once __DIR__ . '/M62B2DevPreflight.php';

/** Opt-in DEV write probe. Creates one labelled Product; never deletes or edits existing records. */
final class M62B2DevWrite
{
    /** @return array<string,mixed> */
    public function execute(string $groupId, string $uomId, string $username, string $password, ?string $targetState = null, bool $preflightOnly = false): array
    {
        M62B2DevPlan::id($groupId);
        M62B2DevPlan::id($uomId);
        if ($targetState !== null && preg_match('/\A[A-Z][A-Z0-9_:-]{0,19}\z/D', $targetState) !== 1) {
            throw new InvalidArgumentException('Invalid optional target state.');
        }
        $marker = M62B2DevPlan::marker(bin2hex(random_bytes(6)));
        $original = M62B2DevPlan::standard($marker, $groupId, $uomId);
        $transport = new M62B2DevTransport();
        $report = [
            'verdict' => 'FAIL_DEV_PRODUCT_WRITE', 'phase' => 'preflight', 'marker' => $marker,
            'created_product_id' => null, 'created_product_code' => null,
            'manual_or_auto_code' => null, 'create_http' => null, 'patch_http' => null,
            'duplicate_rejected' => false, 'combo_failure_rejected' => false,
            'unexpected_duplicate_product_id' => null, 'unexpected_combo_product_id' => null,
            'http_visible_no_orphan' => false, 'setstate' => 'NOT_TESTED',
            'sql_rollback_verified' => false, 'numbering_counter_atomicity' => 'NOT_TESTED',
            'independent_baseline_parity' => 'NOT_TESTED',
            'fixture_retained' => !$preflightOnly, // No DELETE route. Never invent SQL cleanup.
            'preflight_only' => $preflightOnly,
            'preflight_step' => 'authentication',
            'preflight' => ['authentication' => [], 'product_group' => null, 'product_uom' => null],
        ];
        try {
            $transport->login($username, $password, static function (string $step, array $response) use (&$report): void {
                // A bounded status/error code is enough to diagnose CSRF/login/session failures.
                // Do not retain usernames, tokens, cookies, raw responses or credentials.
                $report['preflight']['authentication'][$step] = M62B2DevPreflight::http($response);
            });
            $report['preflight']['authentication']['pass'] = true;
            foreach (['product-groups' => $groupId, 'product-uoms' => $uomId] as $type => $id) {
                $key = $type === 'product-groups' ? 'product_group' : 'product_uom';
                $report['preflight_step'] = $key;
                $response = $transport->request('GET', '/api/v1/'.$type.'/'.$id);
                $check = M62B2DevPreflight::reference($type, $id, $response);
                $report['preflight'][$key] = $check;
                if (!$check['pass']) {
                    throw new RuntimeException('DEV reference failed preflight.');
                }
            }
            $report['preflight_step'] = 'complete';
            if ($preflightOnly) {
                $report['phase'] = 'preflight_complete';
                $report['verdict'] = 'PASS_DEV_PRODUCT_PREFLIGHT';
                return $report; // No business mutation requests in this mode.
            }
            $report['phase'] = 'create';
            $created = $transport->request('POST', '/api/v1/products', $original);
            $auto = false;
            // Explicitly handle the accepted baseline's AUTO_REQUIRED series policy.
            if ($created['status'] !== 201
                && ($created['json']['error']['code'] ?? null) === 'NUM_IDENTIFIER_MANUAL_NOT_ALLOWED') {
                $auto = true;
                $payload = $original;
                unset($payload['product_code']);
                $created = $transport->request('POST', '/api/v1/products', $payload);
            }
            $report['create_http'] = $created['status'];
            if ($created['status'] !== 201 || !is_array($created['json']['data'] ?? null)) {
                $report['create_error_code'] = is_string($created['json']['error']['code'] ?? null)
                    ? $created['json']['error']['code'] : null;
                throw new RuntimeException('DEV Product CREATE was not accepted.');
            }
            $data = $created['json']['data'];
            $id = M62B2DevPlan::id((string)($data['id'] ?? ''));
            $code = $data['product_code'] ?? null;
            if (($data['sku'] ?? null) !== $original['sku'] || !is_string($code) || $code === '') {
                throw new RuntimeException('DEV CREATE returned inconsistent product identity.');
            }
            $report['created_product_id'] = $id;
            $report['created_product_code'] = $code;
            $report['manual_or_auto_code'] = $auto ? 'AUTO_DEFAULT' : 'MANUAL';
            $read = $transport->request('GET', '/api/v1/products/'.$id);
            if ($read['status'] !== 200 || ($read['json']['data']['sku'] ?? null) !== $original['sku']
                || ($read['json']['data']['product_code'] ?? null) !== $code) {
                throw new RuntimeException('DEV CREATE read-back mismatch.');
            }
            $report['phase'] = 'patch';
            $description = M62B2DevPlan::updatedDescription($marker);
            $patched = $transport->request('PATCH', '/api/v1/products/'.$id, ['description' => $description]);
            $report['patch_http'] = $patched['status'];
            if ($patched['status'] !== 200 || ($patched['json']['data']['description'] ?? null) !== $description) {
                throw new RuntimeException('DEV Product PATCH did not succeed.');
            }
            $after = $transport->request('GET', '/api/v1/products/'.$id);
            if ($after['status'] !== 200 || ($after['json']['data']['description'] ?? null) !== $description
                || ($after['json']['data']['product_code'] ?? null) !== $code
                || ($after['json']['data']['sku'] ?? null) !== $original['sku']) {
                throw new RuntimeException('DEV PATCH persisted read-back mismatch.');
            }
            $report['phase'] = 'duplicate';
            $duplicate = $transport->request('POST', '/api/v1/products', M62B2DevPlan::duplicateSku($original, $marker, $auto));
            $report['duplicate_rejected'] = $duplicate['status'] === 409
                && ($duplicate['json']['error']['code'] ?? null) === 'PRODUCT_IDENTIFIER_DUPLICATE';
            if (!$report['duplicate_rejected']) {
                if ($duplicate['status'] === 201) {
                    $report['unexpected_duplicate_product_id'] = (string)($duplicate['json']['data']['id'] ?? 'UNKNOWN');
                }
                $report['duplicate_status'] = $duplicate['status'];
                $report['duplicate_error_code'] = $duplicate['json']['error']['code'] ?? null;
                throw new RuntimeException('Duplicate SKU was not rejected as expected.');
            }
            $report['phase'] = 'combo_rollback';
            $combo = $transport->request('POST', '/api/v1/products', M62B2DevPlan::failedCombo($original, $marker, $auto));
            // The missing component must fail AFTER the Product insert has been attempted.
            $report['combo_failure_rejected'] = $combo['status'] === 404
                && ($combo['json']['error']['code'] ?? null) === 'PRODUCT_NOT_FOUND';
            if (!$report['combo_failure_rejected']) {
                if ($combo['status'] === 201) {
                    $report['unexpected_combo_product_id'] = (string)($combo['json']['data']['id'] ?? 'UNKNOWN');
                }
                $report['combo_status'] = $combo['status'];
                $report['combo_error_code'] = $combo['json']['error']['code'] ?? null;
                throw new RuntimeException('Expected COMBO component lookup failure was not observed.');
            }
            // This checks PBAC-visible rows only; the separate SQL audit proves persisted absence.
            $matches = $transport->request('GET', '/api/v1/products?q='.$marker.'&page=1&page_size=100');
            $rows = $matches['json']['data'] ?? null;
            $report['http_visible_no_orphan'] = $matches['status'] === 200 && is_array($rows)
                && count($rows) === 1 && (string)($rows[0]['id'] ?? '') === $id;
            if (!$report['http_visible_no_orphan']) throw new RuntimeException('Unexpected Product visible after failed transactions.');
            if ($targetState !== null) {
                $report['phase'] = 'setstate';
                $stateResponse = $transport->request('POST', '/api/v1/products/'.$id.'/actions/set-state', ['status' => $targetState]);
                $readState = $transport->request('GET', '/api/v1/products/'.$id);
                $report['setstate'] = $stateResponse['status'] === 200 && $readState['status'] === 200
                    && ($readState['json']['data']['status'] ?? null) === $targetState
                    ? 'PASS_HTTP_PERSISTED' : 'FAIL_HTTP_TRANSITION';
                if ($report['setstate'] !== 'PASS_HTTP_PERSISTED') {
                    $report['setstate_status'] = $stateResponse['status'];
                    $report['setstate_error_code'] = $stateResponse['json']['error']['code'] ?? null;
                    throw new RuntimeException('Configured DEV state transition failed.');
                }
            }
            $report['phase'] = 'complete';
            $report['verdict'] = 'PASS_DEV_PRODUCT_CREATE_PATCH';
        } catch (Throwable $error) {
            // Do not serialize credentials, headers, cookies or raw exception text.
            $report['failure_class'] = get_class($error);
            if ($preflightOnly) $report['verdict'] = 'FAIL_DEV_PRODUCT_PREFLIGHT';
        }
        return $report;
    }
}
