<?php

declare(strict_types=1);

namespace DinkERP\Tools\Architecture;

/**
 * Dependency-growth scanner for the staged v1.1.1 architecture baseline.
 *
 * It is NOT a proof of runtime calls, public method use or dynamic SQL writes.
 * The frozen snapshot represents OBSERVED (not approved) legacy, and may not
 * be used to authorize a new dependency. No application code is loaded.
 */
final class ModuleBoundaryScanner
{
    /** @param array<string,mixed> $policy
     *  @param array<string,mixed> $baseline
     */
    public function __construct(
        private readonly array $policy,
        private readonly array $baseline,
    ) {
    }

    /** @return array<string,mixed> */
    public function scan(string $root): array
    {
        $app = rtrim($root, '/\\') . '/backend/app';
        if (! is_dir($app)) {
            throw new \RuntimeException('Missing backend/app: ' . $app);
        }
        $modules = $this->policy['modules'] ?? [];
        $owners = $this->policy['table_owners'] ?? [];
        if (! is_array($modules) || ! is_array($owners) || count($modules) === 0) {
            throw new \RuntimeException('Invalid architecture policy: module/table owner registry');
        }
        $existing = array_fill_keys($this->baseline['observed_references'] ?? [], true);
        $existingWrites = array_fill_keys($this->baseline['observed_write_risks'] ?? [], true);
        $existingLegacy = array_fill_keys($this->baseline['legacy_files'] ?? [], true);
        $existingCycles = array_fill_keys($this->baseline['observed_cycles'] ?? [], true);
        $allReferences = [];
        $allWriteRisks = [];
        $legacyFiles = [];
        $graph = [];
        $violations = [];
        $uncovered = [];
        $files = 0;
        foreach ($modules as $module) {
            $graph[$module] = [];
        }
        foreach ($this->phpFiles($app) as $absolute) {
            $relative = 'backend/app/' . substr($absolute, strlen($app) + 1);
            $relative = str_replace('\\', '/', $relative);
            $contents = (string) file_get_contents($absolute);
            $files++;
            $sourceModule = $this->sourceModule($relative);
            $isInfrastructure = str_starts_with($relative, 'backend/app/Infrastructure/');
            $isController = str_starts_with($relative, 'backend/app/Controllers/');
            $isLegacy = preg_match('~\Abackend/app/(?:Services|Repositories)/[^/]+\.php\z~', $relative) === 1;
            if ($isLegacy) {
                $legacyFiles[] = $relative;
                if (! isset($existingLegacy[$relative])) {
                    $violations[] = $this->issue('ARCH-04', $relative, 'New top-level legacy class is not permitted without a reviewed exception');
                }
            }
            if ($sourceModule === null && ! $isInfrastructure && ! $isController) {
                continue;
            }
            if ($isController && preg_match('~->\s*(?:table|transBegin|transCommit|transRollback)\s*\(|(?:\$db|\$connection|\$this->db)\s*->\s*query\s*\(~', $this->withoutComments($contents)) === 1) {
                $violations[] = $this->issue('ARCH-02', $relative, 'Controller directly accesses SQL or a transaction');
            }
            foreach ($this->qualifiedNames($contents) as $target) {
                $provider = $this->targetModule($target);
                $targetIsLegacy = preg_match('~\AApp\\\\(?:Services|Repositories|Authorization|Bootstrap)\\\\~', $target) === 1;
                $targetIsRepository = preg_match('~(?:Repository|RepositoryInterface|RepositoryPort)\z~', $target) === 1;
                $governed = ($sourceModule !== null && (($provider !== null && $sourceModule !== $provider) || $targetIsLegacy))
                    || ($isInfrastructure && $provider !== null)
                    || ($isController && $targetIsRepository && ($provider !== null || str_starts_with($target, 'App\\Repositories\\')));
                if (! $governed) {
                    continue;
                }
                $key = $relative . '|' . $target;
                $allReferences[$key] = true;
                if ($sourceModule !== null && $provider !== null && $sourceModule !== $provider) {
                    $graph[$sourceModule][$provider] = true;
                }
                if (isset($existing[$key])) {
                    // Historical observation is NOT a public API approval.
                    continue;
                }
                $rule = null;
                if ($sourceModule === 'Product' && $provider === 'Pricing') {
                    $rule = 'ARCH-02: Product must not import Pricing';
                } elseif ($isInfrastructure && $provider !== null) {
                    $rule = 'ARCH-02: generic Infrastructure must not import business modules';
                } elseif ($isController && $targetIsRepository) {
                    $rule = 'ARCH-02: HTTP controller must not import repositories';
                } elseif ($sourceModule !== null && $provider !== null &&
                    ($targetIsRepository || preg_match('~\\\\(?:Infrastructure|Internal|Compatibility)\\\\~', $target) === 1)) {
                    $rule = 'ARCH-01: consumer must not import another owner internal implementation';
                } elseif ($sourceModule !== null && $targetIsLegacy) {
                    $rule = 'ARCH-04: canonical module must not gain new legacy dependency';
                } elseif ($sourceModule !== null && $provider !== null
                    && $this->approvedPublicImport($sourceModule, $provider, $target)) {
                    continue;
                } else {
                    $rule = 'ARCH-01: new cross-module import has no approved public contract';
                }
                $violations[] = $this->issue(substr($rule, 0, 7), $relative, $rule . ' -> ' . $target);
            }
            if ($sourceModule !== null || $isInfrastructure) {
                foreach ($this->directWrites($contents) as $write) {
                    $owner = $owners[$write['table']] ?? null;
                    $writer = $sourceModule ?? 'Infrastructure';
                    if ($owner === null) {
                        $uncovered[] = $relative . '|' . $write['table'] . '|' . $write['verb'];
                        continue;
                    }
                    // Infrastructure/Import owns import job tables, not Product mutations.
                    $allowedWriter = $sourceModule ?? (str_starts_with($relative, 'backend/app/Infrastructure/Import/') ? 'ImportInfrastructure' : 'Infrastructure');
                    if ($owner === $allowedWriter) {
                        continue;
                    }
                    $key = $relative . '|' . $write['table'] . '|' . $write['verb'];
                    $allWriteRisks[$key] = true;
                    if (! isset($existingWrites[$key])) {
                        $violations[] = $this->issue('ARCH-03', $relative,
                            'Possible direct cross-owner ' . $write['verb'] . ' on ' . $write['table'] .
                            ' (owner=' . $owner . ', writer=' . $writer . '); manual SQL review required');
                    }
                }
            }
        }
        // Report unexpected roots (but never create a new root automatically).
        $moduleRoot = $app . '/Modules';
        foreach (scandir($moduleRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || ! is_dir($moduleRoot . '/' . $entry)) {
                continue;
            }
            if (! in_array($entry, $modules, true)) {
                $violations[] = $this->issue('ARCH-02', 'backend/app/Modules/' . $entry,
                    'Root module not approved in architecture policy');
            }
        }
        $cycles = $this->stronglyConnectedCycles($graph);
        foreach ($cycles as $cycle) {
            if (! isset($existingCycles[$cycle])) {
                $violations[] = $this->issue('ARCH-02', 'module-dependency-graph', 'New transitive module cycle: ' . $cycle);
            }
        }
        $actual = array_keys($allReferences);
        $risks = array_keys($allWriteRisks);
        sort($actual);
        sort($risks);
        sort($legacyFiles);
        sort($uncovered);
        $retired = array_values(array_diff(array_keys($existing), $actual));
        sort($retired);
        $violations = array_values(array_unique($violations, SORT_REGULAR));

        return [
            'status' => $violations === [] ? 'PASS_NEW_EDGE_GATE_ONLY' : 'FAIL',
            'standard' => 'DINKERP-BE-ARCH-001 v1.1.1 (PROPOSED)',
            'coverage' => 'static PHP qualified names + direct literal SQL-builder writes only',
            'files_scanned' => $files,
            'observed_legacy_references' => count(array_intersect($actual, array_keys($existing))),
            'legacy_files' => count($legacyFiles),
            'baseline_write_risks' => count(array_intersect($risks, array_keys($existingWrites))),
            'cycles' => $cycles,
            'new_violations' => $violations,
            'baseline_references_to_review' => $actual,
            'baseline_write_risks_to_review' => $risks,
            'unknown_table_writes_to_review' => array_values(array_unique($uncovered)),
            'retired_baseline_references' => $retired,
            'open_gates' => ['ARCH-01 method calls require owner review', 'ARCH-03 dynamic SQL writes require review and MariaDB tests', 'ARCH-05 runtime/bootstrap recursion not verified'],
        ];
    }

