<?php

declare(strict_types=1);

require_once __DIR__.'/../m85/M85SourceAcceptance.php';

/** M8.6A Product Catalog SQL Scope; source gate never calls HTTP or MariaDB. */
final class M86SourceAcceptance
{
    /** @return array{operations:int,new_routes:int,scope_providers:int,catalog_read:string,organization_read:string} */
    public static function verify(string $root): array
    {
        $previous=M85SourceAcceptance::verify($root);
        if ($previous['operations']!==150 || $previous['new_routes']!==0) {
            throw new RuntimeException('M8.6A must keep 150/150 frozen routes and handlers.');
        }
        $modules=$root.'/backend/app/Modules/';
        foreach ([
            'Product/Catalog/ProductCatalogSql.php',
            'Product/Catalog/ProductCatalogScopeProvider.php',
        ] as $rel) {
            if (!is_file($modules.$rel)) throw new RuntimeException('Missing Catalog SQL scope: '.$rel);
        }
        $source=(string)file_get_contents($modules.'Product/Catalog/ProductCatalogService.php');
        foreach (['ProductCatalogSql::collection(', 'ProductCatalogSql::detail(',
            'ProductCatalogScopeProvider(', 'AuthorizationSqlScopeCompiler(',
            'COUNT', 'AUTH_SCOPE_PBAC_MISMATCH', 'hasBaseViewPermission(',
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ', 'transBegin()', 'transCommit()', 'transRollback()',
            'WF_CONCURRENT_STATE_CHANGE', 'GET_LOCK(', 'RELEASE_LOCK('] as $term) {
            if (!str_contains($source,$term)) throw new RuntimeException('Catalog guard missing: '.$term);
        }
        foreach (['AuthorizedListPage::paginate(',
            "->table(\$this->table)->orderBy('id', 'ASC')->get()->getResultArray()"] as $bad) {
            if (str_contains($source,$bad)) throw new RuntimeException('Catalog in-memory full scan reintroduced.');
        }
        $scope=(string)file_get_contents($modules.'Access/Query/AuthorizationSqlScopeCompiler.php');
        $authorized=(string)file_get_contents($modules.'Access/Query/AuthorizedSqlScope.php');
        $canonical=(string)file_get_contents($modules.'Access/UnifiedAccessService.php');
        foreach (['product_group', 'product_uom'] as $resource) {
            if (!str_contains($scope,"'$resource'=>'MASTER'")
                || !str_contains($authorized,"'$resource'")
                || !str_contains($canonical,"'$resource'")) {
                throw new RuntimeException('Unreviewed Product catalog MASTER type: '.$resource);
            }
        }
        $sql=(string)file_get_contents($modules.'Product/Catalog/ProductCatalogSql.php');
        foreach (['COUNT(*) AS total','LIMIT ? OFFSET ?','scope->predicate(',
            'ESCAPE', 'Product catalog requires exact reviewed MASTER scope'] as $term) {
            if (!str_contains($sql,$term)) throw new RuntimeException('Catalog SQL guard missing: '.$term);
        }
        $catalogTest=(string)file_get_contents($root.'/backend/tests/m61_contract_pure.php');
        if (!str_contains($catalogTest,'SQL-first authorized pagination')) {
            throw new RuntimeException('M6.1 legacy source gate not adapted for reviewed SQL implementation.');
        }
        return ['operations'=>150,'new_routes'=>0,'scope_providers'=>4,
            'catalog_read'=>'SQL_SCOPED','organization_read'=>'NOT_MIGRATED'];
    }
}
