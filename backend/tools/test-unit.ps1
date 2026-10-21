# Single public entrypoint for the 104-class / 629-case backend UNIT suites.
# Syntax:
#   .\tools\test-unit.ps1 RUN
#   .\tools\test-unit.ps1 RUN UNIT-STANDARD
#   .\tools\test-unit.ps1 RUN NO-DROP
#   .\tools\test-unit.ps1 CHECK
#   .\tools\test-unit.ps1 CHECK UNIT-STANDARD
#   .\tools\test-unit.ps1 CHECK NO-DROP
# A literal separating '-' is also accepted: RUN - UNIT-STANDARD.
# CHECK uses only PHP source manifests: no PHPUnit bootstrap or database access.
# RUN ALL is serial: STANDARD first, then the current NO-DROP database baseline.

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$tokens = @($args)
$usage = 'Usage: .\tools\test-unit.ps1 RUN|CHECK [UNIT-STANDARD|NO-DROP] (optional separator: RUN - NO-DROP)'
if ($tokens.Count -lt 1 -or $tokens.Count -gt 3) {
    throw $usage
}

$action = ([string]$tokens[0]).ToUpperInvariant()
if ($action -notin @('RUN', 'CHECK')) {
    throw $usage
}

$suite = 'ALL'
if ($tokens.Count -eq 2) {
    $suite = [string]$tokens[1]
} elseif ($tokens.Count -eq 3) {
    if ([string]$tokens[1] -ne '-') {
        throw $usage
    }
    $suite = [string]$tokens[2]
}
# Also understand the compact -NO-DROP / -UNIT-STANDARD spelling.
$suite = $suite.TrimStart('-').ToUpperInvariant()
if ($suite -notin @('ALL', 'UNIT-STANDARD', 'NO-DROP')) {
    throw $usage
}

$backend = Split-Path -Parent $PSScriptRoot
$oldCiEnvironment = [Environment]::GetEnvironmentVariable('CI_ENVIRONMENT', 'Process')
$oldSkipGrants = [Environment]::GetEnvironmentVariable('ERP_NO_DROP_SKIP_GRANTS', 'Process')

function Invoke-PHPChecked {
    param([string[]]$Arguments, [string]$Description)
    & php @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$Description failed (PHP exit code $LASTEXITCODE). STOP."
    }
}

Push-Location $backend
try {
    # Fail closed on an unexpected or changed 91/13 class partition.
    # These checks never connect to MySQL, even for CHECK.
    if ($suite -in @('ALL', 'NO-DROP')) {
        Invoke-PHPChecked -Description 'NO-DROP source gate' -Arguments @('tools/test-unit-no-drop-source-gate.php')
    }
    if ($suite -in @('ALL', 'UNIT-STANDARD')) {
        Invoke-PHPChecked -Description 'UNIT-STANDARD source gate' -Arguments @('tools/test-unit-standard-source-gate.php')
    }

    if ($action -eq 'CHECK') {
        Invoke-PHPChecked -Description 'UNIT class/method inventory' -Arguments @('tools/test-unit-list.php', "--suite=$suite")
        Write-Host 'UNIT CHECK PASS: source-only discovery; no PHPUnit executed, no database connected.'
        return
    }

    $env:CI_ENVIRONMENT = 'testing'
    if ($suite -in @('ALL', 'UNIT-STANDARD')) {
        Write-Host '===== UNIT-STANDARD: 13 classes / 123 tests ====='
        Invoke-PHPChecked -Description 'UNIT-STANDARD PHPUnit' -Arguments @(
            'vendor/bin/phpunit',
            '-c', 'phpunit.unit-standard.xml',
            '--no-coverage', '--colors=never', '--fail-on-empty-test-suite', '--testdox'
        )
        Write-Host 'UNIT-STANDARD PASS.'
    }

    if ($suite -in @('ALL', 'NO-DROP')) {
        # Explicit project policy: use the present erp_test state as the baseline.
        # This is NOT a grants-based DDL prevention. The attestation still checks
        # the actual loopback target and compares every table/schema before/after.
        $env:ERP_NO_DROP_SKIP_GRANTS = '1'
        Write-Warning 'NO-DROP MySQL grant check is skipped by request. Back up erp_test first; DDL is not blocked by grants.'
        Write-Host '===== NO-DROP: 91 classes / 506 expected test cases ====='
        Invoke-PHPChecked -Description 'NO-DROP guarded PHPUnit/data attestation' -Arguments @(
            'tools/test-current.php', '--mode=unit-safe', '--confirm-test-db',
            '--target=127.0.0.1:3306/erp_test', '--prefix=dinktech_'
        )
        Write-Host 'NO-DROP PASS: schema and data attestation passed.'
    }

    if ($suite -eq 'ALL') {
        Write-Host 'UNIT RUN PASS: UNIT-STANDARD 123 + NO-DROP 506 = 629 tests (sequential cohorts).'
    }
} finally {
    if ($null -eq $oldCiEnvironment) {
        Remove-Item Env:CI_ENVIRONMENT -ErrorAction SilentlyContinue
    } else {
        $env:CI_ENVIRONMENT = $oldCiEnvironment
    }
    if ($null -eq $oldSkipGrants) {
        Remove-Item Env:ERP_NO_DROP_SKIP_GRANTS -ErrorAction SilentlyContinue
    } else {
        $env:ERP_NO_DROP_SKIP_GRANTS = $oldSkipGrants
    }
    Pop-Location
}
