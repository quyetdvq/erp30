<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use JsonException;

/** Immutable technical audit sink for configuration mutations. */
final class ConfigurationAuditEventRepository implements ConfigurationAuditEventWriter
{
    public const ERROR_PERSISTENCE_FAILED = 'CONFIGURATION_AUDIT_PERSISTENCE_FAILED';
    public const ERROR_FIELD_UNSUPPORTED = 'CONFIGURATION_AUDIT_FIELD_UNSUPPORTED';
    public const ERROR_FIELD_REQUIRED = 'CONFIGURATION_AUDIT_FIELD_REQUIRED';

    /** @var list<string> */
    private const FIELDS = [
        'configuration_type', 'context_type', 'organization_id', 'setting_key',
        'operation', 'old_value_json', 'new_value_json', 'actor_user_id',
        'request_id', 'created_at',
    ];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $event */
    public function insert(array $event): int
    {
        foreach ($event as $field => $_value) {
            if (! in_array($field, self::FIELDS, true)) {
                throw new DomainException(self::ERROR_FIELD_UNSUPPORTED, ['field' => $field]);
            }
        }
        foreach (['configuration_type', 'context_type', 'setting_key', 'operation', 'actor_user_id', 'created_at'] as $field) {
            if (! array_key_exists($field, $event)) {
                throw new DomainException(self::ERROR_FIELD_REQUIRED, ['field' => $field]);
            }
        }

        try {
            if (! $this->db->table('configuration_audit_events')->insert($event)) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
            }
            if (! $this->db instanceof BaseConnection) {
                throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'insert');
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
    public function findAll(): array
    {
        $result = $this->db->table('configuration_audit_events')->orderBy('id', 'ASC')->get();
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'findAll');
        }

        return array_values($result->getResultArray());
    }

    /** @param mixed $value */
    public static function encodeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new DomainException(self::ERROR_FIELD_REQUIRED, ['field' => 'value'], $exception);
        }
    }
}
