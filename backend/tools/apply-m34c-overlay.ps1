# M3.4C Overlay post-extraction cleanup. This script can remove ONLY eight pinned
# obsolete M3.4B.5 PHP source files from Modules/Access/Mutation. No DB/HTTP.
# Run after extracting the M3.4C overlay over an existing M3.4B.5 workspace.
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$backend = Split-Path -Parent $PSScriptRoot
$modules = Join-Path $backend 'app\Modules'
if (-not (Test-Path -LiteralPath (Join-Path $modules 'Access\Mutation') -PathType Container)) {
    throw 'Expected DinkERP backend/app/Modules/Access/Mutation is missing. No deletion performed.'
}
$expected = @(
    @{ Module = 'Identity'; Class = 'SecurityUserMutationController'; Hash = 'f80c3fc781bc91467cfd21accdf6f9776b709e82ec67902d19194e6a1b9b532a' },
    @{ Module = 'Identity'; Class = 'SecurityUserMutationService'; Hash = 'e38aeb83aa4bcf8b05d7c74740ca956f6bc8ba1b80a45154a5ce7f15f2122458' },
    @{ Module = 'Identity'; Class = 'SecurityUserMutationRepository'; Hash = '7765c843293e27a1d0f0189887bb25ac4e5fa5d83288cc8aad1364e8ed681962' },
    @{ Module = 'Identity'; Class = 'SecurityUserMutationRules'; Hash = '1cf9f51944bea7ea84a144ccbfa4ae400a040fd6eb259f192bdfc0e8c11a9b1a' },
    @{ Module = 'Workflow'; Class = 'WorkflowMutationController'; Hash = '7c4b77f99a0dfc50f0129b4be09087cdb2b2aa3756d7a4bd1e2838444aef5929' },
    @{ Module = 'Workflow'; Class = 'WorkflowMutationService'; Hash = '66335071c6d512ddb99b8af944d12a8a22cc71785e9719fe264d2246c6d4b766' },
    @{ Module = 'Workflow'; Class = 'WorkflowMutationRepository'; Hash = '34184347adbcda6b2870b8dbfa8a8a430a5ccce5bf103d212260bd1734880f86' },
    @{ Module = 'Workflow'; Class = 'WorkflowMutationRules'; Hash = '3c1171ed8745bbaa985eb9f35c148fe840769a8132327f44cb6d9c182ee8c850' }
)

# Validate ALL source hashes and destination classes before ANY file deletion.
$remove = @()
foreach ($item in $expected) {
    $old = Join-Path $modules ('Access\Mutation\' + $item.Class + '.php')
    $new = Join-Path $modules ($item.Module + '\Mutation\' + $item.Class + '.php')
    if (-not (Test-Path -LiteralPath $new -PathType Leaf)) {
        throw ('M3.4C new module class missing: ' + $item.Class + '. No deletion performed.')
    }
    if (Test-Path -LiteralPath $old -PathType Leaf) {
        $actual = (Get-FileHash -LiteralPath $old -Algorithm SHA256).Hash.ToLowerInvariant()
        if ($actual -ne $item.Hash) {
            throw ('M3.4C old class differs from pinned M3.4B.5: ' + $item.Class + '. No deletion performed.')
        }
        $remove += $old
    }
}
foreach ($class in @('IdentityReadController.php', 'IdentityReadService.php', 'IdentityReadRepository.php')) {
    if (-not (Test-Path -LiteralPath (Join-Path $modules ('Identity\Management\' + $class)) -PathType Leaf)) {
        throw ('M3.4C Identity GET class missing: ' + $class + '. No deletion performed.')
    }
}
foreach ($class in @('WorkflowReadController.php', 'WorkflowReadService.php', 'WorkflowReadRepository.php', 'WorkflowReadContract.php')) {
    if (-not (Test-Path -LiteralPath (Join-Path $modules ('Workflow\Management\' + $class)) -PathType Leaf)) {
        throw ('M3.4C Workflow GET class missing: ' + $class + '. No deletion performed.')
    }
}
foreach ($file in $remove) {
    Remove-Item -LiteralPath $file -Force -ErrorAction Stop
}
Write-Host ('M3.4C CLEANUP PASS: removed ' + $remove.Count + '/8 pinned obsolete Access classes. No database or baseline/frontend changes.')
