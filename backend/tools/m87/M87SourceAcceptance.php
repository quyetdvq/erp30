<?php

declare(strict_types=1);

require_once __DIR__.'/../m86e/M86ESourceAcceptance.php';
require_once __DIR__.'/M87Plan.php';

final class M87SourceAcceptance
{
    /** @return array{operations:int,resources:int,read_only:bool} */
    public static function verify(string $root): array
    {
        $base=M86ESourceAcceptance::verify($root);
        if ($base['operations']!==150 || count(M87Plan::resources())!==9) {
            throw new RuntimeException('M8.7 requires M8.6E and exactly nine scoped collections.');
        }
        $files=[
            'backend/tools/m87/M87Plan.php',
            'backend/tools/m87/M87SqlVerifier.php',
            'backend/tools/m87/M87Http.php',
            'backend/tools/m87_http_readonly.php',
            'backend/app/Commands/M87DevSqlAudit.php',
            'backend/tests/m87_plan_pure.php',
            'backend/tests/m87_sql_contract_pure.php',
            'backend/tests/m87_http_safety_pure.php',
            'docs/backend-rewrite/M8_7_DEV_SQL_PBAC_RUNTIME_VERIFICATION.md',
        ];
        foreach ($files as $file) {
            if (!is_file($root.'/'.$file) || is_link($root.'/'.$file)) {
                throw new RuntimeException('M8.7 verified source not present: '.$file);
            }
        }
        $verifier=(string)file_get_contents($root.'/backend/tools/m87/M87SqlVerifier.php');
        $command=(string)file_get_contents($root.'/backend/app/Commands/M87DevSqlAudit.php');
        $http=(string)file_get_contents($root.'/backend/tools/m87/M87Http.php');
        foreach (['SET TRANSACTION READ ONLY','transRollback()','ContactScope(',
            'canView(', 'EXPLAIN FORMAT=JSON ', 'AuthorizedListPage::paginate('] as $term) {
            if (!str_contains($verifier,$term)) throw new RuntimeException('M8.7 READ-only gate missing: '.$term);
        }
        foreach (['devEnvironment(ENVIRONMENT)','M87Plan::args(','M87SqlVerifier','production_acceptance'] as $term) {
            if (!str_contains($command,$term)) throw new RuntimeException('M8.7 command safety missing: '.$term);
        }
        foreach (['127.0.0.1','/api/v1/auth/login','follow_location\'=>0'] as $term) {
            if (!str_contains($http,$term)) throw new RuntimeException('M8.7 HTTP safety missing: '.$term);
        }
        return ['operations'=>150,'resources'=>9,'read_only'=>true];
    }
}
