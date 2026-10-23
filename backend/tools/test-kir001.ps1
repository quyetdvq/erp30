# Dedicated KIR-001 Product COMBO + AUTO transaction remediation verification.
# CHECK is source-only. RUN uses CI4 DatabaseTestTrait against guarded erp_test.

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$action = if ($args.Count -eq 0) { 'CHECK' } else { ([string]$args[0]).ToUpperInvariant() }
if ($args.Count -gt 1 -or $action -notin @('CHECK', 'RUN')) {
    throw 'Usage: .\tools\test-kir001.ps1 CHECK|RUN'
}

$backend = Split-Path -Parent $PSScriptRoot
$oldCiEnvironment = [Environment]::GetEnvironmentVariable('CI_ENVIRONMENT', 'Process')

function Invoke-PHPChecked {
    param([string[]]$Arguments, [string]$Description)
    & php @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$Description failed (PHP exit code $LASTEXITCODE). STOP."
    }
}

Push-Location $backend
try {
    Invoke-PHPChecked -Description 'KIR-001 source gate' -Arguments @('tools/test-kir001-source-gate.php')
    if ($action -eq 'CHECK') {
        Write-Host 'KIR-001 CHECK PASS: source-only; no database connected.'
        return
    }

    Write-Warning 'KIR-001 RUN may use CI4 migration/refresh behavior on erp_test. Back up erp_test first.'
    $env:CI_ENVIRONMENT = 'testing'

    $runtimeExitCode = 0
    $cleanupExitCode = 0
    & php vendor/bin/phpunit -c phpunit.kir001.xml --no-coverage --colors=never --fail-on-empty-test-suite --testdox
    $runtimeExitCode = $LASTEXITCODE

    # Always remove committed worker fixtures before returning control to another lane.
    & php tools/test-kir001-cleanup.php
    $cleanupExitCode = $LASTEXITCODE

    if ($cleanupExitCode -ne 0) {
        throw "KIR-001 cleanup/residue attestation failed (PHP exit code $cleanupExitCode). STOP."
    }
    if ($runtimeExitCode -ne 0) {
        throw "KIR-001 MariaDB/Shield remediation verification failed (PHP exit code $runtimeExitCode). STOP."
    }
    Write-Host 'KIR-001 C2 RUNTIME REMEDIATION PASS; fixture cleanup/residue attestation PASS.'
} finally {
    if ($null -eq $oldCiEnvironment) {
        Remove-Item Env:CI_ENVIRONMENT -ErrorAction SilentlyContinue
    } else {
        $env:CI_ENVIRONMENT = $oldCiEnvironment
    }
    Pop-Location
}
