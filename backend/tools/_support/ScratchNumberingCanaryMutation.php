<?php

declare(strict_types=1);

use App\Services\AutoNumberingBusinessMutation;
use CodeIgniter\Database\ConnectionInterface;

/** Loaded only after CI4 autoload has initialized. */
final class ScratchNumberingCanaryMutation implements AutoNumberingBusinessMutation
{
    public ?int $insertedId = null;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $sku,
        private readonly bool $forceRollback = false,
    ) {
    }

    public function isIdentifierOccupied(string $identifier): bool
    {
        return false;
    }

    public function persist(string $identifier): void
    {
        $inserted = $this->db->table('products')->insert([
            'code' => $identifier,
            'sku' => $this->sku,
            'product_type' => 'STANDARD',
            'serial_management_enabled' => 0,
            'status' => 'ACTIVE',
            'created_at' => '2026-10-06 04:05:06',
        ]);
        if (! $inserted || ($id = (int) $this->db->insertID()) < 1) {
            throw new RuntimeException('03C-02 STOP: canary Product insertion failed.');
        }
        $this->insertedId = $id;
        if ($this->forceRollback) {
            throw new RuntimeException('03C-02 forced business mutation rollback.');
        }
    }
}
