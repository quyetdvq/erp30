<?php

declare(strict_types=1);

/**
 * Pure diagnostic formatter. Never emits raw exception messages, SQL,
 * connection strings, secrets, paths, stack traces or fixture values.
 */
final class ScratchNumberingPilotFailure
{
    public static function describe(Throwable $error, string $stage): string
    {
        $stage = preg_match('/\A[a-z][a-z0-9_]{0,47}\z/D', $stage) === 1 ? $stage : 'unknown';
        $summary = 'stage=' . $stage . '; type=' . self::safeType($error)
            . '; origin=' . self::safeOrigin($error);
        if ($error instanceof \App\Exceptions\DomainException) {
            $code = $error->getErrorCode();
            if (is_string($code) && preg_match('/\A[A-Z][A-Z0-9_]{1,79}\z/D', $code) === 1) {
                $summary .= '; domain=' . $code;
            }
        }
        $previous = $error->getPrevious();
        if ($previous !== null) {
            $summary .= '; cause_type=' . self::safeType($previous)
                . '; cause_origin=' . self::safeOrigin($previous);
        }
        return $summary;
    }

    private static function safeType(Throwable $error): string
    {
        $parts = explode('\\', get_class($error));
        $type = end($parts);
        return is_string($type) && preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,63}\z/D', $type) === 1
            ? $type : 'Throwable';
    }

    private static function safeOrigin(Throwable $error): string
    {
        $source = basename(str_replace('\\', '/', $error->getFile()));
        $source = preg_match('/\A[A-Za-z0-9_.-]{1,80}\z/D', $source) === 1
            ? $source : 'unknown';
        return $source . ':' . max(0, $error->getLine());
    }
}
