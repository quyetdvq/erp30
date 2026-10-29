<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Bootstrap\AccessReadAdapters;
use App\Modules\Access\Ports\OrganizationFactsPort;
use App\Shared\Read\PositiveId;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use UnexpectedValueException;

/**
 * M3.2 single PBAC evaluation entry point for *persisted READ targets*.
 * Identity, State and scope are authoritative DB facts, never HTTP hints.
 * Mutation authorization will be introduced separately with write locks.
 */
final class UnifiedAccessService
{
    /** Existing M2.1.1 read resources have fixed contractual domains. */
    private const APPROVED_READ_DOMAINS = [
        'province' => 'MASTER', 'ward' => 'MASTER', 'organization' => 'ORGANIZATION',
        'department' => 'MASTER', 'position' => 'MASTER', 'employee' => 'MASTER',
        'employee_assignment' => 'ORGANIZATION',
        'organization_contact' => 'ORGANIZATION', 'employee_contact' => 'ORGANIZATION',
        'customer' => 'MASTER', 'customer_contact' => 'MASTER', 'customer_interaction' => 'ORGANIZATION',
        // Pinned M3.3 API-030 domains from baseline ResourceActionSeeder.
        'user' => 'SYSTEM', 'authorization_group' => 'SYSTEM',
        'notification_setting' => 'SYSTEM', 'notification_operations' => 'SYSTEM',
        'resource' => 'SYSTEM', 'workflow_state' => 'SYSTEM',
        'lifecycle' => 'SYSTEM', 'policy' => 'ORGANIZATION',
        'authorization_log' => 'ORGANIZATION',
        // Product imports are scoped to the trusted GROUP root (baseline ImportJobScopeResolver).
        'import_job' => 'ORGANIZATION',
        // M7.3 Pricing lists are owned by a persisted Organization, never MASTER.
        'price_list' => 'ORGANIZATION',
    ];

    /** @var array<string,mixed> */
    private array $actor;
    private RuntimePolicyReader $policies;
    private ResourceStateRepository $states;
    private OrganizationFactsPort $organizations;
    private ?string $groupRoot = null;
    /** @var array<string,list<string>> */
    private array $ancestorIds = [];

    public function __construct(User $user, ?BaseConnection $db = null, ?DateTimeImmutable $instant = null)
    {
        $connection = $db ?? db_connect();
        $this->actor = (new RuntimeActorReader($connection))->snapshot($user, $instant);
        $this->policies = new RuntimePolicyReader($connection);
        $this->states = new ResourceStateRepository($connection);
        $this->organizations = AccessReadAdapters::organizations($connection);
    }

    /**
     * Base VIEW grant from the same persisted ERP/Shield permission snapshot
     * used by decidePersisted(). Shield User::can() alone does not include
     * active ERP group_permissions and can incorrectly return false.
     *
     * This is only a precondition for empty list reads, never a PBAC allow:
     * every fetched row must still pass canView() with State/scope/policies.
     */
    public function hasBaseViewPermission(string $type): bool
    {
        $type = ResourceStateCatalog::normalizedType($type);
        return isset($this->actor['permissions'][$type . '.view']);
    }

    /**
     * Compile the exact VIEW result for every canonical Lifecycle State of a
     * MASTER resource. MASTER uses one trusted GROUP-root scope for all rows;
     * therefore VIEW depends on actor, action, policy lanes and State, but NOT
     * on the Product/UOM primary key. The caller must constrain SQL by the
     * returned canonical State codes before COUNT, OFFSET or LIMIT.
     *
     * Restricted to explicitly reviewed MASTER resources. ORGANIZATION
     * resources require a different scope-aware compiler; never fall back to
     * an unscoped query or approximate ALLOW-policy JOIN.
     *
     * @return list<string> Canonical status codes authorized to view.
     */
    public function viewableMasterLifecycleStatuses(string $type): array
    {
        $type = ResourceStateCatalog::normalizedType($type);
        if (! in_array($type, ['product', 'product_group', 'product_uom', 'employee', 'department', 'position', 'province', 'ward', 'customer', 'customer_contact'], true)) {
            throw new UnexpectedValueException('SQL VIEW scope is not approved for resource: ' . $type);
        }
        $resource = $this->policies->resourceAction($type, 'view');
        if ($resource['domain'] !== 'MASTER') {
            throw new UnexpectedValueException('SQL VIEW scope requires MASTER domain: ' . $type);
        }
        $definition = $this->states->definitionFor($type);
        if ($definition['mode'] !== ResourceStateCatalog::LIFECYCLE
            || $definition['by_code'] === []) {
            throw new UnexpectedValueException('SQL VIEW scope requires canonical Lifecycle states: ' . $type);
        }
        $ancestors = $this->ancestors($this->groupRoot());
        $allowed = [];
        foreach ($definition['by_code'] as $status => $stateId) {
            if ($this->viewDecisionForState($type, $resource, $ancestors, $stateId)['allowed']) {
                $allowed[] = $status;
            }
        }
        return $allowed;
    }

