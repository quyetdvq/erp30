# 03C-04C quarantine investigation. No test execution, no cleanup, no DML.
[CmdletBinding()]
param([switch]$Inspect)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Push-Location (Split-Path -Parent $PSScriptRoot)
try {
    & php tools/scratch-numbering-quarantine-readonly.php --plan
    if ($LASTEXITCODE -ne 0) { throw '03C-04C STOP: offline forensic plan failed.' }
    if (-not $Inspect) {
        Write-Host '03C-04C VERIFIED OFFLINE. Use -Inspect for read-only investigation; all scratch methods remain on HOLD.'
        return
    }
    if (Test-Path Env:ERP_SCRATCH_DB_PASSWORD) {
        throw '03C-04C STOP: inherited scratch password denied.'
    }
    if (Test-Path Env:ERP_SCRATCH_NUMBERING_TOKEN) {
        throw '03C-04C STOP: scratch worker environment is not allowed.'
    }
    Write-Host '03C-04C ERP_TEST READ-ONLY FORENSIC:'
    & php tools/scratch-numbering-quarantine-readonly.php --erp-test
    if ($LASTEXITCODE -ne 0) {
        Write-Warning '03C-04C erp_test read-only inspection inconclusive; preserve both databases.'
    }
    $bstr = [IntPtr]::Zero
    try {
        $env:ERP_SCRATCH_DB_USER = 'erp_scratch_runner'
        $secret = Read-Host -Prompt 'Nhap mat khau erp_test_scratch (CHI DOC)' -AsSecureString
        if ($secret.Length -eq 0) { throw '03C-04C STOP: empty scratch password.' }
        $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
        $env:ERP_SCRATCH_DB_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
        Write-Host '03C-04C SCRATCH READ-ONLY FORENSIC:'
        & php tools/scratch-numbering-quarantine-readonly.php --scratch
        if ($LASTEXITCODE -ne 0) { throw '03C-04C STOP: scratch read-only inspection inconclusive.' }
    } finally {
        Remove-Item Env:ERP_SCRATCH_DB_PASSWORD -ErrorAction SilentlyContinue
        Remove-Item Env:ERP_SCRATCH_DB_USER -ErrorAction SilentlyContinue
        if ($bstr -ne [IntPtr]::Zero) {
            [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
        }
        Remove-Variable secret -ErrorAction SilentlyContinue
    }
    Write-Host '03C-04C FORENSIC COMPLETE (READ-ONLY). QUARANTINE remains active; do not run PHPUnit, DELETE or reset schemas.'
} finally {
    Pop-Location
}
