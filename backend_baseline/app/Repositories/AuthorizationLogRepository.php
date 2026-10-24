<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/** Persistence-only insert boundary for immutable authorization evidence. */
final class AuthorizationLogRepository
{
    public const ERROR_PERSISTENCE_FAILED = 'AUTHORIZATION_LOG_PERSISTENCE_FAILED';
    public const ERROR_CONNECTION_INVALID = 'AUTHORIZATION_LOG_CONNECTION_INVALID';
    public const ERROR_FIELD_UNSUPPORTED = 'AUTHORIZATION_LOG_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'AUTHORIZATION_LOG_FIELD_REQUIRED';

    /** @var list<string> */
    private const FIELDS = [
        'user_id', 'organization_id', 'resource_id', 'action', 'state_id',
        'decision', 'matched_policy_id', 'reason', 'request_id', 'created_at',
    ];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        foreach ($data as $field => $_value) {
            if (! in_array($field, self::FIELDS, true)) {
                throw new DomainException(self::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
        foreach (['user_id', 'resource_id', 'action', 'decision', 'created_at'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw new DomainException(self::ERROR_FIELD_REQUIRED, ['field' => $field]);
            }
        }

        try {
            if (! $this->db->table('authorization_logs')->insert($data)) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
            }
            if (! $this->db instanceof BaseConnection) {
                throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => 'insert']);
            }
            $id = (int) $this->db->insertID();
        } catch (PersistenceException|DomainException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert', $exception);
        }
        if ($id < 1) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
        }

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function findAllWithResourceType(): array
    {
        try {
            $result = $this->db->table('authorization_logs al')
                ->select('al.*, r.type resource_type')
                ->join('resources r', 'r.id = al.resource_id')
                ->orderBy('al.id', 'ASC')
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAllWithResourceType', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAllWithResourceType');
        }

        return array_values($result->getResultArray());
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        try {
            $result = $this->db->table('authorization_logs')->where('id', $id)->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findById', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findById');
        }

        $row = $result->getRowArray();

        return $row === null ? null : $row;
    }
}
