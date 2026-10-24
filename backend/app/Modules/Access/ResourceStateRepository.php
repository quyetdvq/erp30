<?php

declare(strict_types=1);

namespace App\Modules\Access;

use CodeIgniter\Database\BaseConnection;
use UnexpectedValueException;

/**
 * Indexed DB adapter for the canonical M3 Resource State rules. Cached only
 * during this short-lived instance; never cache across requests or writes.
 */
final class ResourceStateRepository
{
    private BaseConnection $db;
    /** @var array<string,array<string,mixed>> */
    private array $definitions = [];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return array<string,mixed> */
    public function definitionFor(string $resourceType): array
    {
        $type = ResourceStateCatalog::normalizedType($resourceType);
        if (isset($this->definitions[$type])) {
            return $this->definitions[$type];
        }
        $result = $this->db->table('resources')
            ->select('id, type, status, lifecycle_id')
            ->where('type', $type)->get();
        $row = $this->first($result, 'Resource ' . $type);
        if ($row === null) {
            throw new UnexpectedValueException('Unknown Resource: ' . $type);
        }
        return $this->definitions[$type] = $this->buildDefinition($row);
    }

    /** @param array<string,mixed> $resource @return array<string,mixed> */
    public function buildDefinition(array $resource): array
    {
        $type = ResourceStateCatalog::normalizedType((string) ($resource['type'] ?? ''));
        $mode = ResourceStateCatalog::mode($type);
        ResourceStateCatalog::assertBinding($type, $resource['lifecycle_id'] ?? null);
        if ($mode === ResourceStateCatalog::ALL_STATES) {
            return ResourceStateValidator::definition($resource, null, []);
        }
        if ($mode === ResourceStateCatalog::NATIVE) {
            $query = $this->db->table('workflow_states')
                ->select('id, code, status')
                ->whereIn('code', ResourceStateCatalog::nativeCodes($type));
            if ($type !== 'workflow_state') {
                $query->where('status', 'ACTIVE');
            }
            return ResourceStateValidator::definition($resource, null, $this->many($query->get(), 'native States: ' . $type));
        }
        $lifecycleId = $resource['lifecycle_id'];
        $lifecycleResult = $this->db->table('lifecycles')
            ->select('id, status')->where('id', $lifecycleId)->get();
        $lifecycle = $this->first($lifecycleResult, 'Lifecycle: ' . $type);
        $members = $this->many($this->db->table('lifecycle_states ls')
            ->select('ws.id AS id, ws.code AS code, ws.status AS status, ls.is_initial AS is_initial')
            ->join('workflow_states ws', 'ws.id = ls.state_id')
            ->where('ls.lifecycle_id', $lifecycleId)
            ->orderBy('ls.sequence', 'ASC')->get(), 'Lifecycle States: ' . $type);
        return ResourceStateValidator::definition($resource, $lifecycle, $members);
    }

    /**
     * For PBAC use ONLY with a persisted row fetched by a trusted repository.
     * @param array<string,mixed> $persistedRow
     */
    public function persistedStateId(string $type, array $persistedRow, mixed $clientHint = null): ?string
    {
        $definition = $this->definitionFor($type);
        $id = ResourceStateValidator::persistedStateId($definition, $persistedRow);
        ResourceStateValidator::assertHint($definition, $id, $clientHint);
        return $id;
    }

    /** Null is All States. Does NOT assert that the policy is ACTIVE. */
    public function assertPolicyState(string $type, mixed $stateId): void
    {
        ResourceStateValidator::assertPolicyState($this->definitionFor($type), $stateId);
    }

    /**
     * For admin State selection only. Does not imply authorization to read
     * or mutate those resources.
     * @return list<array{state_id:?string,code:string}>
     */
    public function policyStateOptions(string $type): array
    {
        $definition = $this->definitionFor($type);
        $result = [['state_id' => null, 'code' => 'ALL_STATES']];
        foreach ($definition['by_code'] as $code => $id) {
            $result[] = ['state_id' => $id, 'code' => $code];
        }
        return $result;
    }

    /** @param array<string,mixed> $persistedRow @param array<string,mixed> $input */
    public function assertDirectEdit(string $type, array $persistedRow, array $input): void
    {
        ResourceStateValidator::assertDirectEdit($this->definitionFor($type), $persistedRow, $input);
    }

    public function initialState(string $type, mixed $requested = null): ?string
    {
        return ResourceStateValidator::initialState($this->definitionFor($type), $requested);
    }

    /**
     * Prepare a Transition target; an authorized write use-case MUST check
     * PBAC and perform the update+audit atomically. Does not modify DB.
     * @param array<string,mixed> $persistedRow
     */
    public function transitionTarget(string $type, array $persistedRow, string $action): string
    {
        $definition = $this->definitionFor($type);
        if ($definition['mode'] !== ResourceStateCatalog::LIFECYCLE) {
            throw new UnexpectedValueException('Native and All States Resources do not use Lifecycle Transitions.');
        }
        $rows = $this->many($this->db->table('lifecycle_transitions')
            ->select('lifecycle_id, from_state_id, to_state_id, action_code, status')
            ->where('lifecycle_id', $definition['lifecycle_id'])
            ->where('status', 'ACTIVE')->get(), 'Lifecycle Transitions: ' . $type);
        return ResourceStateValidator::transitionTarget($definition, $persistedRow, $rows, $action);
    }

    /**
     * Refuse to orphan any specific-State policy, including INACTIVE policies.
     * Perform a fresh catalog fetch in the transaction when actually writing.
     * @param array<string,mixed> $nextResourceWithLifecycle
     */
    public function assertReassignmentSafe(string $type, array $nextResourceWithLifecycle): void
    {
        $current = $this->definitionFor($type);
        $nextResourceWithLifecycle['type'] = $type;
        $next = $this->buildDefinition($nextResourceWithLifecycle);
        if ($current['lifecycle_id'] === $next['lifecycle_id']) {
            return;
        }
        $resourceQuery = $this->db->table('resources')->select('id')->where('type', $type)->get();
        $resource = $this->first($resourceQuery, 'Resource ID');
        if ($resource === null) {
            throw new UnexpectedValueException('Unknown Resource: ' . $type);
        }
        $policies = $this->many($this->db->table('policies')
            ->select('id, state_id, status')
            ->where('resource_id', $resource['id'])->get(), 'Policies for Lifecycle reassignment');
        ResourceStateValidator::assertReassignmentSafe($next, $policies);
    }

    /** @return list<array<string,mixed>> */
    private function many(mixed $result, string $context): array
    {
        if ($result === false) {
            $error = $this->db->error();
            throw new UnexpectedValueException($context . ' query failed: ' . ($error['message'] ?? 'unknown SQL error'));
        }
        return $result->getResultArray();
    }

    /** @return array<string,mixed>|null */
    private function first(mixed $result, string $context): ?array
    {
        if ($result === false) {
            $error = $this->db->error();
            throw new UnexpectedValueException($context . ' query failed: ' . ($error['message'] ?? 'unknown SQL error'));
        }
        return $result->getRowArray();
    }
}
