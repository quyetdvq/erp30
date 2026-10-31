<?php

declare(strict_types=1);

/**
 * HF05 route / API envelope / read-only boundary guard. No CI4 bootstrap,
 * authenticated network calls, SQL connection, schema changes, or credentials.
 */
require_once __DIR__ . '/../tools/routes/RouteInventory.php';

use DinkERP\Tools\Routes\RouteInventory;

$root = dirname(__DIR__, 2);
$count = 0;
$check = static function (bool $ok, string $label) use (&$count): void {
    if (!$ok) throw new RuntimeException('HF05 CONTRACT FAIL: ' . $label);
    ++$count;
    echo 'PASS: ' . $label . PHP_EOL;
};
$source = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value) || $value === '') throw new RuntimeException('Missing file: ' . $path);
    return $value;
};
$numberRoutes = $source('backend/app/Config/Routes/ApiV1/NumberingRoutes.php');
$rootRoutes = $source('backend/app/Config/Routes.php');
$controller = $source('backend/app/Modules/Numbering/Configuration/NumberingController.php');
$service = $source('backend/app/Modules/Numbering/Configuration/NumberingService.php');
$projection = $source('backend/app/Modules/Numbering/Configuration/NumberingOverview.php');
$contract = $source('contracts/openapi/openapi.yaml');
$extensions = json_decode($source('backend/tests/fixtures/routes_extensions_approved.json'), true, 512, JSON_THROW_ON_ERROR);
$check(str_contains($rootRoutes, '$routes->setAutoRoute(false)'), 'AutoRoute remains disabled');
$check(str_contains($rootRoutes, "group('api/v1', ['filter' => 'cors']"), 'Only reviewed api/v1 CORS group owns the endpoint');
$check(substr_count($rootRoutes, "require __DIR__ . '/Routes/ApiV1/NumberingRoutes.php';") === 1,
    'Numbering route file mounted exactly once');
$check(substr_count($numberRoutes, "\$routes->get('numbering-series/overview'") === 1,
    'Exactly one GET overview route is registered');
$check(str_contains($numberRoutes, "[\\App\\Modules\\Numbering\\Configuration\\NumberingController::class, 'overview']"),
    'Overview is dispatched to the Numbering owner controller');
$check(!preg_match('/\$routes->(?:post|put|patch|delete)\(\'numbering-series\/overview\'/i', $numberRoutes),
    'No mutation verb is exposed for the overview');
$expectedRoutes = [
    'get' => [
        'resources/(:num)/numbering-series',
        'resources/(:num)/numbering-series/(:num)',
    ],
    'post' => [
        'resources/(:num)/numbering-series',
        'resources/(:num)/numbering-series/(:num)/actions/set-state',
        'resources/(:num)/numbering-series/actions/validate-config',
    ],
    'patch' => ['resources/(:num)/numbering-series/(:num)'],
];
foreach ($expectedRoutes as $method => $paths) {
    foreach ($paths as $path) {
        $check(str_contains($numberRoutes, "\$routes->$method('$path',"),
            "Existing $method $path preserved");
    }
}
$check(substr_count($numberRoutes, '$routes->') === 7,
    'Exactly six existing Numbering endpoints plus the additive overview');
$target = ['method' => 'GET', 'path' => 'api/v1/numbering-series/overview',
    'handler' => ['App\\Modules\\Numbering\\Configuration\\NumberingController', 'overview'],
    'options' => ['filter' => 'cors']];
$approved = array_values(array_filter($extensions['routes'] ?? [], static fn (array $row): bool =>
    ($row['path'] ?? null) === 'api/v1/numbering-series/overview'));
$check($approved === [$target], 'Only one exact reviewed GET/CORS extension is approved');
$rows = RouteInventory::read($root);
$actual = array_values(array_filter($rows, static fn (array $row): bool =>
    ($row['path'] ?? null) === 'api/v1/numbering-series/overview'));
$check($actual === [$target], 'Parsed runtime route inventory agrees with the extension manifest');
$check(count($rows) === 180 + count($extensions['routes']),
    'Frozen route count plus approved extensions is unchanged');
$check(str_contains($controller, 'public function overview(): ResponseInterface')
    && str_contains($controller, 'ApiResponder::success($this->response, $this->service()->overview())'),
    'Controller uses the shared 200/API-envelope responder');
$check(str_contains($controller, 'if (!$actor instanceof User) throw new DomainException(\'UNAUTHENTICATED\')')
    && str_contains($controller, 'return new NumberingService($actor)'),
    'Controller checks Shield authentication before constructing the service');
$check(!str_contains($controller, '->table(') && !str_contains($controller, '->query('),
    'Controller cannot bypass the Service/PBAC reader');
$overviewStart = strpos($service, 'public function overview(): array');
$listingStart = strpos($service, 'public function listing(');
$check($overviewStart !== false && $listingStart !== false && $listingStart > $overviewStart,
    'Overview is a dedicated read method, not a replacement for listing');
$overviewMethod = substr($service, $overviewStart, $listingStart - $overviewStart);
$check(str_contains($overviewMethod, "table('resources')")
    && str_contains($overviewMethod, "table('auto_numbering_series')"),
    'Overview SQL reads only the persisted Resource and series tables');
$check(str_contains($overviewMethod, "canView('resource', \$resource)")
    && str_contains($overviewMethod, "decideMutation('resource', 'configure_numbering', \$resource)"),
    'Authoritative VIEW plus configure_numbering are both enforced');
$check(str_contains($overviewMethod, 'array_chunk($ids, 200)')
    && str_contains($overviewMethod, "whereIn('resource_id', \$batch)"),
    'Series SELECTs are bounded to server-authorized IDs in 200-ID chunks');
$check(!preg_match('/->(?:insert|update|delete|replace|transBegin|query)\s*\(/', $overviewMethod)
    && !str_contains($overviewMethod, '$_GET') && !str_contains($overviewMethod, '$_POST'),
    'Overview neither writes data nor trusts client-provided IDs');
$check(str_contains($projection, "'allowed_actions' => ['configure_numbering' => true]")
    && str_contains($projection, 'NumberingRules::visible($raw)'),
    'Projection uses permitted Resource fields and the existing series serializer');
$check(str_contains($projection, 'Invalid or cross-Resource numbering overview row.')
    && str_contains($projection, 'Duplicate Resource in numbering overview.'),
    'Cross-scope and duplicate source rows reject the whole response');
$check((bool) preg_match('/^  \/api\/v1\/numbering-series\/overview:\R    get:/m', $contract),
    'OpenAPI advertises only the GET operation');
$check(str_contains($contract, 'operationId: getAutoNumberingOverview')
    && str_contains($contract, "'#/components/responses/AutoNumberingOverviewResponse'"),
    'OpenAPI response matches the shared overview envelope');
$check(str_contains($contract, "AutoNumberingOverviewResponse: {allOf: [{\$ref: '#/components/schemas/SuccessResponse'}]")
    && str_contains($contract, "data: {\$ref: '#/components/schemas/AutoNumberingOverview'}"),
    'OpenAPI response data uses the overview schema');
$check(str_contains($contract, "'401': {\$ref: '#/components/responses/Unauthorized'}")
    && str_contains($contract, "'403': {\$ref: '#/components/responses/Forbidden'}"),
    'Authentication and permission failures remain part of the OpenAPI contract');

echo 'TOTAL: ' . $count . ' HF05 route/security/OpenAPI contract assertions passed.' . PHP_EOL;
