<?php

declare(strict_types=1);

/**
 * SUP-T09A: bounded, local-loopback Supplier HTTP verification.
 * Uses existing M87Http transport: GET only, except POST auth/login.
 * This is a precheck, NEVER full PBAC, CAS, DB-concurrency or Bank acceptance.
 */
final class SupplierHttpAudit
{
    private const MISSING_ID = '18446744073709551615';

    /** @param array<string,string|false|null> $env @return list<string> */
    public static function preflight(array $env): array
    {
        $errors = [];
        if (($env['SUP_UAT_CONSENT'] ?? null) !== 'DEV_HTTP_READ_ONLY') {
            $errors[] = 'MISSING_EXPLICIT_DEV_READ_ONLY_CONSENT';
        }
        if (!in_array($env['SUP_UAT_HOST'] ?? null, ['api.monmin.net', 'localhost'], true)) {
            $errors[] = 'INVALID_LOCAL_HOST';
        }
        if (!in_array($env['SUP_UAT_SCHEME'] ?? null, ['http', 'https'], true)) {
            $errors[] = 'INVALID_LOCAL_SCHEME';
        }
        foreach (['SUP_UAT_A_USER', 'SUP_UAT_A_PASSWORD', 'SUP_UAT_A_ID'] as $name) {
            if (!is_string($env[$name] ?? null) || $env[$name] === '') {
                $errors[] = 'MISSING_' . $name;
            }
        }
        if (!self::positiveId($env['SUP_UAT_A_ID'] ?? null)) $errors[] = 'INVALID_ACTOR_A_ID';
        $hasB = false;
        foreach (['SUP_UAT_B_USER', 'SUP_UAT_B_PASSWORD', 'SUP_UAT_B_ID'] as $name) {
            if (is_string($env[$name] ?? null) && $env[$name] !== '') $hasB = true;
        }
        if ($hasB) {
            foreach (['SUP_UAT_B_USER', 'SUP_UAT_B_PASSWORD', 'SUP_UAT_B_ID'] as $name) {
                if (!is_string($env[$name] ?? null) || $env[$name] === '') {
                    $errors[] = 'MISSING_' . $name;
                }
            }
            if (!self::positiveId($env['SUP_UAT_B_ID'] ?? null)) $errors[] = 'INVALID_ACTOR_B_ID';
            if (($env['SUP_UAT_A_USER'] ?? null) === ($env['SUP_UAT_B_USER'] ?? null)
                || ($env['SUP_UAT_A_ID'] ?? null) === ($env['SUP_UAT_B_ID'] ?? null)) {
                $errors[] = 'ACTORS_MUST_BE_DISTINCT';
            }
        }
        $restricted = $env['SUP_UAT_RESTRICTED_SUPPLIER_ID'] ?? null;
        if ($restricted !== null && $restricted !== false && $restricted !== '') {
            if (!self::positiveId($restricted)) $errors[] = 'INVALID_RESTRICTED_SUPPLIER_ID';
            if (!$hasB) $errors[] = 'RESTRICTED_ID_REQUIRES_TWO_ACTORS';
        }
        return array_values(array_unique($errors));
    }

    /** @return array<string,string|false> */
    public static function processEnvironment(): array
    {
        $keys = [
            'SUP_UAT_CONSENT', 'SUP_UAT_HOST', 'SUP_UAT_SCHEME',
            'SUP_UAT_A_USER', 'SUP_UAT_A_PASSWORD', 'SUP_UAT_A_ID',
            'SUP_UAT_B_USER', 'SUP_UAT_B_PASSWORD', 'SUP_UAT_B_ID',
            'SUP_UAT_RESTRICTED_SUPPLIER_ID',
        ];
        $values = [];
        foreach ($keys as $key) $values[$key] = getenv($key);
        return $values;
    }

    private static function positiveId(mixed $value): bool
    {
        if (!is_string($value) && !is_int($value)) return false;
        $id = (string)$value;
        $max = self::MISSING_ID;
        return preg_match('/\A[1-9][0-9]*\z/D', $id) === 1
            && (strlen($id) < strlen($max)
                || (strlen($id) === strlen($max) && strcmp($id, $max) <= 0));
    }

