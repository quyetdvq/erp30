<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Bootstrap\AccessReadAdapters;
use App\Shared\Read\PositiveId;
use App\Shared\Read\UtcBusinessDate;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use UnexpectedValueException;

/**
 * Restricted VIEW-only PBAC guard used by M2 read HTTP endpoints.
 * It never accepts actor, scope, or workflow state from the client.
 * It is intentionally *not* a general mutation authorization API.
 */
final class ReadViewGate
{
    private const DOMAINS = [
        'province' => 'MASTER', 'ward' => 'MASTER', 'organization' => 'ORGANIZATION',
        'department' => 'MASTER', 'position' => 'MASTER', 'employee' => 'MASTER',
        'employee_assignment' => 'ORGANIZATION',
    ];
    private const NATIVE_STATUSES = [
        'province' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'ward' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'organization' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'department' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'position' => ['ACTIVE', 'INACTIVE', 'CLOSED'],
        'employee' => ['ACTIVE', 'INACTIVE'],
        'employee_assignment' => ['ACTIVE', 'INACTIVE'],
    ];

    private BaseConnection $db;
    private string $userId;
    /** @var array<string,true> */
    private array $permissions = [];
    /** @var list<string> */
    private array $groupIds = [];
    /** @var list<array<string,mixed>> */
    private array $assignments = [];
    private bool $eligible = false;
    /** @var array<string,array<string,mixed>|null> */
    private array $resources = [];
    /** @var array<string,string|null> */
    private array $groupRoot = [];
    /** @var array<string,list<string>> */
    private array $ancestors = [];
    /** @var array<string,string> */
    private array $states = [];
    /** @var array<string,list<array{participant_type:string,participant_ref_id:string,effect:string}>> */
    private array $policyRows = [];

    public function __construct(User $user, ?BaseConnection $db = null, ?DateTimeImmutable $instant = null)
    {
        $this->db = $db ?? db_connect();
        $this->userId = PositiveId::value((string) $user->id);
        $this->permissions = array_fill_keys((new BasePermissionReader($this->db))->forUser($user), true);
        $now = $instant ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $facts = (new ScopeFactsReader())->employeeFactsAt($this->userId, $now);
        $this->eligible = ($facts['employee']['status'] ?? null) === 'ACTIVE'
            && $facts['effective_assignments'] !== [];
        $this->assignments = $facts['effective_assignments'];
        $shieldGroups = config('Auth')->tables['groups_users'];
        $memberships = $this->db->table($shieldGroups)->select('group')
            ->where('user_id', $this->userId)->get()->getResultArray();
        $membershipCodes = [];
        foreach ($memberships as $member) {
            $code = strtolower(trim((string) ($member['group'] ?? '')));
            if ($code === '') {
                throw new UnexpectedValueException('Invalid group membership.');
            }
            $membershipCodes[$code] = true;
        }
        if ($membershipCodes !== []) {
            foreach ($this->db->table('groups')->select('id, code, status')
                ->where('status', 'ACTIVE')->get()->getResultArray() as $row) {
                if (isset($membershipCodes[strtolower(trim((string) $row['code']))])) {
                    $this->groupIds[] = PositiveId::value((string) $row['id']);
                }
            }
        }
    }

    /** @param array<string,mixed> $persistedRow */
    public function canView(string $type, array $persistedRow): bool
    {
        if (! array_key_exists($type, self::DOMAINS)) {
            throw new \InvalidArgumentException('Unsupported VIEW resource.');
        }
        if (! $this->eligible || ! isset($this->permissions[$type . '.view'])) {
            return false;
        }
        $catalog = $this->resource($type);
        if ($catalog === null) {
            return false;
        }
        if (($catalog['status'] ?? null) !== 'ACTIVE' || ($catalog['domain'] ?? null) !== self::DOMAINS[$type]) {
            return false;
        }
        $stateId = $this->stateId($type, $persistedRow, $catalog);
        $scopeId = $this->scopeId($type, $persistedRow);
        $ancestorIds = $this->ancestors($scopeId);
        $applicable = $this->applicablePolicies($catalog, $ancestorIds, $stateId);
        $scopedAssignments = array_values(array_filter($this->assignments, static fn (array $row): bool =>
            isset($row['organization_id']) && in_array((string) $row['organization_id'], $ancestorIds, true)));
        if (self::DOMAINS[$type] === 'ORGANIZATION' && $scopedAssignments === []) {
            return false;
        }
        return PolicyReadLanes::allows($applicable, $ancestorIds, $this->userId, $this->groupIds, $scopedAssignments);
    }

    /** @return array<string,mixed>|null */
    private function resource(string $type): ?array
    {
        if (! array_key_exists($type, $this->resources)) {
            $resourceQuery = $this->db->table('resources')
                ->select('id, domain, status, lifecycle_id')
                ->where('type', $type)->get();
            if ($resourceQuery === false) {
                throw new UnexpectedValueException('PBAC resource query failed; check the application database logs.');
            }
            $resource = $resourceQuery->getRowArray();
            if ($resource !== null) {
                $actionQuery = $this->db->table('resource_actions')->select('id')
                    ->where('resource_id', $resource['id'])->where('code', 'view')->get();
                if ($actionQuery === false) {
                    throw new UnexpectedValueException('PBAC VIEW action query failed; check the application database logs.');
                }
                $action = $actionQuery->getRowArray();
                // A missing VIEW action must not grant access.
                $resource = $action === null ? null : array_merge($resource, ['action_id' => $action['id']]);
            }
            $this->resources[$type] = $resource;
        }
        return $this->resources[$type];
    }

