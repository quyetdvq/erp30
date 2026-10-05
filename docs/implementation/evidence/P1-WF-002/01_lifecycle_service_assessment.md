# P1-WF-002 LifecycleService Assessment

Status: DONE
Risk: HIGH
Task: P1-WF-002

## Scope

Implemented lifecycle configuration invariants and canonical authorized state
change orchestration. No API, UI, Gate 4, or P1-AUTH-090 work was started.

## Decision boundary

DEC-WF-002 is applied through `LifecycleTargetAdapter`. The service knows only
the canonical resource type and typed target snapshot. Concrete business table
access remains in a future resource adapter; no generic Resource Instance/state
table or Resource Instance Authorization was added.

## Configuration

Active lifecycles require exactly one initial member. Multiple final members are
accepted. New membership requires an active Workflow State. Transitions require
the same lifecycle and member source/destination states. Duplicate
`lifecycle + from_state + action_code` rows are rejected before persistence.
Lifecycle row locking and repository transactions make configuration batches
atomic. `action_code` is normalized as machine identity and never display text.

## Execution order

The service loads a registered adapter snapshot, resolves lifecycle and current
membership, and resolves an ACTIVE transition before calling Authorization.
Authorization receives the current state, adapter-provided persisted target and
authoritative organization, plus the single `OperationContext` evaluation time.
DENY produces no target mutation and records required audit evidence. ALLOW
enters the adapter transaction, locks/re-reads the target, rejects a changed
state/lifecycle/organization as `CONCURRENT_MODIFICATION`, mutates only to the
catalog destination, and records audit before commit.

## Security and failure semantics

Unknown resource types fail closed. There is no System Admin branch. Stable
codes distinguish invalid lifecycle configuration, state membership,
`WF_INVALID_TRANSITION`, authorization denial, adapter availability, and
concurrent modification. Infrastructure exceptions are not converted into
transition-not-found errors.

## Evidence

- Focused tests: `P1Wf002LifecycleServiceTest.php`, 8 tests / 27 assertions.
- WF-001 and AUTH-002/003/004 plus workflow seed regression: 24 tests / 111
  assertions.
- PHPStan level 8 passed for changed service, ports, adapters, auth integration,
  and focused tests.
