<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../../app/Shared/Http/ApiErrorMapper.php';

use App\Exceptions\DomainException;
use App\Shared\Http\ApiErrorMapper;

$cases = [
    'NOTIFICATION_INVALID_REQUEST' => 422,
    'NOTIFICATION_INVALID_KEY' => 422,
    'NOTIFICATION_REVISION_CONFLICT' => 409,
    'NOTIFICATION_OVERRIDE_LOCKED' => 403,
    'NOTIFICATION_ACCESS_DENIED' => 403,
    'NOTIFICATION_SETTINGS_DISABLED' => 403,
    'NOTIFICATION_CONFIGURATION_INVALID' => 500,
    'NOTIFICATION_STORAGE_FAILED' => 500,
    'NOTIFICATION_NOT_FOUND' => 404,
    'NOTIFICATION_INVALID_CURSOR' => 422,
];
foreach ($cases as $code => $status) {
    $actual = ApiErrorMapper::map(new DomainException($code));
    if ($actual['status'] !== $status || ($status !== 500 && $actual['code'] !== $code)) {
        throw new RuntimeException($code . ' mapped incorrectly: ' . json_encode($actual));
    }
}
echo "M11.1A HTTP ERROR MAPPING PASS\n";
