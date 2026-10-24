# BE-REORG-014H: READ-ONLY HTTP timing on a confirmed local test ERP instance.
# This measures full HTTP GET wall time, not DB query count or SQL EXPLAIN.
# Never paste session secrets into source, terminal output, reports or chat.
# Required: ERP_014H_AUTH_COOKIE or ERP_014H_BEARER_TOKEN environment variable.
# Explicit test DB confirmation is an OPERATOR ATTESTATION, not a connection check.
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string]$BaseUrl,
    [Parameter(Mandatory = $true)][ValidateSet('erp_test')][string]$ConfirmTestDatabase,
    [ValidateRange(10, 500)][int]$Samples = 40,
    [ValidateRange(1, 50)][int]$Warmup = 5,
    [ValidatePattern('^[A-Za-z0-9_-]{1,40}$')][string]$Cohort = 'authorized-test-actor',
    [string]$OutputPath = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$uri = $null
if (-not [Uri]::TryCreate($BaseUrl, [UriKind]::Absolute, [ref]$uri)) {
    throw 'BaseUrl must be an absolute HTTP URL.'
}
if ($uri.Scheme -ne 'http' -or @('127.0.0.1', 'localhost') -notcontains $uri.DnsSafeHost.ToLowerInvariant() -or
    $uri.UserInfo -ne '' -or $uri.Query -ne '' -or $uri.Fragment -ne '') {
    throw 'STOP: only http://127.0.0.1 or http://localhost with no credentials, query or fragment is permitted.'
}
$cookie = [Environment]::GetEnvironmentVariable('ERP_014H_AUTH_COOKIE')
$token = [Environment]::GetEnvironmentVariable('ERP_014H_BEARER_TOKEN')
if (([string]::IsNullOrWhiteSpace($cookie) -and [string]::IsNullOrWhiteSpace($token)) -or
    (-not [string]::IsNullOrWhiteSpace($cookie) -and -not [string]::IsNullOrWhiteSpace($token))) {
    throw 'STOP: configure exactly one authentication environment variable: ERP_014H_AUTH_COOKIE or ERP_014H_BEARER_TOKEN.'
}
if ($ConfirmTestDatabase -ne 'erp_test') { throw 'STOP: test database must be erp_test.' }

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

function Invoke-LocalRead([string]$url) {
    $watch = [System.Diagnostics.Stopwatch]::StartNew()
    $response = $null
    try {
        $response = $client.GetAsync($url).GetAwaiter().GetResult()
        $body = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
        $watch.Stop()
        $status = [int]$response.StatusCode
        if ($status -ne 200) {
            throw ('STOP: GET returned HTTP ' + $status + '. Check approved test actor permissions; no response content has been logged.')
        }
        $parsed = ConvertFrom-Json -InputObject $body
        if ($null -eq $parsed -or $null -eq $parsed.meta -or $null -eq $parsed.data -or
            $null -eq $parsed.meta.total -or $null -eq $parsed.meta.page -or $null -eq $parsed.meta.page_size) {
            throw 'STOP: collection response is missing data/meta.total/page/page_size.'
        }
        if ([int]$parsed.meta.page -ne 1 -or [int]$parsed.meta.page_size -ne 20) {
            throw 'STOP: collection pagination does not match request.'
        }
        return [pscustomobject]@{
            elapsed_ms = $watch.Elapsed.TotalMilliseconds
            authorized_total = [string]$parsed.meta.total
            response_bytes = [System.Text.Encoding]::UTF8.GetByteCount($body)
        }
    }
    finally {
        if ($null -ne $response) { $response.Dispose() }
    }
}

function Get-NearestRank([double[]]$data, [double]$percent) {
    [Array]::Sort($data)
    $index = [Math]::Max(0, [int][Math]::Ceiling($data.Length * $percent) - 1)
    return [Math]::Round($data[$index], 3)
}

# Keep the cases identical across successive runs. Use separate sessions if
# comparing PBAC cohorts; never compare an unprotected actor to a scoped actor
# without labeling those different security contexts.
$cases = @(
    @{ Name = 'groups-page'; Path = '/api/v1/product-groups?page=1&page_size=20' },
    @{ Name = 'groups-status'; Path = '/api/v1/product-groups?page=1&page_size=20&filter%5Bstatus%5D=ACTIVE' },
    @{ Name = 'uoms-page'; Path = '/api/v1/product-uoms?page=1&page_size=20' },
    @{ Name = 'uoms-status'; Path = '/api/v1/product-uoms?page=1&page_size=20&filter%5Bstatus%5D=ACTIVE' }
)

try {
    $summaries = @()
    foreach ($case in $cases) {
        $url = $BaseUrl.TrimEnd('/') + $case.Path
        for ($i = 0; $i -lt $Warmup; $i++) { $null = Invoke-LocalRead $url }
        $times = New-Object 'double[]' $Samples
        $totals = @{}
        $bytes = 0
        for ($i = 0; $i -lt $Samples; $i++) {
            $measurement = Invoke-LocalRead $url
            $times[$i] = $measurement.elapsed_ms
            $totals[$measurement.authorized_total] = $true
            $bytes += $measurement.response_bytes
        }
        $p50 = Get-NearestRank ([double[]]$times.Clone()) 0.50
        $p95 = Get-NearestRank ([double[]]$times.Clone()) 0.95
        $summaries += [pscustomobject]@{
            scenario = $case.Name
            sample_count = $Samples
            p50_http_ms = $p50
            p95_http_ms = $p95
            avg_response_bytes = [int][Math]::Round($bytes / $Samples)
            distinct_authorized_totals = @($totals.Keys | Sort-Object)
            stable_total = ($totals.Count -eq 1)
        }
    }
    $report = [ordered]@{
        change_id = 'BE-REORG-014H'
        measurement_type = 'AUTHENTICATED_LOCAL_TEST_HTTP_GET_ONLY'
        result = 'PASS'
        database_attestation = 'operator-confirmed erp_test; NOT automatically verified by the HTTP probe'
        base_url_host = $uri.DnsSafeHost
        cohort_label = $Cohort
        samples_per_case = $Samples
        warmup_per_case = $Warmup
        measurement_limits = 'GET time includes HTTP and server execution; DB query count, SQL EXPLAIN, cache state, PBAC internals, memory, concurrency and DB identity are NOT measured.'
        cases = @($summaries)
    }
    $json = $report | ConvertTo-Json -Depth 7
    if ($OutputPath -ne '') { [System.IO.File]::WriteAllText($OutputPath, $json + [Environment]::NewLine) }
    Write-Output $json
}
finally {
    $client.Dispose()
    $handler.Dispose()
}
