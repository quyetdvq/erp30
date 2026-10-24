# Backend v2 daily test entrypoint, modeled on backend_basline/tools/test-unit.ps1.
# Usage (from backend/):
#   .\tools\test-unit.ps1 CHECK
#   .\tools\test-unit.ps1 RUN                 # offline only; never writes DB
#   .\tools\test-unit.ps1 RUN UNIT-STANDARD   # same offline test suite
#   .\tools\test-unit.ps1 RUN DB-READ         # existing DB, read-only audits
#   .\tools\test-unit.ps1 RUN ALL             # offline + read-only audits
#   .\tools\test-unit.ps1 RUN INTEGRATION     # explicit disposable MariaDB sandbox + M3.4D acceptance
#   .\tools\test-unit.ps1 RUN ACCEPTANCE      # source/golden-master gates; no SQL/HTTP
# Optional '-' separator is accepted, like the baseline runner.
# NO-DROP is not an alias: the baseline's data attestation is NOT reproduced here.

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$tokens = @($args)
$usage = 'Usage: .\tools\test-unit.ps1 CHECK|RUN [UNIT-STANDARD|DB-READ|ALL|INTEGRATION|ACCEPTANCE] (optional separator: RUN - DB-READ)'
if ($tokens.Count -lt 1 -or $tokens.Count -gt 3) { throw $usage }
$action = ([string]$tokens[0]).ToUpperInvariant()
if ($action -notin @('CHECK', 'RUN')) { throw $usage }
$suite = 'UNIT-STANDARD'
if ($tokens.Count -eq 2) {
    $suite = [string]$tokens[1]
} elseif ($tokens.Count -eq 3) {
    if ([string]$tokens[1] -ne '-') { throw $usage }
    $suite = [string]$tokens[2]
}
$suite = $suite.TrimStart('-').ToUpperInvariant()
if ($suite -notin @('UNIT-STANDARD', 'DB-READ', 'ALL', 'INTEGRATION', 'ACCEPTANCE')) { throw $usage }

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
    Invoke-PHPChecked -Description 'Pinned source and test inventory' -Arguments @('tools/test-unit-check.php')

    if ($action -eq 'CHECK') {
        Write-Host 'CHECK PASS: source and OpenAPI inventory only. No test, HTTP, or DB operation.'
        return
    }

    if ($suite -in @('UNIT-STANDARD', 'ALL')) {
        Write-Host '===== UNIT-STANDARD: pure/fixture regressions (NO DATABASE) ====='
        Invoke-PHPChecked -Description 'Offline regression suite' -Arguments @('tools/m34b_run.php', '--offline')
        Write-Host 'UNIT-STANDARD PASS: offline only; NOT HTTP/MariaDB integration.'
        Invoke-PHPChecked -Description 'M3.4D static and baseline golden-master acceptance' -Arguments @('tools/m34d_accept.php', '--offline')
        Invoke-PHPChecked -Description 'M4 source/route extension acceptance (14 new APIs; no DB)' -Arguments @('tools/m4_accept.php')
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
        Write-Host 'INTEGRATION PASS: M3.4D sandbox 29/29; M4 new HTTP/MariaDB operations remain UNTESTED.'
    }

    Write-Host "TEST-UNIT RUN PASS: $suite."
} finally {
    Pop-Location
}
