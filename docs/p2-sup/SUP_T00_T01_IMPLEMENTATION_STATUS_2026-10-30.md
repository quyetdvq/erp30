# P2-SUP — T00/T01 Implementation Status (2026-10-30)

**Product decisions:** SUP-DEC-01..12 approved; no expansion of G1 scope. **Engineering:** PARTIAL — not ready for runtime deployment or acceptance.

## Source baseline

`erp_v2.0.0_fe_product_detail_02_header_footer_fixed.zip`; existing application migrations up to `2026-10-29-000027_AddHistoryMonitorIndexes.php`.

## Implemented (source only)

- `000028_CreateSupplierCoreTables.php`: exactly two new SUP tables. Includes 25 supplier columns (including nullable encrypted bank metadata; never plaintext bank fields), group-root/code compound uniqueness, foreign keys to Organization/Province/Ward, company usage composite PK and reverse index. **Does not create account data, grant access or seed policies.**
- `SupplierRules.php`: strictly validates selected Supplier profile fields, code format, group/term catalog and All/list Company Usage shape; accepts duplicate phone values. This is a pure starting contract, **not a full backend validation layer**. Actual Organization type/status/root, Province-Ward relationship, backend permission, merge semantics and Numbering are NOT YET IMPLEMENTED.
- `tests/sup/supplier_rules_pure.php`: focused unit tests; do not equate with MariaDB integration.

## STOP conditions / open engineering work

1. **Bank security**: ciphertext column sizes and nonce/tag format are candidates, not approved cryptographic envelope. Durable security access audit, key custody, algorithm and key rotation must be reviewed before enabling Bank. The schema candidate itself requires DBA review before migration.
2. **T01 remaining**: explicit idempotent resource/action catalog and Workflow/Lifecycle provisioning, required MASTER scope, schema DDL review and DEV preflight. No implicit security grants.
3. **T02 onward**: Numbering with special Supplier default-series precedence; SQL-first PBAC COUNT/ROWS/detail and direct-ID checks; canonical Service transaction/CAS; Shared Contact owner SUPPLIER and owner parent guard; HIS Recorder; API OpenAPI and Vue UI; translations; unit and browser UAT.
4. **Performance and safety**: actual MariaDB EXPLAIN, two-actor negative PBAC, concurrency, DBPrefix, migration on a backed-up DEV clone, no release claim.

## Validation performed

- `php -l` migration: PASS.
- `php -l` SupplierRules: PASS.
- `php backend/tests/sup/supplier_rules_pure.php`: 14/14 PASS.
- Database migration: **NOT RUN**; P1 routes unchanged because none added.

## Operator notice

**Do not run `php spark migrate` merely because this source patch exists.** A pending migration may be picked up automatically and bank cryptographic metadata and DDL still require DBA/Security review. Apply to source review branch first; run on DEV only after design gate signoff and database backup.

## Next planned code task

SUP-T01B resource/action and Lifecycle bootstrap without grants; SUP-T02 Numbering; SUP-T03 SQL-first PBAC; SUP-T04 create/update transactions; later T06 Contacts, bank secured path, API/UI and History. Supplier transaction history stays deferred until PUR/FIN.

## Addendum — SUP-T01B (2026-10-30)

- Implemented explicit `supplier:catalog-provision CONFIRM_SUPPLIER_CATALOG_ONLY` command and transactional Resource/Action/Lifecycle catalog provisioner. **No automatic invocation, PBAC grants, policy or bank API.**
- `supplier`/`supplier_contact` are Lifecycle-backed MASTER Resources; verified existing Supplier custom Lifecycle is preserved; T01B.1 uses Customer Contact Lifecycle for supplier_contact without coupling to other Contact resources.
- Reviewed exactly one extension of the M5.4.1 module ownership list: `Supplier`, while all 16 historical owners remain pinned.
- Regression: Supplier Rules 14/14; Catalog Policy 36/36; simulated provision 14/14; M5.4.1 owner 44/44; M3.4B offline 168/168; M19.24 Routes 476/476. **Actual MariaDB remains NOT_RUN.**
- Runbook: `docs/p2-sup/SUP_T01B_CATALOG_LIFECYCLE_QA_2026-10-30.md`.
- T00 bank schema/audit/security blocker is unchanged. Do **not** run migration `000028` or supplier:catalog-provision on DEV until actual DDL and key/audit plan approved.

## Addendum — SUP-T01B.1 CUS prevention review (2026-10-30)

- Found and corrected unbounded/unknown master profile fields, missing partial PATCH semantics, and a falsely strict three-owner Contact Lifecycle binding. No migration/API/permission changes.
- Supplier Contact now follows the Customer Contact Lifecycle only; Organization/Employee Contact are not queried or mutated by the Supplier catalog command.
- Guarded SUP source regression: Rules 48/48, Catalog 38/38, Fake Provision 16/16; pinned offline 168/168 and route architecture 476/476. Still **no MariaDB or HTTP acceptance**.
- Every T02..T08 patch must implement the targeted cases in `SUP_T01B1_CUS_LESSONS_PREVENTION_GATE_2026-10-30.md`, including the previously fixed Customer bugs. Bank DDL/crypto/audit blocker remains OPEN.

## Addendum — SUP-T02 Numbering participant (2026-10-30)

- Implemented dedicated Supplier Numbering precedence, **not** a copy of Customer `AUTO_IF_EMPTY` fallback. Every configured default (including INACTIVE) blocks manual codes; malformed/ambiguous defaults fail closed.
- `SupplierNumberIssuer` uses P1 Numbering tables, GROUP root, locking reads and an external transaction callback; it does not generate default series or grant any permissions. SupplierService integration is deferred to T04.
- Offline tests: `supplier_numbering_pure.php`, `supplier_numbering_issuer_fixture.php`; see `SUP_T02_NUMBERING_SOURCE_QA_2026-10-30.md` for acceptance criteria.
- No DB migration executed, no real counter concurrency test yet. Bank DDL/Security gate remains OPEN.
