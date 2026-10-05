<?php

declare(strict_types=1);

namespace App\Infrastructure\Lock;

use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Database\ResultInterface;
use RuntimeException;

final class MariaDbDomainLockService implements DomainLockService
{
    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function acquire(string $domainKey, int $timeoutSeconds = 5): void
    {
        if ($domainKey === '' || $timeoutSeconds < 0) {
            throw new RuntimeException('Invalid domain lock request.');
        }

        $result = $this->db->query(
            'SELECT GET_LOCK(?, ?) AS acquired',
            [$this->namespaced($domainKey), $timeoutSeconds],
        );
        if ($result === false) {
            throw new RuntimeException('Unable to acquire domain lock.');
        }

        if (! $result instanceof ResultInterface) {
            throw new RuntimeException('Unable to read domain lock result.');
        }
        $row = $result->getRowArray();
        if ((int) ($row['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Domain lock timeout: ' . $domainKey);
        }
    }

    public function release(string $domainKey): void
    {
        $result = $this->db->query('SELECT RELEASE_LOCK(?) AS released', [$this->namespaced($domainKey)]);
        if ($result === false) {
            throw new RuntimeException('Unable to release domain lock.');
        }
    }

    public function synchronized(string $domainKey, callable $callback, int $timeoutSeconds = 5): mixed
    {
        $this->acquire($domainKey, $timeoutSeconds);
        try {
            return $callback();
        } finally {
            $this->release($domainKey);
        }
    }

    private function namespaced(string $domainKey): string
    {
        $environment = defined('ENVIRONMENT') ? ENVIRONMENT : 'development';
        $name = 'dinkerp:' . $environment . ':' . $this->db->getDatabase() . ':' . $domainKey;
        if (strlen($name) <= 64) {
            return $name;
        }

        return substr($name, 0, 31) . ':' . substr(hash('sha256', $name), 0, 32);
    }
}
