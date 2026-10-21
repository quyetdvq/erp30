# TST-PERF-003E-03C-02. Explicit, single-scenario scratch-only DML canary.
# Default is pure offline PLAN. -RunPilot asks interactively for scratch secret,
# runs 03C-01 read-only preflight, fixed-source canary, and 03C-01 postflight.
[CmdletBinding()]
param(
    [switch]$RunPilot,
    [switch]$Diagnose
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Push-Location (Split-Path -Parent $PSScriptRoot)
try {
    if ($RunPilot -and $Diagnose) {
        throw '03C-02 STOP: -RunPilot and -Diagnose are mutually exclusive.'
    }
    & php tools/test-scratch-numbering-pilot.php
    if ($LASTEXITCODE -ne 0) { throw '03C-02 STOP: offline negative gate failed.' }
    & php tools/test-unit-no-drop-source-gate.php
    if ($LASTEXITCODE -ne 0) { throw '03C-02 STOP: unit NO-DROP source gate failed.' }
    & php tools/scratch-numbering-pilot.php --plan
    if ($LASTEXITCODE -ne 0) { throw '03C-02 STOP: offline plan rejected.' }
    & php tools/scratch-numbering-diagnose.php --plan
    if ($LASTEXITCODE -ne 0) { throw '03C-02D STOP: diagnostic offline plan rejected.' }
    if (-not $RunPilot -and -not $Diagnose) {
        Write-Host '03C-02 PLAN VERIFIED (OFFLINE). No database connection, no SQL writes. Use -RunPilot for the scratch-only canary.'
        return
    }

    $bstr = [IntPtr]::Zero
    try {
        if (Test-Path Env:ERP_SCRATCH_DB_PASSWORD) {
            throw '03C-02 STOP: inherited scratch password is not allowed. Start a clean shell.'
        }
        $env:ERP_SCRATCH_DB_USER = 'erp_scratch_runner'
        $secret = Read-Host -Prompt 'Nhap mat khau erp_test_scratch (chi scratch)' -AsSecureString
        if ($secret.Length -eq 0) { throw '03C-02 STOP: empty scratch secret.' }
        $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
        $env:ERP_SCRATCH_DB_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)

        # Fail closed before ANY DML; existing 03C-01 validates both schemas,
        # dedicated grants, 50 FKs, and empty scratch business tables.
        & .\tools\test-scratch-readiness.ps1 -CheckScratch
        if ($LASTEXITCODE -ne 0) { throw '03C-02 STOP: mandatory read-only scratch preflight failed.' }

        $pilotExit = 2
        try {
            if ($Diagnose) {
                & php tools/scratch-numbering-diagnose.php `
                    --read-only `
                    --target=127.0.0.1:3306/erp_test_scratch `
                    --prefix=dinktech_
            } else {
                & php tools/scratch-numbering-pilot.php `
                    --execute `
                    --target=127.0.0.1:3306/erp_test_scratch `
                    --prefix=dinktech_
            }
            $pilotExit = $LASTEXITCODE
        } finally {
            # Always demand a fresh full 03C-01 attestation, even if the
            # canary failed. NEVER attempt a broad emergency cleanup.
            & .\tools\test-scratch-readiness.ps1 -CheckScratch
            if ($LASTEXITCODE -ne 0) {
                if ($Diagnose) {
                    throw '03C-02D STOP: read-only postflight failed. Do not start pilot; investigate.'
                }
                throw '03C-02 QUARANTINE: scratch postflight failed. Do not rerun or reset DB; inspect exact owned rows.'
            }
        }
        if ($pilotExit -ne 0) {
            if ($Diagnose) {
                throw '03C-02D STOP: read-only diagnostic failed; postflight was clean. Do not run the live canary yet.'
            }
            throw '03C-02 STOP: canary failed; postflight was clean. Investigate the classified stage.'
        }
        if ($Diagnose) {
            Write-Host '03C-02D VERIFIED READ-ONLY: dependency diagnostic and 03C-01 postflight passed. Canary execution remains UNVERIFIED.'
        } else {
            Write-Host '03C-02 VERIFIED: scratch-only canary + cleanup + 03C-01 postflight passed. 506/506 unit-safe checkpoint unchanged.'
        }
    } finally {
        Remove-Item Env:ERP_SCRATCH_DB_PASSWORD -ErrorAction SilentlyContinue
        Remove-Item Env:ERP_SCRATCH_DB_USER -ErrorAction SilentlyContinue
        if ($bstr -ne [IntPtr]::Zero) {
            [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
        }
        Remove-Variable secret -ErrorAction SilentlyContinue
    }
} finally {
    Pop-Location
}
