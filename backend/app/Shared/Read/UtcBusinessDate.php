<?php

declare(strict_types=1);

namespace App\Shared\Read;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Match v1.2.4 EmployeeAssignmentService: evaluate effective assignments on a UTC date. */
final class UtcBusinessDate
{
    public static function at(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    }

    public static function validate(string $date): string
    {
        if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $date) !== 1) {
            throw new InvalidArgumentException('Expected a YYYY-MM-DD UTC business date.');
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Invalid UTC business date.');
        }
        return $date;
    }

    /** Inclusive date boundaries, independent of the timezone of the PHP process. */
    public static function effective(array $assignment, string $date): bool
    {
        self::validate($date);
        $from = (string) ($assignment['effective_from'] ?? '');
        $to = $assignment['effective_to'] ?? null;
        if ($from !== '') {
            self::validate($from);
        } else {
            return false;
        }
        if ($to !== null) {
            self::validate((string) $to);
        }
        return ($assignment['status'] ?? null) === 'ACTIVE'
            && $from <= $date
            && ($to === null || (string) $to >= $date);
    }
}
