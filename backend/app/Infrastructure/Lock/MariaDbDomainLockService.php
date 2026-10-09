<?php

declare(strict_types=1);

namespace App\Infrastructure\Lock;

use App\Exceptions\ConcurrencyException;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Database\ResultInterface;
use Throwable;

final class MariaDbDomainLockService implements DomainLockService
{
    private const DEFAULT_TIMEOUT_SECONDS = 5;

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;
    private int $configuredTimeoutSeconds;
    private int $configuredRetryCount;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(?ConnectionInterface $db = null, ?int $configuredTimeoutSeconds = null)
    {
        $this->db = $db ?? db_connect();
        $configured = $configuredTimeoutSeconds ?? (int) (env(
            'lock.timeout_seconds',
            getenv('DINKERP_DOMAIN_LOCK_TIMEOUT') ?: self::DEFAULT_TIMEOUT_SECONDS,
        ));
        if ($configured < 0) {
            throw new \InvalidArgumentException('Domain lock timeout must be non-negative.');
        }
        $this->configuredTimeoutSeconds = $configured;

        $retryCount = (int) env('lock.retry_count', 0);
        if ($retryCount < 0 || $retryCount > 1) {
            throw new \InvalidArgumentException('Domain lock retry count must be 0 or 1.');
        }
        $this->configuredRetryCount = $retryCount;
    }

    public function retryCount(): int
    {
        return $this->configuredRetryCount;
    }

    public function acquire(string $domainKey, int $timeoutSeconds = 5): void
    {
        if ($domainKey === '' || $timeoutSeconds < 0) {
            throw new ConcurrencyException('lock.acquire.invalid');
        }
        $timeoutSeconds = $timeoutSeconds === self::DEFAULT_TIMEOUT_SECONDS ? $this->configuredTimeoutSeconds : $timeoutSeconds;

        try {
            $result = $this->db->query(
                'SELECT GET_LOCK(?, ?) AS acquired',
                [$this->namespaced($domainKey), $timeoutSeconds],
            );
        } catch (Throwable $exception) {
            throw new ConcurrencyException('lock.acquire', $exception);
        }
        if ($result === false) {
            throw new ConcurrencyException('lock.acquire');
        }

        if (! $result instanceof ResultInterface) {
            throw new ConcurrencyException('lock.acquire.result');
        }
        $row = $result->getRowArray();
        if ((int) ($row['acquired'] ?? 0) !== 1) {
            throw new ConcurrencyException('lock.acquire.timeout');
        }
    }

    public function release(string $domainKey): void
    {
        try {
            $result = $this->db->query('SELECT RELEASE_LOCK(?) AS released', [$this->namespaced($domainKey)]);
        } catch (Throwable $exception) {
            throw new ConcurrencyException('lock.release', $exception);
        }
        if ($result === false || ! $result instanceof ResultInterface) {
            throw new ConcurrencyException('lock.release');
        }
        $row = $result->getRowArray();
        if (($row['released'] ?? null) !== 1 && ($row['released'] ?? null) !== '1') {
            throw new ConcurrencyException('lock.release');
        }
    }

    public function synchronized(string $domainKey, callable $callback, int $timeoutSeconds = 5): mixed
    {
        $this->acquire($domainKey, $timeoutSeconds);
        $result = null;
        $callbackException = null;
        try {
            $result = $callback();
        } catch (Throwable $exception) {
            $callbackException = $exception;
        }
        try {
            $this->release($domainKey);
        } catch (Throwable $releaseException) {
            if ($callbackException === null) {
                throw $releaseException;
            }
        }
        if ($callbackException !== null) {
            throw $callbackException;
        }

        return $result;
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
