<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Modules\Settings\Display\Contracts\ConfigurationAuditErrors;
use App\Modules\Settings\Display\Infrastructure\SqlConfigurationAuditEventRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility entrypoint. New configuration-audit persistence belongs in the Settings module. */
final class ConfigurationAuditEventRepository implements ConfigurationAuditEventWriter
{
    public const ERROR_PERSISTENCE_FAILED = ConfigurationAuditErrors::ERROR_PERSISTENCE_FAILED;
    public const ERROR_FIELD_UNSUPPORTED = ConfigurationAuditErrors::ERROR_FIELD_UNSUPPORTED;
    public const ERROR_FIELD_REQUIRED = ConfigurationAuditErrors::ERROR_FIELD_REQUIRED;

    private readonly SqlConfigurationAuditEventRepository $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->delegate = new SqlConfigurationAuditEventRepository($db);
    }

    /** @param array<string, mixed> $event */
    public function insert(array $event): int
    {
        return $this->delegate->insert($event);
    }

    /** @return list<array<string, mixed>> */
    public function findAll(): array
    {
        return $this->delegate->findAll();
    }

    public static function encodeValue(mixed $value): ?string
    {
        return SqlConfigurationAuditEventRepository::encodeValue($value);
    }
}
