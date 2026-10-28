<?php

declare(strict_types=1);

if (! function_exists('CUSTOMER_GROUP')) {
    /** @return array<string,string> Customer group codes are stable API identifiers. */
    function CUSTOMER_GROUP(): array
    {
        return [
            'STANDARD' => 'Tiêu chuẩn',
            'TMDT' => 'Thương mại điện tử',
            'VIP1' => 'Vip 1',
            'VIP2' => 'Vip 2',
        ];
    }
}
