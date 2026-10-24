<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Bootstrap\AccessReadAdapters;
use App\Modules\Access\Ports\OrganizationFactsPort;
use App\Modules\Access\Ports\PeopleFactsPort;
use App\Shared\Read\PositiveId;
use App\Shared\Read\UtcBusinessDate;
use DateTimeImmutable;

/**
 * Sole narrow bridge for PBAC M3 to read employment and organizational facts.
 * NOT a decision engine. Never turns a snapshot into an allow/deny result.
 * Caller supplies one captured operation time; no implicit clock drift.
 */
final class ScopeFactsReader
{
    public function __construct(
        private readonly ?PeopleFactsPort $people = null,
        private readonly ?OrganizationFactsPort $organizations = null,
    ) {
    }

    private function people(): PeopleFactsPort
    {
        return $this->people ?? AccessReadAdapters::people();
    }

    private function organizations(): OrganizationFactsPort
    {
        return $this->organizations ?? AccessReadAdapters::organizations();
    }

    /**
     * @return array{employee:array<string,mixed>|null,effective_assignments:list<array<string,mixed>>,utc_business_date:string}
     */
    public function employeeFactsAt(int|string $userId, DateTimeImmutable $instant): array
    {
        $date = UtcBusinessDate::at($instant);
        $employee = $this->people()->employeeForUser(PositiveId::value($userId));
        $assignments = $employee === null ? [] : $this->people()->effectiveAssignments(
            PositiveId::value((string) $employee['id']),
            $date,
        );
        return [
            'employee' => $employee,
            'effective_assignments' => $assignments,
            'utc_business_date' => $date,
        ];
    }

    /** @return list<string> Root first, requested organization last. */
    public function organizationAncestorIds(int|string $organizationId): array
    {
        $ids = [];
        foreach ($this->organizations()->ancestorChain($organizationId) as $row) {
            $ids[] = PositiveId::value((string) $row['id']);
        }
        return $ids;
    }
}
