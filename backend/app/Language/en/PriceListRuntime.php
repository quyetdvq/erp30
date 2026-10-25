<?php

declare(strict_types=1);

return [
    'PRICE_LIST_NOT_FOUND' => 'The Price List was not found.',
    'PRICE_LIST_AUTHORIZATION_DENIED' => 'You do not have permission for this Price List action.',
    'PRICE_LIST_REQUIRED_FIELD' => 'A required Price List field is missing or invalid.',
    'PRICE_LIST_INVALID_FIELD' => 'The Price List field or query is not supported.',
    'PRICE_LIST_STATUS_REQUIRES_SETSTATE' => 'Use the set-state action to change a Price List state.',
    'PRICE_LIST_INVALID_STATUS' => 'The requested Price List state is invalid.',
    'PRICE_LIST_INVALID_EFFECTIVE_INTERVAL' => 'The Price List effective interval is invalid.',
    'PRICE_LIST_INVALID_ORGANIZATION_SCOPE' => 'Price Lists require a GROUP or COMPANY organization.',
    'PRICE_LIST_INVALID_REFERENCE' => 'The selected Pricing reference is invalid.',
    'PRICE_LIST_INVALID_ITEM' => 'The Product/UOM Price List item is invalid.',
    'PRICE_LIST_ITEM_DUPLICATE' => 'The same Product/UOM pair occurs more than once.',
    'PRICE_LIST_INVALID_PRICE' => 'The price must be non-negative DECIMAL(18,2).',
    'PRICE_LIST_INACTIVE_REFERENCE' => 'New or changed Price List references must be active.',
    'PRICE_LIST_DUPLICATE' => 'The Price List code or Product/UOM pair already exists.',
    'PRICE_LIST_CONCURRENT_MODIFICATION' => 'This Price List was updated concurrently.',
    'PRICE_LIST_SCOPE_TOO_COMPLEX' => 'The Price List visibility scope is too large to compile safely.',
    'PRICE_LIST_CONNECTION_INVALID' => 'Pricing requires verified transactional InnoDB tables.',
    'PRICE_LIST_LOCK_FAILED' => 'Pricing is busy. Try again.',
    'PRICE_LIST_MUTATION_FAILED' => 'Price List changes could not be saved.',
];
