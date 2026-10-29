<?php

declare(strict_types=1);

/** Regression for the exact M8.6D / OpenAPI request shape; no DB or CI bootstrap. */
require_once __DIR__ . '/../app/Shared/Read/PositiveId.php';
require_once __DIR__ . '/../app/Shared/Read/OrganizationLabelResolveRequest.php';

use App\Shared\Read\OrganizationLabelResolveRequest as Input;

$n = 0;
$check = static function (bool $ok, string $why) use (&$n): void {
    if (! $ok) throw new RuntimeException('M19.19 Organization resolve: ' . $why);
    $n++;
};
$valid = [
    ['{"organization_ids":["1","2"]}', ['1','2']],
    ['{"organization_ids":[]}', []],
    ['{"organization_ids":[1,2]}', ['1','2']],
    ['{"organization_ids":["001",1,"2","002"]}', ['1','2']],
    ['{"organization_ids":["18446744073709551615"]}', ['18446744073709551615']],
    ['{"organization_ids":[18446744073709551615]}', ['18446744073709551615']],
    ['{"organization_ids":["3"],"unused":true}', ['3']],
    [json_encode(['organization_ids'=>range(1,50)], JSON_THROW_ON_ERROR), array_map('strval',range(1,50))],
];
foreach ($valid as [$body, $expected]) {
    $actual = Input::ids($body);
    $check($actual === $expected, 'accepts OpenAPI object and canonical IDs: ' . substr($body,0,70));
}
$invalid = [
    '', 'not JSON', '{}', 'null', '[]', '["1"]', '"1"',
    '{"organization_ids":null}', '{"organization_ids":"1"}',
    '{"organization_ids":{"0":"1"}}', '{"organization_ids":true}',
    '{"organization_ids":[0]}', '{"organization_ids":[-1]}',
    '{"organization_ids":[1.5]}', '{"organization_ids":[null]}',
    '{"organization_ids":[true]}', '{"organization_ids":["0"]}',
    '{"organization_ids":[" 1"]}', '{"organization_ids":["1e3"]}',
    '{"organization_ids":["18446744073709551616"]}',
    '{"organization_ids":["1", {"id":"2"}]}',
    json_encode(['organization_ids'=>range(1,51)], JSON_THROW_ON_ERROR),
    '{"organization_ids": [' . str_repeat(' ', 8200) . '"1"]}',
    '{"organization_ids": [[[[["1"]]]]]}',
];
foreach ($invalid as $body) {
    try {
        Input::ids($body);
        throw new RuntimeException('invalid payload was accepted: ' . substr($body,0,80));
    } catch (InvalidArgumentException) {
        $n++;
    }
}
$source = (string) file_get_contents(__DIR__ . '/../app/Controllers/Api/ReadCatalogController.php');
$check(str_contains($source, 'OrganizationLabelResolveRequest::ids('), 'controller uses strict OpenAPI object reader');
$check(str_contains($source, 'organizationSqlService($user)->organizationLabels($ids)'), 'controller retains SQL-first/PBAC label lookup');
$check(!str_contains($source, 'organizationLabels(array_keys($unique))'), 'no old root-array parser');
$contract = (string) file_get_contents(__DIR__ . '/../../contracts/openapi/openapi.yaml');
$check(str_contains($contract, 'required: [organization_ids]'), 'frozen request shape untouched');
$check(str_contains($contract, 'maxItems: 50'), '50-item limit intact');
echo "M19.19 ORG LABEL REQUEST: {$n}/{$n} PASS\n";
