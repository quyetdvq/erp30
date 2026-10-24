<?php

declare(strict_types=1);

/**
 * M3.4B's only database authority: a freshly generated local schema with an
 * unpredictable name and marker. This class never connects to public hosts,
 * never queries personal data from a source database, and never reuses a
 * pre-existing test schema as the writable target.
 */
class M34BFailure extends RuntimeException {}
final class M34BIncompleteTemplate extends M34BFailure {}

final class M34BSafety
{
    public const SOURCE_DEFAULT = 'erp_test';
    public const PREFIX = 'erp_m34b_';
    public const SUFFIX = '_sandbox';

    public static function identifier(string $name): string
    {
        if (preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $name) !== 1) {
            throw new M34BFailure('Unsafe SQL identifier.');
        }
        return '`' . $name . '`';
    }

    public static function sourceName(?string $value): string
    {
        $name = strtolower(trim($value ?? self::SOURCE_DEFAULT));
        self::identifier($name);
        if (preg_match('/(?:_test|_clone|_sandbox)\z/D', $name) !== 1 || str_starts_with($name, self::PREFIX)) {
            throw new M34BFailure('Schema template must be a local test/clone schema (never erp/production).');
        }
        return $name;
    }

    /**
     * "erp" is NOT a selectable template. The resolver may use it as a
     * metadata-only fallback after the default local test schema is proved
     * incomplete. Never interpret this exception as write authorization.
     */
    public static function metadataSourceName(string $name, bool $trustedFallback = false): string
    {
        if ($trustedFallback && $name === 'erp') {
            return 'erp';
        }
        return self::sourceName($name);
    }

    public static function freshName(string $nonce): string
    {
        if (preg_match('/\A[a-f0-9]{32}\z/D', $nonce) !== 1) {
            throw new M34BFailure('Sandbox nonce must be 16 cryptographically random bytes as hex.');
        }
        return self::PREFIX . substr($nonce, 0, 16) . self::SUFFIX;
    }

    public static function assertSandbox(string $name, string $nonce): void
    {
        if ($name !== self::freshName($nonce) || $name === self::sourceName(self::SOURCE_DEFAULT)) {
            throw new M34BFailure('Writable schema is not the unique M3.4B disposable sandbox.');
        }
    }

    public static function mysqlEndpoint(string $host, int $port): void
    {
        if (! in_array($host, ['127.0.0.1', 'localhost'], true) || $port < 1 || $port > 65535) {
            throw new M34BFailure('MariaDB must be local loopback; external MySQL and sockets are forbidden.');
        }
    }

    public static function assertUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'http'
            || ($parts['host'] ?? null) !== '127.0.0.1' || ! isset($parts['port'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['path']) || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new M34BFailure('Only bare http://127.0.0.1:<port> local test origin is allowed.');
        }
    }

    public static function sanitizedException(Throwable $error): string
    {
        $message = $error->getMessage();
        // Never print a DSN, authentication secret or full SQL containing data.
        if ($error instanceof PDOException) {
            $code = (string) $error->getCode();
            return 'Local MariaDB operation failed (SQLSTATE ' . preg_replace('/[^A-Z0-9]/', '', $code) . ').';
        }
        return $message;
    }
}
