# BE-PERF-015A: authenticated read-only HTTP parity, opt-in flag OFF versus ON.
# Run BASELINE with flag OFF; CANDIDATE with flag ON (restart test web server).
# COMPARE is offline. No response body or authentication secret is persisted.
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][ValidateSet('BASELINE','CANDIDATE','COMPARE')][string]$Mode,
    [string]$BaseUrl = '',
    [ValidateSet('erp_test')][string]$ConfirmTestDatabase = 'erp_test',
    [ValidatePattern('^[A-Za-z0-9_-]{1,40}$')][string]$Cohort = 'same-test-actor',
    [string]$ReportDirectory = '.'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$dir = [System.IO.Path]::GetFullPath($ReportDirectory)
if (-not [System.IO.Directory]::Exists($dir)) { throw 'ReportDirectory must exist.' }
$baselinePath = [System.IO.Path]::Combine($dir, 'BE-PERF-015A_baseline.json')
$candidatePath = [System.IO.Path]::Combine($dir, 'BE-PERF-015A_candidate.json')

if ($Mode -eq 'COMPARE') {
    if (-not (Test-Path -LiteralPath $baselinePath) -or -not (Test-Path -LiteralPath $candidatePath)) {
        throw 'Missing baseline/candidate reports. STOP.'
    }
    $baseline = Get-Content -LiteralPath $baselinePath -Raw | ConvertFrom-Json
    $candidate = Get-Content -LiteralPath $candidatePath -Raw | ConvertFrom-Json
    if ($baseline.change_id -ne 'BE-PERF-015A' -or $candidate.change_id -ne 'BE-PERF-015A' -or
        $baseline.cohort -ne $candidate.cohort -or $baseline.base_url -ne $candidate.base_url) {
        throw 'Different test cohorts, base URLs, or report format. STOP.'
    }
    if ($baseline.mode -ne 'BASELINE' -or $candidate.mode -ne 'CANDIDATE') { throw 'Invalid report modes.' }
    $left = @($baseline.cases)
    $right = @($candidate.cases)
    if ($left.Count -ne $right.Count) { throw 'HTTP case count differs.' }
    $failures = @()
    for ($i=0; $i -lt $left.Count; $i++) {
        if ($left[$i].scenario -ne $right[$i].scenario -or
            [int]$left[$i].status -ne [int]$right[$i].status -or
            $left[$i].error_code -ne $right[$i].error_code -or
            $left[$i].sha256 -ne $right[$i].sha256) {
            $failures += [string]$left[$i].scenario
        }
    }
    if ($failures.Count -gt 0) {
        throw ('HTTP PARITY FAIL (status/error/data/meta differ): ' + ($failures -join ', '))
    }
    Write-Output ('BE-PERF-015A HTTP PARITY PASS: ' + $left.Count + ' matched GET response hashes, including authorized total. No content logged.')
    return
}

if ($ConfirmTestDatabase -ne 'erp_test') { throw 'Test DB must be erp_test.' }
$uri = $null
if (-not [Uri]::TryCreate($BaseUrl, [UriKind]::Absolute, [ref]$uri) -or
    $uri.Scheme -ne 'http' -or @('localhost', '127.0.0.1') -notcontains $uri.DnsSafeHost.ToLowerInvariant() -or
    $uri.UserInfo -ne '' -or $uri.Query -ne '' -or $uri.Fragment -ne '') {
    throw 'STOP: only localhost/127.0.0.1 plain HTTP URL with no query or user info.'
}
$cookie = [Environment]::GetEnvironmentVariable('ERP_014H_AUTH_COOKIE')
$token = [Environment]::GetEnvironmentVariable('ERP_014H_BEARER_TOKEN')
if (([string]::IsNullOrWhiteSpace($cookie) -and [string]::IsNullOrWhiteSpace($token)) -or
    (-not [string]::IsNullOrWhiteSpace($cookie) -and -not [string]::IsNullOrWhiteSpace($token))) {
    throw 'Configure exactly one ERP_014H_AUTH_COOKIE or ERP_014H_BEARER_TOKEN; do not paste it here.'
}

Add-Type -AssemblyName System.Net.Http
$handler = New-Object System.Net.Http.HttpClientHandler
$handler.AllowAutoRedirect = $false
$handler.UseCookies = $false
$client = [System.Net.Http.HttpClient]::new($handler)
$client.Timeout = [TimeSpan]::FromSeconds(30)
$client.DefaultRequestHeaders.Accept.ParseAdd('application/json')
$client.DefaultRequestHeaders.TryAddWithoutValidation('Cache-Control', 'no-cache') | Out-Null
if (-not [string]::IsNullOrWhiteSpace($cookie)) {
    $client.DefaultRequestHeaders.TryAddWithoutValidation('Cookie', $cookie) | Out-Null
} else {
    $client.DefaultRequestHeaders.TryAddWithoutValidation('Authorization', ('Bearer ' + $token)) | Out-Null
}