    /**
     * Trusted ORGANIZATION slot compilation for reviewed org-scoped resources.
     * Organization IDs must come from the persisted Organization catalog,
     * never from client IDs or the business rows being returned. Each (org, State)
     * decision uses the SAME ancestry and PBAC lanes as persisted canView().
     *
     * @param list<string> $trustedOrganizationIds
     * @return list<array{organization_id:string,status:string}>
     */
    public function viewableOrganizationStateBuckets(string $type, array $trustedOrganizationIds): array
    {
        $type = ResourceStateCatalog::normalizedType($type);
        if (!in_array($type, ['price_list', 'organization', 'employee_assignment'], true)
            && !in_array($type, ['organization_contact', 'employee_contact'], true)) {
            throw new UnexpectedValueException('Unsupported ORGANIZATION SQL scope: ' . $type);
        }
        $resource = $this->policies->resourceAction($type, 'view');
        if ($resource['domain'] !== 'ORGANIZATION') {
            throw new UnexpectedValueException('Reviewed SQL scope requires ORGANIZATION domain.');
        }
        $definition = $this->states->definitionFor($type);
        if ($definition['mode'] !== ($type === 'employee_assignment'
            ? ResourceStateCatalog::NATIVE : ResourceStateCatalog::LIFECYCLE)
            || $definition['by_code'] === []) {
            throw new UnexpectedValueException('Reviewed Organization resource has no canonical States.');
        }
        $slots = [];
        $seen = [];
        foreach ($trustedOrganizationIds as $rawId) {
            $id = PositiveId::value($rawId);
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $ancestors = $this->ancestors($id);
            foreach ($definition['by_code'] as $status => $stateId) {
                if ($this->viewDecisionForState($type, $resource, $ancestors, $stateId)['allowed']) {
                    $slots[] = ['organization_id'=>$id, 'status'=>$status];
                    if (count($slots) > 16384) {
                        throw new UnexpectedValueException('AUTH_SCOPE_COMPILATION_UNSUPPORTED: too many organization/State slots.');
                    }
                }
            }
        }
        return $slots;
    }

    /**
     * The Product import action has no persisted target/State; unlike create or
     * edit it cannot use decideMutation(), which requires a locked object.
     * Authorization still applies base permission, active Resource, effective
     * assignments, GROUP scope and all policy lanes including ABSOLUTE_DENY.
     */
    public function canStartProductImport(): bool
    {
        $resource = $this->policies->resourceAction('product', 'import');
        if ($resource['domain'] !== 'MASTER') {
            throw new UnexpectedValueException('Product import requires MASTER domain.');
        }
        $ancestors = $this->ancestors($this->groupRoot());
        $base = isset($this->actor['permissions']['product.import']);
        $pre = AccessDecisionCore::decide(
            $resource['status'] === 'ACTIVE', $base, $this->actor['employee_active'],
            $this->actor['effective_assignments'], 'MASTER', $ancestors,
            $this->actor['user_id'], $this->actor['group_ids'], [],
        );
        if (in_array($pre['reason'], [
            'AUTH_RESOURCE_INACTIVE', 'AUTH_NO_BASE_PERMISSION', 'AUTH_EMPLOYEE_INELIGIBLE',
            'AUTH_NO_EFFECTIVE_ASSIGNMENT', 'AUTH_OUT_OF_ASSIGNMENT_SCOPE',
        ], true)) return false;
        $decision = AccessDecisionCore::decide(
            $resource['status'] === 'ACTIVE', $base, $this->actor['employee_active'],
            $this->actor['effective_assignments'], 'MASTER', $ancestors,
            $this->actor['user_id'], $this->actor['group_ids'],
            $this->policies->applicable($resource, $ancestors, null),
        );
        return $decision['allowed'];
    }

