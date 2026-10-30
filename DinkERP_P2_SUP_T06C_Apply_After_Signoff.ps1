# Stage T06C only after authorized PC-06 + Contact PC-07 + Contact MOD-G2 review.
# This script does NOT verify signatures; operator switches are an attestation.
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$ReviewRecord,
    [switch]$Pc06Signed,
    [switch]$ContactPc07Signed,
    [switch]$ContactModG2Signed,
    [string]$PatchName = '.\DinkERP_P2_SUP_T06C_Contact_Canonical_SDK_UI_Candidate.patch'
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
if (-not ($Pc06Signed -and $ContactPc07Signed -and $ContactModG2Signed)) {
    throw 'STOP: PC-06, Contact PC-07 and Contact MOD-G2 signoffs are required. Do not infer approval from tests.'
}
if (-not (Test-Path -LiteralPath $ReviewRecord -PathType Leaf)) {
    throw "STOP: Signed review record missing: $ReviewRecord"
}
# Fail closed for the original T06C0 review-template rows. This is a format check,
# NOT identity or cryptographic verification; the operator must verify signatures.
$recordText = Get-Content -LiteralPath $ReviewRecord -Raw
$reviewLines = @($recordText -split "`r?`n")
foreach ($reviewId in @(
    'PC-06 Security',
    'PC-06 Backend',
    'PC-07 Contact API / Tech Lead',
    'PC-07 FE consumer',
    'MOD-G2 authorization'
)) {
    $matching = @($reviewLines | Where-Object { $_ -like "| $reviewId |*" })
    if ($matching.Count -ne 1) { throw "STOP: Missing or duplicate signed row: $reviewId" }
    $fields = @($matching[0].Trim().Trim('|') -split '\|' | ForEach-Object { $_.Trim().Trim('*').Trim() })
    if ($fields.Count -ne 5 -or $fields[1] -match '(?i)UNASSIGNED|PENDING' -or
        $fields[2] -notmatch '^(APPROVED|APPROVED_WITH_CONDITIONS)$' -or
        $fields[4] -notmatch '(?i)\b[0-9a-f]{7,40}\b') {
        throw "STOP: Reviewer / decision / reviewed commit incomplete: $reviewId"
    }
    if ($fields[2] -eq 'APPROVED_WITH_CONDITIONS' -and
        ($fields[3] -eq '' -or $fields[3] -eq '-' -or $fields[3] -eq '—')) {
        throw "STOP: Conditional approval lacks conditions/evidence: $reviewId"
    }
}
$root = $PSScriptRoot
if (-not (Test-Path -LiteralPath (Join-Path $root 'backend\tools\test-unit.ps1'))) {
    throw 'STOP: Place script at DinkERP repository root before running.'
}
function RunChecked {
    param([string]$Title, [scriptblock]$Action)
    Write-Host "--- $Title ---" -ForegroundColor Cyan
    & $Action
    if ($LASTEXITCODE -ne 0) { throw "STOP: $Title failed with exit code $LASTEXITCODE" }
}
Push-Location $root
try {
    if (-not (Test-Path -LiteralPath $PatchName -PathType Leaf)) {
        throw "STOP: Patch not found: $PatchName"
    }
    Write-Warning 'Operator must independently verify authenticated reviewer signatures, recorded conditions and reviewed commit. The script cannot verify their authenticity.'
    RunChecked 'patch preflight' { git apply --check $PatchName }
    RunChecked 'patch apply' { git apply $PatchName }
    RunChecked 'patch reverse-check' { git apply --reverse --check $PatchName }
    RunChecked 'diff check' { git diff --check }
    Push-Location '.\frontend'
    try {
        RunChecked 'T06C source-contract test' { node --test .\scripts\sup-t06c-contact-contract-source.test.mjs }
        RunChecked 'T06B2 compatibility test' { node --test .\scripts\sup-t06b2-contact-contract-review.test.mjs }
        RunChecked 'OpenAPI canonical Redocly lint' { pnpm run openapi:lint }
        RunChecked 'Official SDK generation and ownership check' { pnpm run openapi:generate:check }
        RunChecked 'T06C generated SDK parity' { node --test .\scripts\sup-t06c-generated-parity.test.mjs }
        RunChecked 'T06B1 portable owner tests' { node --experimental-strip-types --test .\scripts\sup-t06b1-contact-owner.test.mjs }
        # One invocation per file avoids PowerShell array flattening into a single Vitest filter.
        RunChecked 'Contact List Vitest' { pnpm exec vitest run src/components/contacts/__tests__/contactListUnified.spec.ts }
        RunChecked 'Contact Owner Vitest' { pnpm exec vitest run src/components/contacts/__tests__/contactOwnerPolicy.spec.ts }
        RunChecked 'Frontend lint' { pnpm run lint }
        RunChecked 'Frontend type-check' { pnpm run type-check }
        RunChecked 'Frontend local imports' { pnpm run check:imports }
        RunChecked 'Frontend production build' { pnpm run build }
    } finally {
        Pop-Location
    }
    Push-Location '.\backend'
    try {
        RunChecked 'SUP-UNIT offline' { .\tools\test-unit.ps1 RUN SUP-UNIT }
        RunChecked 'CUS-UNIT offline' { .\tools\test-unit.ps1 RUN CUS-UNIT }
        RunChecked 'P1-STANDARDS offline' { .\tools\test-unit.ps1 RUN P1-STANDARDS }
        RunChecked 'ROUTES frozen registry' { .\tools\test-unit.ps1 RUN ROUTES }
    } finally {
        Pop-Location
    }
    RunChecked 'final diff check' { git diff --check }
    Write-Host 'SOURCE/CLIENT GATES PASS. DEV UAT, Security/DBA and release acceptance remain BLOCKED/PENDING.' -ForegroundColor Green
} finally {
    Pop-Location
}
