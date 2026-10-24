<?php

declare(strict_types=1);

namespace App\Modules\Organization\Contracts;

/** Stable error codes shared by the module and its compatibility entrypoints. */
final class OrganizationErrors
{
    public const ERROR_SECOND_GROUP_ROOT = 'ORG_SECOND_GROUP_ROOT';
    public const ERROR_INVALID_PARENT = 'ORG_INVALID_PARENT';
    public const ERROR_HIERARCHY_CYCLE = 'ORG_HIERARCHY_CYCLE';
    public const ERROR_COMPANY_RESOLUTION_FAILED = 'ORG_COMPANY_RESOLUTION_FAILED';
    public const ERROR_ACTIVE_DATE_INVALID = 'ORG_ACTIVE_DATE_INVALID';
    public const ERROR_WARD_PROVINCE_MISMATCH = 'ORG_WARD_PROVINCE_MISMATCH';
    public const ERROR_REFERENCE_INVALID = 'ORG_REFERENCE_INVALID';
    public const ERROR_MUTATION_FAILED = 'ORG_MUTATION_FAILED';
    public const ERROR_PERSISTENCE_FAILED = 'ORG_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'ORG_CONNECTION_INVALID';
    public const ERROR_UPDATE_EMPTY = 'ORG_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'ORG_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'ORG_FIELD_REQUIRED';

    private function __construct()
    {
    }
}