    /** Only evaluate response shape and safe Supplier fields. No payloads in report. */
    public static function inspectList(array $response): array
    {
        $json = $response['json'] ?? null;
        $meta = is_array($json) ? ($json['meta'] ?? null) : null;
        $rows = is_array($json) ? ($json['data'] ?? null) : null;
        if (($response['status'] ?? null) !== 200 || !is_array($meta)
            || !is_array($rows) || !array_is_list($rows)
            || count($rows) > 25 || !self::isDecimal($meta['total'] ?? null, true)
            || (int)($meta['page'] ?? -1) !== 1
            || (int)($meta['page_size'] ?? -1) !== 25) {
            return ['pass' => false, 'total' => null, 'visible' => null, 'rows' => []];
        }
        if ((int)$meta['total'] < count($rows)) {
            return ['pass' => false, 'total' => null, 'visible' => null, 'rows' => []];
        }
        foreach ($rows as $row) {
            if (!self::validSupplierRow($row)) {
                return ['pass' => false, 'total' => null, 'visible' => null, 'rows' => []];
            }
        }
        return ['pass' => true, 'total' => (string)$meta['total'], 'visible' => count($rows), 'rows' => $rows];
    }

    public static function inspectDetail(array $response): bool
    {
        $json = $response['json'] ?? null;
        return ($response['status'] ?? null) === 200 && is_array($json)
            && self::validSupplierRow($json['data'] ?? null, true);
    }

    public static function inspectUsage(array $response): bool
    {
        $json = $response['json'] ?? null;
        $usage = is_array($json) ? ($json['data'] ?? null) : null;
        if (($response['status'] ?? null) !== 200 || !is_array($usage)
            || !is_bool($usage['all_companies'] ?? null)
            || !self::isDecimal($usage['row_version'] ?? null)
            || !is_array($usage['company_ids'] ?? null) || !array_is_list($usage['company_ids'])) return false;
        if (self::hasBankFields($usage)) return false;
        $ids = [];
        foreach ($usage['company_ids'] as $id) {
            if (!self::positiveId($id)) return false;
            $ids[] = (string)$id;
        }
        if (count($ids) !== count(array_unique($ids))) return false;
        return $usage['all_companies'] ? $ids === [] : $ids !== [];
    }

    private static function validSupplierRow(mixed $row, bool $detail = false): bool
    {
        if (!is_array($row) || !self::positiveId($row['id'] ?? null)
            || !self::isDecimal($row['row_version'] ?? null)
            || !is_string($row['supplier_code'] ?? null)
            || !is_string($row['status'] ?? null)
            || self::hasBankFields($row)) return false;
        if ($detail && (!array_key_exists('all_companies', $row)
            || !is_array($row['company_ids'] ?? null))) return false;
        return true;
    }

    private static function hasBankFields(array $record): bool
    {
        foreach (array_keys($record) as $field) {
            if (is_string($field) && preg_match('/bank|iban|swift|account_number/i', $field) === 1) return true;
        }
        return false;
    }

    private static function isDecimal(mixed $value, bool $allowZero = false): bool
    {
        if (!is_int($value) && !is_string($value)) return false;
        return preg_match($allowZero ? '/\A(?:0|[1-9][0-9]*)\z/D' : '/\A[1-9][0-9]*\z/D', (string)$value) === 1;
    }

