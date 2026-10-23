# KIR-001-C1 — Product COMBO + AUTO transaction characterization

**Status:** `RUNTIME_CHARACTERIZED / OWNERSHIP_CONFLICT_CONFIRMED`
**Scope:** Product `createProduct()` orchestration + Numbering allocator transaction ownership only.
**Production behavior changed:** **No.** This C1 slice added runtime characterization and guarded test tooling only. C2 remediation is documented separately.

## Why this checkpoint exists

Source inspection shows one conditional ownership conflict:

1. `Api050ProductCatalogService::createProduct()` opens an outer transaction whenever the request contains the `combo_items` key, including an explicit empty list.
2. Inside that transaction, `ProductMasterService::create()` may enter AUTO Numbering.
3. `AutoNumberingCounterAllocator::allocate()` rejects any call when `transDepth > 0` because the allocator owns its own transaction.

That call path is observable from source, but transaction behavior must be pinned on MariaDB/Shield before changing either owner. This checkpoint therefore records **current behavior**, not the desired remediation.

## Runtime matrix added

`tests/integration/Kir001ProductComboAutoTransactionCharacterizationTest.php` contained the six C1 cases at characterization time. C2 subsequently evolves/renames that runtime lane to `Kir001ProductComboAutoTransactionRemediationTest.php`:

| Case | Purpose | Current behavior pinned by test |
|---|---|---|
| `AUTO_IF_EMPTY` + manual code + valid `combo_items` | Control path through the same series plumbing without allocator ownership | COMBO parent and component relation succeed; no Numbering counter is created |
| `AUTO_REQUIRED` + blank code + valid `combo_items` | Exact KIR-001 path | `NUM_COUNTER_TRANSACTION_REQUIRED`; no Product row and no counter persist |
| `AUTO_REQUIRED` + blank code + explicit `combo_items: []` | Prove key-presence, not item count, selects the outer transaction | Same nested-transaction guard; no Product row/counter |
| `AUTO_REQUIRED` + blank code + **no** `combo_items` key | Control path for allocator ownership | Product creation succeeds and counter records sequence `1` |
| `AUTO_IF_EMPTY` + manual code + invalid component | Verify existing outer COMBO transaction atomicity | Composition validation fails and newly inserted parent is rolled back |
| create allowed, `product.edit` denied | Verify security boundary and rollback after parent insert | COMBO composition is denied and parent is rolled back |

Every case asserts transaction depth returns to zero after the command.

## Why this is not in NO-DROP

The NO-DROP lane deliberately begins an outer rollback transaction around each test. That would make Numbering observe `transDepth > 0` even for a normal standalone AUTO create, which would contaminate the KIR-001 measurement. The characterization therefore uses a dedicated guarded PHPUnit configuration and normal CI4 `DatabaseTestTrait` behavior.

The runner verifies that the selected test connection is exactly:

- host: `127.0.0.1`
- port: `3306`
- database: `erp_test`
- prefix: `dinktech_`

Because CI4 migration/refresh behavior is retained, back up `erp_test` before runtime execution.

## Commands

From `backend`:

```powershell
.\tools\test-kir001.ps1 CHECK
.\tools\test-kir001.ps1 RUN
```

Equivalent direct source-only gate:

```powershell
php tools/test-kir001-source-gate.php
```

The runtime command requires installed Composer dependencies and a reachable guarded MariaDB test database.

## Runtime evidence

Operator execution on **2026-10-22** against guarded `127.0.0.1:3306/erp_test` reported:

- PHP 8.3.30 / PHPUnit 10.5.65;
- **6/6 tests PASS**;
- **50 assertions**;
- transaction depth returned to zero in every characterized command.

This satisfies the C1 decision rule and confirms the ownership conflict: standalone AUTO allocation succeeds, while the same AUTO allocation under the Product COMBO outer transaction is rejected by the allocator's nested-transaction guard before remediation.

The status is therefore `RUNTIME_CHARACTERIZED / OWNERSHIP_CONFLICT_CONFIRMED`. C2 may change transaction ownership only with the following proof matrix:

- AUTO collision behavior through the COMBO path;
- counter rollback/reuse when composition fails after identifier allocation;
- same-session participation proof for Product + Numbering + COMBO;
- advisory-lock behavior;
- concurrent AUTO COMBO creates;
- error/PBAC parity and rollback plan.

C2 supersedes the nested-transaction freeze with an explicit own-or-participate decision. The Product COMBO outer transaction remains the command boundary; C2 runtime verification is still required before KIR-001 can close.
