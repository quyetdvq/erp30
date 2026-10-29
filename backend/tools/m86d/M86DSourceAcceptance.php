<?php

declare(strict_types=1);

require_once __DIR__ . '/../m86c/M86CSourceAcceptance.php';

/** M8.6D: mandatory canonical VIEW + SQL for reviewed Organization/People. Source only. */
final class M86DSourceAcceptance
{
    /** @return array{operations:int,read_engine:string,org_people_sql:string,legacy_modes:string} */
    public static function verify(string $root): array
    {
        $previous = M86CSourceAcceptance::verify($root);
        if ($previous['operations'] !== 150
            || $previous['people_master'] !== 'SQL_ALWAYS'
            || $previous['read_mode'] !== 'CANONICAL_ONLY') {
            throw new RuntimeException('Expected M8.6C mandatory SQL baseline.');
        }
        $controller = (string) file_get_contents($root . '/backend/app/Controllers/Api/ReadCatalogController.php');
        $bridge = (string) file_get_contents($root . '/backend/app/Modules/Access/ReadAccessBridge.php');
        $m32Verify = (string) file_get_contents($root . '/backend/app/Commands/M32Verify.php');
        $forbidden = [
            'DINKERP_M32_READ_MODE',
            'DINKERP_M86B_SQL_SCOPE_MODE',
            'DINKERP_M86C_SQL_SCOPE_MODE',
            'peopleSqlEnabled(', 'organizationSqlEnabled(',
        ];
        foreach ($forbidden as $term) {
            if (str_contains($controller . $bridge . $m32Verify, $term)) {
                throw new RuntimeException('Obsolete authorization switch remains: ' . $term);
            }
        }
        foreach ([
            "in_array(\$resource, ['employee', 'department', 'position'], true)",
            "in_array(\$resource, ['organization', 'employee_assignment'], true)",
            "in_array(\$resource, ['province', 'ward'], true)",
            'locationSqlService($user, $instant)->list(',
            'locationSqlService($user)->show(',
            'peopleSqlService($user, $instant)->list(',
            'organizationSqlService($user, $instant)->list(',
            'peopleSqlService($user)->show(',
            'organizationSqlService($user)->show(',
            "organizationSqlService(\$user)->list('organization'",
            'organizationSqlService($user)->organizationLabels(',
            'throw new \\UnexpectedValueException(\'Unreviewed catalog resource.\')',
        ] as $term) {
            if (!str_contains($controller, $term)) {
                throw new RuntimeException('Mandatory SQL dispatch missing: ' . $term);
            }
        }
        if (str_contains($controller, 'authorizedRows(') || str_contains($controller, 'AuthorizedListPage::paginate(')) {
            throw new RuntimeException('Location SQL-first regression: in-memory paging forbidden.');
        }
        foreach ([
            'new UnifiedAccessService(', 'func_num_args() > 3',
            '->canView($type, $persistedRow)',
        ] as $term) {
            if (!str_contains($bridge, $term)) {
                throw new RuntimeException('Canonical-only VIEW bridge missing: ' . $term);
            }
        }
        if (str_contains($bridge, 'ReadViewGate') || str_contains($bridge, 'log_message(')) {
            throw new RuntimeException('Legacy/shadow evaluator must not be reachable from runtime bridge.');
        }
        return [
            'operations' => 150,
            'read_engine' => 'UNIFIED_ONLY',
            'org_people_sql' => 'MANDATORY',
            'legacy_modes' => 'REMOVED',
        ];
    }
}