    /**
     * @param callable(string):object $clientFactory must provide request() and login().
     * @param array<string,string|false|null> $env preflighted values, never logged.
     * @return array{status:string,checks:list<array{label:string,pass:bool,detail:string}>,gaps:list<string>}
     */
    public function execute(callable $clientFactory, array $env): array
    {
        $checks = [];
        $gaps = [];
        $check = static function (string $label, bool $pass, string $detail = '') use (&$checks): void {
            $checks[] = ['label' => $label, 'pass' => $pass, 'detail' => $detail];
        };
        $anonymous = $clientFactory('anonymous');
        $anon = $anonymous->request('GET', '/api/v1/suppliers?page=1&page_size=25&sort=id');
        $check('Anonymous Supplier read denied', ($anon['status'] ?? null) === 401,
            'expected HTTP 401, got ' . (int)($anon['status'] ?? 0));

        $a = $clientFactory('A');
        $a->login((string)$env['SUP_UAT_A_USER'], (string)$env['SUP_UAT_A_PASSWORD'], (string)$env['SUP_UAT_A_ID']);
        $aResult = $this->checkActor($a, 'A', $check, $gaps);

        $hasB = is_string($env['SUP_UAT_B_USER'] ?? null) && $env['SUP_UAT_B_USER'] !== '';
        $b = null;
        if ($hasB) {
            $b = $clientFactory('B');
            $b->login((string)$env['SUP_UAT_B_USER'], (string)$env['SUP_UAT_B_PASSWORD'], (string)$env['SUP_UAT_B_ID']);
            $this->checkActor($b, 'B', $check, $gaps);
        } else {
            $gaps[] = 'SECOND_ACTOR_NOT_SUPPLIED';
        }
        $restricted = (string)($env['SUP_UAT_RESTRICTED_SUPPLIER_ID'] ?? '');
        if ($restricted !== '' && $b !== null) {
            $path = '/api/v1/suppliers/' . $restricted;
            $aResponse = $a->request('GET', $path);
            $bResponse = $b->request('GET', $path);
            $check('A can view restricted scenario', self::inspectDetail($aResponse), 'expected scoped HTTP 200');
            $check('B cannot view restricted scenario', ($bResponse['status'] ?? null) === 404,
                'expected non-disclosing HTTP 404');
        } else {
            $gaps[] = 'CROSS_ACTOR_RESTRICTED_FIXTURE_NOT_SUPPLIED';
        }
        if (($aResult['visible'] ?? 0) === 0) $gaps[] = 'NO_A_VISIBLE_SUPPLIER_FOR_DETAIL_USAGE';
        $allPassed = !in_array(false, array_column($checks, 'pass'), true);
        return [
            'status' => $allPassed ? 'PASS_READONLY_HTTP_PRECHECK' : 'FAIL_READONLY_HTTP_PRECHECK',
            'checks' => $checks,
            'gaps' => array_values(array_unique($gaps)),
        ];
    }

    /** @param callable(string,bool,string):void $check @param list<string> $gaps */
    private function checkActor(object $client, string $label, callable $check, array &$gaps): array
    {
        $list = $client->request('GET', '/api/v1/suppliers?page=1&page_size=25&sort=id');
        $shape = self::inspectList($list);
        $check('Actor ' . $label . ' Supplier list', $shape['pass'],
            'HTTP ' . (int)($list['status'] ?? 0) . '; visible=' . (int)($shape['visible'] ?? 0)
                . '; scoped_total=' . ($shape['total'] ?? 'unknown'));
        $invalid = $client->request('GET', '/api/v1/suppliers?page=1&page_size=101&sort=id');
        $check('Actor ' . $label . ' page cap', ($invalid['status'] ?? null) === 400,
            'expected HTTP 400, got ' . (int)($invalid['status'] ?? 0));
        $missing = $client->request('GET', '/api/v1/suppliers/' . self::MISSING_ID);
        $check('Actor ' . $label . ' missing Supplier hides existence', ($missing['status'] ?? null) === 404,
            'expected HTTP 404, got ' . (int)($missing['status'] ?? 0));
        if ($shape['pass'] && $shape['visible'] > 0) {
            $id = (string)$shape['rows'][0]['id'];
            $detail = $client->request('GET', '/api/v1/suppliers/' . $id);
            $check('Actor ' . $label . ' Supplier detail', self::inspectDetail($detail), 'expected HTTP 200 with no Bank fields');
            $usage = $client->request('GET', '/api/v1/suppliers/' . $id . '/company-usages');
            $check('Actor ' . $label . ' Company Usage', self::inspectUsage($usage), 'expected HTTP 200 with projected usage');
        } else {
            $gaps[] = 'ACTOR_' . $label . '_DETAIL_USAGE_NOT_EXERCISED';
        }
        return $shape;
    }
}
