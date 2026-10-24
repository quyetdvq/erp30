# KIR-001-C2 — Product COMBO + AUTO transaction ownership remediation

**Status:** `SOURCE_READY / RUNTIME_REMEDIATION_NOT_RUN`
**Precondition:** KIR-001-C1 runtime characterization PASS on 2026-10-22: 6 tests / 50 assertions.
**API/schema/PBAC change:** None.
**Production files changed:** Numbering allocator only; Product COMBO command boundary is retained.

## Confirmed problem

C1 proved the following real MariaDB/Shield behavior:

1. `Api050ProductCatalogService::createProduct()` owns an outer transaction when the payload contains `combo_items`.
2. Manual `AUTO_IF_EMPTY` COMBO creation is atomic inside that transaction.
3. Standalone `AUTO_REQUIRED` Product creation succeeds and the Numbering allocator owns its transaction.
4. `AUTO_REQUIRED` + COMBO fails before Product mutation because the allocator rejects `transDepth > 0`.
5. Post-parent COMBO validation/PBAC failures roll back the parent correctly on the manual control path.

The defect is therefore transaction **ownership compatibility**, not Product validation, PBAC, or COMBO rollback semantics.

## Decision

Use an **own-or-participate** policy in `AutoNumberingCounterAllocator`:

- when `transDepth === 0`, Numbering starts, validates, commits, and rolls back its own transaction exactly as before;
- when a transaction is already active, Numbering participates on the same connection and does **not** commit, roll back, or reset the caller's transaction;
- `SqlNumberingCounterRepository::findForUpdate()` still requires an active transaction, so row-lock semantics remain explicit;
- the Product COMBO facade remains the physical command transaction owner for Product + Numbering + COMBO composition.

No new interface, event bus, transaction manager, unit-of-work abstraction, or cross-module callback is introduced.

## Why Product remains the outer owner

Moving the COMBO composition after a Numbering-owned commit would allow a Product/counter pair to commit before composition validation or `product.edit` PBAC. Compensation would then be required and sequence reuse semantics would change. Keeping the existing Product command transaction preserves the already-characterized atomicity boundary.

## Failure ownership

| Situation | Physical transaction owner | Expected behavior |
|---|---|---|
| Standalone AUTO create | Numbering allocator | allocator commit/rollback unchanged |
| COMBO + manual identifier | Product command | Product + composition commit/rollback together |
| COMBO + AUTO identifier | Product command | Product + counter + composition commit/rollback together |
| Numbering failure while participating | Product command | allocator maps its error but does not close/reset caller transaction; caller rolls back |
| COMBO validation/PBAC failure after allocation | Product command | Product row and counter insert/update roll back together |

## Verification matrix

The dedicated guarded KIR-001 lane contains nine MariaDB/Shield cases:

1. `AUTO_IF_EMPTY` manual COMBO control path remains successful.
2. `AUTO_REQUIRED` + non-empty `combo_items` succeeds.
3. `AUTO_REQUIRED` + explicit empty `combo_items` succeeds.
4. `AUTO_REQUIRED` without `combo_items` preserves standalone allocator ownership.
5. Invalid composition after first allocation rolls back Product and first counter; retry reuses sequence `1`.
6. Invalid composition after an existing counter update restores the old value; retry reuses the rolled-back next sequence.
7. `product.create` allow + `product.edit` deny rolls back Product and counter.
8. Occupied first generated Product code advances to the next candidate and persists the actual issued sequence.
9. Two concurrent first-counter AUTO COMBO creates produce distinct sequences and one counter identity.

`P1Num004CounterAllocatorTest` also verifies in UNIT-STANDARD that an allocator call inside a caller transaction returns with the caller's transaction still active and that caller rollback removes the counter.

## Advisory-lock/concurrency risk

The first-counter path retains the existing Numbering advisory lock and `FOR UPDATE`/unique counter identity behavior. In a participating transaction, the advisory lock is released before the Product command physically commits, so C2 acceptance explicitly requires the concurrent AUTO COMBO case. Do not claim KIR-001 closed if that case is flaky, times out, duplicates a sequence, or creates more than one counter identity.

## Commands

From `backend` after backing up `erp_test`:

```powershell
.\tools\test-unit.ps1 RUN UNIT-STANDARD
.\tools\test-kir001.ps1 RUN
```

Then run the existing NO-DROP cohort because the allocator is shared infrastructure:

```powershell
.\tools\test-unit.ps1 RUN NO-DROP
```

Source-only checks can be run without a database:

```powershell
php tools/test-kir001-source-gate.php
php tools/test-be-reorg-014i-operation-context.php
php tools/test-be-reorg-014h-residual.php
php tools/test-be-reorg-014e-api050-audit.php
```

## Acceptance rule

KIR-001 may move to `REMEDIATED_RUNTIME_VERIFIED` only when:

- KIR-001 C2: all 9 cases PASS, including concurrency;
- UNIT-STANDARD PASS with the ambient-owner assertions;
- NO-DROP PASS with unchanged schema/data attestation;
- architecture/source gates PASS;
- no API, schema, permission/action, or error-contract regression is observed.

Until then this patch is `SOURCE_READY / RUNTIME_REMEDIATION_NOT_RUN`.

## Rollback

Revert the C2 patch. This restores the allocator nested-transaction rejection and the C1 characterization behavior. No migration or data rollback is required because C2 changes no schema.
