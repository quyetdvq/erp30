# 03C-04B five-method scratch runner. Default: offline only. Live: ONE exact method.
# NEVER use against erp_test, never invoke directly as vendor/bin/phpunit.
[CmdletBinding()]
param(
    [switch]$RunMethod,
    [string]$Class = '',
    [string]$Method = ''
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Push-Location (Split-Path -Parent $PSScriptRoot)
try {
    if ($RunMethod) {
        throw '03C-04B HOLD: live scratch PHPUnit is quarantined; only read-only forensic inspection is allowed.'
    }
    if (-not $RunMethod -and ($Class -ne '' -or $Method -ne '')) {
        throw '03C-04B STOP: -Class/-Method require -RunMethod.'
    }
    if ($RunMethod -and ($Class -cnotmatch '^[A-Za-z][A-Za-z0-9_]*Test$' -or
        $Method -cnotmatch '^test[A-Z][A-Za-z0-9_]*$')) {
        throw '03C-04B STOP: one exact original test class/method is required.'
    }
    & php tools/test-unit-no-drop-source-gate.php
    if ($LASTEXITCODE -ne 0) { throw '03C-04B STOP: NO-DROP source gate failed.' }
    & php tools/test-validated-lanes-guard.php
    if ($LASTEXITCODE -ne 0) { throw '03C-04B STOP: validated lane gate failed.' }
    & php tools/test-scratch-transaction-method-plan.php
    if ($LASTEXITCODE -ne 0) { throw '03C-04B STOP: seven-method contract failed.' }
    & php tools/test-scratch-numbering-phpunit.php
    if ($LASTEXITCODE -ne 0) { throw '03C-04B STOP: pure runner gate failed.' }
    & php tools/scratch-numbering-phpunit.php --plan
    if ($LASTEXITCODE -ne 0) { throw '03C-04B STOP: offline runner plan failed.' }
    if (-not $RunMethod) {
        Write-Host '03C-04B VERIFIED OFFLINE: scratch PHPUnit runner is not authorized for DML without -RunMethod.'
        return
    }
    if (Test-Path Env:ERP_SCRATCH_DB_PASSWORD) {
        throw '03C-04B STOP: inherited scratch password denied; use a clean shell.'
    }
    if (Test-Path Env:ERP_SCRATCH_NUMBERING_TOKEN) {
        throw '03C-04B STOP: inherited scratch worker token denied.'
    }
    $bstr = [IntPtr]::Zero
    try {
        $env:ERP_SCRATCH_DB_USER = 'erp_scratch_runner'
        $secret = Read-Host -Prompt 'Nhap mat khau erp_test_scratch (chi scratch)' -AsSecureString
        if ($secret.Length -eq 0) { throw '03C-04B STOP: empty scratch password.' }
        $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
        $env:ERP_SCRATCH_DB_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
        & .\tools\test-scratch-readiness.ps1 -CheckScratch
        if ($LASTEXITCODE -ne 0) { throw '03C-04B STOP: scratch preflight failed.' }
        $exitCode = 2
        try {
            & php tools/scratch-numbering-phpunit.php `
                --execute `
                --target=127.0.0.1:3306/erp_test_scratch `
                --prefix=dinktech_ `
                "--class=$Class" `
                "--method=$Method"
            $exitCode = $LASTEXITCODE
        } finally {
            & .\tools\test-scratch-readiness.ps1 -CheckScratch
            if ($LASTEXITCODE -ne 0) {
                throw '03C-04B QUARANTINE: scratch postflight NOT clean. Do not rerun or reset the DB.'
            }
        }
        if ($exitCode -ne 0) {
            throw '03C-04B STOP: method did not PASS; postflight clean. Keep method unverified.'
        }
        Write-Host '03C-04B VERIFIED: one original PHPUnit method PASS, scratch cleanup & 03C-01 postflight PASS. No class promoted.'
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