    /** @return list<string> */
    public function qualifiedNames(string $php): array
    {
        $names = [];
        foreach (token_get_all($php) as $token) {
            if (! is_array($token) || ! in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }
            $name = ltrim($token[1], '\\');
            if (str_starts_with($name, 'App\\')) {
                $names[$name] = true;
            }
        }
        return array_keys($names);
    }

    /** @return list<array{table:string,verb:string}> */
    public function directWrites(string $php): array
    {
        $found = [];
        $source = $this->withoutComments($php);
        // High-confidence subset: a literal table name and mutation within
        // the same fluent statement. Variable builders and interpolated SQL
        // are NOT covered and must be checked with integration tests/review.
        preg_match_all('~->table\s*\(\s*[\x27\"]([a-z_][a-z_0-9]*)[\x27\"]\s*\)([^;{}]*)~is', $source, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (preg_match_all('~->\s*(insert|update|delete)\s*\(~i', $match[2], $operations) < 1) {
                continue;
            }
            foreach ($operations[1] as $verb) {
                $found[$match[1] . '|' . strtolower($verb)] = ['table' => $match[1], 'verb' => strtoupper($verb)];
            }
        }
        // A second conservative subset: a directly embedded SQL string.
        // Dynamic variables, heredocs and prepared/constructed SQL are outside
        // the scanner's coverage and MUST NOT be reported as verified.
        preg_match_all('~->query\s*\(\s*[\x27\"]\s*(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+[`]?([a-z_][a-z_0-9]*)[`]?~i', $source, $sql, PREG_SET_ORDER);
        foreach ($sql as $statement) {
            $verb = str_starts_with(strtoupper($statement[1]), 'INSERT') ? 'INSERT'
                : (str_starts_with(strtoupper($statement[1]), 'DELETE') ? 'DELETE' : 'UPDATE');
            $found[$statement[2] . '|' . strtolower($verb)] = ['table' => $statement[2], 'verb' => $verb];
        }
        return array_values($found);
    }

