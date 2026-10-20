# TST-PERF-003D WAVE01 - fail-closed bulk acceptance for 12 NEW reviewed classes.
# Single command; one per-class isolation pass + whole reviewed cohort in three orders.
# NO DROP/TRUNCATE/migration/fixture cleanup, and no DB writes from this script.
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
            throw 'NO-DROP attestation failed. Stop without cleanup.'
        }
        $record = $lines[-1] | ConvertFrom-Json
        if ($record.status -ne 'NO_DROP_VERIFIED' -or
            $record.target -ne $Target -or
            $record.prefix -ne $Prefix -or
            $record.effective_account -ne 'erp_test_runner@127.0.0.1' -or
            $record.table_count -ne 41 -or $record.row_count -ne 43) {
            throw 'NO-DROP: incorrect target, DML account, or baseline size.'
        }
        return $record
    }

    $before = Get-NoDropRecord
    if ($before.schema_sha256 -ne $ExpectedSchema -or
        $before.data_sha256 -ne $ExpectedData) {
        throw 'NO-DROP: baseline fingerprint mismatch before Wave01.'
    }
    Write-Host 'WAVE01 PREFLIGHT PASSED (read-only).'

    $raw = @(& php tools/no-drop-audit.php --format=json)
    if ($LASTEXITCODE -ne 0 -or $raw.Count -eq 0) {
        throw 'WAVE01 audit failed.'
    }
    $audit = ($raw -join "`n") | ConvertFrom-Json
    if ($audit.policy -ne 'AUDIT_ONLY_NO_APPROVAL' -or
        $audit.summary.approved_classes -ne 30 -or
        $audit.summary.status_counts.APPROVED -ne 30 -or
        $audit.summary.status_counts.QUARANTINED_UNSAFE -ne 1 -or
        (@($audit.classes | Where-Object {
            $_.status -eq 'UNAPPROVED_TRANSACTION_TRAIT' -or
            $_.status -eq 'APPROVED_SOURCE_MISMATCH' -or
            $_.status -eq 'QUARANTINE_SOURCE_MISMATCH'
        })).Count -ne 0) {
        throw 'WAVE01 unexpected or unsafe reviewed source state.'
    }
    $approvedRows = @($audit.classes | Where-Object { $_.status -eq 'APPROVED' })
    $approvedTestCount = 0
    foreach ($row in $approvedRows) { $approvedTestCount += [int]$row.test_methods }
    if ($approvedRows.Count -ne 30 -or $approvedTestCount -ne 157) {
        throw 'WAVE01 approved class/test count mismatch.'
    }
    Write-Host 'WAVE01 AUDIT PASSED: 30 approved classes / 157 test methods; Product UOM quarantined.'

    # This is a READ-ONLY manifest of classes the patch actually converts.
    # Each default run independently attests schema + all table contents.
    $checks = @(
        @('authorization', 'BeReorg008DecisionBatchTest'),
        @('authorization', 'BeReorg008DecisionParityTest'),
        @('authorization', 'BeReorg008DecisionCompatibilityTest'),
        @('authorization', 'BeReorg008DecisionFactoryTest'),
        @('authorization', 'ChangeAuthDomain001AuthorizationTest'),
        @('workflow', 'BeReorg009LifecycleFactoryTest'),
        @('workflow', 'BeReorg010StateResolutionFactoryTest'),
        @('workflow', 'BeReorg010StateResolutionDatabaseTest'),
        @('workflow', 'BeReorg011BusinessActionsFactoryTest'),
        @('workflow', 'BeReorg012WriteBoundaryFactoryTest'),
        @('workflow', 'BeReorg012WriteBoundaryCompatibilityTest'),
        @('settings', 'P1Db003ConfigurationAuditEventsTest')
    )
    $started = [System.Diagnostics.Stopwatch]::StartNew()
    try {
        foreach ($pair in $checks) {
            $module = $pair[0]
            $class = $pair[1]
            Write-Host "`nWAVE01 PER-CLASS NO-DROP | $class"
            & php tools/test-current.php --mode=module "--module=$module" "--class=$class" `
                --confirm-test-db "--target=$Target" "--prefix=$Prefix"
            if ($LASTEXITCODE -ne 0) {
                throw "WAVE01 failed for $class. NO automatic data cleanup."
            }
        }
        Write-Host "`nWAVE01 12-CLASS ISOLATION PASSED. Running all approved classes together."
        & "$PSScriptRoot\test-no-drop-cohort.ps1" `
            -Target $Target -Prefix $Prefix -ExpectedSchema $ExpectedSchema `
            -ExpectedData $ExpectedData -WithPHPStan:$WithPHPStan
        if (-not $?) {
            throw 'WAVE01 approved cohort wrapper failed.'
        }
    } finally {
        $started.Stop()
        Write-Host "WAVE01 elapsed: $([math]::Round($started.Elapsed.TotalSeconds,3)) seconds"
        $after = Get-NoDropRecord
        if ($after.schema_sha256 -ne $before.schema_sha256 -or
            $after.data_sha256 -ne $before.data_sha256 -or
            $after.table_count -ne $before.table_count -or
            $after.row_count -ne $before.row_count -or
            $after.effective_account -ne $before.effective_account) {
            throw 'WAVE01 SCHEMA/DATA DRIFT. Stop; no automatic cleanup.'
        }
        Write-Host 'WAVE01 POSTFLIGHT PASSED: 41 tables / 43 rows unchanged.'
    }
    Write-Host 'WAVE01 NO-DROP VALIDATION PASSED (157 distinct approved tests; NOT full regression).'
} finally {
    Pop-Location
}