    /** @param array<string,mixed> $row */
    private function scopeId(string $type, array $row): string
    {
        if (self::DOMAINS[$type] === 'MASTER') {
            return $this->groupRoot();
        }
        if ($type === 'organization') {
            return PositiveId::value((string) ($row['id'] ?? ''));
        }
        return PositiveId::value((string) ($row['organization_id'] ?? ''));
    }

    private function groupRoot(): string
    {
        if (! isset($this->groupRoot['root'])) {
            $roots = (AccessReadAdapters::organizations($this->db))->groupRoots();
            if (count($roots) !== 1) {
                throw new UnexpectedValueException('There must be exactly one GROUP root.');
            }
            $this->groupRoot['root'] = PositiveId::value((string) $roots[0]['id']);
        }
        return $this->groupRoot['root'];
    }

    /** @return list<string> */
    private function ancestors(string $scopeId): array
    {
        if (! isset($this->ancestors[$scopeId])) {
            $chain = (AccessReadAdapters::organizations($this->db))->ancestorChain($scopeId);
            $ids = [];
            foreach ($chain as $node) {
                $ids[] = PositiveId::value((string) $node['id']);
            }
            if ($ids === [] || $ids[0] !== $this->groupRoot() || ($chain[0]['type'] ?? null) !== 'GROUP'
                || ($chain[0]['parent_id'] ?? null) !== null
                || ! in_array(($chain[count($chain) - 1]['type'] ?? null), ['GROUP','COMPANY','BRANCH','PLANT','WAREHOUSE'], true)) {
                throw new UnexpectedValueException('Invalid GROUP ancestry.');
            }
            $this->ancestors[$scopeId] = $ids;
        }
        return $this->ancestors[$scopeId];
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $resource */
    private function stateId(string $type, array $row, array $resource): string
    {
        $status = $row['status'] ?? null;
        if (! is_string($status) || trim($status) === '') {
            throw new UnexpectedValueException('Persisted State missing.');
        }
        $status = strtoupper(trim($status));
        $lifecycleId = $resource['lifecycle_id'] ?? null;
        $key = $type . ':' . ($lifecycleId ?? 'native') . ':' . $status;
        if (! isset($this->states[$key])) {
            if ($type === 'employee_assignment' && $lifecycleId !== null) {
                throw new UnexpectedValueException('Native assignment cannot have a Lifecycle.');
            }
            if ($lifecycleId === null) {
                if (! in_array($status, self::NATIVE_STATUSES[$type], true)) {
                    throw new UnexpectedValueException('Unexpected persisted State code.');
                }
                $state = $this->db->table('workflow_states')->select('id')
                    ->where('code', $status)->where('status', 'ACTIVE')->get()->getRowArray();
                if ($state === null) {
                    throw new UnexpectedValueException('Active State code not found.');
                }
            } else {
                $lifecycle = $this->db->table('lifecycles')->select('id, status')
                    ->where('id', $lifecycleId)->get()->getRowArray();
                if ($lifecycle === null || $lifecycle['status'] !== 'ACTIVE') {
                    throw new UnexpectedValueException('Inactive or missing Lifecycle.');
                }
                $members = $this->db->table('lifecycle_states ls')->select('ws.id, ws.code, ws.status')
                    ->join('workflow_states ws', 'ws.id = ls.state_id')
                    ->where('ls.lifecycle_id', $lifecycleId)->get()->getResultArray();
                if ($members === []) {
                    throw new UnexpectedValueException('Lifecycle has no States.');
                }
                $state = null;
                foreach ($members as $member) {
                    if ($member['status'] !== 'ACTIVE') {
                        throw new UnexpectedValueException('Lifecycle references disabled State.');
                    }
                    if (strtoupper((string) $member['code']) === $status) {
                        $state = $member;
                    }
                }
                if ($state === null) {
                    throw new UnexpectedValueException('Persisted State is not in Lifecycle.');
                }
            }
            $this->states[$key] = PositiveId::value((string) $state['id']);
        }
        return $this->states[$key];
    }

    /**
     * @param array<string,mixed> $resource
     * @param list<string> $ancestors
     * @return list<array{participant_type:string,participant_ref_id:string,effect:string}>
     */
    private function applicablePolicies(array $resource, array $ancestors, string $stateId): array
    {
        $key = (string) $resource['id'] . ':' . $resource['action_id'] . ':' . $stateId . ':' . implode(',', $ancestors);
        if (! isset($this->policyRows[$key])) {
            $rows = $this->db->table('policies p')
                ->select('p.participant_type, p.participant_ref_id, pa.effect')
                ->join('policy_actions pa', 'pa.policy_id = p.id')
                ->where('p.resource_id', $resource['id'])
                ->where("BINARY p.status = 'ACTIVE'", null, false)
                ->whereIn('p.organization_id', $ancestors)
                ->where('pa.resource_action_id', $resource['action_id'])
                ->groupStart()->where('p.state_id', null)->orWhere('p.state_id', $stateId)->groupEnd()
                ->orderBy('p.id', 'ASC')->get()->getResultArray();
            $policies = [];
            foreach ($rows as $row) {
                $effect = strtoupper(trim((string) $row['effect']));
                if (! in_array($effect, ['ALLOW', 'DENY', 'ABSOLUTE_DENY'], true)) {
                    throw new UnexpectedValueException('Invalid policy effect.');
                }
                $policies[] = [
                    'participant_type' => (string) $row['participant_type'],
                    'participant_ref_id' => PositiveId::value((string) $row['participant_ref_id']),
                    'effect' => $effect,
                ];
            }
            $this->policyRows[$key] = $policies;
        }
        return $this->policyRows[$key];
    }
}
