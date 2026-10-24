<?php

declare(strict_types=1);

namespace App\Modules\People\Mutation;

use App\Exceptions\DomainException;
use App\Shared\Read\PositiveId;
use DateTimeImmutable;
use InvalidArgumentException;

/** Pure API-020 Employee and Assignment validation. No trust in client audit or scope hints. */
final class PeopleMutationRules
{
    private const EMPLOYEE = ['user_id', 'employee_code', 'employee_name', 'gender', 'date_of_birth',
        'place_of_origin', 'hire_date', 'manager_employee_id', 'description', 'status'];
    private const ASSIGNMENT = ['employee_id', 'organization_id', 'department_id', 'position_id',
        'is_primary', 'effective_from', 'effective_to', 'description', 'status'];
    private const AUDIT = ['created_by', 'updated_by', 'created_at', 'updated_at'];

    public static function id(mixed $value, string $field = 'id', string $code = 'EMP_ASSIGNMENT_REFERENCE_INVALID'): string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new DomainException($code, ['field'=>$field]);
        }
        try {
            return PositiveId::value($value);
        } catch (InvalidArgumentException $e) {
            throw new DomainException($code, ['field'=>$field], $e);
        }
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function employee(array $input, bool $create): array
    {
        $out = [];
        foreach ($input as $field=>$value) {
            if (in_array($field, self::AUDIT, true)) continue;
            if (!in_array($field, self::EMPLOYEE, true)) {
                throw new DomainException('EMP_FIELD_UNSUPPORTED', ['field'=>(string)$field]);
            }
            if (in_array($field, ['user_id', 'manager_employee_id'], true)) {
                $out[$field] = $value === null ? null : self::id($value, $field,
                    $field === 'user_id' ? 'EMPLOYEE_USER_DUPLICATE' : 'EMP_MANAGER_REFERENCE_INVALID');
            } elseif (in_array($field, ['employee_code','employee_name'], true)) {
                if (!is_string($value) || trim($value) === '') {
                    throw new DomainException('EMP_FIELD_REQUIRED', ['field'=>$field]);
                }
                $out[$field] = trim($value);
            } elseif ($field === 'gender') {
                if ($value !== null && (!is_string($value) || !in_array($value, ['MALE','FEMALE','OTHER'], true))) {
                    throw new DomainException('EMPLOYEE_GENDER_INVALID', ['field'=>$field]);
                }
                $out[$field] = $value;
            } elseif (in_array($field, ['date_of_birth','hire_date'], true)) {
                $out[$field] = self::date($value, $field, 'EMP_FIELD_UNSUPPORTED');
            } elseif ($field === 'status') {
                $out[$field] = self::status($value, 'EMPLOYEE_STATUS_INVALID');
            } else {
                if ($value !== null && !is_string($value)) {
                    throw new DomainException('EMP_FIELD_UNSUPPORTED', ['field'=>$field]);
                }
                $out[$field] = $value;
            }
        }
        if ($create) {
            foreach (['employee_code','employee_name'] as $field) {
                if (!isset($out[$field])) throw new DomainException('EMP_FIELD_REQUIRED', ['field'=>$field]);
            }
        } elseif ($out === []) {
            throw new DomainException('EMP_UPDATE_EMPTY');
        }
        return $out;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function assignment(array $input, bool $create): array
    {
        $out = [];
        foreach ($input as $field=>$value) {
            if (in_array($field, self::AUDIT, true)) continue;
            if (!in_array($field, self::ASSIGNMENT, true)) {
                throw new DomainException('EMP_ASSIGNMENT_FIELD_UNSUPPORTED', ['field'=>(string)$field]);
            }
            if (in_array($field, ['employee_id','organization_id','department_id','position_id'], true)) {
                if ($value === null && in_array($field, ['employee_id','organization_id'], true)) {
                    throw new DomainException('EMP_ASSIGNMENT_REFERENCE_INVALID', ['field'=>$field]);
                }
                $out[$field] = $value === null ? null : self::id($value, $field);
            } elseif ($field === 'is_primary') {
                $out[$field] = self::boolean($value);
            } elseif (in_array($field, ['effective_from','effective_to'], true)) {
                $out[$field] = self::date($value, $field, 'EMP_ASSIGNMENT_DATE_INVALID');
            } elseif ($field === 'status') {
                $status = self::status($value, 'EMP_ASSIGNMENT_REFERENCE_INVALID');
                if (!in_array($status, ['ACTIVE','INACTIVE'], true)) {
                    throw new DomainException('EMP_ASSIGNMENT_REFERENCE_INVALID', ['field'=>'status']);
                }
                $out[$field] = $status;
            } else {
                if ($value !== null && !is_string($value)) {
                    throw new DomainException('EMP_ASSIGNMENT_FIELD_UNSUPPORTED', ['field'=>$field]);
                }
                $out[$field] = $value;
            }
        }
        if ($create) {
            foreach (['employee_id','organization_id','effective_from'] as $field) {
                if (!isset($out[$field])) {
                    throw new DomainException($field === 'effective_from' ? 'EMP_ASSIGNMENT_DATE_INVALID' : 'EMP_ASSIGNMENT_REFERENCE_INVALID',
                        ['field'=>$field]);
                }
            }
            $out['is_primary'] ??= 0;
            $out['status'] ??= 'ACTIVE';
        } elseif ($out === []) {
            throw new DomainException('EMP_ASSIGNMENT_UPDATE_EMPTY');
        }
        return $out;
    }

    /** @param array<string,mixed> $candidate */
    public static function assignmentPeriod(array $candidate): void
    {
        $from = self::date($candidate['effective_from'] ?? null, 'effective_from', 'EMP_ASSIGNMENT_DATE_INVALID');
        $to = self::date($candidate['effective_to'] ?? null, 'effective_to', 'EMP_ASSIGNMENT_DATE_INVALID');
        if ($from === null || ($to !== null && $to < $from)) {
            throw new DomainException('EMP_ASSIGNMENT_DATE_INVALID', ['field'=>$from === null ? 'effective_from' : 'effective_to']);
        }
    }

    /** @param array<string,mixed> $left @param array<string,mixed> $right */
    public static function overlaps(array $left, array $right): bool
    {
        return ($left['status'] ?? '') === 'ACTIVE' && (int)($left['is_primary'] ?? 0) === 1
            && ($right['status'] ?? '') === 'ACTIVE' && (int)($right['is_primary'] ?? 0) === 1
            && (string)$left['effective_from'] <= (string)($right['effective_to'] ?? '9999-12-31')
            && (string)$right['effective_from'] <= (string)($left['effective_to'] ?? '9999-12-31');
    }

    /** @param list<array<string,mixed>> $rows */
    public static function assertManagerChain(array $rows, ?string $employeeId, ?string $managerId): void
    {
        if ($managerId === null) return;
        if ($employeeId !== null && $managerId === $employeeId) {
            throw new DomainException('EMP_MANAGER_SELF');
        }
        $byId = [];
        foreach ($rows as $row) $byId[(string)$row['id']] = $row;
        $seen=[];
        $cursor=$managerId;
        while ($cursor !== null) {
            if (isset($seen[$cursor])) throw new DomainException('EMP_MANAGER_CYCLE');
            $seen[$cursor] = true;
            if ($employeeId !== null && $cursor === $employeeId) {
                throw new DomainException('EMP_MANAGER_CYCLE');
            }
            $row=$byId[$cursor] ?? null;
            if ($row === null) throw new DomainException('EMP_MANAGER_REFERENCE_INVALID');
            if ($cursor === $managerId && ($row['status'] ?? null) !== 'ACTIVE') {
                throw new DomainException('EMP_MANAGER_INACTIVE');
            }
            $cursor=$row['manager_employee_id'] === null ? null : (string)$row['manager_employee_id'];
        }
    }

    private static function date(mixed $value, string $field, string $code): ?string
    {
        if ($value === null) return null;
        if (!is_string($value)) throw new DomainException($code, ['field'=>$field]);
        $date=DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors=DateTimeImmutable::getLastErrors();
        if ($date === false || $date->format('Y-m-d') !== $value
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new DomainException($code, ['field'=>$field]);
        }
        return $value;
    }

    private static function status(mixed $value, string $code): string
    {
        $value=is_string($value) ? strtoupper(trim($value)) : '';
        if (preg_match('/\A[A-Z][A-Z0-9_:-]{0,49}\z/D',$value)!==1) {
            throw new DomainException($code, ['field'=>'status']);
        }
        return $value;
    }

    private static function boolean(mixed $value): int
    {
        if (in_array($value, [true, 1, '1'], true)) return 1;
        if (in_array($value, [false, 0, '0'], true)) return 0;
        throw new DomainException('EMP_ASSIGNMENT_REFERENCE_INVALID', ['field'=>'is_primary']);
    }
}
