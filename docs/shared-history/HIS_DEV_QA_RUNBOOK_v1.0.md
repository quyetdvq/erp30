# HIS-01 — DEV QA & Migration Runbook

> Bắt buộc chỉ thực hiện migration trên DEV clone có backup. Không dùng `erp_test` làm production. Không tự động thực hiện HTTP UAT thay operator.

## Preflight PowerShell

```powershell
cd D:\XAMPP\htdocs\erp
$patch = '.\DinkERP_HIS01_Shared_System_History_Foundation.patch'
git status --short
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw 'STOP: source/patch mismatch' }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw 'STOP: patch failure' }
git diff --check
if ($LASTEXITCODE -ne 0) { throw 'STOP: whitespace error' }

cd .\backend
.\tools\test-unit.ps1 RUN HIS-UNIT
.\tools\test-unit.ps1 RUN CUS-UNIT
.\tools\test-unit.ps1 RUN UNIT-STANDARD
.\tools\test-unit.ps1 RUN ROUTES
.\tools\test-unit.ps1 RUN P1-STANDARDS
```

## Migration (DEV backup, write pause)

```powershell
cd D:\XAMPP\htdocs\erp\backend
php spark migrate:status
# DBA: ensure ONLY reviewed 000026 (and other independently approved migrations) pending.
# Backup DEV schema/data and pause Customer writes first.
php spark migrate
php spark migrate:status
```

Expected new migration: `2026-10-29-000026 CreateBusinessHistoryEvents`, table physical prefix e.g. `dinktech_business_history_events`. Running patched CustomerService **before** table exists causes History insert failure and rolls back Customer mutation by design; migration is a deployment prerequisite. Do not deploy piecemeal.

## MariaDB checks, execute on DEV only

```sql
SHOW CREATE TABLE dinktech_business_history_events;
SHOW INDEX FROM dinktech_business_history_events;
-- Fill ? through prepared queries or DB client parameters; never guess actor IDs.
EXPLAIN SELECT COUNT(*) FROM dinktech_business_history_events
  WHERE resource_type = 'customer' AND resource_id = 1
    AND owner_domain = 'MASTER' AND owner_organization_id IS NULL;
EXPLAIN SELECT id, event_type FROM dinktech_business_history_events
  WHERE resource_type = 'customer' AND resource_id = 1
    AND owner_domain = 'MASTER' AND owner_organization_id IS NULL
  ORDER BY id DESC LIMIT 25 OFFSET 0;
```

Replace `dinktech_` with **actual** DBPrefix, and `resource_id` with an independently authorized DEV Customer row. EXPLAIN must demonstrate indexed parent lookups. Do not run DROP/DELETE/truncate to 'test' retention.

## Operator/UAT matrix

| Action | Expected |
|---|---|
| Create new Customer with initial DRAFT | One CREATE; no raw PII in event |
| Edit name/address 15 times in DRAFT | Zero UPDATEs |
| Submit via generic `setstate` (if applicable) | STATE_CHANGE, **not falsely APPROVE** |
| Genuine workflow approve (future explicit action) | Proper typed APPROVE only when action exists |
| Transition into ACTIVE | STATE_CHANGE with redacted checkpoint |
| Change active name/phone and Company Usage in one PATCH | Exactly one UPDATE with redacted fields |
| Submit same values to active PATCH | No UPDATE event |
| Actor B without Customer VIEW, direct history URL | No-leak 404, no total/rows |
| Actor A with VIEW for this status | 200 scoped History + total |
| Inject DB error/disable History INSERT during a disposable DEV Customer write | Business mutation rolls back, no partial Customer update |
| Concurrent CREATE/modify | No duplicate CREATE; transaction consistent |
| Old Customer before HIS rollout | Empty History, never fabricated backfill |
| Browser UI vi/en, modal pagination | Expected accessible History tab; no raw PII |

DB failure-injection must use disposable fixtures and restore DB privileges/settings; never manipulate a production account's grants during office hours.

## UI/build

```powershell
cd D:\XAMPP\htdocs\erp\frontend
pnpm run type-check
pnpm run build
pnpm run lint
pnpm run check:imports
pnpm run openapi:lint
pnpm run openapi:generate:check
```

`HIS-01` code is a source candidate until DEV MariaDB, HTTP negative PBAC, frontend/build and canonical OpenAPI generation are verified. Existing Customer and other P1 tests passing are not equivalent to full History UAT.

## Rollback

- Before DEV data: `git apply -R --check` then `git apply -R` while patch still matches. **Stop Customer writes and review migration first**; code without History schema (or vice versa) is not a valid deployed combination.
- After History events exist, do **NOT** drop table. Keep data, plan forward-fix or controlled rollback under DBA review.
- Retain backup/approval records. DB immutability hardening, retention and bank-access audit are separate release blockers.
