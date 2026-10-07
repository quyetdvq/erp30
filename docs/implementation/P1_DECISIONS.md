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

## DEC-P1-001 — Canonical Employee gender catalog

- Approved by the Product Owner for P1 Employee API/UI contract closure.
- Canonical stored/API values are `MALE`, `FEMALE`, `OTHER`, and `NULL`.
- `NULL` means not specified; `UNSPECIFIED` is not a valid code.
- Presentation labels are `Nam / Male`, `Nữ / Female`, `Khác / Other`, and
  `Chưa khai báo / Not specified`; labels are never persisted or sent as API
  values.
- Keep `employees.gender VARCHAR(20)`; do not add a lookup table or migration.

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

## DEC-ARCH-001 — Product and Pricing modular-monolith boundaries

- Product and Pricing are separate backend modules under `App\\Modules\\Product`
  and `App\\Modules\\Pricing`.
- Product owns ProductGroup, UOM, Product, role/ParentChild, structural Combo,
  and Product-specific numbering integration.
- Pricing owns PriceList, pricing resolution, and monetary Combo allocation.
- Product must not depend on Pricing. Pricing may consume Product only through
  stable public Product contracts/read ports and must not bind to concrete Product
  repositories.
- Generic Auto Numbering remains shared infrastructure; ProductIdentifierAdapter
  belongs to Product. Import remains separate.
- This is a physical relocation only: no schema, API, UI, or business semantic
  change. The completed P1-PRD-001 implementation is moved, not reimplemented.

## DEC-CHG-POLICY-001 — One Policy per normalized scope

- `policies` scope identity is `organization_id + resource_id + normalized_state + participant_type + participant_ref_id`.
- `normalized_state` uses positive `state_id` or sentinel `0` for `ALL_STATES` in a stored generated column; MariaDB 10.4.32 enforces one unique key over the five dimensions.
- Duplicate POST/PATCH is `409 POLICY_SCOPE_ALREADY_EXISTS`. Complete Policy Action/Effect replacement is atomic.
- Legacy reconciliation retains the lowest Policy ID and unions actions using `ABSOLUTE_DENY > ALLOW > DENY`; inability to prove equivalence blocks migration without discarding rows.

## DEC-CHG-IAM-001 — Shield User mutation boundary

- Shield tables/configuration remain the only identity/password authority; no local User/password persistence is introduced.
- User create/edit/state, group membership, direct permission, and administrator reset-password are distinct operations. Password reset requires the dedicated `user.reset_password` action because no approved equivalent exists in the current catalog.
- State vocabulary is `ACTIVE`, `INACTIVE`, `LOCKED`, `SUSPENDED`, `BANNED`, `DISABLED`; group labels never substitute for canonical membership or PBAC.
- Existing `/users/{id}/permissions` GET/PUT remains the direct ALLOW-only retrofit through `user.assign_permission`; PBAC and `ABSOLUTE_DENY` remain authoritative.
