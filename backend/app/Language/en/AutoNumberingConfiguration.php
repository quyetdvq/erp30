<?php

declare(strict_types=1);

return [
    'NUM_SERIES_NOT_FOUND' => 'The numbering series was not found.',
    'NUM_RESOURCE_NOT_FOUND' => 'The numbering resource was not found.',
    'NUM_INVALID_CONFIGURATION' => 'The numbering configuration is invalid.',
    'NUM_AUTHORIZATION_DENIED' => 'The numbering configuration operation is not authorized.',
    'NUM_ACTIVE_DEFAULT_CONFLICT' => 'The resource already has an active default numbering series.',
    'NUM_ISSUED_FIELD_IMMUTABLE' => 'The issued numbering series identity is immutable.',
    'NUM_ISSUED_NO_HARD_DELETE' => 'An issued numbering series cannot be hard-deleted.',
    'NUM_PATTERN_VALIDATOR_REQUIRED' => 'An active numbering series requires the canonical pattern validator.',
    'NUM_PATTERN_INVALID' => 'The numbering pattern is invalid.',
    'NUM_PATTERN_COLLISION' => 'The numbering pattern collides with another active series.',
    'NUM_SCOPE_INVALID' => 'The numbering scope or reset policy is invalid.',
    'NUM_SCOPE_CONTEXT_REQUIRED' => 'An authoritative evaluation organization is required for this scope.',
    'NUM_SCOPE_RESOLUTION_FAILED' => 'The authoritative numbering scope could not be resolved.',
    'NUM_GROUP_ROOT_INVALID' => 'The canonical GROUP root is missing, inactive, or ambiguous.',
    'NUM_COMPANY_CONTEXT_REQUIRED' => 'A canonical Company context is required for this numbering operation.',
    'NUM_BUSINESS_TIMEZONE_REQUIRED' => 'A configured business timezone is required for this numbering period.',
    'NUM_BUSINESS_TIMEZONE_INVALID' => 'The configured business timezone is invalid.',
    'NUM_BUSINESS_TIMEZONE_RESOLUTION_FAILED' => 'The business timezone could not be resolved.',
    'NUM_PATTERN_METADATA_REQUIRED' => 'Parsed canonical pattern metadata is required.',
];
