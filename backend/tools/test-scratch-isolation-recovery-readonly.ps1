# 03C-04D: read-only proof of three wrong-target S04B rows. NO cleanup.
[CmdletBinding()]
param([switch]$Inspect)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Push-Location (Split-Path -Parent $PSScriptRoot)
try {
    & php tools/test-scratch-isolation-recovery-readonly.php
    if ($LASTEXITCODE -ne 0) { throw '03C-04D HOLD: offline gate failed.' }
    & php tools/scratch-isolation-recovery-readonly.php --plan
    if ($LASTEXITCODE -ne 0) { throw '03C-04D HOLD: offline plan failed.' }
    if (-not $Inspect) {
        Write-Host '03C-04D VERIFIED OFFLINE: no database accessed; QUARANTINE remains active.'
        return
    }
    if (Test-Path Env:ERP_SCRATCH_DB_PASSWORD) { throw '03C-04D HOLD: inherited scratch credentials rejected.' }
    if (Test-Path Env:ERP_SCRATCH_NUMBERING_TOKEN) { throw '03C-04D HOLD: inherited worker context rejected.' }
    & php tools/scratch-isolation-recovery-readonly.php --inspect
    if ($LASTEXITCODE -ne 0) { throw '03C-04D HOLD: incident ownership/virtual baseline not verified.' }
    Write-Host '03C-04D READ-ONLY INSPECTION COMPLETE. Do not delete, reset or rerun PHPUnit.'
} finally {
    Pop-Location
}
