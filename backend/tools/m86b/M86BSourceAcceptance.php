<?php

declare(strict_types=1);

require_once __DIR__.'/../m86/M86SourceAcceptance.php';

/** M8.6B guarded Organization/Assignment multi-company SQL pushdown. No DB. */
final class M86BSourceAcceptance
{
    /** @return array{operations:int,org_scope:string,assignment_scope:string,read_mode:string} */
    public static function verify(string $root): array
    {
        $previous=M86SourceAcceptance::verify($root);
        if ($previous['operations']!==150 || $previous['new_routes']!==0) {
            throw new RuntimeException('M8.6B must keep exactly 150/150 API operations.');
        }
        $org=$root.'/backend/app/Modules/Organization/Read/';
        foreach(['OrganizationPeopleScopeProvider.php','OrganizationPeopleSql.php',
            'OrganizationPeopleReadService.php'] as $name) {
            if (!is_file($org.$name)) throw new RuntimeException('Missing Organization SQL: '.$name);
        }
        $scope=(string)file_get_contents($root.'/backend/app/Modules/Access/Query/AuthorizedSqlScope.php');
        $compiler=(string)file_get_contents($root.'/backend/app/Modules/Access/Query/AuthorizationSqlScopeCompiler.php');
        $canonical=(string)file_get_contents($root.'/backend/app/Modules/Access/UnifiedAccessService.php');
        $controller=(string)file_get_contents($root.'/backend/app/Controllers/Api/ReadCatalogController.php');
        $service=(string)file_get_contents($org.'OrganizationPeopleReadService.php');
        $sql=(string)file_get_contents($org.'OrganizationPeopleSql.php');
        $provider=(string)file_get_contents($org.'OrganizationPeopleScopeProvider.php');
        foreach([
            "'organization'=>'ORGANIZATION'", "'employee_assignment'=>'ORGANIZATION'",
        ] as $term) if (!str_contains($compiler,$term)) throw new RuntimeException('Unregistered SQL scope provider '.$term);
        foreach([
            "['price_list', 'organization', 'employee_assignment']",
            '$this->ancestors($id)', 'viewDecisionForState(',
            "ResourceStateCatalog::NATIVE",
        ] as $term) if (!str_contains($canonical,$term)) throw new RuntimeException('Canonical Organization PBAC not retained '.$term);
        foreach([
            "['price_list', 'organization', 'employee_assignment']",
            "'id' : 'organization_id'", 'UPPER(TRIM(', 'COLLATE utf8mb4_bin',
        ] as $term) if (!str_contains($scope,$term)) throw new RuntimeException('Scope bind/mapping not retained '.$term);
        foreach([
            "in_array($" . "resource, ['organization', 'employee_assignment'], true)",
            'organizationSqlService($user, $instant)->list(',
            'organizationSqlService($user)->show(',
            'organizationSqlService($user)->list(\'organization\'',
            'organizationSqlService($user)->organizationLabels(',
            'ReadAccessBridge($user', 'AuthorizedListPage::paginate(',
        ] as $term) if (!str_contains($controller,$term)) {
            throw new RuntimeException('Canonical Organization dispatch missing '.$term);
        }
        foreach(['DINKERP_M86B_SQL_SCOPE_MODE', 'DINKERP_M32_READ_MODE',
            'organizationSqlEnabled('] as $obsolete) {
            if (str_contains($controller,$obsolete)) {
                throw new RuntimeException('Obsolete Organization scope switch: '.$obsolete);
            }
        }
        foreach([
            'COUNT(*) AS total', 'LIMIT ? OFFSET ?', 'scope->predicate(',
            'p.effective_from <= ?', 'p.effective_to IS NULL OR p.effective_to >= ?',
            'SELECT p.id, p.code, p.name, p.type, p.status',
        ] as $term) if (!str_contains($sql,$term)) throw new RuntimeException('Org/Assignment SQL guard missing '.$term);
        foreach([
            'limit(513)', 'count($rows) > 512', 'viewableOrganizationStateBuckets(',
        ] as $term) if (!str_contains($provider,$term)) throw new RuntimeException('Metadata trust boundary missing '.$term);
        foreach([
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',
            'transBegin()', 'transCommit()', 'transRollback()', 'transDepth !== 0',
            'AUTH_SCOPE_PBAC_MISMATCH', 'OrganizationPeopleSql::collection(',
            'OrganizationPeopleSql::detail(', 'OrganizationPeopleSql::labels(',
        ] as $term) if (!str_contains($service,$term)) throw new RuntimeException('Read service guard missing '.$term);
        if (str_contains($service,'AuthorizedListPage::paginate(')) {
            throw new RuntimeException('In-memory pagination forbidden in SQL reader.');
        }
        return ['operations'=>150, 'org_scope'=>'SQL_ALWAYS','assignment_scope'=>'SQL_ALWAYS',
            'read_mode'=>'CANONICAL_ONLY'];
    }
}
