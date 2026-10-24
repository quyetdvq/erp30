<?php

declare(strict_types=1);

/**
 * M5.3 developer i18n: API transport localizes messages, domain internals
 * remain stable diagnostics. No composer, MariaDB, network or CI4 bootstrap.
 */
if (! function_exists('lang')) {
    function lang(string $line, array $args = [], ?string $locale = null): string
    {
        $locale = $locale === 'en' ? 'en' : 'vi';
        [$group, $key] = explode('.', $line, 2);
        $path = __DIR__ . '/../app/Language/' . $locale . '/' . $group . '.php';
        $messages = require $path;
        return (string) ($messages[$key] ?? $line);
    }
}

require_once __DIR__ . '/../app/Shared/Http/RequestMetadata.php';
require_once __DIR__ . '/../app/Shared/Http/ApiEnvelope.php';
require_once __DIR__ . '/../app/Shared/Http/ApiResponder.php';
require_once __DIR__ . '/../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../app/Shared/Http/ApiErrorMapper.php';

use App\Shared\Http\ApiEnvelope;
use App\Shared\Http\ApiErrorMapper;
use App\Shared\Http\ApiResponder;
use App\Shared\Http\RequestMetadata;

$assertions = 0;
$check = static function (bool $condition, string $label) use (&$assertions): void {
    if (! $condition) {
        throw new RuntimeException('M5.3 bilingual contract failed: ' . $label);
    }
    ++$assertions;
};

$catalog = [];
foreach (['en', 'vi'] as $locale) {
    $p = __DIR__ . '/../app/Language/' . $locale . '/ApiRuntime.php';
    $check(is_file($p), $locale . ' overlay exists');
    $catalog[$locale] = require $p;
    $check(is_array($catalog[$locale]) && array_keys($catalog[$locale]) === ['contactPrimaryScopeUnavailable', 'contactStateRequiresSetstate', 'mutationDisabled'], $locale . ' exact overlay');
    $check(is_string($catalog[$locale]['mutationDisabled']) && strlen($catalog[$locale]['mutationDisabled']) >= 20, $locale . ' full translation');
}
$check($catalog['en']['mutationDisabled'] !== $catalog['vi']['mutationDisabled'], 'languages differ');

foreach (['en', 'vi'] as $locale) {
    RequestMetadata::begin('M53-i18n-' . $locale, $locale);
    $check(ApiResponder::message('ACCESS_MUTATION_NOT_ENABLED', 403) === $catalog[$locale]['mutationDisabled'], $locale . ' gate localized');
    $check(ApiResponder::message('AUTH_POLICY_DENIED', 403) === (require __DIR__ . '/../app/Language/' . $locale . '/Api.php')['forbidden'], $locale . ' PBAC stays baseline');
    $check(ApiResponder::message('LOCATION_CODE_DUPLICATE', 409) === (require __DIR__ . '/../app/Language/' . $locale . '/Api.php')['conflict'], $locale . ' M5 fallback stays baseline');
    $check(ApiResponder::message('WF_INVALID_TRANSITION', 422) === (require __DIR__ . '/../app/Language/' . $locale . '/Api.php')['validation'], $locale . ' workflow fallback stays baseline');
    foreach ([new InvalidArgumentException('READ_ENDPOINT_UNSUPPORTED'), new UnexpectedValueException('ERP_PERMISSION_CATALOG_MAPPING_INVALID')] as $error) {
        $mapped = ApiErrorMapper::map($error);
        $message = ApiResponder::message($mapped['code'], $mapped['status']);
        $expected = (require __DIR__ . '/../app/Language/' . $locale . '/Api.php')['serverError'];
        $check($mapped['status'] === 500 && $mapped['code'] === 'INTERNAL_SERVER_ERROR' && $message === $expected, $locale . ' internal exception sanitized');
        $json = ApiEnvelope::failure($mapped['code'], $message);
        $check($json['error']['message'] === $expected && ! str_contains($json['error']['message'], $error->getMessage()), $locale . ' diagnostic not leaked');
    }
}
RequestMetadata::begin('M53-i18n-fallback', 'fr');
$check(ApiResponder::message('ACCESS_MUTATION_NOT_ENABLED', 403) === $catalog['vi']['mutationDisabled'], 'unsupported locale defaults vi');

$source = static fn (string $rel): string => (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
$check(str_contains($source('backend/app/Controllers/Api/ReadCatalogController.php'), "'READ_ENDPOINT_UNSUPPORTED'"), 'read endpoint internal code stable');
$check(str_contains($source('backend/app/Modules/Identity/Management/IdentityReadRepository.php'), "'ERP_PERMISSION_CATALOG_MAPPING_INVALID'"), 'permission mapping internal code stable');
$check(! str_contains($source('backend/app/Controllers/Api/ReadCatalogController.php'), 'Unsupported read endpoint.'), 'raw read diagnostic removed');
$check(! str_contains($source('backend/app/Modules/Identity/Management/IdentityReadRepository.php'), 'Invalid ERP base permission catalog mapping.'), 'raw catalog diagnostic removed');
$check(str_contains($source('frontend/src/services/api.ts'), "config.headers.set('Accept-Language', i18n.global.locale.value)"), 'frontend sends selected locale');
$check(str_contains($source('backend/app/Shared/Http/RequestMetadataFilter.php'), "['vi', 'en']"), 'backend accepts exactly en and vi');
$check(str_contains($source('backend/app/Shared/Http/ApiResponder.php'), "'ApiRuntime.mutationDisabled'"), 'only write gate has runtime override');
$check(str_contains($source('backend/app/Shared/Http/ApiExceptionHandler.php'), 'ApiResponder::error('), 'HTTP exception handler uses localized API error');
foreach (['Locations/Mutation/LocationMutationController.php', 'Organization/Mutation/OrganizationMutationController.php', 'People/Mutation/PeopleMutationController.php'] as $controller) {
    $body = $source('backend/app/Modules/' . $controller);
    $check(str_contains($body, 'DINKERP_M33B_WRITE_MODE') && str_contains($body, "'ACCESS_MUTATION_NOT_ENABLED'"), $controller . ' keeps gated PBAC mutation');
}

echo "M5.3 I18N CONTRACT: {$assertions}/{$assertions} PASS (en/vi, internal errors, DEV gate, baseline messages).\n";
