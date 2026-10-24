<?php

declare(strict_types=1);

/**
 * M4 immutable contract extension. M3.4D's exact seventy prior routes and
 * the complete v1.2.4 baseline manifest remain independently SHA-256 pinned.
 * This class never bootstraps CI4 or opens a database connection.
 */
require_once dirname(__DIR__) . '/m34d/M34DAcceptance.php';

final class M4SourceAcceptance
{
    /** @return array<string,array{0:string,1:string,2:string,3:int}> */
    public static function expected(): array
    {
        $settings = 'App\\Modules\\Settings\\Display\\DisplaySettingsController';
        $numbering = 'App\\Modules\\Numbering\\Configuration\\NumberingController';
        $workflow = 'App\\Modules\\Workflow\\Execution\\BusinessWorkflowController';
        return [
            'GET display-settings/runtime' => [$settings, 'runtime', 'getDisplaySettingsRuntime', 200],
            'GET display-settings/definitions' => [$settings, 'definitions', 'listDisplaySettingDefinitions', 200],
            'GET display-settings/values' => [$settings, 'values', 'listDisplaySettingValues', 200],
            'PATCH display-settings' => [$settings, 'update', 'updateDisplaySettings', 200],
            'POST display-settings/actions/reset' => [$settings, 'reset', 'resetDisplaySettings', 200],
            'GET resources/(:num)/numbering-series' => [$numbering, 'listing', 'listNumberingSeries', 200],
            'POST resources/(:num)/numbering-series' => [$numbering, 'create', 'createNumberingSeries', 201],
            'GET resources/(:num)/numbering-series/(:num)' => [$numbering, 'show', 'getNumberingSeries', 200],
            'PATCH resources/(:num)/numbering-series/(:num)' => [$numbering, 'update', 'updateNumberingSeries', 200],
            'POST resources/(:num)/numbering-series/(:num)/actions/set-state' => [$numbering, 'setState', 'setNumberingSeriesState', 200],
            'POST resources/(:num)/numbering-series/actions/validate-config' => [$numbering, 'validateConfig', 'validateNumberingSeriesConfig', 200],
            'GET business-workflow/(:segment)/states' => [$workflow, 'states', 'getBusinessWorkflowStates', 200],
            'GET business-workflow/(:segment)/(:num)/options' => [$workflow, 'options', 'getBusinessWorkflowOptions', 200],
            'POST business-workflow/(:segment)/(:num)/actions/(:segment)' => [$workflow, 'execute', 'executeBusinessWorkflowAction', 200],
        ];
    }

    /** @param array<string,array<string,array|string>> $registered */
    public static function validateRegistered(array $registered): void
    {
        foreach (self::expected() as $key => [$class,$method]) {
            [$verb,$path] = explode(' ', $key, 2);
            if (($registered[$verb]['api/v1/'.$path] ?? null) !== [$class, $method]) {
                throw new RuntimeException('M4 missing or unreviewed registered route: '.$key);
            }
        }
        $count = 0;
        foreach ($registered as $verb=>$routes) if ($verb !== 'OPTIONS') $count += count($routes);
        if ($count !== 84) throw new RuntimeException('M4 expected exactly 84 routes, found '.$count);
    }

    /** @return array<string,mixed> */
    public static function verify(string $root): array
    {
        $prior = M34DAcceptance::contract($root);
        if (($prior['implemented_routes'] ?? null) !== 70) throw new RuntimeException('Pinned M3.4D route footprint changed');
        M34Contract::operations($root);
        $manifest = $root.'/docs/backend-rewrite/openapi-operations-v1.2.4.json';
        if (!is_file($manifest)) $manifest = $root.'/backend/tests/fixtures/baseline_v1_2_4/contract/openapi-operations-v1.2.4.json';
        if (!is_file($manifest) || !hash_equals(M34Contract::MANIFEST_SHA256, (string) hash_file('sha256',$manifest))) {
            throw new RuntimeException('M4 baseline manifest SHA-256 mismatch');
        }
        $document = json_decode((string) file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);
        $baseline = [];
        foreach ($document['operations'] as $op) $baseline[$op['method'].' '.$op['path']] = $op;
        if (count($baseline) !== 150) throw new RuntimeException('M4 baseline operations changed');
        $source = (string) file_get_contents($root.'/backend/app/Config/M4Routes.php');
        preg_match_all('~\\$routes->(get|post|patch|put|delete)\(\x27([^\x27]+)\x27,\s*\[(\\\\App\\\\Modules\\\\[A-Za-z0-9_\\\\]+)::class,\x27([A-Za-z0-9_]+)\x27\]\);~', $source, $all, PREG_SET_ORDER);
        if (count($all) !== 14 || preg_match_all('/\$routes->(?:get|post|patch|put|delete|options)\s*\(/', $source)!==14) {
            throw new RuntimeException('Exactly 14 explicitly owned M4 routes required');
        }
        $matched=[];
        foreach ($all as $row) {
            $key = strtoupper($row[1]).' '.$row[2];
            $handler = ltrim($row[3],'\\');
            if (isset($matched[$key]) || !isset(self::expected()[$key])) throw new RuntimeException('Unreviewed M4 route '.$key);
            [$expectedClass,$expectedMethod,$operationId,$status] = self::expected()[$key];
            if ($handler !== $expectedClass || $row[4] !== $expectedMethod) throw new RuntimeException('M4 wrong handler '.$key);
            $handlerFile=$root.'/backend/app/'.str_replace('\\','/',substr($handler,4)).'.php';
            $handlerCode=is_file($handlerFile) ? (string)file_get_contents($handlerFile) : '';
            if (preg_match('/\bpublic\s+function\s+'.preg_quote($expectedMethod,'/').'\s*\(/',$handlerCode)!==1) {
                throw new RuntimeException('M4 controller missing method '.$key);
            }
            $path='/api/v1/'.$row[2];
            if (str_contains($path,'numbering-series')) {
                $path=preg_replace('/\(:num\)/','{resource_id}',$path,1);
                $path=str_replace('(:num)','{series_id}',$path);
            } elseif (str_contains($path,'business-workflow')) {
                $path=preg_replace('/\(:segment\)/','{resource}',$path,1);
                $path=str_replace('(:num)','{id}',$path);
                $path=str_replace('(:segment)','{action_code}',$path);
            }
            $reference=$baseline[strtoupper($row[1]).' '.$path] ?? null;
            if (($reference['operation_id'] ?? null) !== $operationId || ($reference['success_statuses'] ?? null) !== [$status]) {
                throw new RuntimeException('M4 official baseline method/path/status mismatch '.$key);
            }
            $matched[$key]=$operationId;
        }
        if (count($matched)!==14) throw new RuntimeException('M4 reviewed operation missing');
        if (!str_contains((string)file_get_contents($root.'/backend/app/Config/Routes.php'),"require __DIR__ . '/M4Routes.php';")) {
            throw new RuntimeException('M4 route registration not included');
        }
        return [
            'existing_routes'=>70,'m4_routes'=>count($matched),'total_routes'=>84,
            'baseline_operations'=>150,'m4_operation_ids'=>array_values($matched),
            'scope'=>'Static pinned reference and owner method; HTTP/DB M4 not proven',
        ];
    }
}
