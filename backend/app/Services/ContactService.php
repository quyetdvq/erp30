<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use App\Repositories\ContactRepository;
use CodeIgniter\Database\ConnectionInterface;

final class ContactService
{
    public const ERROR_OWNER_TRANSFER_UNSUPPORTED = 'CONTACT_OWNER_TRANSFER_UNSUPPORTED';
    public const ERROR_STATUS_INVALID = 'CONTACT_STATUS_INVALID';
    public const ERROR_TYPE_REQUIRED = 'CONTACT_TYPE_REQUIRED';
    public const ERROR_OWNER_REFERENCE_REQUIRED = 'CONTACT_OWNER_REFERENCE_REQUIRED';
    public const ERROR_TYPE_UNSUPPORTED = 'CONTACT_TYPE_UNSUPPORTED';
    public const ERROR_OWNER_NOT_FOUND = 'CONTACT_OWNER_NOT_FOUND';
    public const ERROR_WARD_PROVINCE_MISMATCH = 'CONTACT_WARD_PROVINCE_MISMATCH';

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
            throw new DomainException(self::ERROR_OWNER_TRANSFER_UNSUPPORTED);
        }
        $this->assertWardBelongsToProvince($data);

        return $this->repo()->update($id, $data);
    }

    public function inactivate(int $id, string $status = 'INACTIVE'): bool
    {
        if (! in_array($status, ['INACTIVE', 'CLOSED'], true)) {
            throw new DomainException(self::ERROR_STATUS_INVALID, ['status' => $status]);
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
            throw new DomainException(self::ERROR_TYPE_REQUIRED, ['field' => 'contact_type']);
        }

        return $this->normalizeType($data['contact_type']);
    }

    /** @param array<string, mixed> $data */
    private function ownerId(array $data): int
    {
        if (! isset($data['contact_ref_id']) || ! is_int($data['contact_ref_id']) && ! is_numeric($data['contact_ref_id'])) {
            throw new DomainException(self::ERROR_OWNER_REFERENCE_REQUIRED, ['field' => 'contact_ref_id']);
        }

        return (int) $data['contact_ref_id'];
    }

    private function normalizeType(string $type): string
    {
        $normalized = strtoupper($type);
        if (! array_key_exists($normalized, self::OWNER_TABLES)) {
            throw new DomainException(self::ERROR_TYPE_UNSUPPORTED, ['contact_type' => $type]);
        }

        return $normalized;
    }

    private function assertOwnerExists(string $type, int $ownerId): void
    {
        $result = $this->db->table(self::OWNER_TABLES[$type])->select('id')->where('id', $ownerId)->get();
        if ($result === false || $result->getRowArray() === null) {
            throw new DomainException(self::ERROR_OWNER_NOT_FOUND, [
                'contact_type' => $type,
                'contact_ref_id' => $ownerId,
            ]);
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
            throw new DomainException(self::ERROR_WARD_PROVINCE_MISMATCH, [
                'province_id' => (int) $data['province_id'],
                'ward_id' => (int) $data['ward_id'],
            ]);
        }
    }
}
