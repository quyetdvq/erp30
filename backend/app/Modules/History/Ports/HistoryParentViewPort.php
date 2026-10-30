<?php

declare(strict_types=1);

namespace App\Modules\History\Ports;

use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;

/** Parent authorization port: History cannot import a business module. */
interface HistoryParentViewPort
{
    /** Reject when parent is missing or not viewable; do not disclose existence. */
    public function assertVisible(string $resource, string $id, UnifiedAccessService $access,
        BaseConnection $db): void;
}
