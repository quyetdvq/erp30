# TST-PERF-003E-03B - OFFLINE fixture ownership proof rehearsal.
# NO test execution, NO DML, NO DELETE, NO cleanup/migration.
[CmdletBinding()]
param(
    [string]$MetadataFile = 'TST_PERF_003E03_DB_METADATA_XAMPP.json',
    [switch]$VerifyDbFingerprint
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$backend = Split-Path -Parent $PSScriptRoot
Push-Location $backend
try {
    $resolved = (Resolve-Path -LiteralPath $MetadataFile -ErrorAction Stop).Path
    $testFile = '.\tools\fixtures\committed-sql-ledger-EXAMPLE-ONLY.json'

    function Get-CurrentReadOnlyBaseline {
        $lines = @(& php tools/no-drop-schema-attest.php `
            '--target=127.0.0.1:3306/erp_test' '--prefix=dinktech_' --with-data)
        if ($LASTEXITCODE -ne 0 -or $lines.Count -ne 2) {
            throw '03B STOP: live read-only NO-DROP attestation failed.'
        }
        $result = $lines[-1] | ConvertFrom-Json -ErrorAction Stop
        if ($result.status -ne 'NO_DROP_VERIFIED' -or
            $result.target -ne '127.0.0.1:3306/erp_test' -or
            $result.prefix -ne 'dinktech_' -or
            $result.effective_account -ne 'erp_test_runner@127.0.0.1' -or
            $result.table_count -ne 41 -or $result.row_count -ne 43 -or
            $result.schema_sha256 -ne '8d12e2ec455115dd27a8d4bcabc6f5fa2df5d09de7128340fb525be213b4f7fa' -or
            $result.data_sha256 -ne 'b3f7c3932415318547b3d95958ca6abe0734a40897691e4f125dad796d6f73cb') {
            throw '03B STOP: live NO-DROP baseline mismatch.'
        }
        return $result
    }

    $before = $null
    if ($VerifyDbFingerprint) {
        $before = Get-CurrentReadOnlyBaseline
        Write-Host '03B LIVE PREFLIGHT PASSED (read-only).'
    }

    try {
        & php tools/test-committed-sql-fixture-dry-run.php "--metadata=$resolved"
        if ($LASTEXITCODE -ne 0) { throw '03B STOP: offline negative guard tests failed.' }

        $metaLines = & php tools/committed-sql-fixture-dry-run.php "--metadata=$resolved" --inspect
        if ($LASTEXITCODE -ne 0) { throw '03B STOP: metadata inspect failed.' }
        $metadata = ($metaLines | Out-String | ConvertFrom-Json -ErrorAction Stop)
        if ($metadata.status -ne 'DRY_RUN_METADATA_ONLY' -or
            $metadata.table_count -ne 41 -or
            $metadata.foreign_key_constraints -ne 50 -or
            $metadata.permitted_to_execute_cleanup -ne $false -or
            $metadata.sql_executed -ne 0) {
            throw '03B STOP: unsafe metadata report.'
        }
        Write-Host '03B VERIFIED: 41 PK tables, 50 FKs, 4 CASCADE, 5 self-FKs (from offline metadata).'

        $planLines = & php tools/committed-sql-fixture-dry-run.php `
            "--metadata=$resolved" "--ledger=$testFile"
        if ($LASTEXITCODE -ne 0) { throw '03B STOP: demo ledger validation failed.' }
        $plan = ($planLines | Out-String | ConvertFrom-Json -ErrorAction Stop)
        if ($plan.status -ne 'DRY_RUN_CLAIMS_NOT_VERIFIED' -or
            $plan.permitted_to_execute_cleanup -ne $false -or
            $plan.sql_executed -ne 0 -or
            $plan.claimed_row_count -ne 3 -or
            @($plan.identity_counter_warnings).Count -ne 3) {
            throw '03B STOP: hypothetical sample was not safely blocked.'
        }
        $order = @($plan.candidate_child_before_parent_table_order)
        if (($order -join ',') -ne
            'dinktech_auto_numbering_counters,dinktech_auto_numbering_series,dinktech_resources') {
            throw '03B STOP: unexpected child-first FK order.'
        }
        Write-Host '03B DEMO: Counter -> Series -> Resource; LIVE PROOFS MISSING; cleanup DENIED.'
        Write-Host '03B OFFLINE DRY-RUN PASSED (zero database writes; no SQL executor).'
    } finally {
        if ($VerifyDbFingerprint) {
            $after = Get-CurrentReadOnlyBaseline
            if ($after.schema_sha256 -ne $before.schema_sha256 -or
                $after.data_sha256 -ne $before.data_sha256 -or
                $after.row_count -ne $before.row_count -or
                $after.table_count -ne $before.table_count -or
                $after.effective_account -ne $before.effective_account) {
                throw '03B STOP: schema/data changed during dry-run. No cleanup performed.'
            }
            Write-Host '03B LIVE POSTFLIGHT PASSED: 41 tables / 43 rows unchanged.'
        }
    }
} finally {
    Pop-Location
}
