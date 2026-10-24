<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Application port for the canonical business identifier of one Resource.
 * Concrete adapters own their approved persistence/uniqueness namespace;
 * Auto Numbering never receives a client-selected table or column.
 */
interface ResourceIdentifierAdapter
{
    public const ERROR_METADATA_INVALID = 'NUM_IDENTIFIER_METADATA_INVALID';
    public const ERROR_ADAPTER_REGISTRATION_INVALID = 'NUM_IDENTIFIER_ADAPTER_REGISTRATION_INVALID';
    public const ERROR_ADAPTER_NOT_FOUND = 'NUM_IDENTIFIER_ADAPTER_NOT_FOUND';
    public const ERROR_RESOURCE_IDENTITY_MISMATCH = 'NUM_IDENTIFIER_RESOURCE_IDENTITY_MISMATCH';
    public const ERROR_SERIES_INVALID = 'NUM_IDENTIFIER_SERIES_INVALID';
    public const ERROR_IDENTIFIER_INVALID = 'NUM_IDENTIFIER_INVALID';
    public const ERROR_IDENTIFIER_TOO_LONG = 'NUM_IDENTIFIER_TOO_LONG';

    public function metadata(): ResourceIdentifierMetadata;

    /**
     * Return whether the identifier is occupied in this adapter's canonical
     * business uniqueness namespace. The caller supplies the NUM-003 context.
     */
    public function isIdentifierOccupied(string $identifier, NumberingScopePeriodContext $context): bool;

    /** Validate a non-blank manual identifier for AUTO_IF_EMPTY. */
    public function validateManualIdentifier(string $identifier, NumberingScopePeriodContext $context): void;

    /**
     * Classify only this Resource's canonical identifier unique violation.
     * Unrelated persistence failures must return false and propagate.
     */
    public function isIdentifierCollision(\Throwable $exception): bool;

}
