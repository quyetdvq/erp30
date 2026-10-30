# P2-SUP T02 — Numbering Precedence & Issuer Source QA

**Date:** 2026-10-30
**Baseline:** `erp_v2.0.0_sup_t01b1_cus_prevention_fixed.zip`
**State:** Source-only Numbering participant and regression PASS. **NOT integrated with a Supplier API or MariaDB.**

## Approved decision

SUP-DEC-05 requires P1 Numbering, GROUP scope, immutable supplier_code and unique `(group_organization_id, supplier_code)`; *unlike Customer*, any configured default forbids manual code, even if that default is INACTIVE or its `generation_mode` is `AUTO_IF_EMPTY`.

Precedence (must be evaluated in this order):

1. Load **every** `is_default=1` row for resource type `supplier`, including inactive rows. Zero = no default; more than one = reject ambiguous. One inactive or malformed = reject; **never** fall back to manual or bypass via an explicit alternate Series.
2. If a valid ACTIVE GROUP default exists, require AUTO. An explicit requested Series may be used only if it is itself valid, ACTIVE, belongs to `supplier` and is GROUP-scoped, and the configured default has already passed validation.
3. Without any default, valid explicit requested Series selects AUTO; without a requested Series, a valid canonical manual code is required.
4. Do not implicitly seed or alter Numbering config in migration, catalog provisioning or API bootstrap. Any optional initial series must be created through explicitly authorized P1 Numbering configuration after the database preflight.

## Source changes

- `SupplierNumberingPolicy.php`: pure selection, strict series/group validation, canonical manual/Series codes, `{RESOURCE}` exactly `supplier`, date periods, overflow guard and bounded retries.
- `SupplierNumberIssuer.php`: participates in the **owning** Supplier-master transaction and receives a callback `(canonical_supplier_code, trusted_group_id)` to persist Supplier **and Company Usage**. Uses existing P1 tables and CodeIgniter builder prefixes, locks `resources`, `auto_numbering_series` (default and requested), `organizations` Group root, and counter reads via `FOR UPDATE`. Checks candidate availability **within the Group**; counter mutation is in the same parent transaction, never committed separately. No HTTP route or grants.
- `SupplierService` does not exist yet. T04 MUST acquire the same Supplier-master advisory mutex for every create, start physical transaction, authorize and validate location/usage/initial Lifecycle, call `SupplierNumberIssuer::issue()` with a callback doing the Supplier+Company Usage inserts, catch duplicate SQL 1062 from the `(group_organization_id, supplier_code)` UNIQUE index, and rollback on every error. The issuer deliberately does **not** acquire its own unrelated mutex, begin or commit a nested transaction.

## Offline evidence

| Verification | Result |
|---|---|
| `php tests/sup/supplier_numbering_pure.php` | 64/64 PASS |
| `php tests/sup/supplier_numbering_issuer_fixture.php` | 34/34 PASS on fake connection |
| Supplier T01A/B/B.1 Rules / Catalog / Provision | Previous 48/48 + 38/38 + 16/16; rerun in Windows |
| Regression runner | `SUP-UNIT` and `UNIT-STANDARD` invoke both T02 tests |
| PHP syntax | Source and test files PASS |
| Actual MariaDB / concurrent HTTP workers | NOT RUN |

The fake connection verifies manual mode, auto/default issuance, canonical GROUP ID, skipped collisions, requested series, default INACTIVE/ambiguous negatives, `FOR UPDATE` reads, and parent rollback responsibility. It cannot prove actual InnoDB locks, concurrent counter races or HTTP 409/422 mapping.

## Acceptance stop conditions before T04/G4

- **Do not** run Supplier migration `000028` / catalog provisioning: bank metadata schema, encryption/key custody and durable bank audit review remain pending.
- **Do not** claim Numbering is operational: there is no SupplierService/Create endpoint invoking the issuer yet.
- On an approved DEV clone, create a valid Supplier series only via explicitly authorized P1 Numbering management. Verify no-default/manual, ACTIVE default/auto, INACTIVE default/reject, ambiguous default/reject, requested alternate and wrong-scope negatives. Capture SQL counter and generated code evidence.
- Execute actual two-worker concurrent Supplier create on MariaDB with separate HTTP actors, confirm distinct codes, unique `(group_organization_id, supplier_code)`, and rollback of counter + Supplier + Company Usage together when a mutation fails.
- Confirm Numbering config concurrency uses the shared Resource row lock. Counter gap after a **committed** insert/usage failure is not allowed; a rollback must revert all three writes.

## Next task

SUP-T03 SQL-first PBAC COUNT/page/detail and direct-ID guard, followed by T04 transactional SupplierService that **actually uses** this Numbering participant. No frontend, Bank API or historic PO/FIN mock data is activated by T02.
