<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ContactRepository;
use CodeIgniter\Database\ConnectionInterface;
use InvalidArgumentException;
use RuntimeException;

final class ContactService
{
    /** @var array<string, string> */
    private const OWNER_TABLES = [
        'EMPLOYEE' => 'employees',
        'ORGANIZATION' => 'organizations',
    ];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?ContactRepository $repository = null,
        ?ConnectionInterface $db = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $type = $this->ownerType($data);
        $ownerId = $this->ownerId($data);
        $this->assertOwnerExists($type, $ownerId);
        $this->assertWardBelongsToProvince($data);

        return $this->repo()->insert($data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        if (array_key_exists('contact_type', $data) || array_key_exists('contact_ref_id', $data)) {
            throw new InvalidArgumentException('Contact owner transfer is not supported.');
        }
        $this->assertWardBelongsToProvince($data);

        return $this->repo()->update($id, $data);
    }

    public function inactivate(int $id, string $status = 'INACTIVE'): bool
    {
        if (! in_array($status, ['INACTIVE', 'CLOSED'], true)) {
            throw new InvalidArgumentException('Contact status must be INACTIVE or CLOSED.');
        }

        return $this->repo()->update($id, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listForOwner(string $contactType, int $contactRefId, ?string $status = null): array
    {
        $normalizedType = $this->normalizeType($contactType);
        $this->assertOwnerExists($normalizedType, $contactRefId);

        return $this->repo()->findByOwner($normalizedType, $contactRefId, $status);
    }

    /**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        return $this->repo()->search($filters);
    }

    private function repo(): ContactRepository
    {
        return $this->repository ?? new ContactRepository($this->db);
    }

    /** @param array<string, mixed> $data */
    private function ownerType(array $data): string
    {
        if (! isset($data['contact_type']) || ! is_string($data['contact_type'])) {
            throw new InvalidArgumentException('Contact type is required.');
        }

        return $this->normalizeType($data['contact_type']);
    }

    /** @param array<string, mixed> $data */
    private function ownerId(array $data): int
    {
        if (! isset($data['contact_ref_id']) || ! is_int($data['contact_ref_id']) && ! is_numeric($data['contact_ref_id'])) {
            throw new InvalidArgumentException('Contact owner id is required.');
        }

        return (int) $data['contact_ref_id'];
    }

    private function normalizeType(string $type): string
    {
        $normalized = strtoupper($type);
        if (! array_key_exists($normalized, self::OWNER_TABLES)) {
            throw new InvalidArgumentException('Unsupported contact type: ' . $type);
        }

        return $normalized;
    }

    private function assertOwnerExists(string $type, int $ownerId): void
    {
        $result = $this->db->table(self::OWNER_TABLES[$type])->select('id')->where('id', $ownerId)->get();
        if ($result === false || $result->getRowArray() === null) {
            throw new InvalidArgumentException('Contact owner does not exist.');
        }
    }

    /** @param array<string, mixed> $data */
    private function assertWardBelongsToProvince(array $data): void
    {
        if (! array_key_exists('province_id', $data) || ! array_key_exists('ward_id', $data)) {
            return;
        }
        if ($data['province_id'] === null || $data['ward_id'] === null) {
            return;
        }

        $result = $this->db->table('wards')
            ->select('id')
            ->where('id', (int) $data['ward_id'])
            ->where('province_id', (int) $data['province_id'])
            ->get();
        if ($result === false || $result->getRowArray() === null) {
            throw new InvalidArgumentException('Ward does not belong to province.');
        }
    }
}
