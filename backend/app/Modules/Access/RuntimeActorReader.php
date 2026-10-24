<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Modules\People\PeopleReadRepository;
use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use UnexpectedValueException;

/**
 * Request-scoped, read-only authoritative actor snapshot for M3.2.
 * Shield is the source of identity and memberships; ERP groups must be ACTIVE.
 * No user/group/assignment hints are accepted from an HTTP request.
 */
final class RuntimeActorReader
{
    public function __construct(private readonly ?BaseConnection $connection = null)
    {
    }

    /**
     * @return array{user_id:string,permissions:array<string,true>,group_ids:list<string>,employee_active:bool,effective_assignments:list<array<string,mixed>>,evaluation_utc:string}
     */
    public function snapshot(User $user, ?DateTimeImmutable $instant = null): array
    {
        $db = $this->connection ?? db_connect();
        $now = ($instant ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
        $userId = PositiveId::value((string) $user->id);
        $permissionKeys = (new BasePermissionReader($db))->forUser($user);
        $scope = (new ScopeFactsReader(new PeopleReadRepository($db)))->employeeFactsAt($userId, $now);
        $membershipTable = config('Auth')->tables['groups_users'];
        $result = $db->table($membershipTable)->select('group')->where('user_id', $userId)->get();
        if ($result === false) {
            throw new UnexpectedValueException('Shield group membership query failed.');
        }
        $codes = [];
        foreach ($result->getResultArray() as $member) {
            $code = strtolower(trim((string) ($member['group'] ?? '')));
            if ($code === '') {
                throw new UnexpectedValueException('Invalid Shield group membership.');
            }
            $codes[$code] = true;
        }
        $ids = [];
        if ($codes !== []) {
            $result = $db->table('groups')->select('id, code')
                ->where('status', 'ACTIVE')->orderBy('id', 'ASC')->get();
            if ($result === false) {
                throw new UnexpectedValueException('ERP authorization group query failed.');
            }
            foreach ($result->getResultArray() as $group) {
                $code = strtolower(trim((string) ($group['code'] ?? '')));
                if ($code === '') {
                    throw new UnexpectedValueException('Invalid ERP group code.');
                }
                if (isset($codes[$code])) {
                    $ids[] = PositiveId::value((string) $group['id']);
                }
            }
        }
        return [
            'user_id' => $userId,
            'permissions' => array_fill_keys($permissionKeys, true),
            'group_ids' => array_values(array_unique($ids)),
            'employee_active' => ($scope['employee']['status'] ?? null) === 'ACTIVE',
            'effective_assignments' => $scope['effective_assignments'],
            'evaluation_utc' => $now->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
