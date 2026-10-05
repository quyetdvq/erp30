<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

final class OrganizationRepository
{
    /** @var list<string> */
    private const FIELDS = [
        'parent_id', 'type', 'code', 'name', 'legal_name', 'tax_code', 'address',
        'province_id', 'ward_id', 'description', 'status', 'active_from', 'active_to',
        'created_by', 'updated_by', 'created_at', 'updated_at',
    ];

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
        $result = $this->db->table('organizations')->where('id', $id)->get();
        $row = $result === false ? null : $result->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        $result = $this->db->table('organizations')->where('code', $code)->get();
        $row = $result === false ? null : $result->getRowArray();

        return $row === null ? null : $row;
    }

    /**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function findAll(array $filters = []): array
    {
        $builder = $this->db->table('organizations')->orderBy('id', 'ASC');
        foreach (['parent_id', 'type', 'status'] as $field) {
            if (array_key_exists($field, $filters)) {
                $builder->where($field, $filters[$field]);
            }
        }

        $result = $builder->get();

        return $result === false ? [] : array_values($result->getResultArray());
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $payload = $this->payload($data, ['type', 'code', 'name', 'status']);
        if (! $this->db->table('organizations')->insert($payload)) {
            throw new \RuntimeException('Unable to persist organization.');
        }

        if (! $this->db instanceof BaseConnection) {
            throw new \RuntimeException('Organization persistence requires a writable database connection.');
        }

        return (int) $this->db->insertID();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $payload = $this->payload($data);
        if ($payload === []) {
            throw new InvalidArgumentException('Organization update cannot be empty.');
        }

        return $this->db->table('organizations')->where('id', $id)->update($payload);
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
                throw new InvalidArgumentException('Unsupported organization field: ' . $field);
            }
        }
        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidArgumentException('Missing organization field: ' . $field);
            }
        }

        return $data;
    }
}
