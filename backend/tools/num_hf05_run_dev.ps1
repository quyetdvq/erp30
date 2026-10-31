# HF05 DEV acceptance: two actors, loopback-only authenticated HTTP GETs.
# No business mutations; the only POST is the session /auth/login call.
# The operator must confirm that local Apache points to a DEV clone.
# Example (from backend/):
#   .\tools\num_hf05_run_dev.ps1 -ActorAllow 1 -ActorDeny 2 -LoginAllow quyetdv -LoginDeny thangdv -DevConfirmation DEV_CLONE_ONLY -HttpHost api.monmin.net -Scheme https
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][ValidatePattern('^[1-9][0-9]{0,17}$')][string]$ActorAllow,
    [Parameter(Mandatory=$true)][ValidatePattern('^[1-9][0-9]{0,17}$')][string]$ActorDeny,
    [Parameter(Mandatory=$true)][ValidateNotNullOrEmpty()][string]$LoginAllow,
    [Parameter(Mandatory=$true)][ValidateNotNullOrEmpty()][string]$LoginDeny,
    [Parameter(Mandatory=$true)][ValidateSet('DEV_CLONE_ONLY')][string]$DevConfirmation,
    [ValidateSet('localhost','api.monmin.net')][string]$HttpHost = 'api.monmin.net',
    [ValidateSet('http','https')][string]$Scheme = 'https',
    [ValidateRange(1,100)][int]$MaxResources = 30
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
if ($ActorAllow -eq $ActorDeny -or $LoginAllow -eq $LoginDeny) {
    throw 'Two distinct DEV actors are required.'
}

function Read-HF05Secret([string]$prompt) {
    $secret = Read-Host $prompt -AsSecureString
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
        $secret.Dispose()
    }
}

$keys = @(
    'NUM_HF05_DEV_CONSENT','NUM_HF05_DEV_HOST','NUM_HF05_DEV_SCHEME',
    'NUM_HF05_ALLOW_ID','NUM_HF05_ALLOW_USER','NUM_HF05_ALLOW_PASSWORD',
    'NUM_HF05_DENY_ID','NUM_HF05_DENY_USER','NUM_HF05_DENY_PASSWORD',
    'NUM_HF05_HTTP_MAX_RESOURCES'
)
$secretA = $null
$secretB = $null
$backend = Split-Path -Parent $PSScriptRoot
Push-Location $backend
try {
    if (-not (Test-Path -LiteralPath '.\tools\num_hf05_dev_http.php' -PathType Leaf)) {
        throw 'HF05 DEV audit entrypoint is missing.'
    }
    $secretA = Read-HF05Secret 'DEV allow actor password (hidden)'
    $secretB = Read-HF05Secret 'DEV deny actor password (hidden)'
    if ([string]::IsNullOrEmpty($secretA) -or [string]::IsNullOrEmpty($secretB)) {
        throw 'DEV credentials must be non-empty.'
    }
    $env:NUM_HF05_DEV_CONSENT = 'DEV_HTTP_READ_ONLY'
    $env:NUM_HF05_DEV_HOST = $HttpHost
    $env:NUM_HF05_DEV_SCHEME = $Scheme
    $env:NUM_HF05_ALLOW_ID = $ActorAllow
    $env:NUM_HF05_ALLOW_USER = $LoginAllow
    $env:NUM_HF05_ALLOW_PASSWORD = $secretA
    $env:NUM_HF05_DENY_ID = $ActorDeny
    $env:NUM_HF05_DENY_USER = $LoginDeny
    $env:NUM_HF05_DENY_PASSWORD = $secretB
    $env:NUM_HF05_HTTP_MAX_RESOURCES = [string]$MaxResources
    $secretA = $null
    $secretB = $null

    Write-Host 'HF05: local DEV loopback only; GET overview, scoped GET parity and PBAC negative test.'
    Write-Host 'Session login is the only POST. No business mutations, DDL or direct SQL.'
    & php 'tools/num_hf05_dev_http.php'
    if ($LASTEXITCODE -ne 0) {
        throw "HF05 DEV HTTP acceptance did not PASS (exit $LASTEXITCODE). Review sanitized report."
    }
    Write-Host 'HF05 DEV HTTP PASS' -ForegroundColor Green
} finally {
    foreach ($key in $keys) {
        Remove-Item -Path ('Env:' + $key) -ErrorAction SilentlyContinue
    }
    $secretA = $null
    $secretB = $null
    Pop-Location
}
