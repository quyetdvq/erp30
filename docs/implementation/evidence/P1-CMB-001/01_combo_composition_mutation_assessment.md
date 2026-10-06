# P1-CMB-001 COMBO Composition Mutation Assessment

Status: DONE

## Scope and module boundary

`App\\Modules\\Product\\Combo\\ComboCompositionRepository` is a persistence-only
adapter for `product_combo_items`. `ComboCompositionService` owns the authorized
atomic replacement of a COMBO Product's direct component set. The task adds no
migration, route/controller, OpenAPI/client, frontend, import, pricing, flatten,
allocation, or new permission/resource/action surface.

## Composition contract

- The owner must be an active selectable `COMBO` Product with an active base UOM.
- Components may be active `SIMPLE`, `CHILD`, or `COMBO` Products. `PARENT`,
  missing, inactive, self, duplicate, malformed, and invalid-base-UOM references
  are rejected. Existing inactive rows may remain retained as historical data;
  newly added rows must be active/selectable.
- Quantities are exact decimal strings, strictly positive, limited to the
  `DECIMAL(18,6)` boundary, and compared without binary floating point.
- Replacement is diff-based and atomic: retained rows keep identity, changed
  quantities update, removed rows delete, and new rows insert. Repository output
  is deterministic by component ID.

## Graph and concurrency invariants

Nested COMBO edges are validated as a complete resulting graph with DFS active
path detection, so direct, deep, and persisted cycles fail closed. The service
acquires `combo-graph` before sorted `product-structure:{root}` advisory locks
on the injected DB session, re-reads owner/rows after locking, and performs the
replacement in one transaction. Product master conversion now rejects
COMBO-to-STANDARD while composition rows exist, preventing an owner from
becoming `PARENT`/non-COMBO while still owning composition.

## Authorization and audit

Product `view` authorizes reads and Product `edit` authorizes replacement using
the canonical GROUP evaluation context. Client evaluation hints cannot override
the resolver. Audit/user timestamps are supplied by the trusted
`OperationContext`; no client audit fields are accepted.

## Validation evidence

- Focused P1-CMB-001: PASS, 6 tests / 63 assertions, including independent
  reciprocal/deep cycle workers and SIMPLE-versus-first-child race.
- P1-PRD-003 ProductMaster: PASS, 6 tests / 44 assertions.
- P1-PRD-004 ProductRole: PASS, 4 tests / 29 assertions.
- P1-UOM-001 Product UOM: PASS, 6 tests / 69 assertions.
- P1-PRC-001 Price List: PASS, 5 tests / 73 assertions.
- P1-PRC-002 Pricing: PASS, 6 tests / 35 assertions.
- DB-060 Product/UOM/COMBO schema: PASS, 2 tests / 293 assertions.
- Mandatory authorization regression: PASS, 9 tests / 68 assertions.
- Full backend PHPUnit `--no-coverage`: PASS, 281 tests / 2,974 assertions.
- PHPStan level 8 application: PASS; architecture guard: PASS; context cache:
  PASS; ledger validator: PASS; staged scoped `git diff --check`: PASS.

The pre-existing unrelated edit under `docs/thamkhao` was preserved and is not
part of this task.
