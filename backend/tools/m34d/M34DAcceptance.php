<?php

declare(strict_types=1);

/**
 * M3.4D: source/golden-master acceptance gates. No CI4 bootstrap, DB or HTTP.
 * All statuses explicitly distinguish static proof, local sandbox evidence,
 * and the still-missing independent baseline live-parity/production gates.
 */
require_once dirname(__DIR__) . '/m34b/M34BPlan.php';
require_once __DIR__ . '/M34DSourceFingerprint.php';

final class M34DViolation extends RuntimeException {}

final class M34DAcceptance
{
    public const ROUTE_COUNT = 70;
    public const BASELINE_OPERATION_COUNT = 150;
    public const MUTATION_COUNT = 29;
    // Frozen M3.4B.5 footprint: HTTP method, route and method name (sorted).
    public const ROUTE_FOOTPRINT_SHA256 = '136b7c3c9263a4ceb3ee7c35d398c1d6cebf4e34ad0fecfc30de731339314315';

    /** @return array<string,mixed> */
    public static function source(string $root): array
    {
        $checks = [
            'OPENAPI_70' => static fn (): array => self::contract($root),
            'MODULE_GRAPH' => static fn (): array => self::architecture($root),
            'PBAC_STATE' => static fn (): array => self::pbacState($root),
            'SHIELD_WORKFLOW' => static fn (): array => self::identityWorkflow($root),
            'HTTP_SECURITY' => static fn (): array => self::httpSecurity($root),
            'BASELINE_LOCALES' => static fn (): array => self::baselineLocale($root),
        ];
        $gates = [];
        foreach ($checks as $id => $callback) {
            try {
                $gates[$id] = ['status'=>'PASS', 'level'=>'STATIC_PINNED', 'details'=>$callback()];
            } catch (Throwable $e) {
                // Never expose source content, .env credentials or stack traces.
                $gates[$id] = ['status'=>'FAIL', 'level'=>'STATIC_PINNED',
                    'reason'=>substr($e->getMessage(), 0, 400)];
            }
        }
        return $gates;
    }

