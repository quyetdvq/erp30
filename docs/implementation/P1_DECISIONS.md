# P1 Implementation Decisions

This file records approved Product Owner implementation decisions. It is
operational evidence only; it does not replace or amend the UR, FRS, DBD,
Implementation Contract, Implementation Plan, API Design, or UI Design.

## DEC-WF-001 — Workflow identity

- `workflow_states.code` is the canonical logic identity.
- `workflow_states.name` is display data only.
- Bootstrap `name` must equal `code`.

## DEC-WF-002 — Canonical Lifecycle Target Adapter

- P1 does not create a generic Resource Instance/state table.
- `LifecycleService` uses the `LifecycleTargetAdapter` application port.
- Concrete resources provide an explicitly registered adapter when they persist
  workflow state; unknown resources fail closed.
- The adapter supplies canonical resource type, target snapshot/current state,
  applicable lifecycle, authoritative Evaluation Organization, target lock and
  concurrency re-read, and expected-state-to-destination mutation.
- `LifecycleService` contains no business table/column knowledge. It resolves
  transition before authorization, authorizes the current state, and performs
  lock/re-read, exact catalog destination mutation, and required audit in the
  adapter transaction.
- Client-supplied current state, lifecycle, destination, and organization are
  never authoritative. There is no System Admin special path or new schema.

## DEC-SEC-001 — Privileged Authorization Group bootstrap

- Group code: `SYSTEM_ADMIN`.
- Display name: `System Admin`.
- Status: `ACTIVE`.
- Description: `NULL`.
- The approved permission set is the explicit 92-action snapshot; it is not
  expanded by future auto-grant behavior.
- Membership is assigned through the Shield user ID CLI/deployment path.
- There is no runtime bypass for this group.

## DEC-ORG-001 — Canonical GROUP root bootstrap

- Code: `GROUP_DINKTECH`.
- Name: `Dinktech Group`.
- Description: `Tập đoàn Dinktech`.
- Type: `GROUP`.
- Status: `ACTIVE`.
- Parent: `NULL`.
- Approved nullable fields are `NULL`.
- A conflicting GROUP root is a blocking condition.
- No Company is seeded.

## DEC-PRD-001 — Multi-level Product Group hierarchy

- Approved by the Product Owner before Gate 5; this is an approved decision, not an open assumption.
- Product Group persistence is an adjacency list with nullable `parent_id` self FK; `NULL` is the root and depth is arbitrary.
- Product Group code remains globally unique within the business Group.
- Self-parent and direct/indirect cycles are rejected; valid reparenting is allowed.
- Status is persisted per node; inactivation does not cascade to descendants.
- New Product selection requires an ACTIVE target and ACTIVE every-ancestor path; existing Product references remain valid/readable.
- Product Group is a shared GROUP-level master; no Company ownership or Product Company Usage Scope is introduced.
- Hierarchy mutation is serialized by DomainLockService key `product-group-tree` in the same transaction before cycle validation.
- No closure table, nested-set, materialized-path, level/depth helper, or other hierarchy table is introduced.
