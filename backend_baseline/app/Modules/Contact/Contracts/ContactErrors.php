<?php

declare(strict_types=1);

namespace App\Modules\Contact\Contracts;

/** Stable Contact error vocabulary shared by module and compatibility entrypoints. */
final class ContactErrors
{
    public const ERROR_OWNER_TRANSFER_UNSUPPORTED = 'CONTACT_OWNER_TRANSFER_UNSUPPORTED';
    public const ERROR_STATUS_INVALID = 'CONTACT_STATUS_INVALID';
    public const ERROR_TYPE_REQUIRED = 'CONTACT_TYPE_REQUIRED';
    public const ERROR_OWNER_REFERENCE_REQUIRED = 'CONTACT_OWNER_REFERENCE_REQUIRED';
    public const ERROR_TYPE_UNSUPPORTED = 'CONTACT_TYPE_UNSUPPORTED';
    public const ERROR_OWNER_NOT_FOUND = 'CONTACT_OWNER_NOT_FOUND';
    public const ERROR_WARD_PROVINCE_MISMATCH = 'CONTACT_WARD_PROVINCE_MISMATCH';
    public const ERROR_NOT_FOUND = 'CONTACT_NOT_FOUND';

    public const ERROR_PERSISTENCE_FAILED = 'CONTACT_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'CONTACT_CONNECTION_INVALID';
    public const ERROR_OWNER_IMMUTABLE = 'CONTACT_OWNER_IMMUTABLE';
    public const ERROR_UPDATE_EMPTY = 'CONTACT_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'CONTACT_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'CONTACT_FIELD_REQUIRED';

    private function __construct()
    {
    }
}
