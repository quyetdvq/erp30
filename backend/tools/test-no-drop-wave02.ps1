# TST-PERF-003D WAVE02 - reviewed five-class NO-DROP cohort extension.
# PowerShell 5.1+; no DB reset, migration, DDL, truncate or automatic cleanup.
[CmdletBinding()]
param(
    [string]$Target = '127.0.0.1:3306/erp_test',
    [string]$Prefix = 'dinktech_',
    [string]$ExpectedSchema = '8d12e2ec455115dd27a8d4bcabc6f5fa2df5d09de7128340fb525be213b4f7fa',
    [string]$ExpectedData = 'b3f7c3932415318547b3d95958ca6abe0734a40897691e4f125dad796d6f73cb',
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
            throw 'NO-DROP attestation failed. Do not reset or clean the database.'
        }
        $record = $lines[-1] | ConvertFrom-Json
        if ($record.status -ne 'NO_DROP_VERIFIED' -or
            $record.target -ne $Target -or
            $record.prefix -ne $Prefix -or
            $record.effective_account -ne 'erp_test_runner@127.0.0.1' -or
            $record.table_count -ne 41 -or $record.row_count -ne 43) {
            throw 'NO-DROP unexpected database, user or baseline row count.'
        }
        return $record
    }

    $before = Get-NoDropRecord
    if ($before.schema_sha256 -ne $ExpectedSchema -or $before.data_sha256 -ne $ExpectedData) {
        throw 'WAVE02 baseline mismatch. STOP; no cleanup.'
    }
    Write-Host 'WAVE02 PREFLIGHT PASSED (read-only).'

    $lines = @(& php tools/no-drop-audit.php --format=json)
    if ($LASTEXITCODE -ne 0 -or $lines.Count -eq 0) {
        throw 'WAVE02 static audit failed.'
    }
    $audit = ($lines -join "`n") | ConvertFrom-Json
    $approved = @($audit.classes | Where-Object { $_.status -eq 'APPROVED' })
    $methods = 0
    foreach ($row in $approved) { $methods += [int]$row.test_methods }
    $bad = @($audit.classes | Where-Object {
        $_.status -eq 'APPROVED_SOURCE_MISMATCH' -or
        $_.status -eq 'QUARANTINE_SOURCE_MISMATCH' -or
        $_.status -eq 'UNAPPROVED_TRANSACTION_TRAIT'
    })
    $quarantine = @($audit.classes | Where-Object { $_.status -eq 'QUARANTINED_UNSAFE' })
    if ($audit.policy -ne 'AUDIT_ONLY_NO_APPROVAL' -or
        $audit.summary.approved_classes -ne 35 -or
        $approved.Count -ne 35 -or $methods -ne 191 -or
        $bad.Count -ne 0 -or $quarantine.Count -ne 1 -or
        $quarantine[0].class -ne 'P1Prd002ProductUomMasterServiceTest') {
        throw 'WAVE02 unsafe source, changed allowlist or quarantine mismatch.'
    }
    $checks = @(
        @('contact',      'BeReorg003ContactCharacterizationTest',                  9, 'backend/tests/database/BeReorg003ContactCharacterizationTest.php'),
        @('organization', 'BeReorg002OrganizationCharacterizationTest',             8, 'backend/tests/database/BeReorg002OrganizationCharacterizationTest.php'),
        @('organization', 'P1Org002OrganizationServiceTest',                        7, 'backend/tests/unit/P1Org002OrganizationServiceTest.php'),
        @('workflow',     'BeReorg011BusinessActionsDatabaseTest',                   5, 'backend/tests/database/BeReorg011BusinessActionsDatabaseTest.php'),
        @('workflow',     'BeReorg012WriteBoundaryDatabaseTest',                    5, 'backend/tests/database/BeReorg012WriteBoundaryDatabaseTest.php')
    )
    foreach ($entry in $checks) {
        $matches = @($approved | Where-Object {
            $_.class -eq $entry[1] -and $_.approved_module -eq $entry[0] -and
            $_.test_methods -eq $entry[2] -and $_.path -eq $entry[3]
        })
        if ($matches.Count -ne 1) {
            throw "WAVE02 class audit mismatch: $($entry[1])"
        }
    }
    Write-Host 'WAVE02 AUDIT PASSED: 35 approved classes, 191 methods; Product UOM quarantined.'

    $watch = [System.Diagnostics.Stopwatch]::StartNew()
    try {
        # Each first-pass class run has its own pre/post schema + data fingerprint.
        # Never auto-retry or auto-reset on failure.
        foreach ($entry in $checks) {
            $module = $entry[0]
            $class = $entry[1]
            Write-Host "`nWAVE02 PER-CLASS | $class"
            & php tools/test-current.php --mode=module "--module=$module" "--class=$class" `
                --confirm-test-db "--target=$Target" "--prefix=$Prefix"
            if ($LASTEXITCODE -ne 0) {
                throw "WAVE02 isolated test failed: $class. Stop without cleanup."
            }
        }
        Write-Host '`nWAVE02 5-CLASS ISOLATION PASSED. Running full approved cohort.'
        & "$PSScriptRoot\test-no-drop-cohort.ps1" -Target $Target -Prefix $Prefix `
            -ExpectedSchema $ExpectedSchema -ExpectedData $ExpectedData -WithPHPStan:$WithPHPStan
        if (-not $?) {
            throw 'WAVE02 cohort script failed.'
        }
    } finally {
        $watch.Stop()
        Write-Host "WAVE02 elapsed: $([math]::Round($watch.Elapsed.TotalSeconds,3)) seconds"
        $after = Get-NoDropRecord
        if ($after.schema_sha256 -ne $before.schema_sha256 -or
            $after.data_sha256 -ne $before.data_sha256 -or
            $after.table_count -ne $before.table_count -or
            $after.row_count -ne $before.row_count -or
            $after.effective_account -ne $before.effective_account) {
            throw 'WAVE02 SCHEMA/DATA DRIFT. STOP without cleanup.'
        }
        Write-Host 'WAVE02 POSTFLIGHT PASSED: 41 tables / 43 rows unchanged.'
    }
    Write-Host 'WAVE02 NO-DROP VALIDATION PASSED (191 approved methods; NOT full regression).'
} finally {
    Pop-Location
}
