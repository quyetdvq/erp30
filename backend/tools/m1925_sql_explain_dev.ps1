# M19.25: DEV SQL/PBAC + EXPLAIN only, no HTTP/UAT and no business mutations.
# Requires CI_ENVIRONMENT=development/testing and an explicitly attested DEV DB clone.
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][ValidatePattern('^[1-9][0-9]*$')][string]$ActorA,
    [Parameter(Mandatory=$true)][ValidatePattern('^[1-9][0-9]*$')][string]$ActorB,
    [Parameter(Mandatory=$true)][ValidatePattern('^[A-Za-z][A-Za-z0-9_]*$')][string]$Database,
    [Parameter(Mandatory=$true)][ValidateSet('DEV_CLONE_ONLY')][string]$DevConfirmation,
    [ValidateRange(1,5000)][int]$MaxRows = 2000,
    [string]$CasesFile = ''
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
if ($ActorA -eq $ActorB) { throw 'Two distinct DEV Shield actors are required.' }
if ($Database -match '(^|_)(prod|production|live)($|_)') { throw 'Production-like DB name is forbidden.' }
if ($CasesFile -ne '') {
    $CasesFile = (Resolve-Path -LiteralPath $CasesFile -ErrorAction Stop).Path
    if (-not (Test-Path -LiteralPath $CasesFile -PathType Leaf)) { throw 'Case manifest is not a file.' }
}
$backend = Split-Path -Parent $PSScriptRoot
Push-Location $backend
try {
    $dir = 'writable/m87/reports'
    $before = @{}
    if (Test-Path -LiteralPath $dir -PathType Container) {
        Get-ChildItem -LiteralPath $dir -File -Filter 'm87_*.json' | ForEach-Object { $before[$_.FullName] = $true }
    }
    Write-Host 'M19.25: DEV SQL-only PBAC/EXPLAIN. No HTTP requests or DB writes.'
    $arguments = @('spark','m87:db-audit',$ActorA,$ActorB,$Database,'DEV_READ_ONLY',[string]$MaxRows)
    if ($CasesFile -ne '') { $arguments += $CasesFile }
    & php @arguments
    if ($LASTEXITCODE -ne 0) { throw 'M8.7 DEV SQL/PBAC audit FAILED or BLOCKED. STOP.' }
    $fresh = @(Get-ChildItem -LiteralPath $dir -File |
        Where-Object { $_.Name -match '^m87_[0-9]{8}_[0-9]{6}_[a-f0-9]{8}\.json$' -and -not $before.ContainsKey($_.FullName) })
    if ($fresh.Count -ne 1) { throw 'Cannot safely identify the current run report. STOP.' }
    & php 'tools/m1925_sql_explain_review.php' $fresh[0].FullName
    if ($LASTEXITCODE -ne 0) { throw 'DEV SQL EXPLAIN summary failed. STOP.' }
    Write-Host 'SQL-only report generated. HTTP/frontend UAT is independently owned by the operator.'
} finally {
    Pop-Location
}
