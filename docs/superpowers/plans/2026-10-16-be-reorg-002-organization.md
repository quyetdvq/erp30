# BE-REORG-002 Organization Implementation Plan

> For agentic workers: use superpowers:executing-plans task-by-task.

**Goal:** Move Organization into its own module without changing existing behavior.
**Architecture:** Repository port, canonical application/SQL implementations, explicit legacy wrappers, fresh factory. Reuse existing safety runner.
**Tech Stack:** CodeIgniter 4, PHP >=8.2, PHPUnit 10, PHPStan level 8; locked dependencies unchanged.
**Spec:** docs/superpowers/specs/2026-10-16-be-reorg-002-organization-design.md

## Global Constraints
No API, schema, migration, frontend, dependency or PHPStan configuration changes. No alteration of existing tests. Start on checkpoint 001B. Preserve constructor parameter names, optional defaults and public constants. Baseline runtime supplied by operator; local runtime must be reported separately.

## Review Focus
Explicit repository on a different connection must remain authoritative. Fresh services must not cache actor/time. Matrix validation must not reorder cycle errors. Reparent failures must preserve database rows and release tree lock. Runner must not treat inherited PHPStan failures or skipped tests as success.

### Task 1: Lock the contracts and characterization
- [x] Capture the two original public contracts by Reflection into tests/_support/Reorg/legacy_organization_contracts.json.
- [x] Add BeReorg002OrganizationCompatibilityTest and BeReorg002OrganizationBoundaryTest; first prove the new module/port/factory do not yet exist.
- [x] Add behavior tests with fixed expected hierarchy, audit, workflow and exception results. Execute offline characterization on checkpoint where possible, never label it MySQL evidence.

### Task 2: Move implementation and wire legacy callers
- [x] Create OrganizationErrors (13 constants), OrganizationRepositoryInterface (8 methods), SqlOrganizationRepository, Application/OrganizationService and OrganizationFactory.
- [x] Replace only the two old class bodies with delegating methods; preserve public signatures. Canonical service uses required port/db; repo() returns the injected port.
- [x] Verify no canonical dependency on legacy wrappers, no extra SQL/conditions or mutation changes, all public contracts match.

### Task 3: Runtime regression and single-command handoff
- [x] Add Organization characterization, HTTP and domain-lock tests using existing fixtures/guards and helper behavior from FIX01/FIX02. Keep original test files intact.
- [x] Add tools/test-organization.php to invoke guarded full suite once via test-foundation.php, keep one log, and verify Organization JUnit cases and prior Foundation gate.
- [x] Run pure-PHP runner/contract checks and offline comparison. Attempt full PHPUnit and PHPStan; record environment blockers verbatim.

### Task 4: Review and package
- [x] Self-review source diff, typed ports/PHPDoc, dependency allowlist, fixtures and runner failure paths (no subagent tool).
- [x] Lint all PHP. Verify apply and reverse hashes, protected files unchanged.
- [x] Export one incremental patch and small handoff package with evidence; report runtime not run if unavailable.

## Execution evidence
All code/delivery steps above are executed. Full PHPUnit and PHPStan were attempted but BLOCKED by absent vendor; runtime acceptance remains with the operator. No item marks those suites PASS. See the handoff evidence for lint, public-contract identity, method-token equivalence, offline behavior probes and patch round-trip hashes.