    /**
     * Exactly the 70 installed HTTP routes map to *distinct* official v1.2.4
     * operations with matching method/path. No heuristic about response body:
     * live HTTP parity for all 70 remains explicitly open.
     *
     * @return array<string,mixed>
     */
    public static function contract(string $root, ?string $routesOverride = null): array
    {
        $ops = M34Contract::operations($root); // independently SHA-256-pinned YAML and manifest
        M34BPlan::assertCoverage($ops);
        $manifestPath = $root.'/docs/backend-rewrite/openapi-operations-v1.2.4.json';
        if (!is_file($manifestPath)) {
            $manifestPath = $root.'/backend/tests/fixtures/baseline_v1_2_4/contract/openapi-operations-v1.2.4.json';
        }
        self::must(is_file($manifestPath)
            && hash_equals(M34Contract::MANIFEST_SHA256, (string) hash_file('sha256', $manifestPath)),
            'Baseline operation manifest SHA-256 mismatch');
        $document = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $baseline = $document['operations'] ?? null;
        self::must(is_array($baseline) && count($baseline) === self::BASELINE_OPERATION_COUNT,
            'Baseline manifest must contain exactly 150 operations');
        $reference = [];
        foreach ($baseline as $op) {
            self::must(is_string($op['method'] ?? null) && is_string($op['path'] ?? null)
                && is_string($op['operation_id'] ?? null) && is_array($op['success_statuses'] ?? null),
                'Invalid baseline operation record');
            $key = $op['method'].' '.$op['path'];
            self::must(!isset($reference[$key]), 'Duplicate baseline route '.$key);
            $reference[$key] = $op;
        }
        $routesPath = $root.'/backend/app/Config/Routes.php';
        $routes = self::withoutComments($routesOverride ?? self::read($routesPath));
        self::must(str_contains($routes, '$routes->setAutoRoute(false)'), 'CI4 autorouting must remain disabled');
        preg_match_all("/\\\$routes->(get|post|patch|put|delete)\\('([^']+)',\\s*\\[([A-Za-z0-9_]+)::class,\\s*'([^']+)'\\]\\);/",
            $routes, $matches, PREG_SET_ORDER);
        self::must(count($matches) === self::ROUTE_COUNT,
            'Expected 70 explicit non-OPTIONS HTTP routes; found '.count($matches));
        preg_match_all('/^use\\s+(App\\\\[^;]+);/m', $routes, $imports, PREG_SET_ORDER);
        $uses = [];
        foreach ($imports as $entry) {
            $class = substr($entry[1], strrpos($entry[1], '\\') + 1);
            self::must(!isset($uses[$class]), 'Duplicate controller import '.$class);
            $uses[$class] = $entry[1];
        }
        $seen = [];
        $footprint = [];
        $operationIds = [];
        $statusCounts = [];
        foreach ($matches as $record) {
            [$raw, $verb, $uri, $controller, $method] = $record;
            $httpMethod = strtoupper($verb);
            $routePath = '/api/v1/'.$uri;
            $normalized = str_replace('(:num)', '{id}', $routePath);
            // Two nested IDs exist only in the Resource Action subresource.
            if (str_contains($normalized, '/resources/{id}/actions/{id}')) {
                $normalized = str_replace('/actions/{id}', '/actions/{action_id}', $normalized);
            }
            $key = $httpMethod.' '.$normalized;
            self::must(!isset($seen[$key]), 'Duplicate installed route '.$key);
            $seen[$key] = true;
            self::must(isset($reference[$key]), 'Unreviewed v2 route '.$key);
            $op = $reference[$key];
            self::must(count($op['success_statuses']) === 1,
                'Ambiguous baseline success status for '.$key);
            $operationIds[] = $op['operation_id'];
            $success = (int) $op['success_statuses'][0];
            $statusCounts[(string) $success] = ($statusCounts[(string) $success] ?? 0) + 1;
            self::must(isset($uses[$controller]), 'Route controller import missing: '.$controller);
            $file = $root.'/backend/app/'.str_replace('\\', '/', substr($uses[$controller], 4)).'.php';
            self::must(is_file($file), 'Route controller class missing: '.$controller);
            $content = self::withoutComments(self::read($file));
            self::must(preg_match('/\\b(?:public)\\s+function\\s+'.preg_quote($method, '/').'\\s*\\(/', $content) === 1,
                'Route handler is missing '.$controller.'::'.$method);
            self::must(self::ownerMatches($uri, $controller),
                'Wrong module owns route '.$key.' ('.$controller.')');
            $footprint[] = $httpMethod.' '.$routePath.' #'.$method;
        }
        self::must(count(array_unique($operationIds)) === self::ROUTE_COUNT, 'Operation IDs reused by routes');
        sort($footprint, SORT_STRING);
        self::must(hash_equals(self::ROUTE_FOOTPRINT_SHA256, hash('sha256', implode("\n", $footprint))),
            'M3.4B.5 70-route HTTP footprint changed');
        foreach ($ops as $name => $op) {
            self::must(in_array($name, $operationIds, true), 'Missing pinned mutation: '.$name);
        }
        self::must(substr_count($routes, '$routes->options(') === 2, 'Reviewed OPTIONS routes changed');
        ksort($statusCounts, SORT_STRING);
        return [
            'baseline_operations'=>count($reference),
            'implemented_routes'=>count($seen),
            'pinned_mutations'=>count($ops),
            'implemented_operation_ids'=>$operationIds,
            'baseline_success_status_reference_counts'=>$statusCounts,
            'route_footprint_sha256'=>self::ROUTE_FOOTPRINT_SHA256,
            'scope'=>'Static path/method/handler and reference status only; no 70-route HTTP response proof',
        ];
    }

    private static function ownerMatches(string $uri, string $controller): bool
    {
        if (str_starts_with($uri, 'auth/')) return $controller === 'AuthController';
        if ($uri === 'health') return $controller === 'HealthController';
        if (str_starts_with($uri, 'security/users') || str_starts_with($uri, 'users/')) {
            return in_array($controller, ['IdentityReadController', 'SecurityUserMutationController'], true);
        }
        if ($uri === 'users') return $controller === 'SecurityUserMutationController';
        if (preg_match('~^(workflow-states|lifecycles)(?:/|$)~', $uri)) {
            return in_array($controller, ['WorkflowReadController', 'WorkflowMutationController'], true);
        }
        if (preg_match('~^(authorization-groups|authorization-logs|resources|policies)(?:/|$)~', $uri)) {
            return in_array($controller, ['AccessReadController', 'AccessMutationController'], true);
        }
        return $controller === 'ReadCatalogController';
    }

