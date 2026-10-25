<?php

declare(strict_types=1);

require_once __DIR__.'/../m86b/M86BSourceAcceptance.php';

/** M8.6C People MASTER SQL-first, opt-in source gate. No HTTP/DB connections. */
final class M86CSourceAcceptance
{
    /** @return array{operations:int,people_master:string,read_mode:string,resources:int} */
    public static function verify(string $root): array
    {
        $previous = M86BSourceAcceptance::verify($root);
        if ($previous['operations'] !== 150) {
            throw new RuntimeException('M8.6C must preserve 150/150 frozen API operations.');
        }
        $base = $root . '/backend/app/Modules/';
        $people = $base . 'People/Read/';
        foreach (['PeopleMasterScopeProvider.php', 'PeopleMasterSql.php',
            'PeopleMasterReadService.php'] as $name) {
            if (!is_file($people . $name)) {
                throw new RuntimeException('Missing M8.6C People SQL component: ' . $name);
            }
        }
        $controller = (string)file_get_contents($root . '/backend/app/Controllers/Api/ReadCatalogController.php');
        $provider = (string)file_get_contents($people . 'PeopleMasterScopeProvider.php');
        $sql = (string)file_get_contents($people . 'PeopleMasterSql.php');
        $service = (string)file_get_contents($people . 'PeopleMasterReadService.php');
        $compiler = (string)file_get_contents($base . 'Access/Query/AuthorizationSqlScopeCompiler.php');
        $scope = (string)file_get_contents($base . 'Access/Query/AuthorizedSqlScope.php');
        $canonical = (string)file_get_contents($base . 'Access/UnifiedAccessService.php');
        foreach (['employee', 'department', 'position'] as $resource) {
            if (!str_contains($compiler, "'" . $resource . "'=>'MASTER'")
                || !str_contains($scope, "'" . $resource . "'")
                || !str_contains($canonical, "'" . $resource . "'")) {
                throw new RuntimeException('Unreviewed MASTER SQL provider: ' . $resource);
            }
        }
        foreach ([
            "in_array($" . "resource, ['employee', 'department', 'position'], true)",
            'peopleSqlService($user, $instant)->list(', 'peopleSqlService($user)->show(',
            'ReadAccessBridge($user', 'AuthorizedListPage::paginate(',
            'organizationSqlService($user, $instant)->list(',
        ] as $term) {
            if (!str_contains($controller, $term)) {
                throw new RuntimeException('Canonical People dispatch missing: ' . $term);
            }
        }
        foreach (['DINKERP_M86C_SQL_SCOPE_MODE', 'DINKERP_M32_READ_MODE',
            'peopleSqlEnabled('] as $obsolete) {
            if (str_contains($controller, $obsolete)) {
                throw new RuntimeException('Obsolete People scope switch: '.$obsolete);
            }
        }
        foreach ([
            'COUNT(*) AS total', 'LIMIT ? OFFSET ?', 'scope->predicate(',
            'EXISTS (SELECT 1 FROM ', 'EFFECTIVE_FILTERS',
            'UtcBusinessDate::at(', 'effective_from <= ?',
            'effective_to IS NULL OR ', 'BINARY COALESCE(CAST(',
        ] as $term) {
            if (!str_contains($sql, $term)) throw new RuntimeException('M8.6C SQL guard missing: ' . $term);
        }
        foreach ([
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',
            'transDepth !== 0', 'transBegin()', 'transCommit()',
            'transRollback()', 'resetTransStatus()', 'AUTH_SCOPE_PBAC_MISMATCH',
            'PeopleMasterSql::collection(', 'PeopleMasterSql::detail(',
            'PeopleMasterScopeProvider(', 'AuthorizationSqlScopeCompiler(',
        ] as $term) {
            if (!str_contains($service, $term)) throw new RuntimeException('M8.6C read guard missing: ' . $term);
        }
        if (str_contains($service, 'AuthorizedListPage::paginate(')) {
            throw new RuntimeException('M8.6C SQL reader cannot use in-memory pagination.');
        }
        foreach (['viewableMasterLifecycleStatuses(', "'MASTER'", "['employee', 'department', 'position']"] as $term) {
            if (!str_contains($provider, $term)) throw new RuntimeException('M8.6C provider guard missing: ' . $term);
        }
        return ['operations'=>150,'people_master'=>'SQL_ALWAYS',
            'read_mode'=>'CANONICAL_ONLY', 'resources'=>3];
    }
}
