# P2-CUS — Required Profile & Unique Customer Phone (29/10/2026)

**Status:** implementation candidate; offline PHP QA PASS, MariaDB migration and browser UAT **NOT RUN**. Based on `erp_v2.0.0_customer_lifecycle_fixed.zip`. Incremental patch; no P2-SUP code changes.

## Business rule

For **INDIVIDUAL** and **ORGANIZATION** Customers:

- On creation, `name`, `phone`, `address` (Main address), `province_id`, and `ward_id` are mandatory. Province/Ward must match a valid pair. Customer code remains optional when Numbering is configured.
- On update, the persisted row merged with PATCH must still have a complete profile; a legacy incomplete Customer must be completed before further edits.
- Phone must be unique across **all Customers** (Group Master) regardless of Company usage or actor VIEW access. Primary Contact phone is NOT a Customer phone uniqueness domain; other contacts may reuse the number.
- Phone equivalence: remove whitespace, spaces, parentheses, dashes and dots. Ten-digit domestic Vietnamese `0xxxxxxxxx` is considered equivalent to `+84xxxxxxxxx`, `0084xxxxxxxxx`, and `84xxxxxxxxx` where x is exactly nine digits. Other international numbers retain the leading `+`. Only 7–15 digits (optional leading +) are accepted after separators are removed. Customer-visible original phone text is preserved; normalized `phone_key` is server-internal.
- Duplicate conflicts return **HTTP 409 `CUSTOMER_PHONE_DUPLICATE`**, with details `field=phone` and no other Customer's ID or name. Missing mandatory field => **HTTP 422 `CUSTOMER_FIELD_REQUIRED`**; invalid phone => **HTTP 422 `CUSTOMER_PHONE_INVALID`**.
- Backend remains the authority; frontend validation offers immediate UX but cannot bypass the global database UNIQUE index.

## Implementation

- `CustomerPhone::key()` shared by Customer mutation and migration.
- `CustomerRules::requiredProfile()` required fields and phone validation.
- `CustomerService::create()` / `update()` use an indexed global duplicate check within the existing serialized transaction, then store canonical phone key. A physical InnoDB unique index resolves concurrent writers and writes outside the Customer service lock. Duplicate-key errors are mapped to domain 409.
- Internal `phone_key` is removed from single-row API responses; list projection does not include it.
- Migration `2026-10-29-000025_AddCustomerPhoneUniqueness.php` adds nullable `customers.phone_key` and unique index `uq_customers_phone_key`. A preflight scans legacy rows and **stops before DDL** on invalid or duplicate nonblank legacy phone values, reporting only affected Customer IDs. Existing missing/blank phone remains NULL to avoid modifying historical Customer data; these rows must be corrected manually on update. No automated data deletion/merge.
- Vue Customer editor marks required fields visibly, shows field feedback, validates geographical selections not covered by native HTML required controls, and maps 409 duplicate message in vi/en.
- OpenAPI `p2-cus.design.openapi.yaml` requires the four new fields for create and declares uniqueness. Nullable legacy Customer read response is preserved.

## DEV database rollout (operator owned)

1. Backup DEV clone and confirm the exact database (`erp`) is **not production**; pause all Customer write traffic during the migration. `git apply` alone **does not** update MariaDB schema.
2. `cd D:\XAMPP\htdocs\erp\backend` then `php spark migrate:status`. Review other pending migrations before continuing.
3. When ready, `php spark migrate`. If migration reports `CUS_PHONE_LEGACY_DUPLICATE_IDS_<id>_<id>` or `CUS_PHONE_LEGACY_INVALID_ID_<id>`, **stop** and resolve the source records under an approved data-cleanup procedure; do not modify or delete them automatically. Re-run migration after correction.
4. Confirm `customers.phone_key` and `uq_customers_phone_key` exist (`SHOW INDEX FROM customers`). With custom table prefixes, inspect the prefixed table instead.
5. Deploy updated backend and frontend **after** migration succeeds. Test on browser with one valid Customer (create); create another with the same phone in different formatting => 409; update another Customer to same phone => 409; edit own Customer without changing phone => allowed if complete; missing Phone/Address/Province/Ward => cannot save; Ward must belong to Province.
6. Verify partial PATCH of a complete Customer, Product/P1 Routes, Contacts and initial Lifecycle state as regression checks.

## Rollback

- `git apply -R` only reverts code. Migration `down()` intentionally refuses to drop the uniqueness constraint, because data written after rollout may rely on it. Manual DB rollback requires a backup, planned downtime, data audit and DBA signoff. Do **not** run `migrate:rollback` to attempt automatic schema removal for this change.

## Offline QA evidence

| Check | Result |
|---|---|
| PHP syntax new/changed classes + migration | PASS |
| Customer core pure | 94/94 PASS |
| Customer source contract | 94/94 PASS |
| Customer SQL/PBAC plan | 48/48 PASS |
| Customer Workflow/Assignees | 51/51 PASS |
| Full `php tools/m34b_run.php --offline` | 168/168 PASS |
| Node TS stripped pure profile/phone equivalence | PASS |
| OpenAPI YAML parse / required properties | PASS |
| Vue type-check, Vitest & build | NOT RUN — frontend dependencies unavailable in this isolated environment; run on XAMPP |
| MariaDB migration / concurrent writes / HTTP / frontend UAT | NOT RUN — operator-owned DEV |

No route additions, PBAC relaxations, workflow changes or P2-SUP changes.
