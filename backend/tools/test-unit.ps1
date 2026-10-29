# Backend v2 daily test entrypoint, modeled on backend_basline/tools/test-unit.ps1.
# Usage (from backend/):
#   .\tools\test-unit.ps1 CHECK
#   .\tools\test-unit.ps1 RUN                 # offline only; never writes DB
#   .\tools\test-unit.ps1 RUN UNIT-STANDARD   # same offline test suite
#   .\tools\test-unit.ps1 RUN DB-READ         # existing DB, read-only audits
#   .\tools\test-unit.ps1 RUN ALL             # offline + read-only audits
#   .\tools\test-unit.ps1 RUN INTEGRATION     # explicit disposable MariaDB sandbox + M3.4D acceptance
#   .\tools\test-unit.ps1 RUN ACCEPTANCE      # source/golden-master gates; no SQL/HTTP
#   .\tools\test-unit.ps1 RUN M4-INTEGRATION  # opt-in M4 14-route HTTP/SQL sandbox
#   .\tools\test-unit.ps1 RUN M4-CONCURRENCY  # opt-in two-writer Numbering config race
#   .\tools\test-unit.ps1 RUN CUS-UNIT       # offline CUS rules + source contract
#   .\tools\test-unit.ps1 RUN P1-STANDARDS   # offline P1 SQL-first/PBAC improvement regression
#   .\tools\test-unit.ps1 RUN ROUTES         # pinned 180-route registry, P1 150 and negative guards
#   .\tools\test-unit.ps1 RUN M11-UNIT         # M11 PHPUnit unit, no DB
#   .\tools\test-unit.ps1 RUN M11-INTEGRATION  # M11 MariaDB E2E, uses database.tests
#   .\tools\test-unit.ps1 RUN M11-RELIABILITY # M11.3A two-process + failure-injection, commits isolated test fixture
#   .\tools\test-unit.ps1 PREPARE M11          # one-time test-only Migrations 019-021
#   .\tools\test-unit.ps1 RUN M11              # both suites
# Optional '-' separator is accepted, like the baseline runner.
# NO-DROP is not an alias: the baseline's data attestation is NOT reproduced here.

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$tokens = @($args)
$usage = 'Usage: .\tools\test-unit.ps1 CHECK|RUN [UNIT-STANDARD|DB-READ|ALL|INTEGRATION|ACCEPTANCE|M4-INTEGRATION|M4-CONCURRENCY|M11|M11-UNIT|M11-INTEGRATION|M11-RELIABILITY|CUS-UNIT|P1-STANDARDS|ROUTES] OR PREPARE M11'
if ($tokens.Count -lt 1 -or $tokens.Count -gt 3) { throw $usage }
$action = ([string]$tokens[0]).ToUpperInvariant()
if ($action -notin @('CHECK', 'RUN', 'PREPARE')) { throw $usage }
$suite = 'UNIT-STANDARD'
if ($tokens.Count -eq 2) {
    $suite = [string]$tokens[1]
} elseif ($tokens.Count -eq 3) {
    if ([string]$tokens[1] -ne '-') { throw $usage }
    $suite = [string]$tokens[2]
}
$suite = $suite.TrimStart('-').ToUpperInvariant()
if ($action -eq 'PREPARE' -and $suite -ne 'M11') { throw $usage }
if ($suite -notin @('UNIT-STANDARD', 'DB-READ', 'ALL', 'INTEGRATION', 'ACCEPTANCE', 'M4-INTEGRATION', 'M4-CONCURRENCY', 'M11', 'M11-UNIT', 'M11-INTEGRATION', 'M11-RELIABILITY', 'CUS-UNIT', 'P1-STANDARDS', 'ROUTES')) { throw $usage }

function Invoke-PHPChecked {
    param([string[]]$Arguments, [string]$Description)
    & php @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$Description failed (PHP exit code $LASTEXITCODE). STOP."
    }
}