$cases = @(
    @{ N='group-page1'; P='/api/v1/product-groups?page=1&page_size=20' },
    @{ N='group-page2'; P='/api/v1/product-groups?page=2&page_size=20' },
    @{ N='group-reverse'; P='/api/v1/product-groups?sort=-id&page_size=5' },
    @{ N='group-state-active'; P='/api/v1/product-groups?filter%5Bstatus%5D=ACTIVE' },
    @{ N='group-state-lowercase'; P='/api/v1/product-groups?filter%5Bstatus%5D=active' },
    @{ N='group-parent-root'; P='/api/v1/product-groups?filter%5Bparent_id%5D=' },
    @{ N='group-parent-id'; P='/api/v1/product-groups?filter%5Bparent_id%5D=1' },
    @{ N='group-parent-noncanonical'; P='/api/v1/product-groups?filter%5Bparent_id%5D=01' },
    @{ N='group-search-fallback'; P='/api/v1/product-groups?q=box' },
    @{ N='group-sort-fallback'; P='/api/v1/product-groups?sort=name' },
    @{ N='group-invalid-sort'; P='/api/v1/product-groups?sort=description' },
    @{ N='uom-page1'; P='/api/v1/product-uoms?page=1&page_size=20' },
    @{ N='uom-page2'; P='/api/v1/product-uoms?page=2&page_size=20' },
    @{ N='uom-reverse'; P='/api/v1/product-uoms?sort=-id&page_size=5' },
    @{ N='uom-state-active'; P='/api/v1/product-uoms?filter%5Bstatus%5D=ACTIVE' },
    @{ N='uom-state-lowercase'; P='/api/v1/product-uoms?filter%5Bstatus%5D=active' },
    @{ N='uom-search-fallback'; P='/api/v1/product-uoms?q=box' },
    @{ N='uom-sort-fallback'; P='/api/v1/product-uoms?sort=name' },
    @{ N='uom-invalid-sort'; P='/api/v1/product-uoms?sort=description' }
)

try {
    $results = @()
    foreach ($case in $cases) {
        $response = $null
        try {
            $response = $client.GetAsync($BaseUrl.TrimEnd('/') + $case.P).GetAwaiter().GetResult()
            $status = [int]$response.StatusCode
            if ($status -ge 500 -or ($status -ge 300 -and $status -lt 400)) {
                throw ('STOP: server error or redirect for ' + $case.N + ' (' + $status + ')')
            }
            $body = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
            $json = $body | ConvertFrom-Json
            if ($null -eq $json -or $null -eq $json.meta) { throw ('Unexpected response envelope for ' + $case.N) }
            if ($json.meta.PSObject.Properties.Name -contains 'request_id') {
                $json.meta.PSObject.Properties.Remove('request_id')
            }
            if ($status -eq 200) {
                if ($json.PSObject.Properties.Name -notcontains 'data' -or
                    $json.meta.PSObject.Properties.Name -notcontains 'total') { throw 'Malformed collection envelope.' }
            }
            $code = ''
            if ($json.PSObject.Properties.Name -contains 'error' -and $null -ne $json.error) {
                $code = [string]$json.error.code
            }
            $normalized = ConvertTo-Json -InputObject $json -Depth 100 -Compress
            $hash = [System.Security.Cryptography.SHA256]::Create()
            try {
                $digest = [BitConverter]::ToString($hash.ComputeHash([Text.Encoding]::UTF8.GetBytes($normalized))).Replace('-','').ToLowerInvariant()
            } finally { $hash.Dispose() }
            $results += [pscustomobject]@{ scenario=$case.N; status=$status; error_code=$code; sha256=$digest }
        } finally {
            if ($null -ne $response) { $response.Dispose() }
        }
    }
    $record = [ordered]@{
        change_id = 'BE-PERF-015A'; mode=$Mode; cohort=$Cohort
        base_url=$BaseUrl.TrimEnd('/'); confirmed_db='erp_test (operator assertion only)'
        limits='Same actor, data, policy, locale, and test web instance required. Ignored request_id only; no DB identity verification.'
        cases=$results
    }
    $path = if ($Mode -eq 'BASELINE') { $baselinePath } else { $candidatePath }
    [System.IO.File]::WriteAllText($path, (($record | ConvertTo-Json -Depth 10) + [Environment]::NewLine))
    Write-Output ('BE-PERF-015A ' + $Mode + ': ' + $results.Count + ' GET hashes recorded; no raw data or credentials saved.')
} finally {
    $client.Dispose()
    $handler.Dispose()
}
