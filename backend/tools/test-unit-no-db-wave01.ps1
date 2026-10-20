# TST-PERF-003E-01: audited no-database PHPUnit cohort (PowerShell 5.1+).
# Does not modify DB credentials or .env; child PHPUnit poisons both CI4 DB groups.
[CmdletBinding()]
param(
    [switch]$WithPHPStan,
    [switch]$VerifyDbFingerprint,
    [string]$Target = '127.0.0.1:3306/erp_test',
    [string]$Prefix = 'dinktech_',
    [string]$ExpectedSchema = '8d12e2ec455115dd27a8d4bcabc6f5fa2df5d09de7128340fb525be213b4f7fa',
    [string]$ExpectedData = 'b3f7c3932415318547b3d95958ca6abe0734a40897691e4f125dad796d6f73cb'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$backend = Split-Path -Parent $PSScriptRoot
Push-Location $backend
try {
    function Get-ReadOnlyAttestation {
        $lines = @(& php tools/no-drop-schema-attest.php "--target=$Target" "--prefix=$Prefix" --with-data)
        if ($LASTEXITCODE -ne 0 -or $lines.Count -ne 2) {
            throw 'UNIT NO-DB: NO-DROP read-only attestation failed; STOP.'
        }
        $record = $lines[-1] | ConvertFrom-Json
        if ($record.status -ne 'NO_DROP_VERIFIED' -or $record.target -ne $Target -or
            $record.prefix -ne $Prefix -or
            $record.effective_account -ne 'erp_test_runner@127.0.0.1' -or
            $record.table_count -ne 41 -or $record.row_count -ne 43 -or
            $record.schema_sha256 -ne $ExpectedSchema -or
            $record.data_sha256 -ne $ExpectedData) {
            throw 'UNIT NO-DB: unexpected DB account or baseline; STOP.'
        }
        return $record
    }

    $before = $null
    if ($VerifyDbFingerprint) {
        $before = Get-ReadOnlyAttestation
        Write-Host 'UNIT NO-DB PREFLIGHT: read-only schema/data baseline PASS.'
    }

    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    $finished = $false
    try {
        & php tools/test-unit-no-db-guard.php
        if ($LASTEXITCODE -ne 0) { throw 'UNIT NO-DB: manifest/source/XML self-check failed.' }

        foreach ($seed in @('default', '17', '29')) {
            Write-Host "`nUNIT NO-DB COHORT | $seed"
            $args = @('tools/test-current.php', '--mode=unit-cohort')
            if ($seed -ne 'default') { $args += @('--order=random', "--seed=$seed") }
            & php @args
            if ($LASTEXITCODE -ne 0) { throw "UNIT NO-DB FAILED ($seed). STOP; do not alter the test DB." }
        }

        if ($WithPHPStan) {
            & php vendor/bin/phpstan analyse --no-progress --no-ansi --memory-limit=1G
            if ($LASTEXITCODE -ne 0) { throw 'UNIT NO-DB: PHPStan failed.' }
        }
        $finished = $true
    } finally {
        $sw.Stop()
        Write-Host "UNIT NO-DB elapsed: $([math]::Round($sw.Elapsed.TotalSeconds,3)) seconds"
        if ($VerifyDbFingerprint) {
            $after = Get-ReadOnlyAttestation
            if ($after.schema_sha256 -ne $before.schema_sha256 -or
                $after.data_sha256 -ne $before.data_sha256 -or
                $after.table_count -ne $before.table_count -or
                $after.row_count -ne $before.row_count -or
                $after.effective_account -ne $before.effective_account) {
                throw 'UNIT NO-DB: schema/data drift. STOP without cleanup.'
            }
            Write-Host 'UNIT NO-DB POSTFLIGHT: 41 tables / 43 rows unchanged.'
        }
    }
    if (-not $finished) { throw 'UNIT NO-DB: incomplete validation.' }
    Write-Host 'UNIT NO-DB CANDIDATE VALIDATION PASSED (13 classes / 104 methods; not full regression).'
} finally {
    Pop-Location
}
