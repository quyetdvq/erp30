# TST-PERF-003E-02: unified invocation of TWO approved and independently tested
# lanes. Retains each lane's own fail-closed runner and fingerprints.
# Does NOT enable full regression, legacy-fast, migration, DDL or unapproved tests.
[CmdletBinding()]
param(
    [switch]$WithPHPStan,
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
    function Get-ValidatedAttestation {
        $lines = @(& php tools/no-drop-schema-attest.php "--target=$Target" "--prefix=$Prefix" --with-data)
        if ($LASTEXITCODE -ne 0 -or $lines.Count -ne 2) {
            throw 'VALIDATED LANES: malformed or failed NO-DROP attestation. STOP.'
        }
        $record = $lines[-1] | ConvertFrom-Json
        if ($record.status -ne 'NO_DROP_VERIFIED' -or
            $record.target -ne $Target -or $record.prefix -ne $Prefix -or
            $record.effective_account -ne 'erp_test_runner@127.0.0.1' -or
            $record.table_count -ne 41 -or $record.row_count -ne 43 -or
            $record.schema_sha256 -ne $ExpectedSchema -or
            $record.data_sha256 -ne $ExpectedData) {
            throw 'VALIDATED LANES: unexpected account/target/schema/data baseline. STOP.'
        }
        return $record
    }

    # No database access until approved source, unit bootstrap, and disjoint
    # manifest are checked. Does not auto-promote anything from static audit.
    & php tools/test-validated-lanes-guard.php
    if ($LASTEXITCODE -ne 0) { throw 'VALIDATED LANES: source/cross-lane guard failed.' }
    & php tools/test-no-drop-guard.php
    if ($LASTEXITCODE -ne 0) { throw 'VALIDATED LANES: privilege-parser self-check failed.' }
    & php tools/test-no-drop-data-guard.php
    if ($LASTEXITCODE -ne 0) { throw 'VALIDATED LANES: fingerprint self-check failed.' }

    $before = Get-ValidatedAttestation
    Write-Host 'VALIDATED LANES PREFLIGHT PASSED: 41 tables / 43 rows (read-only).'

    $watch = [System.Diagnostics.Stopwatch]::StartNew()
    $completed = $false
    try {
        Write-Host "`nVALIDATED LANES: UNIT NO-DB (13 classes, 106 expected cases/run)"
        & .\tools\test-unit-no-db-wave01.ps1 -VerifyDbFingerprint `
            -Target $Target -Prefix $Prefix -ExpectedSchema $ExpectedSchema -ExpectedData $ExpectedData
        if (-not $?) { throw 'VALIDATED LANES: unit lane did not complete successfully.' }

        Write-Host "`nVALIDATED LANES: ROLLBACK DB (35 classes, 199 expected cases/run)"
        & .\tools\test-no-drop-cohort.ps1 `
            -Target $Target -Prefix $Prefix -ExpectedSchema $ExpectedSchema -ExpectedData $ExpectedData
        if (-not $?) { throw 'VALIDATED LANES: DB cohort did not complete successfully.' }

        if ($WithPHPStan) {
            & php vendor/bin/phpstan analyse --no-progress --no-ansi --memory-limit=1G
            if ($LASTEXITCODE -ne 0) { throw 'VALIDATED LANES: PHPStan failed.' }
        }
        $completed = $true
    } finally {
        $watch.Stop()
        Write-Host "VALIDATED LANES elapsed: $([math]::Round($watch.Elapsed.TotalSeconds,3)) seconds"
        # Always attest after either lane fails. Do not clean/reset anything.
        $after = Get-ValidatedAttestation
        if ($after.schema_sha256 -ne $before.schema_sha256 -or
            $after.data_sha256 -ne $before.data_sha256 -or
            $after.table_count -ne $before.table_count -or
            $after.row_count -ne $before.row_count -or
            $after.effective_account -ne $before.effective_account) {
            throw 'VALIDATED LANES: SCHEMA/DATA DRIFT. STOP. No cleanup performed.'
        }
        Write-Host 'VALIDATED LANES POSTFLIGHT PASSED: schema and data unchanged.'
    }

    if (-not $completed) { throw 'VALIDATED LANES: incomplete validation.' }
    Write-Host 'VALIDATED LANES PASSED: 48 distinct approved classes; 295 test methods; 305 expected cases per order; not full regression.'
} finally {
    Pop-Location
}
