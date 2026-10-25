<?php

declare(strict_types=1);

require_once __DIR__.'/../m8/M8SourceAcceptance.php';

/** M8.5 static source acceptance; no HTTP/DB/file mutations. */
final class M85SourceAcceptance
{
    public static function verify(string $root): array
    {
        $prior=M8SourceAcceptance::verify($root);
        if ($prior['total'] !== 150) throw new RuntimeException('M8.5 must preserve frozen 150/150 routes.');
        $base='backend/app/Modules/';
        foreach ([
            'Access/Query/AuthorizedSqlScope.php',
            'Access/Query/AuthorizationSqlScopeCompiler.php',
            'Access/Query/ResourceScopeProvider.php',
            'Access/Query/EffectiveActionHintCache.php',
            'Product/Master/ProductScopeProvider.php',
            'Product/Master/ProductReadSql.php',
            'Pricing/PriceList/PriceListScopeProvider.php',
        ] as $file) {
            if (!is_file($root.'/'.$base.$file)) throw new RuntimeException('Missing M8.5 Access/Scope file: '.$file);
        }
        $service=(string)file_get_contents($root.'/'.$base.'Product/Master/ProductReadService.php');
        foreach (['AuthorizationSqlScopeCompiler', 'ProductReadSql::collection(',
            'ProductReadSql::detail(', 'ProductReadRules::describeJoined(',
            'EffectiveActionHintCache', 'canView(', 'AUTH_SCOPE_PBAC_MISMATCH',
            'transBegin(', 'transCommit(', 'transRollback(', 'bool $allowBorrowedDetail = false', '}, true);'] as $guard) {
            if (!str_contains($service,$guard)) throw new RuntimeException('M8.5 Product scope guard missing: '.$guard);
        }
        foreach (['function allRows(', 'AuthorizedListPage::paginate(',
            "->table('products')->orderBy('id', 'ASC')->get()"] as $bad) {
            if (str_contains($service,$bad)) throw new RuntimeException('Product full table paging resurrected.');
        }
        $scope=(string)file_get_contents($root.'/'.$base.'Access/Query/AuthorizedSqlScope.php');
        if (!str_contains($scope, "'ORGANIZATION'") || !str_contains($scope, 'organization_id')
            || !str_contains($scope, '1 = 0') || !str_contains($scope, "'MASTER'")) {
            throw new RuntimeException('SQL compiler lost domain/scope fail-closed semantics.');
        }
        $sql=(string)file_get_contents($root.'/'.$base.'Product/Master/ProductReadSql.php');
        if (!str_contains($sql, 'COUNT(*) AS total') || !str_contains($sql, 'LIMIT ? OFFSET ?')
            || !str_contains($sql, 'scope->predicate(') || !str_contains($sql, 'ORDER BY ')) {
            throw new RuntimeException('SQL VIEW must run before count/sort/page.');
        }
        $readRules=(string)file_get_contents($root.'/'.$base.'Product/Master/ProductReadRules.php');
        if (!str_contains($readRules,'function describeJoined(') || !str_contains($readRules,'ImageUrl::for(')) {
            throw new RuntimeException('Product bounded projection missing metadata redaction.');
        }
        return ['operations'=>150,'new_routes'=>0,'scope_providers'=>2,'product_read'=>'SQL_SCOPED',
            'organization_pricing'=>'PROVIDER_READY_NOT_SWITCHED'];
    }
}