    /** @return list<string> Group-scoped native ImportJob States authorized for VIEW. */
    public function viewableProductImportStatuses(): array
    {
        $resource = $this->policies->resourceAction('import_job', 'view');
        if ($resource['domain'] !== 'ORGANIZATION') {
            throw new UnexpectedValueException('Import job view requires ORGANIZATION domain.');
        }
        $definition = $this->states->definitionFor('import_job');
        if ($definition['mode'] !== ResourceStateCatalog::NATIVE || $definition['by_code'] === []) {
            throw new UnexpectedValueException('Import job requires native States.');
        }
        $ancestors = $this->ancestors($this->groupRoot());
        $allowed = [];
        foreach ($definition['by_code'] as $status => $stateId) {
            if ($this->viewDecisionForState('import_job', $resource, $ancestors, $stateId)['allowed']) {
                $allowed[] = $status;
            }
        }
        return $allowed;
    }

    /**
     * @param array<string,mixed> $persistedRow A row already fetched by a trusted backend repository.
     * @return array{allowed:bool,reason:string,matched_policy_ids:list<string>,decisive_policy_ids:list<string>}
     */
    public function decidePersisted(string $type, string $action, array $persistedRow): array
    {
        $type = ResourceStateCatalog::normalizedType($type);
        // No public write routes or implicit mutation grants in M3.2.
        if (strtolower(trim($action)) !== 'view') {
            throw new UnexpectedValueException('M3.2 can only authorize persisted VIEW targets.');
        }
        if ($persistedRow === []) {
            throw new UnexpectedValueException('Persisted target is required.');
        }
        $resource = $this->policies->resourceAction($type, 'view');
        if (isset(self::APPROVED_READ_DOMAINS[$type])
            && $resource['domain'] !== self::APPROVED_READ_DOMAINS[$type]) {
            throw new UnexpectedValueException('Unapproved Resource domain for accepted VIEW contract: ' . $type);
        }
        // Enforce correct State even for disabled resources, matching the
        // baseline's persisted-State-before-policy ordering.
        $stateId = $this->states->persistedStateId($type, $persistedRow);
        $scopeId = $this->evaluationOrganization($type, $resource['domain'], $persistedRow);
        $ancestors = $this->ancestors($scopeId);
        return $this->viewDecisionForState($type, $resource, $ancestors, $stateId);
    }

    /**
     * Shared PBAC decision for a verified VIEW resource, canonical State ID,
     * and trusted organization ancestry. Used by persisted-row VIEW as well
     * as its exact MASTER state-scope compilation.
     *
     * @param array<string,mixed> $resource
     * @param list<string> $ancestors
     * @return array{allowed:bool,reason:string,matched_policy_ids:list<string>,decisive_policy_ids:list<string>}
     */
    private function viewDecisionForState(string $type, array $resource, array $ancestors, ?string $stateId): array
    {
        $pre = AccessDecisionCore::decide(
            $resource['status'] === 'ACTIVE',
            isset($this->actor['permissions'][$type . '.view']),
            $this->actor['employee_active'],
            $this->actor['effective_assignments'],
            $resource['domain'],
            $ancestors,
            $this->actor['user_id'],
            $this->actor['group_ids'],
            [],
        );
        if (in_array($pre['reason'], [
            'AUTH_RESOURCE_INACTIVE', 'AUTH_NO_BASE_PERMISSION', 'AUTH_EMPLOYEE_INELIGIBLE',
            'AUTH_NO_EFFECTIVE_ASSIGNMENT', 'AUTH_OUT_OF_ASSIGNMENT_SCOPE',
        ], true)) {
            return $pre;
        }
        return AccessDecisionCore::decide(
            $resource['status'] === 'ACTIVE',
            isset($this->actor['permissions'][$type . '.view']),
            $this->actor['employee_active'],
            $this->actor['effective_assignments'],
            $resource['domain'],
            $ancestors,
            $this->actor['user_id'],
            $this->actor['group_ids'],
            $this->policies->applicable($resource, $ancestors, $stateId),
        );
    }

