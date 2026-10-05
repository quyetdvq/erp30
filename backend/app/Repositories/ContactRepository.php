<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DomainException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

final class ContactRepository
{
    public const ERROR_PERSISTENCE_FAILED = 'CONTACT_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'CONTACT_CONNECTION_INVALID';
    public const ERROR_OWNER_IMMUTABLE = 'CONTACT_OWNER_IMMUTABLE';
    public const ERROR_UPDATE_EMPTY = 'CONTACT_UPDATE_EMPTY';
    public const ERROR_FIELD_UNSUPPORTED = 'CONTACT_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'CONTACT_FIELD_REQUIRED';

    /** @var list<string> */
    private const FIELDS = [
        'contact_type', 'contact_ref_id', 'description', 'status', 'name', 'email',
        'phone', 'mobile', 'address', 'province_id', 'ward_id', 'province_name',
        'ward_name', 'created_by', 'updated_by', 'created_at', 'updated_at',
    ];

    /** @var list<string> */
    private const OWNER_FIELDS = ['contact_type', 'contact_ref_id'];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $result = $this->db->table('contacts')->where('id', $id)->get();
        $row = $result === false ? null : $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $payload = $this->payload($data, ['contact_type', 'contact_ref_id', 'status']);
        if (! $this->db->table('contacts')->insert($payload)) {
            throw new DomainException(self::ERROR_PERSISTENCE_FAILED, ['operation' => 'insert']);
        }
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'insert']);
        }

        return (int) $this->db->insertID();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        foreach (self::OWNER_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                throw new DomainException(self::ERROR_OWNER_IMMUTABLE, ['field' => $field]);
            }
        }
        $payload = $this->payload($data);
        if ($payload === []) {
            throw new DomainException(self::ERROR_UPDATE_EMPTY, ['operation' => 'update']);
        }

        return $this->db->table('contacts')->where('id', $id)->update($payload);
    }

    /** @return list<array<string, mixed>> */
    public function findByOwner(string $contactType, int $contactRefId, ?string $status = null): array
    {
        $builder = $this->db->table('contacts')
            ->where('contact_type', $contactType)
            ->where('contact_ref_id', $contactRefId)
            ->orderBy('id', 'ASC');
        if ($status !== null) {
            $builder->where('status', $status);
        }
        $result = $builder->get();

        return $result === false ? [] : array_values($result->getResultArray());
    }

    /**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $builder = $this->db->table('contacts')->orderBy('id', 'ASC');
        if (isset($filters['status'])) {
            $builder->where('status', $filters['status']);
        }
        if (isset($filters['contact_type'])) {
            $builder->where('contact_type', $filters['contact_type']);
        }
        if (isset($filters['query']) && is_string($filters['query']) && $filters['query'] !== '') {
            $query = $filters['query'];
            $builder->groupStart()
                ->like('name', $query)
                ->orLike('email', $query)
                ->orLike('phone', $query)
                ->orLike('mobile', $query)
                ->groupEnd();
        }
        $result = $builder->get();

        return $result === false ? [] : array_values($result->getResultArray());
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $required
     * @return array<string, mixed>
     */
    private function payload(array $data, array $required = []): array
    {
        foreach ($data as $field => $_value) {
            if (! in_array($field, self::FIELDS, true)) {
                throw new DomainException(self::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                throw new DomainException(self::ERROR_FIELD_REQUIRED, ['field' => $field]);
            }
        }

        return $data;
    }
}
