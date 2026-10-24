<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use UnexpectedValueException;

/**
 * Canonical action and applicable-policy SQL read for a short-lived batch.
 * The caller supplies a validated, trusted organization ancestor chain and
 * State ID (including null for All States). No application-level writes.
 */
final class RuntimePolicyReader
{
    /** @var array<string,array<string,mixed>> */
    private array $resources = [];
    /** @var array<string,list<array<string,mixed>>> */
    private array $policyRows = [];

    public function __construct(private readonly ?BaseConnection $connection = null)
    {
    }

    private function db(): BaseConnection
    {
        return $this->connection ?? db_connect();
    }

    /** @return array{id:string,type:string,status:string,domain:string,lifecycle_id:?string,action_id:string} */
    public function resourceAction(string $type, string $action): array
    {
        $type = ResourceStateCatalog::normalizedType($type);
        $action = self::actionCode($action);
        $key = $type . '.' . $action;
        if (isset($this->resources[$key])) {
            return $this->resources[$key];
        }
        $result = $this->db()->table('resources')->select('id, type, status, domain, lifecycle_id')
            ->where('type', $type)->get();
        if ($result === false) {
            throw new UnexpectedValueException('Resource catalog SQL failed.');
        }
        $row = $result->getRowArray();
        if ($row === null) {
            throw new UnexpectedValueException('Unknown Resource: ' . $type);
        }
        if (! in_array(($row['domain'] ?? null), ['SYSTEM', 'MASTER', 'ORGANIZATION'], true)) {
            throw new UnexpectedValueException('Invalid Resource domain: ' . $type);
        }
        $result = $this->db()->table('resource_actions')->select('id, resource_id, code')
            ->where('resource_id', $row['id'])->where('code', $action)->get();
        if ($result === false) {
            throw new UnexpectedValueException('Resource Action SQL failed.');
        }
        $actionRow = $result->getRowArray();
        if ($actionRow === null || (string) $actionRow['resource_id'] !== (string) $row['id']) {
            throw new UnexpectedValueException('Unregistered Resource Action: ' . $key);
        }
        $value = [
            'id' => PositiveId::value((string) $row['id']),
            'type' => $type,
            'status' => (string) $row['status'],
            'domain' => (string) $row['domain'],
            'lifecycle_id' => $row['lifecycle_id'] === null ? null : PositiveId::value((string) $row['lifecycle_id']),
            'action_id' => PositiveId::value((string) $actionRow['id']),
        ];
        return $this->resources[$key] = $value;
    }

    /**
     * Lifecycle action codes may contain ':' or '-' under the frozen baseline
     * OpenAPI. Only already-registered resource_actions can authorize them.
     */
    public static function actionCode(string $action): string
    {
        $action = strtolower(trim($action));
        if (preg_match('/\A[a-z][a-z0-9_:-]{0,49}\z/D', $action) !== 1) {
            throw new UnexpectedValueException('Invalid Resource Action.');
        }
        return $action;
    }

    /**
     * @param list<string> $ancestorIds Root-first, trusted organization IDs.
     * @return list<array{id:string,participant_type:string,participant_ref_id:string,effect:string}>
     */
    public function applicable(array $resource, array $ancestorIds, ?string $stateId): array
    {
        if ($ancestorIds === []) {
            throw new UnexpectedValueException('Empty organization scope.');
        }
        $ancestors = array_map(static fn ($id): string => PositiveId::value((string) $id), $ancestorIds);
        $key = implode(':', [(string) $resource['id'], (string) $resource['action_id'], $stateId ?? 'ALL', implode(',', $ancestors)]);
        if (isset($this->policyRows[$key])) {
            return $this->policyRows[$key];
        }
        $builder = $this->db()->table('policies p')
            ->select('p.id, p.participant_type, p.participant_ref_id, pa.effect')
            ->join('policy_actions pa', 'pa.policy_id = p.id')
            ->where('p.resource_id', $resource['id'])
            ->where("BINARY p.status = 'ACTIVE'", null, false)
            ->whereIn('p.organization_id', $ancestors)
            ->where('pa.resource_action_id', $resource['action_id']);
        if ($stateId === null) {
            $builder->where('p.state_id', null);
        } else {
            $builder->groupStart()->where('p.state_id', null)
                ->orWhere('p.state_id', PositiveId::value($stateId))->groupEnd();
        }
        $result = $builder->orderBy('p.id', 'ASC')->get();
        if ($result === false) {
            throw new UnexpectedValueException('Applicable policies SQL failed.');
        }
        $rows = [];
        foreach ($result->getResultArray() as $row) {
            $effect = strtoupper(trim((string) ($row['effect'] ?? '')));
            $participant = strtoupper(trim((string) ($row['participant_type'] ?? '')));
            if (! in_array($effect, ['ALLOW', 'DENY', 'ABSOLUTE_DENY'], true)
                || ! in_array($participant, ['USER','GROUP','ORGANIZATION','DEPARTMENT','POSITION'], true)) {
                throw new UnexpectedValueException('Invalid applicable PBAC policy.');
            }
            $rows[] = [
                'id' => PositiveId::value((string) $row['id']),
                'participant_type' => $participant,
                'participant_ref_id' => PositiveId::value((string) $row['participant_ref_id']),
                'effect' => $effect,
            ];
        }
        return $this->policyRows[$key] = $rows;
    }
}