    /**
     * M3.3B write authorization. This is intentionally NOT the M3.2
     * decidePersisted() API: read-only assertions and the accepted read
     * behavior must remain intact while write endpoints are gated separately.
     * The trusted target comes from a locked SQL row, never the JSON body.
     *
     * @param array<string,mixed>|null $persistedRow null ONLY for create
     * @param array<string,mixed> $createCandidate validated create fields
     * @return array{allowed:bool,reason:string,matched_policy_ids:list<string>,decisive_policy_ids:list<string>}
     */
    public function decideMutation(string $type, string $action, ?array $persistedRow, array $createCandidate = [], bool $transitionAction = false): array
    {
        $type = ResourceStateCatalog::normalizedType($type);
        $action = strtolower(trim($action));
        if ($transitionAction) {
            if ($persistedRow === null || !preg_match('/\A[a-z][a-z0-9_:-]*\z/D', $action)
                || in_array($action, ['view','create'], true)) {
                throw new UnexpectedValueException('Unapproved Workflow transition Action.');
            }
        } elseif (! in_array($action, ['create', 'edit', 'setstate', 'delete', 'assign_member', 'assign_permission', 'reset_password', 'configure_numbering'], true)) {
            throw new UnexpectedValueException('Mutation Action is not approved.');
        }
        if (($action === 'create') !== ($persistedRow === null)) {
            throw new UnexpectedValueException('CREATE requires a candidate; all other actions require a trusted locked row.');
        }
        $resource = $this->policies->resourceAction($type, $action);
        if (isset(self::APPROVED_READ_DOMAINS[$type]) &&
            $resource['domain'] !== self::APPROVED_READ_DOMAINS[$type]) {
            throw new UnexpectedValueException('Unapproved Resource domain for mutation: ' . $type);
        }
        if ($action === 'create') {
            // Authorization of a new native object uses its intended canonical
            // state; for policy, 'state_id' in the JSON is the target resource
            // State, NOT the state of the policy being created.
            // A Lifecycle resource may start at DRAFT (or another configured
            // initial State). Resolve CREATE PBAC against that persisted,
            // server-owned initial State, never an untrusted JSON status.
            $initial = ResourceStateCatalog::mode($type) === ResourceStateCatalog::LIFECYCLE
                ? $this->states->initialState($type)
                : null;
            $target = ['status' => $type === 'authorization_group'
                ? ($createCandidate['status'] ?? 'ACTIVE') : ($initial ?? 'ACTIVE')];
            // Native Shield User State is derived from BOTH status and active.
            // The candidate has no persisted row yet, so set the trusted
            // canonical create-side active flag rather than accepting JSON.
            if ($type === 'user') {
                $target['active'] = true;
            }
            if ($resource['domain'] === 'ORGANIZATION') {
                if (! isset($createCandidate['organization_id'])) {
                    throw new UnexpectedValueException('Create lacks a validated organization target.');
                }
                $target['organization_id'] = PositiveId::value((string) $createCandidate['organization_id']);
                if ($type === 'organization') {
                    // M5.2: a new Organization has no persisted ID yet. Its
                    // CREATE scope is its validated existing parent; the
                    // actual inserted child is independently VIEW-checked.
                    $target['id'] = $target['organization_id'];
                    $target['status'] = (string) ($createCandidate['status'] ?? 'ACTIVE');
                }
            }
        } else {
            $target = $persistedRow;
        }
        $stateId = $this->states->persistedStateId($type, $target);
        $scopeId = $this->evaluationOrganization($type, $resource['domain'], $target);
        $ancestors = $this->ancestors($scopeId);
        // Recovery exception is restricted to the catalog resource.setstate
        // action, never arbitrary writes or a bypass of the base permission.
        $recovery = $type === 'resource' && $action === 'setstate';
        $pre = AccessDecisionCore::decide(
            $resource['status'] === 'ACTIVE', isset($this->actor['permissions'][$type . '.' . $action]),
            $this->actor['employee_active'], $this->actor['effective_assignments'],
            $resource['domain'], $ancestors, $this->actor['user_id'], $this->actor['group_ids'],
            [], $recovery,
        );
        if (in_array($pre['reason'], [
            'AUTH_RESOURCE_INACTIVE', 'AUTH_NO_BASE_PERMISSION', 'AUTH_EMPLOYEE_INELIGIBLE',
            'AUTH_NO_EFFECTIVE_ASSIGNMENT', 'AUTH_OUT_OF_ASSIGNMENT_SCOPE',
        ], true)) {
            return $pre;
        }
        return AccessDecisionCore::decide(
            $resource['status'] === 'ACTIVE', isset($this->actor['permissions'][$type . '.' . $action]),
            $this->actor['employee_active'], $this->actor['effective_assignments'],
            $resource['domain'], $ancestors, $this->actor['user_id'], $this->actor['group_ids'],
            $this->policies->applicable($resource, $ancestors, $stateId), $recovery,
        );
    }

