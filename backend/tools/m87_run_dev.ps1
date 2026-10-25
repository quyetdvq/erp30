# M8.7A controlled DEV audit: one invocation performs SQL then local HTTP.
# No business mutation. Secrets never appear in command-line arguments or reports.
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][ValidatePattern('^[1-9][0-9]*$')][string]$ActorA,
    [Parameter(Mandatory=$true)][ValidatePattern('^[1-9][0-9]*$')][string]$ActorB,
    [Parameter(Mandatory=$true)][ValidatePattern('^[A-Za-z][A-Za-z0-9_]*$')][string]$Database,
    [Parameter(Mandatory=$true)][ValidateNotNullOrEmpty()][string]$LoginA,
    [Parameter(Mandatory=$true)][ValidateNotNullOrEmpty()][string]$LoginB,
    [Parameter(Mandatory=$true)][ValidateSet('DEV_CLONE_ONLY')][string]$DevConfirmation,
    [ValidateSet('localhost','api.monmin.net')][string]$HttpHost = 'api.monmin.net',
    [ValidateSet('http','https')][string]$Scheme = 'https',
    [ValidateRange(1,5000)][int]$MaxRows = 1000,
    [string]$CasesFile = ''
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
if ($ActorA -eq $ActorB) { throw 'Two distinct active DEV Shield users are required.' }
if ($LoginA -eq $LoginB) { throw 'Distinct DEV account logins are required.' }
if ($Database -match '(^|_)(prod|production|live)($|_)') { throw 'Production-like database name rejected.' }
if ($CasesFile -ne '') {
    $casePath = (Resolve-Path -LiteralPath $CasesFile -ErrorAction Stop).Path
    if (-not (Test-Path -LiteralPath $casePath -PathType Leaf)) { throw 'Case manifest is not a file.' }
    $CasesFile = $casePath
}

function Read-M87Secret([string]$label) {
    $secure = Read-Host $label -AsSecureString
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
        $secure.Dispose()
    }
}

$keys = @('M87_DEV_CONSENT','M87_DEV_HOST','M87_DEV_SCHEME',
          'M87_ACTOR_A_USER','M87_ACTOR_A_PASSWORD','M87_ACTOR_B_USER','M87_ACTOR_B_PASSWORD')
$secretA = $null
$secretB = $null
$backend = Split-Path -Parent $PSScriptRoot
Push-Location $backend
try {
    # Capture credentials BEFORE creating the 15-minute DB report.
    $secretA = Read-M87Secret 'DEV Actor A password (hidden)'
    $secretB = Read-M87Secret 'DEV Actor B password (hidden)'
    if ([string]::IsNullOrEmpty($secretA) -or [string]::IsNullOrEmpty($secretB)) {
        throw 'DEV authentication requires two non-empty passwords.'
    }
    $env:M87_DEV_CONSENT = 'DEV_HTTP_READ_ONLY'
    $env:M87_DEV_HOST = $HttpHost
    $env:M87_DEV_SCHEME = $Scheme
    $env:M87_ACTOR_A_USER = $LoginA
    $env:M87_ACTOR_A_PASSWORD = $secretA
    $env:M87_ACTOR_B_USER = $LoginB
    $env:M87_ACTOR_B_PASSWORD = $secretB
    $secretA = $null
    $secretB = $null

    $folder = Join-Path $backend 'writable/m87/reports'
    $before = @{}
    if (Test-Path -LiteralPath $folder -PathType Container) {
        Get-ChildItem -LiteralPath $folder -File -Filter 'm87_*.json' | ForEach-Object { $before[$_.FullName] = $true }
    }
    Write-Host 'M8.7A: executing READ-ONLY DB audit on the explicitly confirmed DEV clone.'
    $arguments = @('spark','m87:db-audit',$ActorA,$ActorB,$Database,'DEV_READ_ONLY',[string]$MaxRows)
    if ($CasesFile -ne '') { $arguments += $CasesFile }
    & php @arguments
    if ($LASTEXITCODE -ne 0) { throw "M8.7 DB audit rejected the scope (exit $LASTEXITCODE)." }

    $fresh = @(Get-ChildItem -LiteralPath $folder -File |
        Where-Object { $_.Name -match '^m87_[0-9]{8}_[0-9]{6}_[a-f0-9]{8}\.json$' -and -not $before.ContainsKey($_.FullName) } |
        Sort-Object LastWriteTimeUtc -Descending)
    if ($fresh.Count -ne 1) { throw 'Cannot uniquely identify the DB report created by this run.' }
    $report = $fresh[0].FullName
    & php 'tools/m87_report_summary.php' $report
    if ($LASTEXITCODE -ne 0) { throw 'DB report cannot be summarized safely.' }
    & php 'tools/m87_preflight.php' $report
    if ($LASTEXITCODE -ne 0) { throw 'HTTP preflight rejected local environment/report. Review error codes.' }

    Write-Host 'M8.7A: executing localhost-only authenticated HTTP GET comparison.'
    $httpArguments = @('tools/m87_http_readonly.php',$report)
    if ($CasesFile -ne '') { $httpArguments += $CasesFile }
    & php @httpArguments
    if ($LASTEXITCODE -ne 0) { throw "M8.7 HTTP audit blocked/failed (exit $LASTEXITCODE); inspect its sanitized report." }
    Write-Host 'M8.7A: SQL and HTTP phases completed; read coverage gaps and remaining acceptance gates.'
    Write-Host 'Production remains BLOCKED until scenario, baseline, concurrency and performance acceptance.'
} finally {
    foreach ($key in $keys) { Remove-Item -Path ('Env:'+$key) -ErrorAction SilentlyContinue }
    $secretA = $null
    $secretB = $null
    Pop-Location
}
