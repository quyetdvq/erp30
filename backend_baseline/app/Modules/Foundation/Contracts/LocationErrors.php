<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Contracts;

/** Stable location error identities shared by application, persistence and integration. */
final class LocationErrors
{
    public const ERROR_PROVINCE_NOT_FOUND = 'PROVINCE_NOT_FOUND';
    public const ERROR_WARD_NOT_FOUND = 'WARD_NOT_FOUND';
    public const ERROR_PROVINCE_REFERENCE_INVALID = 'WARD_PROVINCE_NOT_FOUND';
    public const ERROR_PROVINCE_INACTIVE = 'WARD_PROVINCE_INACTIVE';
    public const ERROR_WARD_PROVINCE_MISMATCH = 'WARD_PROVINCE_MISMATCH';
    public const ERROR_WARD_REFERENCED = 'WARD_PROVINCE_REASSIGN_REFERENCED';
    public const ERROR_STATUS_INVALID = 'LOCATION_STATUS_INVALID';
    public const ERROR_CODE_DUPLICATE = 'LOCATION_CODE_DUPLICATE';
    public const ERROR_PERSISTENCE_FAILED = 'LOCATION_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'LOCATION_CONNECTION_INVALID';
    public const ERROR_UPDATE_EMPTY = 'LOCATION_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'LOCATION_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'LOCATION_FIELD_REQUIRED';
    public const ERROR_TRANSITION_REQUIRED = 'WF_TRANSITION_REQUIRED';

    private function __construct()
    {
    }
}