    private function withoutComments(string $php): string
    {
        $clean = '';
        foreach (token_get_all($php) as $token) {
            if (! is_array($token)) {
                $clean .= $token;
            } elseif ($token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
                $clean .= $token[1];
            } else {
                $clean .= str_repeat(' ', strlen($token[1]));
            }
        }
        return $clean;
    }

    /** @param array<string,array<string,bool>> $graph
     *  @return list<string>
     */
    public function stronglyConnectedCycles(array $graph): array
    {
        $index = 0;
        $indices = [];
        $low = [];
        $stack = [];
        $onStack = [];
        $components = [];
        $visit = function (string $node) use (&$visit, &$graph, &$index, &$indices, &$low, &$stack, &$onStack, &$components): void {
            $indices[$node] = $index;
            $low[$node] = $index++;
            $stack[] = $node;
            $onStack[$node] = true;
            foreach (array_keys($graph[$node] ?? []) as $next) {
                if (! array_key_exists($next, $indices)) {
                    $visit($next);
                    $low[$node] = min($low[$node], $low[$next]);
                } elseif ($onStack[$next] ?? false) {
                    $low[$node] = min($low[$node], $indices[$next]);
                }
            }
            if ($low[$node] !== $indices[$node]) {
                return;
            }
            $component = [];
            do {
                $top = array_pop($stack);
                $onStack[$top] = false;
                $component[] = $top;
            } while ($top !== $node);
            if (count($component) > 1) {
                sort($component);
                $components[] = implode('->', $component);
            }
        };
        foreach (array_keys($graph) as $node) {
            if (! array_key_exists($node, $indices)) {
                $visit($node);
            }
        }
        sort($components);
        return $components;
    }

    private function approvedPublicImport(string $consumer, string $provider, string $class): bool
    {
        foreach ($this->policy['public_apis'] ?? [] as $public) {
            if (($public['class'] ?? null) === $class
                && ($public['module'] ?? null) === $provider
                && ($public['status'] ?? null) === 'APPROVED'
                && in_array($consumer, $public['consumers'] ?? [], true)
                && ($public['methods'] ?? []) !== []) {
                return true;
            }
        }
        return false;
    }

    private function sourceModule(string $path): ?string
    {
        if (preg_match('~\Abackend/app/Modules/([^/]+)/~', $path, $found) !== 1) {
            return null;
        }
        return $found[1];
    }

    private function targetModule(string $qualifiedName): ?string
    {
        return preg_match('~\AApp\\\\Modules\\\\([^\\\\]+)\\\\~', $qualifiedName, $found) === 1 ? $found[1] : null;
    }

    /** @return list<string> */
    private function phpFiles(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }
        sort($files);
        return $files;
    }

    /** @return array{rule:string,path:string,message:string} */
    private function issue(string $rule, string $path, string $message): array
    {
        return ['rule' => $rule, 'path' => $path, 'message' => $message];
    }
}