    /** @return array<string,mixed> */
    public static function architecture(string $root): array
    {
        $modules = $root.'/backend/app/Modules/';
        $graph = [];
        $counts = [];
        foreach (new DirectoryIterator($modules) as $folder) {
            if (!$folder->isDir() || $folder->isDot()) continue;
            $name = $folder->getFilename();
            $graph[$name] = [];
            $counts[$name] = 0;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder->getPathname(), FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') continue;
                ++$counts[$name];
                $source = self::read($file->getPathname());
                self::must(str_contains($source, 'namespace App\\Modules\\'.$name),
                    'Cross-owner namespace in '.$name.'/'.$file->getFilename());
                self::must(!str_contains($source, 'class_alias('), 'Compatibility class alias in '.$name);
                foreach (self::moduleReferences($source) as $dep) {
                    if ($dep !== $name) $graph[$name][$dep] = true;
                }
            }
        }
        foreach ($graph as $module=>$targets) {
            foreach ($targets as $target=>$_) {
                self::must(isset($graph[$target]), 'Reference to unknown module '.$module.' -> '.$target);
            }
            $graph[$module] = array_keys($targets);
            sort($graph[$module], SORT_STRING);
        }
        ksort($graph, SORT_STRING);
        self::assertAcyclic($graph);
        foreach (['Identity','Access','Workflow'] as $name) {
            self::must(isset($graph[$name]), 'Missing owner module '.$name);
        }
        self::must(!in_array('Identity',$graph['Access'],true) && !in_array('Workflow',$graph['Access'],true),
            'Access must not depend on Identity or Workflow');
        self::must(!in_array('Workflow',$graph['Identity'],true) && !in_array('Identity',$graph['Workflow'],true),
            'Direct Identity <-> Workflow module dependency prohibited');
        foreach (['Identity','Workflow'] as $module) {
            self::must(in_array('Access', $graph[$module], true),
                'Module '.$module.' must reference centralized Access PBAC');
        }
        foreach (['Identity','Workflow'] as $module) {
            self::must($counts[$module] >= 7, 'Missing moved '.$module.' implementation');
        }
        foreach (['SecurityUserMutationController','SecurityUserMutationService','SecurityUserMutationRepository',
            'SecurityUserMutationRules','WorkflowMutationController','WorkflowMutationService',
            'WorkflowMutationRepository','WorkflowMutationRules'] as $class) {
            self::must(!is_file($modules.'Access/Mutation/'.$class.'.php'), 'Obsolete Access class '.$class);
        }
        return ['module_files'=>$counts,'dependency_graph'=>$graph,
            'owner_rules'=>'Access does not import Identity/Workflow; graph acyclic; owner routes validated separately'];
    }

    /** Remove PHP comments, never confusing commented-out handlers with live routes. */
    public static function withoutComments(string $source): string
    {
        $code='';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0],[T_COMMENT,T_DOC_COMMENT],true)) continue;
            $code .= is_array($token) ? $token[1] : $token;
        }
        return $code;
    }

    /** @return list<string> */
    public static function moduleReferences(string $source): array
    {
        $text = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                    $text .= ' ';
                } else {
                    $text .= $token[1];
                }
            } else { $text .= $token; }
        }
        preg_match_all('~App\\\\Modules\\\\([A-Za-z][A-Za-z0-9_]*)\\\\~', $text, $refs);
        return array_values(array_unique($refs[1]));
    }

    /** @param array<string,list<string>> $graph */
    public static function assertAcyclic(array $graph): void
    {
        $colors = [];
        $visit = static function (string $node) use (&$visit, &$colors, $graph): void {
            if (($colors[$node] ?? 0) === 1) throw new M34DViolation('Circular module dependency detected at '.$node);
            if (($colors[$node] ?? 0) === 2) return;
            if (!array_key_exists($node, $graph)) throw new M34DViolation('Unknown referenced module '.$node);
            $colors[$node] = 1;
            foreach ($graph[$node] as $dep) $visit($dep);
            $colors[$node] = 2;
        };
        foreach (array_keys($graph) as $name) $visit($name);
    }

    /** @return array<string,mixed> */
    public static function pbacState(string $root): array
    {
        require_once $root.'/backend/app/Modules/Access/ResourceStateCatalog.php';
        $types = \App\Modules\Access\ResourceStateCatalog::independentTypes();
        self::must(count($types) === 10 && count(array_unique($types)) === 10, 'Expected exactly 10 independent state types');
        $native = $all = [];
        foreach ($types as $type) {
            $mode = \App\Modules\Access\ResourceStateCatalog::mode($type);
            if ($mode === 'NATIVE') $native[] = $type;
            elseif ($mode === 'ALL_STATES') $all[] = $type;
            else throw new M34DViolation('Independent resource cannot be Lifecycle managed');
        }
        sort($native); sort($all);
        self::must(count($native) === 8 && $all === ['authorization_log','display_setting'],
            '8 Native/2 All States classification changed');
        self::must(\App\Modules\Access\ResourceStateCatalog::mode('product') === 'LIFECYCLE',
            'Unknown business Resource cannot fallback to Native State');
        $read = static fn (string $path): string => self::read($root.'/backend/app/Modules/'.$path);
        $decision = $read('Access/PbacDecision.php');
        self::must(str_contains($decision,'ABSOLUTE_DENY') && str_contains($decision,'DENY')
            && str_contains($decision,'ALLOW'), 'Three authoritative PBAC effects missing');
        $central = $read('Access/UnifiedAccessService.php');
        self::must(str_contains($central, 'decideMutation(') && str_contains($central, 'persistedStateId('),
            'Central PBAC must use persisted State and mutation Action');
        foreach (['Identity/Mutation/SecurityUserMutationService.php', 'Workflow/Mutation/WorkflowMutationService.php'] as $path) {
            self::must(str_contains($read($path), 'new UnifiedAccessService('),
                'Owner module bypasses central authorization: '.$path);
        }
        return ['native'=>count($native), 'all_states'=>count($all), 'lifecycle_other_types'=>true,
            'pbac'=>'Centralized persisted-State Action decisions; runtime samples are a separate DB-READ gate'];
    }

    /** @return array<string,mixed> */
    public static function identityWorkflow(string $root): array
    {
        $prefix = $root.'/backend/app/Modules/';
        $repo = self::read($prefix.'Identity/Mutation/SecurityUserMutationRepository.php');
        $service = self::read($prefix.'Identity/Mutation/SecurityUserMutationService.php');
        $workflow = self::read($prefix.'Workflow/Mutation/WorkflowMutationService.php');
        $rules = self::read($prefix.'Workflow/Mutation/WorkflowMutationRules.php');
        self::must(str_contains($repo,'model(UserModel::class')
            && str_contains($repo, 'setEmail(') && !preg_match('/["\']password["\']\s*=>\s*\$data\[/',$repo),
            'Shield User must own password/email persistence');
        self::must(str_contains($service,'setPassword(') && str_contains($service,'transRollback()'),
            'Shield write/rollback boundaries changed');
        self::must(str_contains($workflow,'transRollback()')
            && str_contains($rules,'assertConfiguration(')
            && str_contains($rules,'WF_STATE_NOT_IN_LIFECYCLE'),
            'Lifecycle transaction/graph validation missing');
        $controller = self::read($prefix.'Workflow/Mutation/WorkflowMutationController.php');
        self::must(str_contains($controller, '->configure($id, $this->json())')
            && substr_count($controller, '->configure($id, $this->json())') === 2,
            'Baseline Lifecycle.configure() append semantics changed');
        return ['shield'=>'Shield model, setPassword and SQL rollback boundaries verified statically',
            'workflow'=>'Shared configure() with Lifecycle graph validation and rollback',
            'runtime'=>'Shield hash and forced Policy Action rollback verified separately by M3.4B integration'];
    }

    /** @return array<string,mixed> */
    public static function httpSecurity(string $root): array
    {
        $app = $root.'/backend/app/';
        $routes = self::read($app.'Config/Routes.php');
        $filters = self::read($app.'Config/Filters.php');
        $security = self::read($app.'Config/Security.php');
        $responder = self::read($app.'Shared/Http/ApiResponder.php');
        $envelope = self::read($app.'Shared/Http/ApiEnvelope.php');
        self::must(str_contains($routes,'$routes->setAutoRoute(false)'), 'Autorouting enabled');
        self::must(str_contains($filters,"'before' => ['cors', 'requestmeta', 'csrf']"), 'Global CSRF filter missing');
        self::must(str_contains($security,"public string \$csrfProtection = 'session'")
            && str_contains($security,'public bool $redirect = false'),
            'API CSRF must be session-backed and reject without redirect');
        foreach (['Identity/Mutation/SecurityUserMutationController.php',
            'Workflow/Mutation/WorkflowMutationController.php',
            'Access/Mutation/AccessMutationController.php'] as $path) {
            $text = self::read($app.'Modules/'.$path);
            self::must(str_contains($text,"env('DINKERP_M33B_WRITE_MODE', 'off')")
                && str_contains($text,"'ACCESS_MUTATION_NOT_ENABLED'"),
                'Write gate must default OFF: '.$path);
        }
        self::must(str_contains($responder, "'Content-Language'")
            && str_contains($responder,"'X-Request-ID'")
            && str_contains($envelope,"'details'") && str_contains($envelope,"'request_id'"),
            'HTTP bilingual/error envelope required fields missing');
        return ['write_gate'=>'off by default on all three mutation controllers',
            'csrf'=>'session + global filter + no redirect',
            'envelope'=>'error.code/message/details, meta.request_id, Content-Language, X-Request-ID'];
    }

    /** @return array<string,mixed> */
    public static function baselineLocale(string $root): array
    {
        $manifestPath = $root.'/backend/tests/fixtures/baseline_v1_2_4/contract/language_sha256.json';
        self::must(hash_equals('472026ac03268a333dc5301dd8acca1262646f48ff59103a4100dfabda092eee', (string)hash_file('sha256',$manifestPath)),
            'Frozen v1.2.4 locale SHA-256 manifest was modified');
        $manifest = json_decode(self::read($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $files = $manifest['files'] ?? null;
        self::must(is_array($files) && count($files) === 74, 'Pinned v1.2.4 locale file count must be 74');
        $counts = ['en'=>0, 'vi'=>0];
        foreach ($files as $name=>$expected) {
            self::must(preg_match('~\A(en|vi)/[A-Za-z0-9]+\.php\z~D', (string)$name) === 1,
                'Unexpected locale manifest entry');
            $path = $root.'/backend/app/Language/'.$name;
            self::must(is_file($path) && hash_equals((string)$expected, (string)hash_file('sha256',$path)),
                'Baseline locale byte mismatch: '.$name);
            ++$counts[explode('/',$name,2)[0]];
        }
        self::must($counts === ['en'=>23,'vi'=>51], 'Baseline locale distribution changed');
        $en = require $root.'/backend/app/Language/en/Api.php';
        $vi = require $root.'/backend/app/Language/vi/Api.php';
        self::must(is_array($en) && is_array($vi) && array_keys($en) === array_keys($vi),
            'API bilingual error keys differ');
        foreach (['invalidRequest','unauthenticated','forbidden','notFound','validation','conflict','serverError'] as $key) {
            self::must(is_string($en[$key] ?? null) && is_string($vi[$key] ?? null)
                && $en[$key] !== $vi[$key], 'Error translation missing: '.$key);
        }
        return ['baseline_catalogs'=>74, 'en'=>23, 'vi'=>51, 'api_keys'=>count($en),
            'scope'=>'Baseline-pinned files/messages; negotiation still requires real HTTP locale probes'];
    }

    /**
     * Strictly validate *existing* M3.4B JSON report, never run SQL/HTTP.
     * It can attest local sandbox coverage; it cannot create baseline live evidence.
     * @param array<string,mixed> $report
     * @param array<string,array{method:string,path:string,status:int}> $ops
     * @return array<string,mixed>
     */
    public static function integrationEvidence(array $report, array $ops, string $expectedSourceSha): array
    {
        self::must(($report['milestone'] ?? null)==='M3.4B'
            && ($report['mode'] ?? null)==='AUTO_LOCAL_SANDBOX'
            && ($report['status'] ?? null)==='PASS'
            && ($report['production_mutations'] ?? null)==='NEVER'
            && ($report['baseline_live_parity'] ?? null)==='NOT_TESTED',
            'Only a successful isolated M3.4B report is acceptable');
        self::must(preg_match('/\A[0-9a-f]{64}\z/D', $expectedSourceSha) === 1
            && ($report['backend_source_sha256'] ?? null) === $expectedSourceSha,
            'Sandbox report comes from a different runtime version, or predates M3.4D fingerprinting');
        $real = $report['integration'] ?? null;
        self::must(is_array($real) && ($real['verdict'] ?? null)==='PASS'
            && ($real['contract_operations'] ?? null)===29
            && ($real['successful_positive_mutations'] ?? null)===29
            && ($real['unauthenticated_bilingual_probes'] ?? null)===58
            && ($real['invalid_csrf_probes'] ?? null)===29
            && ($real['authenticated_invalid_bilingual_probes'] ?? null)===58
            && is_int($real['distinct_translated_error_pairs'] ?? null)
            && $real['distinct_translated_error_pairs'] > 0
            && ($real['child_database_attested'] ?? null)===true
            && ($real['shield_session_authenticated'] ?? null)===true
            && ($real['real_db_rollback'] ?? null)==='PASS_DB_ATOMICITY_NOT_BASELINE_PARITY'
            && ($real['failed'] ?? null) === [], 'M3.4B integration counts, safeguards or rollback did not PASS');
        $name = $real['sandbox_name'] ?? null;
        self::must(is_string($name) && preg_match('/\Aerp_m34b_[0-9a-f]{16}_sandbox\z/D',$name)===1
            && ($report['expected_sandbox'] ?? null)===$name,
            'Sandbox proof is missing or incorrectly named');
        $results = $real['results'] ?? null;
        self::must(is_array($results) && count($results)>=29, 'M3.4B per-operation HTTP evidence missing');
        $seen = [];
        foreach ($results as $item) {
            if (!is_array($item)) continue;
            $name = $item['case'] ?? null;
            if (!is_string($name) || !isset($ops[$name])) continue;
            self::must(!isset($seen[$name]) && ($item['pass'] ?? null)===true
                && ($item['expected_status'] ?? null)===$ops[$name]['status']
                && ($item['actual_status'] ?? null)===$ops[$name]['status']
                && is_array($item['changed_tables'] ?? null)
                && count($item['changed_tables'])>0, 'Invalid or duplicate successful mutation evidence: '.$name);
            $seen[$name]=true;
        }
        self::must(count($seen)===29, 'All 29 distinct mutation receipts are required');
        $required = ['shield_hash_after_reset','real_absolute_deny_veto','forced_mariadb_policy_action_insert_rollback'];
        foreach ($required as $name) {
            $found = false;
            foreach ($results as $item) {
                if (is_array($item) && ($item['case'] ?? null)===$name && ($item['pass'] ?? null)===true) {
                    $found=true;
                    break;
                }
            }
            self::must($found, 'Missing distinct MariaDB proof: '.$name);
        }
        return ['status'=>'PASS','level'=>'LOCAL_MARIADB_SANDBOX',
            'counts'=>['mutations'=>29,'unauthenticated'=>58,'csrf'=>29,'invalid_bilingual'=>58],
            'rollback'=>'PASS_DB_ATOMICITY_NOT_BASELINE_PARITY',
            'shield_password_hash'=>true, 'absolute_deny_veto'=>true,
            'scope'=>'Local HTTP and SQL evidence, not independent baseline HTTP or concurrency parity'];
    }

    private static function must(bool $ok, string $reason): void
    {
        if (!$ok) throw new M34DViolation($reason);
    }
    private static function read(string $file): string
    {
        if (!is_file($file) || !is_readable($file)) throw new M34DViolation('Missing reviewed source: '.basename($file));
        $bytes = file_get_contents($file);
        if (!is_string($bytes)) throw new M34DViolation('Cannot read reviewed source: '.basename($file));
        return $bytes;
    }
}
