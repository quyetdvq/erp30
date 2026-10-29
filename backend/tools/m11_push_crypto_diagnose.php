<?php

declare(strict_types=1);

// CLI-only and read-only: generate disposable keys; never display key bytes.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once __DIR__ . '/../app/Modules/Notifications/NotificationPushEcKeyGenerator.php';

use App\Modules\Notifications\NotificationPushEcKeyGenerator;

/** @return string */
function cryptoFailureCategory(array $errors): string
{
    $message = strtolower(implode(' ', $errors));
    if (str_contains($message, 'private key length') || str_contains($message, 'key size')) {
        return 'PHP_KEY_BITS_VALIDATION';
    }
    if (str_contains($message, 'configuration file') || str_contains($message, 'no such file')
        || str_contains($message, 'conf_') || str_contains($message, 'config')) {
        return 'OPENSSL_CONFIGURATION';
    }
    if (str_contains($message, 'provider') || str_contains($message, 'dso support')
        || str_contains($message, 'module')) {
        return 'OPENSSL_PROVIDER';
    }
    if (str_contains($message, 'unsupported') || str_contains($message, 'unknown curve')) {
        return 'OPENSSL_ALGORITHM_UNAVAILABLE';
    }
    return $errors === [] ? 'NO_DETAILS_AVAILABLE' : 'OTHER_OPENSSL_ERROR';
}

/** @return list<string> */
function drainOpenSslErrors(): array
{
    $result = [];
    while (($message = openssl_error_string()) !== false) {
        $result[] = $message;
    }
    return $result;
}

/** @param array<string, int|string> $config */
function probeKey(string $name, array $config): bool
{
    drainOpenSslErrors();
    $key = @openssl_pkey_new($config);
    $errors = drainOpenSslErrors();
    $details = $key instanceof OpenSSLAsymmetricKey ? openssl_pkey_get_details($key) : false;
    $pass = is_array($details) && ($details['type'] ?? null) === OPENSSL_KEYTYPE_EC
        && ($details['bits'] ?? null) === 256
        && ($details['ec']['curve_name'] ?? null) === 'prime256v1'
        && strlen((string) ($details['ec']['x'] ?? '')) === 32
        && strlen((string) ($details['ec']['y'] ?? '')) === 32;
    echo $name, ': ', $pass ? 'P256_PASS' : 'FAIL', PHP_EOL;
    if (! $pass) {
        echo $name, '_CAUSE: ', cryptoFailureCategory($errors), PHP_EOL;
    }
    return $pass;
}

if (! extension_loaded('openssl') || ! defined('OPENSSL_KEYTYPE_EC')) {
    echo "OPENSSL_EXTENSION: UNAVAILABLE\n";
    exit(1);
}

$bundled = __DIR__ . '/../app/Modules/Notifications/openssl-p256.cnf';
$options = [
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'prime256v1',
    'private_key_bits' => 384,
];

echo 'PHP_VERSION: ', PHP_VERSION, PHP_EOL;
echo 'OPENSSL_VERSION: ', OPENSSL_VERSION_TEXT, PHP_EOL;
echo 'OPENSSL_CONF: ', getenv('OPENSSL_CONF') === false ? 'UNSET' : 'SET', PHP_EOL;
echo 'BUNDLED_CONFIG: ', is_readable($bundled) ? 'READABLE' : 'MISSING', PHP_EOL;
$curves = openssl_get_curve_names();
echo 'P256_CURVE_AVAILABLE: ', in_array('prime256v1', $curves, true) ? 'YES' : 'NO', PHP_EOL;

$defaultWorks = probeKey('PHP_EC_OPTIONS', $options);
$bundledWorks = is_readable($bundled) && probeKey('BUNDLED_CONFIG_OPTIONS', $options + ['config' => $bundled]);

try {
    $key = NotificationPushEcKeyGenerator::generate();
    $details = openssl_pkey_get_details($key);
    $valid = is_array($details) && ($details['bits'] ?? null) === 256
        && ($details['ec']['curve_name'] ?? null) === 'prime256v1';
    echo 'M11_EPHEMERAL_GENERATOR: ', $valid ? 'PASS' : 'FAIL', PHP_EOL;
    exit($valid ? 0 : 1);
} catch (Throwable $e) {
    echo 'M11_EPHEMERAL_GENERATOR: FAIL', PHP_EOL;
    echo 'REMEDIATION: Verify PHP CLI OpenSSL configuration and provider modules; do not replace the crypto algorithm.', PHP_EOL;
    exit(1);
}
