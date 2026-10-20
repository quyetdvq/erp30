# TST-PERF-003E-03C-01: scratch readiness gate.
# READ-ONLY for BOTH erp_test and separately pre-provisioned scratch DB.
# NO SQL writes, fixture creation, migration, cleanup, or PHPUnit execution.
[CmdletBinding()]
param(
    [string]$MetadataFile = 'TST_PERF_003E03_DB_METADATA_XAMPP.json',
    [switch]$CheckScratch
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$backend = Split-Path -Parent $PSScriptRoot
Push-Location $backend
try {
    $target = '127.0.0.1:3306/erp_test'
    $prefix = 'dinktech_'
    $scratchTarget = '127.0.0.1:3306/erp_test_scratch'
    $schema = '8d12e2ec455115dd27a8d4bcabc6f5fa2df5d09de7128340fb525be213b4f7fa'
    $data = 'b3f7c3932415318547b3d95958ca6abe0734a40897691e4f125dad796d6f73cb'
    $metadata = (Resolve-Path -LiteralPath $MetadataFile -ErrorAction Stop).Path

    function Get-NoDropAttestation {
        $lines = @(& php tools/no-drop-schema-attest.php '--target=127.0.0.1:3306/erp_test' '--prefix=dinktech_' --with-data)
        if ($LASTEXITCODE -ne 0 -or $lines.Count -ne 2) {
            throw '03C STOP: erp_test NO-DROP attestation missing or failed.'
        }
        $a = $lines[-1] | ConvertFrom-Json -ErrorAction Stop
        if ($a.status -ne 'NO_DROP_VERIFIED' -or
            $a.target -ne $target -or $a.prefix -ne $prefix -or
            $a.effective_account -ne 'erp_test_runner@127.0.0.1' -or
            $a.table_count -ne 41 -or $a.row_count -ne 43 -or
            $a.schema_sha256 -ne $schema -or $a.data_sha256 -ne $data) {
            throw '03C STOP: erp_test baseline/identity drift. Do not provision or cleanup.'
        }
        return $a
    }

    # Source-only checks before any database connection.
    & php tools/test-scratch-readiness.php "--metadata=$metadata"
    if ($LASTEXITCODE -ne 0) { throw '03C STOP: offline fail-closed tests failed.' }

    $before = Get-NoDropAttestation
    Write-Host '03C ERP_TEST PREFLIGHT PASSED: 41 tables / 43 rows, read-only.'

    $verified = $false
    $report = $null
    try {
        $offlineText = @(& php tools/scratch-db-readiness.php --offline "--metadata=$metadata")
        if ($LASTEXITCODE -ne 0 -or $offlineText.Count -ne 1) {
            throw '03C STOP: invalid offline reference report.'
        }
        $offline = $offlineText[-1] | ConvertFrom-Json -ErrorAction Stop
        if ($offline.status -ne 'SCRATCH_NOT_ATTESTED_OFFLINE' -or
            $offline.expected_scratch_target -ne $scratchTarget -or
            $offline.reference_table_count -ne 41 -or
            $offline.reference_fk_count -ne 50 -or
            $offline.pilot_authorized -ne $false -or
            $offline.cleanup_authorized -ne $false -or
            $offline.writes_executed -ne 0) {
            throw '03C STOP: offline scratch gate unexpectedly changed authorization.'
        }
        Write-Host '03C OFFLINE METADATA GATE PASS: 41 tables, 50 FKs; no scratch connection.'

        if ($CheckScratch) {
            # PHP receives dedicated scratch credentials ONLY from the current
            # process environment. Never put a password on a command line.
            $liveText = @(& php tools/scratch-db-readiness.php --live "--metadata=$metadata" "--scratch=$scratchTarget")
            if ($LASTEXITCODE -ne 0 -or $liveText.Count -ne 1) {
                throw '03C NOT READY: scratch connection, grants, schema or empty-data proof failed.'
            }
            $report = $liveText[-1] | ConvertFrom-Json -ErrorAction Stop
            if ($report.status -ne 'SCRATCH_READONLY_ATTESTED' -or
                $report.scratch_target -ne $scratchTarget -or
                $report.effective_account -ne 'erp_scratch_runner@127.0.0.1' -or
                $report.baseline_target -ne $target -or
                $report.schema_sha256 -ne $schema -or
                $report.table_count -ne 41 -or $report.pk_tables -ne 41 -or
                $report.foreign_keys -ne 50 -or $report.business_rows -ne 0 -or
                $report.writes_executed -ne 0 -or
                $report.committed_sql_tests_executed -ne 0 -or
                $report.pilot_authorized -ne $false -or
                $report.cleanup_authorized -ne $false) {
                throw '03C STOP: scratch live attestation has unexpected content.'
            }
            $verified = $true
            Write-Host '03C SCRATCH READ-ONLY ATTESTATION PASS: dedicated DML-only account, schema/FK parity, no business rows.'
        }
    } finally {
        $after = Get-NoDropAttestation
        if ($after.schema_sha256 -ne $before.schema_sha256 -or
            $after.data_sha256 -ne $before.data_sha256 -or
            $after.table_count -ne $before.table_count -or
            $after.row_count -ne $before.row_count -or
            $after.effective_account -ne $before.effective_account) {
            throw '03C STOP: erp_test changed during scratch readiness. No cleanup performed.'
        }
        Write-Host '03C ERP_TEST POSTFLIGHT PASS: schema/data unchanged.'
    }

    if ($verified) {
        # Write ONLY metadata/counter evidence for the next review stage.
        # Report contains no credentials, database row contents or secrets.
        $reportPath = Join-Path $backend 'TST_PERF_003E03C01_SCRATCH_READINESS_XAMPP.json'
        $report | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $reportPath -Encoding UTF8
        Write-Host "03C READINESS REPORT: $reportPath"
        Write-Host '03C-01 SCRATCH READINESS PASSED (READ-ONLY). PILOT AND CLEANUP STILL DENIED.'
    } else {
        Write-Host '03C-01 OFFLINE CHECK PASSED; SCRATCH NOT ATTESTED. Provision independently, then rerun -CheckScratch.'
    }
} finally {
    Pop-Location
}
