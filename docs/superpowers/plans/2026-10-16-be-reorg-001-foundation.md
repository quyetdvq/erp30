# BE-REORG-001 Foundation Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Move the Province/Ward implementation into the Foundation module while preserving the three existing public classes and all observable behavior.

**Architecture:** Explicit composition wrappers at the old namespaces; repository port and errors inside Foundation; lifecycle integration outside Application; non-caching factory for construction. The HTTP facade, callers and database schema remain unchanged.

**Tech Stack:** PHP ^8.2, CodeIgniter 4.7.4, Shield 1.4.1, PHPUnit 10.5.65, PHPStan 2.2.17 (archive lockfile; no updates).

**Spec:** ../specs/2026-10-16-be-reorg-001-foundation-design.md (approved in conversation).

## Global Constraints

- Change only the three legacy production files named by the spec; add its six production files.
- No edits to existing tests, frontend, routes, controller, API facade, schema, migrations, seeds, config, autoload, or dependencies.
- Preserve final classes, public constants, method and constructor parameter names/types/order/defaults, exception class/code/context, and return types.
- Preserve audit context capture, lazy service workflow resolver, transaction and SQL statement order.
- Keep user-supplied repository/connection combinations; do not silently repair or validate them.
- No singleton, static object cache, magic delegation, class alias, or inheritance wrapper.
- A test candidate is not an accepted release: unavailable DB/PHPUnit/PHPStan checks stay explicitly NOT RUN.

## Review Focus

- Invalid lifecycle type must throw the original DomainException before attempting a default DB connection.
- A custom connection and an explicit repository must not be replaced by the factory.
- Historical Ward edits, nullable name_full, empty update, and caller audit fields retain their distinct meanings.
- Duplicate code during reparent and transaction failures keep exception identity and rollback behavior.
- Legacy signatures include named arguments; direct module tests alone cannot prove wrapper compatibility.

## Files and interfaces

Modify only:
- backend/app/Services/FoundationLocationService.php
- backend/app/Repositories/FoundationLocationRepository.php
- backend/app/Services/FoundationLocationLifecycleAdapter.php

Create production:
- backend/app/Modules/Foundation/Contracts/LocationErrors.php: 14 stable error constants.
- backend/app/Modules/Foundation/Application/Ports/LocationRepositoryInterface.php: the nine existing public repository methods.
- backend/app/Modules/Foundation/Application/LocationService.php: same eight service methods; constructor `(LocationRepositoryInterface $repository, ConnectionInterface $db, ?MutationContext $mutationContext = null, ?Clock $clock = null, ?ResourceWorkflowStateResolver $workflowStates = null)`.
- backend/app/Modules/Foundation/Infrastructure/Persistence/SqlLocationRepository.php: existing query/allowlist implementation, port implementation.
- backend/app/Integration/Foundation/LocationLifecycleAdapter.php: existing adapter with port dependency and new SQL default; optional dependencies preserve validation-before-connection ordering.
- backend/app/Bootstrap/FoundationFactory.php: static repository(), service(), lifecycleAdapter(); new object per call; no shared service registration.

Create tests:
- backend/tests/database/BeReorg001FoundationCharacterizationTest.php
- backend/tests/database/BeReorg001FoundationModuleParityTest.php
- backend/tests/unit/BeReorg001FoundationCompatibilityTest.php
- backend/tests/unit/BeReorg001FoundationBoundaryTest.php
- backend/tests/_support/Reorg/legacy_foundation_contracts.json

### Task 1: Pin baseline and regression expectations

**Consumes:** pristine v1.1.5 source and approved spec.
**Produces:** exact public contract fixture; fixed expected-value DB tests; local verification evidence.

- [ ] Capture public signatures/constants from original PHP Reflection without constructing database services.
- [ ] Write characterization tests against legacy entrypoints: audit, null/omitted/empty values, duplicate/error identity, historical Ward edits, reparent success/conflicts, lifecycle mutation boundary.
- [ ] Add compatibility and boundary tests that assert the new module/factory exists and disallow backward imports.
- [ ] Run baseline checks before production edits; record missing dependencies as environment blockers, not PASS/FAIL of application tests.
- [ ] Run offline architecture check RED: expected module classes do not exist yet.

### Task 2: Move implementation and add explicit compatibility wrappers

**Consumes:** legacy signatures and behavioral assertions from Task 1.
**Produces:** new repository port, error contract, canonical service/repository/integration and factory, original public API wrappers.

- [ ] Move repository SQL bodies without query or exception rewrites.
- [ ] Move service bodies; replace cross-layer error owners with LocationErrors; receive repository port and connection in constructor.
- [ ] Move lifecycle adapter; retain invalid-type validation before resolving optional connections, eager adapter resolver, persisted predicate, callback/transaction behavior.
- [ ] Add factory; adapter construction delegates optional dependency resolution to the canonical adapter to preserve the legacy validation ordering. This is not a new validation rule.
- [ ] Replace old classes with final explicit delegates preserving signatures and constants; repository wrapper implements the port.
- [ ] Run syntax, contract, boundary and normalized method-body equivalence checks. Expected: all checks pass.

### Task 3: Exercise wiring and package the test candidate

**Consumes:** canonical classes and original wrappers from Task 2.
**Produces:** testable patch, changed-file archive, pristine-applicability/rollback evidence and operator instructions.

- [ ] Add module parity checks with fixed expected values, wrapper interop, transaction rollback, lifecycle snapshot/mutation, and caller-supplied dependencies.
- [ ] Run available local tests and attempt the full project test command; explicitly record what cannot execute.
- [ ] Self-review the entire diff against C01-C20; maintain a pending-runtime evidence matrix rather than claim release acceptance.
- [ ] Verify only the three original production files changed and every other original file is byte-identical.
- [ ] Generate patch from archive baseline; apply it to a fresh extraction, verify resulting hashes; reverse it and verify exact baseline recovery.
- [ ] Deliver patch and test/rollback commands. Stop at Foundation; do not start the next module.

## Execution note

The user explicitly approved the spec and requested implementation and a patch for operator testing in this turn. Work proceeds directly in an isolated extraction on a local feature branch; no remote repository or production service is touched. Runtime acceptance remains with the before/after operator tests until the missing environment is available.