    /**
     * Only Workflow may supply an action code already resolved from a trusted
     * persisted Lifecycle transition. Still enforces base permission, State,
     * organization scope, policy lanes and ABSOLUTE_DENY.
     * @param array<string,mixed> $target
     * @return array{allowed:bool,reason:string,matched_policy_ids:list<string>,decisive_policy_ids:list<string>}
     */
    public function decideTransitionAction(string $type, string $action, array $target): array
    {
        return $this->decideMutation($type, $action, $target, [], true);
    }

    /** @param array<string,mixed> $persistedRow */
    public function canView(string $type, array $persistedRow): bool
    {
        return $this->decidePersisted($type, 'view', $persistedRow)['allowed'];
    }

    /** @param array<string,mixed> $row */
    private function evaluationOrganization(string $type, string $domain, array $row): string
    {
        if ($domain === 'SYSTEM' || $domain === 'MASTER') {
            return $this->groupRoot();
        }
        if ($domain !== 'ORGANIZATION') {
            throw new UnexpectedValueException('Invalid Resource evaluation domain.');
        }
        // Baseline AuthorizationLogScopeResolver maps a NULL *persisted* log
        // organization to the GROUP root. Other ORGANIZATION resources must
        // have their own persisted scope (never from client hints).
        if (($type === 'authorization_log' && ($row['organization_id'] ?? null) === null)
            || ($type === 'import_job' && ($row['entity_type'] ?? null) === 'PRODUCT')) {
            return $this->groupRoot();
        }
        $value = $type === 'organization' ? ($row['id'] ?? null) : ($row['organization_id'] ?? null);
        if ($value === null) {
            throw new UnexpectedValueException('Persisted record lacks authoritative organization scope: ' . $type);
        }
        return PositiveId::value((string) $value);
    }

    private function groupRoot(): string
    {
        if ($this->groupRoot === null) {
            $roots = $this->organizations->groupRoots();
            if (count($roots) !== 1) {
                throw new UnexpectedValueException('Exactly one GROUP root is required for PBAC.');
            }
            $this->groupRoot = PositiveId::value((string) $roots[0]['id']);
        }
        return $this->groupRoot;
    }

    /** @return list<string> */
    private function ancestors(string $organizationId): array
    {
        if (! isset($this->ancestorIds[$organizationId])) {
            $chain = $this->organizations->ancestorChain($organizationId);
            if ($chain === [] || ($chain[0]['type'] ?? null) !== 'GROUP'
                || ($chain[0]['parent_id'] ?? null) !== null) {
                throw new UnexpectedValueException('PBAC organization ancestry lacks a valid GROUP root.');
            }
            $ids = [];
            foreach ($chain as $node) {
                if (! in_array(($node['type'] ?? null), ['GROUP','COMPANY','BRANCH','PLANT','WAREHOUSE'], true)) {
                    throw new UnexpectedValueException('Invalid organization type in PBAC ancestry.');
                }
                $ids[] = PositiveId::value((string) $node['id']);
            }
            if ($ids[0] !== $this->groupRoot() || $ids[count($ids) - 1] !== $organizationId) {
                throw new UnexpectedValueException('PBAC organization ancestry is inconsistent.');
            }
            $this->ancestorIds[$organizationId] = $ids;
        }
        return $this->ancestorIds[$organizationId];
    }
}
