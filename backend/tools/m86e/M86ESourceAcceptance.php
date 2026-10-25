<?php

declare(strict_types=1);

require_once __DIR__.'/../m86d/M86DSourceAcceptance.php';

/** M8.6E Contact dual-resource ORGANIZATION SQL scope; source-only gate. */
final class M86ESourceAcceptance
{
    /** @return array{operations:int,contact_sql:string,resources:list<string>} */
    public static function verify(string $root): array
    {
        $prior=M86DSourceAcceptance::verify($root);
        if ($prior['operations']!==150 || $prior['read_engine']!=='UNIFIED_ONLY') {
            throw new RuntimeException('M8.6E requires M8.6D canonical enforced source baseline.');
        }
        $base=$root.'/backend/app/Modules/';
        foreach(['Contact/ContactSql.php','Contact/ContactScopeProvider.php',
            'Contact/ContactScope.php','Contact/ContactService.php',
            'Access/Query/AuthorizedSqlScope.php','Access/Query/AuthorizationSqlScopeCompiler.php'] as $file){
            if(!is_file($base.$file) || is_link($base.$file)) {
                throw new RuntimeException('M8.6E reviewed source missing: '.$file);
            }
        }
        $service=(string)file_get_contents($base.'Contact/ContactService.php');
        $sql=(string)file_get_contents($base.'Contact/ContactSql.php');
        $provider=(string)file_get_contents($base.'Contact/ContactScopeProvider.php');
        $compiler=(string)file_get_contents($base.'Access/Query/AuthorizationSqlScopeCompiler.php');
        $scope=(string)file_get_contents($base.'Access/Query/AuthorizedSqlScope.php');
        $canonical=(string)file_get_contents($base.'Access/UnifiedAccessService.php');
        $owner=(string)file_get_contents($base.'Contact/ContactScope.php');
        $guards=[
            [$service, 'ContactSql::collection('],[$service,'ContactSql::detail('],
            [$service,"['count_sql']"],[$service,"['rows_sql']"],
            [$service,'AUTH_SCOPE_PBAC_MISMATCH'],
            [$service,'$readScope->resolve($row)'],
            [$service,'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'],
            [$service,'transDepth !== 0'],[$service,'transBegin()'],
            [$service,'transCommit()'],[$service,'transRollback()'],
            [$service,'decideMutation('],[$service,'decideTransitionAction('],
            [$service,' FOR UPDATE'],[$service,'WF_CONCURRENT_STATE_CHANGE'],
            [$service,"ContactScopeProvider('organization_contact'"],
            [$service,"ContactScopeProvider('employee_contact'"],
            [$sql,'COUNT(*) AS total'],[$sql,'LIMIT ? OFFSET ?'],
            [$sql,"UPPER(TRIM(c.contact_type))"],
            [$sql,'NOT EXISTS (SELECT 1 FROM '],[$sql,'other_a.id <> a.id'],
            [$sql,'BINARY a.status = BINARY ?'],[$sql,'JOIN '],
            [$sql,'SELECT c.*'],[$sql,'effective_from <= ?'],
            [$sql,'effective_to IS NULL OR '],
            [$scope,'contactOwnerPredicate('],
            [$compiler,"'organization_contact'=>'ORGANIZATION'"],
            [$compiler,"'employee_contact'=>'ORGANIZATION'"],
            [$canonical,"['organization_contact', 'employee_contact']"],
            [$provider,"->table('organizations')"],[$provider,'->limit(513)'],
            [$owner,'uniqueActivePrimary('],
        ];
        foreach($guards as [$text,$term]){
            if(!str_contains($text,$term)) throw new RuntimeException('M8.6E source guard missing: '.$term);
        }
        foreach(['AuthorizedListPage::paginate(',"->table('contacts')->orderBy(",
            "DINKERP_M86E_SQL_SCOPE_MODE"] as $forbidden){
            if(str_contains($service,$forbidden)) throw new RuntimeException('M8.6E unsafe Contact fallback: '.$forbidden);
        }
        if(str_contains($sql,'SELECT c.*,')||str_contains($sql,'SELECT c.*, o.organization_id')){
            throw new RuntimeException('Contact SQL must not project synthetic organization_id.');
        }
        return ['operations'=>150,'contact_sql'=>'MANDATORY_DUAL_ORG_SCOPE',
            'resources'=>['organization_contact','employee_contact']];
    }
}
