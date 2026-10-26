# M11.3C DEV-only, one-time Push secret provisioning.
# Does NOT echo generated secrets, does NOT touch flags, DB or any user devices.
# Never re-run to rotate keys on an established deployment: that would invalidate subscriptions.
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][ValidateSet('DEV_ONLY')][string]$DevConfirmation,
    [Parameter(Mandatory = $true)][string]$ContactEmail,
    [string]$OpenSslExe = ''
)
$ErrorActionPreference = 'Stop'

if ($ContactEmail -notmatch '\A[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\z') {
    throw 'Provide a real, valid operations ContactEmail. No data changed.'
}
$backend = Split-Path -Parent $PSScriptRoot
$repo = Split-Path -Parent $backend
$envFile = Join-Path $backend '.env'
if (-not (Test-Path -LiteralPath $envFile -PathType Leaf)) {
    throw 'Missing backend/.env. No data changed.'
}
$before = [System.IO.File]::ReadAllText($envFile)
if ($before -match '(?im)^\s*CI_ENVIRONMENT\s*=\s*["'']?production\b') {
    throw 'Refusing to provision a production configuration with DEV-only tooling.'
}
$names = @(
    'DINKERP_M11_PUSH_ENDPOINT_KEY_B64',
    'DINKERP_M11_PUSH_VAPID_PRIVATE_PEM_B64',
    'DINKERP_M11_PUSH_VAPID_SUBJECT'
)
foreach ($name in $names) {
    if ($before -match ('(?m)^\s*' + [regex]::Escape($name) + '\s*=')) {
        throw "Existing Push secret/configuration entry detected: $name. Refusing to rotate or partially overwrite."
    }
    if (-not [string]::IsNullOrEmpty([Environment]::GetEnvironmentVariable($name, 'Process'))) {
        throw "Push secret is already provided by the process environment: $name. Refusing rotation."
    }
}
# Secret-bearing backend/.env must not be tracked in Git. `--error-unmatch`
# is intentionally NOT used: on Windows PowerShell it throws when .env is
# correctly untracked and $ErrorActionPreference is Stop.
$tracked = @( & git -C $repo ls-files --cached -- backend/.env )
if ($LASTEXITCODE -ne 0) {
    throw 'Unable to verify Git tracking state. No data changed.'
}
if ($tracked.Count -ne 0) {
    throw 'backend/.env is Git-tracked. Untrack/protect it before provisioning.'
}
# Also refuse to write secrets into a file Git could accidentally add later.
& git -C $repo check-ignore --quiet -- backend/.env
if ($LASTEXITCODE -ne 0) {
    throw 'backend/.env is not Git-ignored. Protect it in .gitignore before provisioning.'
}
if ([string]::IsNullOrWhiteSpace($OpenSslExe)) {
    $found = Get-Command openssl.exe -ErrorAction SilentlyContinue
    if ($null -ne $found) { $OpenSslExe = $found.Source }
    if ([string]::IsNullOrWhiteSpace($OpenSslExe)) {
        # D:\XAMPP\htdocs\erp -> D:\XAMPP\apache\bin\openssl.exe
        $xampp = Split-Path -Parent (Split-Path -Parent $repo)
        $candidate = Join-Path $xampp 'apache\bin\openssl.exe'
        if (Test-Path -LiteralPath $candidate) { $OpenSslExe = $candidate }
    }
}
if ([string]::IsNullOrWhiteSpace($OpenSslExe) -or -not (Test-Path -LiteralPath $OpenSslExe -PathType Leaf)) {
    throw 'OpenSSL executable not found. Supply -OpenSslExe with a valid openssl.exe path. No data changed.'
}

# OpenSSL command-line key generation works on XAMPP systems where
# PHP openssl_pkey_new() fails because openssl.cnf is not found.
$temporaryPem = Join-Path ([System.IO.Path]::GetTempPath()) ('dinkerp-vapid-' + [guid]::NewGuid().ToString('N') + '.pem')
try {
    & $OpenSslExe ecparam -name prime256v1 -genkey -noout -out $temporaryPem 2>$null | Out-Null
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $temporaryPem)) {
        throw 'OpenSSL could not generate a new EC P-256 private key.'
    }
    $pemBytes = [System.IO.File]::ReadAllBytes($temporaryPem)
    if ($pemBytes.Length -lt 100) { throw 'Generated EC private key PEM was unexpectedly short.' }
    $seed = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($seed) } finally { $rng.Dispose() }
    $storageKeyB64 = [Convert]::ToBase64String($seed)
    $vapidB64 = [Convert]::ToBase64String($pemBytes)
    $newline = [Environment]::NewLine
    $additions = $newline + '# M11.3C DEV Push secrets; do not commit or share' + $newline +
        'DINKERP_M11_PUSH_ENDPOINT_KEY_B64=' + $storageKeyB64 + $newline +
        'DINKERP_M11_PUSH_VAPID_PRIVATE_PEM_B64=' + $vapidB64 + $newline +
        'DINKERP_M11_PUSH_VAPID_SUBJECT=mailto:' + $ContactEmail + $newline
    # Append-only. Existing credentials, cursor secret and flags are untouched.
    [System.IO.File]::AppendAllText($envFile, $additions, [System.Text.UTF8Encoding]::new($false))
    Write-Host 'M11 DEV Push secrets provisioned in backend/.env (values intentionally hidden).'
    Write-Host 'BACK UP the .env securely outside Git. Keep Push Queue/Send OFF until readiness checks pass.'
} finally {
    if (Test-Path -LiteralPath $temporaryPem) {
        Remove-Item -LiteralPath $temporaryPem -Force -ErrorAction SilentlyContinue
    }
}
