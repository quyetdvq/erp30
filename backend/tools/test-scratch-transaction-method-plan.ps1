# 03C-04A: pure offline, read-only method feasibility gate.
# No credentials, database connection, DML, PHPUnit run or cleanup.
[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Push-Location (Split-Path -Parent $PSScriptRoot)
try {
    if ($args.Count -ne 0) { throw '03C-04 STOP: no live/execute arguments are supported.' }
    & php tools/test-unit-no-drop-source-gate.php
    if ($LASTEXITCODE -ne 0) { throw '03C-04 STOP: NO-DROP source gate failed.' }
    & php tools/test-validated-lanes-guard.php
    if ($LASTEXITCODE -ne 0) { throw '03C-04 STOP: validated-lanes guard failed.' }
    & php tools/test-scratch-transaction-method-plan.php
    if ($LASTEXITCODE -ne 0) { throw '03C-04 STOP: scratch method pure contract failed.' }
    & php tools/scratch-transaction-method-plan.php --plan
    if ($LASTEXITCODE -ne 0) { throw '03C-04 STOP: scratch method plan failed.' }
    Write-Host '03C-04A VERIFIED OFFLINE: 7 original methods source-pinned; scratch execution and cleanup remain DENIED.'
} finally {
    Pop-Location
}