$backend = Split-Path -Parent $PSScriptRoot
Push-Location $backend
try {
    # M11 is ongoing product development; historical pinned rewrite inventory is
    # intentionally not a prerequisite for its standalone PHPUnit suites.
    if ($suite -in @('M11', 'M11-UNIT', 'M11-INTEGRATION', 'M11-RELIABILITY')) {
        if (-not (Test-Path -LiteralPath '.\phpunit.m11.xml' -PathType Leaf)) {
            throw 'M11 PHPUnit configuration phpunit.m11.xml is missing.'
        }
        if ($suite -eq 'M11-RELIABILITY' -and -not (Test-Path -LiteralPath '.\phpunit.m11.reliability.xml' -PathType Leaf)) {
            throw 'M11 reliability PHPUnit configuration phpunit.m11.reliability.xml is missing.'
        }
        if ($action -eq 'CHECK') {
            $oldEnvironment = [Environment]::GetEnvironmentVariable('CI_ENVIRONMENT', 'Process')
            try {
                $env:CI_ENVIRONMENT = 'testing'
                Invoke-PHPChecked -Description 'M11 isolated Spark bootstrap check' -Arguments @(
                    'tools/m11-test-spark.php', '--check-bootstrap'
                )
            } finally {
                if ($null -eq $oldEnvironment) {
                    Remove-Item Env:CI_ENVIRONMENT -ErrorAction SilentlyContinue
                } else {
                    $env:CI_ENVIRONMENT = $oldEnvironment
                }
            }
            Write-Host 'M11 CHECK PASS: PHPUnit configuration and isolated Spark bootstrap. No database connection.'
            return
        }
        if ($action -eq 'PREPARE') {
            # Explicit opt-in schema migration. Do not add this to RUN: PHPUnit
            # fixture tests must remain DML-only and rollback all their changes.
            $oldEnvironment = [Environment]::GetEnvironmentVariable('CI_ENVIRONMENT', 'Process')
            try {
                $env:CI_ENVIRONMENT = 'testing'
                Write-Warning 'PREPARE M11 may CREATE tables ONLY in validated database.tests=erp_test. No DROP/refresh.'
                # The isolated entrypoint sets both PHP environment superglobals
                # and SUPPORTPATH before CodeIgniter 4.7.x builds its autoloader.
                # Do not use bare `php spark` here: .env may override ENVIRONMENT.
                Invoke-PHPChecked -Description 'M11 erp_test schema preparation' -Arguments @(
                    'tools/m11-test-spark.php', 'm11:test-db-prepare', 'CONFIRM_ERP_TEST'
                )
            } finally {
                if ($null -eq $oldEnvironment) {
                    Remove-Item Env:CI_ENVIRONMENT -ErrorAction SilentlyContinue
                } else {
                    $env:CI_ENVIRONMENT = $oldEnvironment
                }
            }
            Write-Host 'M11 PREPARE PASS: schema checked/prepared ONLY on erp_test.'
            return
        }
        if (-not (Test-Path -LiteralPath '.\vendor\bin\phpunit' -PathType Leaf)) {
            throw 'PHPUnit not installed: run composer install (with dev dependencies) in backend first.'
        }
        if ($suite -in @('M11', 'M11-UNIT')) {
            Invoke-PHPChecked -Description 'M11 unit PHPUnit' -Arguments @(
                'vendor/bin/phpunit', '-c', 'phpunit.m11.xml', '--testsuite', 'M11 unit', '--testdox'
            )
        }
        if ($suite -in @('M11', 'M11-INTEGRATION')) {
            Write-Warning 'M11 integration writes temporary fixture rows ONLY to configured database.tests=erp_test; changes are rolled back. Run serially.'
            Invoke-PHPChecked -Description 'M11 MariaDB integration PHPUnit' -Arguments @(
                'vendor/bin/phpunit', '-c', 'phpunit.m11.xml', '--testsuite', 'M11 MariaDB integration', '--testdox'
            )
        }
        if ($suite -eq 'M11-RELIABILITY') {
            Write-Warning 'M11.3A reliability uses TWO real PHP workers and COMMITTED short-lived DML fixtures in guarded erp_test. Cleanup by generated IDs in finally. Do not run concurrently or on a shared test database.'
            Invoke-PHPChecked -Description 'M11.3A two-worker MariaDB reliability PHPUnit' -Arguments @(
                'vendor/bin/phpunit', '-c', 'phpunit.m11.reliability.xml', '--testsuite', 'M11 worker reliability', '--testdox'
            )
        }
        Write-Host "TEST-UNIT RUN PASS: $suite."
        return
    }

    Invoke-PHPChecked -Description 'Pinned source and test inventory' -Arguments @('tools/test-unit-check.php')

    if ($action -eq 'CHECK') {
        Write-Host 'CHECK PASS: source and OpenAPI inventory only. No test, HTTP, or DB operation.'
        return
    }

    if ($suite -eq 'CUS-UNIT') {
        Invoke-PHPChecked -Description 'CUS G1 pure rules tests' -Arguments @('tests/cus/customer_core_pure.php')
        Invoke-PHPChecked -Description 'CUS G1 source contract tests' -Arguments @('tests/cus/customer_source_contract_pure.php')
        Invoke-PHPChecked -Description 'CUS SQL-first scoped query plan tests' -Arguments @('tests/cus/customer_sql_plan_pure.php')
        Invoke-PHPChecked -Description 'CUS Workflow and User candidate regression tests' -Arguments @('tests/cus/customer_workflow_pure.php')
        Invoke-PHPChecked -Description 'P1 Contact Workflow owner-scope regression' -Arguments @('tests/m1920_contact_workflow_scope_pure.php')
        Write-Host 'CUS-UNIT PASS: CUS offline rules and source contract. No DB, HTTP or browser validation.'
        return
    }

    if ($suite -eq 'ROUTES') {
        Write-Host '===== ROUTES: frozen v1.9.23 registry and routing migration gates (NO DB) ====='
        Invoke-PHPChecked -Description '180 frozen method/path/handler/filter registrations' -Arguments @('tools/routes/route_inventory.php', '--verify')
        Invoke-PHPChecked -Description 'M19.24 routing ownership and negative mutations' -Arguments @('tests/m1924_route_architecture_pure.php')
        Invoke-PHPChecked -Description 'M3.3/P1 frozen 150 operations and historical extension chain' -Arguments @('tests/m33_routes_pure.php')
        Invoke-PHPChecked -Description 'M3.4D pinned core route contract' -Arguments @('tests/m34d_contract_pure.php')
        Write-Host 'ROUTES PASS: 180/180 frozen registrations; 150/150 P1; no DB/HTTP. CI4 Spark and DEV HTTP UAT remain pending.'
        return
    }

    if ($suite -eq 'P1-STANDARDS') {
        Write-Host '===== P1-STANDARDS: SQL-first/PBAC performance source regression (NO DB) ====='
        foreach ($test in @(
            'tests/m1923_location_sql_pure.php',
            'tests/m1923_org_scope_pure.php',
            'tests/m1923_contact_parent_batch_pure.php',
            'tests/m85_sql_scope_pure.php',
            'tests/m86_catalog_scope_pure.php',
            'tests/m86b_org_scope_pure.php',
            'tests/m86c_scope_pure.php',
            'tests/m86e_contact_sql_pure.php',
            'tests/m102_effective_row_actions_pure.php',
            'tests/m1920_contact_workflow_scope_pure.php'
        )) {
            Invoke-PHPChecked -Description "P1 SQL/PBAC regression $test" -Arguments @($test)
        }
        Write-Host 'P1-STANDARDS PASS: offline SQL/PBAC source tests only. DEV EXPLAIN and HTTP UAT remain pending.'
        return
    }

    if ($suite -in @('UNIT-STANDARD', 'ALL')) {
        Write-Host '===== UNIT-STANDARD: pure/fixture regressions (NO DATABASE) ====='
        Invoke-PHPChecked -Description 'Offline regression suite' -Arguments @('tools/m34b_run.php', '--offline')
        Write-Host 'UNIT-STANDARD PASS: offline only; NOT HTTP/MariaDB integration.'
        Invoke-PHPChecked -Description 'M3.4D static and baseline golden-master acceptance' -Arguments @('tools/m34d_accept.php', '--offline')
        Invoke-PHPChecked -Description 'M4 source/route extension acceptance (14 new APIs; no DB)' -Arguments @('tools/m4_accept.php')
        Invoke-PHPChecked -Description 'M5.1 Province/Ward source acceptance (six mutations; no DB)' -Arguments @('tools/m5_accept.php')
        Invoke-PHPChecked -Description 'M5.2 Organization source acceptance (three mutations; no DB)' -Arguments @('tools/m52_accept.php')
        Invoke-PHPChecked -Description 'M5.3 Employee/Assignment source acceptance (six mutations; no DB)' -Arguments @('tools/m53_accept.php')
        Invoke-PHPChecked -Description 'M5.4 Department/Position source acceptance (six mutations; no DB)' -Arguments @('tools/m54_accept.php')
        Invoke-PHPChecked -Description 'M5.5 Contact source acceptance (five operations; no DB)' -Arguments @('tools/m55_accept.php')
        Invoke-PHPChecked -Description 'M6.1 Product Group/UOM source acceptance (ten operations; no DB)' -Arguments @('tools/m61_accept.php')
        Invoke-PHPChecked -Description 'M6.2A Product Master READ source acceptance (two operations; no DB)' -Arguments @('tools/m62a_accept.php')
        Invoke-PHPChecked -Description 'M6.2A.1 Product PBAC and public-upload source acceptance (no DB)' -Arguments @('tools/m62a1_accept.php')
        Invoke-PHPChecked -Description 'M6.2B Product Master mutation source acceptance (no DB)' -Arguments @('tools/m62b_accept.php')
        Invoke-PHPChecked -Description 'M6.3A Product Media mutation source acceptance (no DB)' -Arguments @('tools/m63_accept.php')
        Invoke-PHPChecked -Description 'M6.3B Identity Avatar mutation source acceptance (no DB)' -Arguments @('tools/m63b_accept.php')
        Invoke-PHPChecked -Description 'M6.4A Product selector source acceptance (no DB)' -Arguments @('tools/m64a_accept.php')
        Invoke-PHPChecked -Description 'M7.1 Product UOM conversion source acceptance (no DB)' -Arguments @('tools/m71_accept.php')
        Invoke-PHPChecked -Description 'M7.2 Combo item source acceptance (no DB)' -Arguments @('tools/m72_accept.php')
        Invoke-PHPChecked -Description 'M7.3 Pricing source acceptance (no DB)' -Arguments @('tools/m73_accept.php')
        Invoke-PHPChecked -Description 'M7.4 Product relationship lookup source acceptance (no DB)' -Arguments @('tools/m74_accept.php')
        Invoke-PHPChecked -Description 'M8 shared Product Import source acceptance (no DB)' -Arguments @('tools/m8_accept.php')
        Invoke-PHPChecked -Description 'M8.5 Authorization SQL scope and Product read acceptance (no DB)' -Arguments @('tools/m85_accept.php')
        Invoke-PHPChecked -Description 'M8.6A Product Group/UOM SQL scope source acceptance (no DB)' -Arguments @('tools/m86_accept.php')
        Invoke-PHPChecked -Description 'M8.6B Organization/Assignment SQL source acceptance (no DB)' -Arguments @('tools/m86b_accept.php')
        Invoke-PHPChecked -Description 'M8.6C People MASTER SQL source acceptance (no DB)' -Arguments @('tools/m86c_accept.php')
        Invoke-PHPChecked -Description 'M8.6D mandatory canonical VIEW/SQL source acceptance (no DB)' -Arguments @('tools/m86d_accept.php')
        Invoke-PHPChecked -Description 'M8.6E Contact polymorphic SQL PBAC source acceptance (no DB)' -Arguments @('tools/m86e_accept.php')
        Invoke-PHPChecked -Description 'M8.7 DEV SQL/PBAC audit harness source acceptance (NO DB)' -Arguments @('tools/m87_accept.php')
        Invoke-PHPChecked -Description 'M8.7A DEV diagnostics and one-run source acceptance (NO DB)' -Arguments @('tools/m87a_accept.php')
    }

    if ($suite -in @('DB-READ', 'ALL')) {
        Write-Host '===== DB-READ: bounded live MariaDB reads, NO MUTATIONS ====='
        # These are the existing read-only M3/M3.3 audits, NOT baseline NO-DROP.
        # They use the current backend .env connection; confirm its target first.
        Invoke-PHPChecked -Description 'State/lifecycle audit' -Arguments @('spark', 'm3:audit')
        Invoke-PHPChecked -Description 'Read PBAC parity sample' -Arguments @('spark', 'm32:verify', '50', '100')
        Invoke-PHPChecked -Description 'Access VIEW readiness' -Arguments @('spark', 'm33:verify', '20', '75')
        Invoke-PHPChecked -Description 'Groups/resources write-PBAC readiness' -Arguments @('spark', 'm33b:verify')
        Invoke-PHPChecked -Description 'Policy write-PBAC readiness' -Arguments @('spark', 'm33b2:verify')
        Invoke-PHPChecked -Description 'Shield user write-PBAC readiness' -Arguments @('spark', 'm33b3:verify')
        Invoke-PHPChecked -Description 'Workflow write-PBAC readiness' -Arguments @('spark', 'm33b4:verify')
        Write-Host 'DB-READ PASS: checks are read-only; no HTTP mutation or rollback proven.'
    }

    if ($suite -eq 'ACCEPTANCE') {
        Write-Host '===== M3.4D ACCEPTANCE: source/golden-master only (NO DATABASE) ====='
        Invoke-PHPChecked -Description 'M3.4D static and baseline golden-master acceptance' -Arguments @('tools/m34d_accept.php', '--offline')
        Invoke-PHPChecked -Description 'M4 source/route extension acceptance (14 new APIs; no DB)' -Arguments @('tools/m4_accept.php')
        Invoke-PHPChecked -Description 'M5.1 Province/Ward source acceptance (six mutations; no DB)' -Arguments @('tools/m5_accept.php')
        Invoke-PHPChecked -Description 'M5.2 Organization source acceptance (three mutations; no DB)' -Arguments @('tools/m52_accept.php')
        Invoke-PHPChecked -Description 'M5.3 Employee/Assignment source acceptance (six mutations; no DB)' -Arguments @('tools/m53_accept.php')
        Invoke-PHPChecked -Description 'M5.4 Department/Position source acceptance (six mutations; no DB)' -Arguments @('tools/m54_accept.php')
        Invoke-PHPChecked -Description 'M5.5 Contact source acceptance (five operations; no DB)' -Arguments @('tools/m55_accept.php')
        Invoke-PHPChecked -Description 'M6.1 Product Group/UOM source acceptance (ten operations; no DB)' -Arguments @('tools/m61_accept.php')
        Invoke-PHPChecked -Description 'M6.2A Product Master READ source acceptance (two operations; no DB)' -Arguments @('tools/m62a_accept.php')
        Invoke-PHPChecked -Description 'M6.2A.1 Product PBAC and public-upload source acceptance (no DB)' -Arguments @('tools/m62a1_accept.php')
        Invoke-PHPChecked -Description 'M6.2B Product Master mutation source acceptance (no DB)' -Arguments @('tools/m62b_accept.php')
        Invoke-PHPChecked -Description 'M6.3A Product Media mutation source acceptance (no DB)' -Arguments @('tools/m63_accept.php')
        Invoke-PHPChecked -Description 'M6.3B Identity Avatar mutation source acceptance (no DB)' -Arguments @('tools/m63b_accept.php')
        Invoke-PHPChecked -Description 'M6.4A Product selector source acceptance (no DB)' -Arguments @('tools/m64a_accept.php')
        Invoke-PHPChecked -Description 'M7.1 Product UOM conversion source acceptance (no DB)' -Arguments @('tools/m71_accept.php')
        Invoke-PHPChecked -Description 'M7.2 Combo item source acceptance (no DB)' -Arguments @('tools/m72_accept.php')
        Invoke-PHPChecked -Description 'M7.3 Pricing source acceptance (no DB)' -Arguments @('tools/m73_accept.php')
        Invoke-PHPChecked -Description 'M7.4 Product relationship lookup source acceptance (no DB)' -Arguments @('tools/m74_accept.php')
        Invoke-PHPChecked -Description 'M8 shared Product Import source acceptance (no DB)' -Arguments @('tools/m8_accept.php')
        Invoke-PHPChecked -Description 'M8.5 Authorization SQL scope and Product read acceptance (no DB)' -Arguments @('tools/m85_accept.php')
        Invoke-PHPChecked -Description 'M8.6A Product Group/UOM SQL scope source acceptance (no DB)' -Arguments @('tools/m86_accept.php')
        Invoke-PHPChecked -Description 'M8.6B Organization/Assignment SQL source acceptance (no DB)' -Arguments @('tools/m86b_accept.php')
        Invoke-PHPChecked -Description 'M8.6C People MASTER SQL source acceptance (no DB)' -Arguments @('tools/m86c_accept.php')
        Invoke-PHPChecked -Description 'M8.6D mandatory canonical VIEW/SQL source acceptance (no DB)' -Arguments @('tools/m86d_accept.php')
        Invoke-PHPChecked -Description 'M8.6E Contact polymorphic SQL PBAC source acceptance (no DB)' -Arguments @('tools/m86e_accept.php')
        Invoke-PHPChecked -Description 'M8.7 DEV SQL/PBAC audit harness source acceptance (NO DB)' -Arguments @('tools/m87_accept.php')
        Invoke-PHPChecked -Description 'M8.7A DEV diagnostics and one-run source acceptance (NO DB)' -Arguments @('tools/m87a_accept.php')
    }

    if ($suite -eq 'INTEGRATION') {
        Write-Warning 'INTEGRATION will create and mutate a NEW disposable local MariaDB sandbox.'
        Write-Warning 'It is NOT the baseline NO-DROP suite and NOT live baseline HTTP parity.'
        # Keep EXACT output report path from this process, never attach a stale
        # "latest report" that may have been produced before M3.4D refactor.
        $integrationLines = New-Object System.Collections.Generic.List[string]
        & php 'tools/m34b_run.php' | ForEach-Object {
            $line = [string]$_
            [void]$integrationLines.Add($line)
            Write-Host $line
        }
        if ($LASTEXITCODE -ne 0) {
            throw "M3.4B explicit sandbox HTTP/DB integration failed (PHP exit code $LASTEXITCODE). STOP."
        }
        $reportLine = @($integrationLines | Where-Object { $_ -like 'M3.4B report: *' } | Select-Object -Last 1)
        if ($reportLine.Count -ne 1) {
            throw 'M3.4D blocked: cannot associate this integration run with its exact JSON report.'
        }
        $reportPath = ([string]$reportLine[0]).Substring('M3.4B report: '.Length).Trim()
        Invoke-PHPChecked -Description 'M3.4D acceptance bound to exact M3.4B runtime report' -Arguments @('tools/m34d_accept.php', "--integration-report=$reportPath")
        Invoke-PHPChecked -Description 'M4 source/route extension acceptance' -Arguments @('tools/m4_accept.php')
        Invoke-PHPChecked -Description 'M5.1 Province/Ward source acceptance (no DB)' -Arguments @('tools/m5_accept.php')
        Invoke-PHPChecked -Description 'M5.2 Organization source acceptance (no DB)' -Arguments @('tools/m52_accept.php')
        Invoke-PHPChecked -Description 'M5.3 Employee/Assignment source acceptance (no DB)' -Arguments @('tools/m53_accept.php')
        Invoke-PHPChecked -Description 'M5.4 Department/Position source acceptance (no DB)' -Arguments @('tools/m54_accept.php')
        Invoke-PHPChecked -Description 'M5.5 Contact source acceptance (no DB)' -Arguments @('tools/m55_accept.php')
        Invoke-PHPChecked -Description 'M6.1 Product Group/UOM source acceptance (no DB)' -Arguments @('tools/m61_accept.php')
        Invoke-PHPChecked -Description 'M6.2A Product Master READ source acceptance (no DB)' -Arguments @('tools/m62a_accept.php')
        Invoke-PHPChecked -Description 'M6.2A.1 Product PBAC and public-upload source acceptance (no DB)' -Arguments @('tools/m62a1_accept.php')
        Invoke-PHPChecked -Description 'M6.2B Product Master mutation source acceptance (no DB)' -Arguments @('tools/m62b_accept.php')
        Invoke-PHPChecked -Description 'M6.3A Product Media mutation source acceptance (no DB)' -Arguments @('tools/m63_accept.php')
        Invoke-PHPChecked -Description 'M6.3B Identity Avatar mutation source acceptance (no DB)' -Arguments @('tools/m63b_accept.php')
        Invoke-PHPChecked -Description 'M6.4A Product selector source acceptance (no DB)' -Arguments @('tools/m64a_accept.php')
        Invoke-PHPChecked -Description 'M7.1 Product UOM conversion source acceptance (no DB)' -Arguments @('tools/m71_accept.php')
        Invoke-PHPChecked -Description 'M7.2 Combo item source acceptance (no DB)' -Arguments @('tools/m72_accept.php')
        Invoke-PHPChecked -Description 'M7.3 Pricing source acceptance (no DB)' -Arguments @('tools/m73_accept.php')
        Invoke-PHPChecked -Description 'M7.4 Product relationship lookup source acceptance (no DB)' -Arguments @('tools/m74_accept.php')
        Invoke-PHPChecked -Description 'M8 shared Product Import source acceptance (no DB)' -Arguments @('tools/m8_accept.php')
        Invoke-PHPChecked -Description 'M8.5 Authorization SQL scope and Product read acceptance (no DB)' -Arguments @('tools/m85_accept.php')
        Invoke-PHPChecked -Description 'M8.6A Product Group/UOM SQL scope source acceptance (no DB)' -Arguments @('tools/m86_accept.php')
        Invoke-PHPChecked -Description 'M8.6B Organization/Assignment SQL source acceptance (no DB)' -Arguments @('tools/m86b_accept.php')
        Invoke-PHPChecked -Description 'M8.6C People MASTER SQL source acceptance (no DB)' -Arguments @('tools/m86c_accept.php')
        Invoke-PHPChecked -Description 'M8.6D mandatory canonical VIEW/SQL source acceptance (no DB)' -Arguments @('tools/m86d_accept.php')
        Invoke-PHPChecked -Description 'M8.6E Contact polymorphic SQL PBAC source acceptance (no DB)' -Arguments @('tools/m86e_accept.php')
        Invoke-PHPChecked -Description 'M8.7 DEV SQL/PBAC audit harness source acceptance (NO DB)' -Arguments @('tools/m87_accept.php')
        Invoke-PHPChecked -Description 'M8.7A DEV diagnostics and one-run source acceptance (NO DB)' -Arguments @('tools/m87a_accept.php')
        Write-Host 'INTEGRATION PASS: M3.4D sandbox 29/29; M4/M5.1/M5.2/M5.3/M5.4/M5.5/M6.1/M6.2A are source-only in this suite (use their separate integration checks).'
    }

    if ($suite -eq 'M4-INTEGRATION') {
        Write-Warning 'M4.2A writes only to a fresh nonce-owned local MariaDB sandbox.'
        Write-Warning 'Never use for baseline parity; independent baseline and two-writer concurrency remain NOT_TESTED.'
        Invoke-PHPChecked -Description 'M4.2A 14-route isolated HTTP/SQL sandbox' -Arguments @('tools/m42_run.php')
    }

    if ($suite -eq 'M4-CONCURRENCY') {
        Write-Warning 'M4.2B starts TWO local PHP HTTP servers and mutates a new nonce-owned MariaDB sandbox.'
        Write-Warning 'Tests Numbering CONFIGURATION races only; NOT sequence issuance, baseline parity or production readiness.'
        Invoke-PHPChecked -Description 'M4.2B two-writer Numbering configuration race' -Arguments @('tools/m42b_run.php')
    }

    Write-Host "TEST-UNIT RUN PASS: $suite."
} finally {
    Pop-Location
}
