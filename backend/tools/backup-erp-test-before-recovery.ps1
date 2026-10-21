# Dinktech ERP v1.2.0 -- 03C-04D.2: backup only, no recovery or cleanup.
# Requires Hotfix 12 and an incident still matching the pinned 46-row fingerprint.
[CmdletBinding()]
param(
    [string]$BackupDirectory = 'D:\ERP_TEST_RECOVERY_BACKUPS',
    [string]$BackendDirectory = 'D:\XAMPP\htdocs\erp\backend',
    [string]$DumpExecutable = 'D:\XAMPP\mysql\bin\mysqldump.exe'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $DumpExecutable -PathType Leaf)) {
    throw '03C-04D.2 HOLD: mysqldump.exe not found.'
}
if (-not (Test-Path -LiteralPath (Join-Path $BackendDirectory 'tools\scratch-isolation-recovery-readonly.php') -PathType Leaf)) {
    throw '03C-04D.2 HOLD: Hotfix 12 forensic tool not found.'
}
if (Test-Path Env:ERP_SCRATCH_DB_PASSWORD) {
    throw '03C-04D.2 HOLD: unexpected inherited scratch password.'
}
if (Test-Path Env:ERP_SCRATCH_NUMBERING_TOKEN) {
    throw '03C-04D.2 HOLD: inherited scratch worker token.'
}
if (Test-Path Env:MYSQL_PWD) {
    throw '03C-04D.2 HOLD: inherited MYSQL_PWD rejected; use interactive prompt.'
}

$fullBackupDirectory = [System.IO.Path]::GetFullPath($BackupDirectory)
$normalized = $fullBackupDirectory.TrimEnd('\').ToLowerInvariant()
if (-not [System.IO.Path]::IsPathRooted($fullBackupDirectory) -or
    $normalized -match '(?i)\\htdocs(\\|$)' -or
    $normalized -match '(?i)\\(temp|tmp)(\\|$)' -or
    $normalized -eq [System.IO.Path]::GetPathRoot($fullBackupDirectory).TrimEnd('\').ToLowerInvariant()) {
    throw '03C-04D.2 HOLD: backup must be outside htdocs, temporary directories, and volume root.'
}

Push-Location -LiteralPath $BackendDirectory
try {
    Write-Host '03C-04D.2: verifying pinned incident state (read-only).'
    & php .\tools\scratch-isolation-recovery-readonly.php --inspect
    if ($LASTEXITCODE -ne 0) { throw '03C-04D.2 HOLD: incident preflight proof did not pass.' }

    Write-Host 'Stop all other programs, test jobs and applications writing to erp_test.'
    $approval = Read-Host 'Type QUIESCED to confirm erp_test has no concurrent writers'
    if ($approval -cne 'QUIESCED') { throw '03C-04D.2 HOLD: exclusive maintenance confirmation missing.' }

    if (-not (Test-Path -LiteralPath $fullBackupDirectory -PathType Container)) {
        New-Item -Path $fullBackupDirectory -ItemType Directory -Force | Out-Null
    }
    $stamp = (Get-Date).ToString('yyyyMMdd_HHmmss')
    $finalFile = Join-Path $fullBackupDirectory ('erp_test_pre_S04B_recovery_' + $stamp + '.sql')
    $partialFile = $finalFile + '.partial'
    if ((Test-Path -LiteralPath $finalFile) -or (Test-Path -LiteralPath $partialFile)) {
        throw '03C-04D.2 HOLD: backup filename already exists.'
    }

    Write-Host 'Backing up erp_test through SELECT-only mysqldump. Password is prompted securely by the database client.'
    & $DumpExecutable `
        '--no-defaults' `
        '--protocol=TCP' `
        '--host=127.0.0.1' `
        '--port=3306' `
        '--user=erp_test_runner' `
        '--password' `
        '--single-transaction' `
        '--quick' `
        '--skip-lock-tables' `
        '--skip-triggers' `
        '--skip-events' `
        '--skip-routines' `
        '--no-tablespaces' `
        '--default-character-set=utf8mb4' `
        ('--result-file=' + $partialFile) `
        'erp_test'
    if ($LASTEXITCODE -ne 0) {
        throw '03C-04D.2 HOLD: mysqldump failed; partial backup must not be used for recovery.'
    }
    if (-not (Test-Path -LiteralPath $partialFile -PathType Leaf) -or
        (Get-Item -LiteralPath $partialFile).Length -lt 1024) {
        throw '03C-04D.2 HOLD: backup file is absent or unexpectedly small.'
    }

    Write-Host '03C-04D.2: rechecking the pinned incident state (read-only).'
    & php .\tools\scratch-isolation-recovery-readonly.php --inspect
    if ($LASTEXITCODE -ne 0) {
        throw '03C-04D.2 HOLD: incident changed across backup; preserve partial file for investigation.'
    }

    Move-Item -LiteralPath $partialFile -Destination $finalFile -ErrorAction Stop
    $digest = (Get-FileHash -LiteralPath $finalFile -Algorithm SHA256).Hash.ToLowerInvariant()
    $bytes = (Get-Item -LiteralPath $finalFile).Length
    Write-Host '03C-04D.2 BACKUP CAPTURED; recovery and cleanup remain DENIED.'
    Write-Host ('BACKUP_FILE=' + $finalFile)
    Write-Host ('BACKUP_BYTES=' + $bytes)
    Write-Host ('BACKUP_SHA256=' + $digest)
    Write-Host 'Keep this SQL dump confidential and off any publicly served/project directory.'
} finally {
    Pop-Location
}
