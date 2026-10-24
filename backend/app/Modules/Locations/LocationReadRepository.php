<?php

declare(strict_types=1);

namespace App\Modules\Locations;

use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;

/** Internal, read-only location catalog. HTTP list contracts will be added after M3. */
final class LocationReadRepository
{
    public function __construct(private readonly ?BaseConnection $connection = null)
    {
    }

    private function db(): BaseConnection
    {
        return $this->connection ?? db_connect();
    }

    /** @return array<string, mixed>|null */
    public function province(int|string $id): ?array
    {
        return $this->db()->table('provinces')->where('id', PositiveId::value($id))->get()->getRowArray();
    }

    /** @return array<string, mixed>|null */
    public function ward(int|string $id): ?array
    {
        return $this->db()->table('wards')->where('id', PositiveId::value($id))->get()->getRowArray();
    }

    /** @return list<array<string, mixed>> */
    public function provinces(?string $status = null): array
    {
        $query = $this->db()->table('provinces')->orderBy('id', 'ASC');
        if ($status !== null) {
            $query->where('status', $status);
        }
        return array_values($query->get()->getResultArray());
    }

    /** @return list<array<string, mixed>> Wards are always bounded to one province. */
    public function wardsOfProvince(int|string $provinceId, ?string $status = null): array
    {
        $query = $this->db()->table('wards')
            ->where('province_id', PositiveId::value($provinceId))
            ->orderBy('id', 'ASC');
        if ($status !== null) {
            $query->where('status', $status);
        }
        return array_values($query->get()->getResultArray());
    }

    public function wardBelongsToProvince(int|string $wardId, int|string $provinceId): bool
    {
        return $this->db()->table('wards')
            ->select('id')
            ->where('id', PositiveId::value($wardId))
            ->where('province_id', PositiveId::value($provinceId))
            ->get()->getRowArray() !== null;
    }
}
