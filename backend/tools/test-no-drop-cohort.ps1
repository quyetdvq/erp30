# TST-PERF-003C - one command, one PHPUnit process per order, approved classes ONLY.
# No database reset, DDL, TRUNCATE or fixture cleanup. PowerShell 5.1 compatible.
[CmdletBinding()]
param(
    [string]$Target = '127.0.0.1:3306/erp_test',
    [string]$Prefix = 'dinktech_',
    [string]$ExpectedSchema = '8d12e2ec455115dd27a8d4bcabc6f5fa2df5d09de7128340fb525be213b4f7fa',
    [string]$ExpectedData = 'b3f7c3932415318547b3d95958ca6abe0734a40897691e4f125dad796d6f73cb',
    [int[]]$Seeds = @(17, 29),
    [switch]$WithPHPStan
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$backend = Split-Path -Parent $PSScriptRoot
Push-Location $backend
try {
    function Get-NoDropRecord {
        $lines = @(& php tools/no-drop-schema-attest.php "--target=$Target" "--prefix=$Prefix" --with-data)
        if ($LASTEXITCODE -ne 0 -or $lines.Count -ne 2) {
            throw 'NO-DROP attestation failed. Do not run tests.'
        }
        $record = $lines[-1] | ConvertFrom-Json
        if ($record.status -ne 'NO_DROP_VERIFIED' -or
            $record.target -ne $Target -or
            $record.prefix -ne $Prefix -or
            $record.effective_account -ne 'erp_test_runner@127.0.0.1' -or
            $record.table_count -ne 41 -or
            $record.row_count -ne 43) {
            throw 'NO-DROP attestation returned an unexpected database or account.'
        }
        return $record
    }

    $before = Get-NoDropRecord
    if ($before.schema_sha256 -ne $ExpectedSchema -or
        $before.data_sha256 -ne $ExpectedData) {
        throw 'NO-DROP expected baseline mismatch. Stop without cleanup.'
    }
    Write-Host 'COHORT PREFLIGHT PASSED (read-only).'

    # Full audit is read-only and cannot add classes to the reviewed allowlist.
    $auditLines = @(& php tools/no-drop-audit.php --format=json)
    if ($LASTEXITCODE -ne 0 -or $auditLines.Count -eq 0) {
        throw 'NO-DROP static audit failed.'
    }
    $audit = ($auditLines -join "`n") | ConvertFrom-Json
    if ($audit.policy -ne 'AUDIT_ONLY_NO_APPROVAL' -or
        $audit.summary.approved_classes -lt 1) {
        throw 'NO-DROP static audit is malformed.'
    }
    foreach ($row in $audit.classes) {
        if ($row.status -eq 'APPROVED_SOURCE_MISMATCH' -or
            $row.status -eq 'UNAPPROVED_TRANSACTION_TRAIT' -or
            $row.status -eq 'QUARANTINE_SOURCE_MISMATCH') {
            throw "NO-DROP source mismatch detected: $($row.class)."
        }
    }
    $excluded = @($audit.classes | Where-Object { $_.status -eq 'QUARANTINED_UNSAFE' })
    if ($excluded.Count -ne 1 -or
        $excluded[0].class -ne 'P1Prd002ProductUomMasterServiceTest') {
        throw 'NO-DROP: unexpected quarantine inventory. STOP.'
    }
    Write-Host 'COHORT QUARANTINE VERIFIED: Product UOM EXCLUDED (unapproved transaction test).'
    Write-Host "COHORT AUDIT PASSED: $($audit.summary.approved_classes) approved classes."

    $started = [System.Diagnostics.Stopwatch]::StartNew()
    try {
        $orders = @(-1) + $Seeds
        foreach ($seed in $orders) {
            $args = @(
                'tools/test-current.php',
                '--mode=cohort',
                '--confirm-test-db',
                "--target=$Target",
                "--prefix=$Prefix"
            )
            if ($seed -ge 0) {
                $args += @('--order=random', "--seed=$seed")
                Write-Host "`nNO-DROP COHORT | random seed=$seed"
            } else {
                Write-Host "`nNO-DROP COHORT | default"
            }
            & php @args
            if ($LASTEXITCODE -ne 0) {
                throw "NO-DROP COHORT failed for seed=$seed. Do NOT reset database."
            }
        }
    } finally {
        $started.Stop()
        Write-Host "COHORT elapsed: $([math]::Round($started.Elapsed.TotalSeconds, 3)) seconds"
        # Always examine the database after the run, even when PHPUnit fails.
        $after = Get-NoDropRecord
        if ($after.schema_sha256 -ne $before.schema_sha256 -or
            $after.data_sha256 -ne $before.data_sha256 -or
            $after.table_count -ne $before.table_count -or
            $after.row_count -ne $before.row_count -or
            $after.effective_account -ne $before.effective_account) {
            throw 'NO-DROP COHORT SCHEMA/DATA DRIFT. Stop; no automatic cleanup.'
        }
        Write-Host 'COHORT POSTFLIGHT PASSED: 41 tables / 43 rows unchanged.'
    }
    if ($WithPHPStan) {
        & php vendor/bin/phpstan analyse --no-progress --no-ansi --memory-limit=1G
        if ($LASTEXITCODE -ne 0) {
            throw 'PHPStan failed.'
        }
    }
    Write-Host 'NO-DROP COHORT VALIDATION PASSED (approved scope only; not full regression).'
} finally {
    Pop-Location
}
