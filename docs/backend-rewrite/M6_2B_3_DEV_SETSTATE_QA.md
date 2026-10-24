# M6.2B.3 — Product SETSTATE QA on the existing DEV fixture

Status: **source/offline QA only** until the user runs this on XAMPP. No new API routes; **125/150** unchanged.

## Existing evidence

- M6.2B.2 HTTP CREATE 201, PATCH 200, duplicate SKU and negative COMBO checks passed on DEV.
- `php spark m62b2:db-audit M62B2_1B6B52993C72 1466` returned `PASS_DEV_PRODUCT_SQL_ROWS`.
- That command proves Product row persistence, **not** Product SETSTATE, Numbering counter atomicity or two-writer issuance.

## Scope and safety

The new runner reuses Product ID **1466** labelled `M62B2_1B6B52993C72`. It **does not** create or delete Products. It will refuse to send SETSTATE unless the authenticated GET returns the **same** ID, SKU, Product name, description, STANDARD type, null parent ID, canonical status and a `setstate: true` permission hint. The backend must independently authorize the write and validate the Lifecycle transition; GET hints are never authorization.

Only the **DEV** machine/virtual host `api.monmin.net` is supported by the existing HTTP transport. `--preflight` sends auth and Product GET requests only; it performs **no Product mutation** (Shield login may change session metadata). The mutating operation requires `--apply`, an exact fixture ID/marker, uppercase target state and `--confirm=DEV_PRODUCT_SETSTATE`. A resulting Product state change is deliberately retained. No SQL cleanup is permitted.

**Do not guess the target state.** The read-only command resolves a configured, unambiguous active Lifecycle transition using `ResourceStateRepository::transitionTarget('product', row, 'setstate')`. If `next_setstate=UNAVAILABLE`, do not run `--apply`; instead, inspect the configured Lifecycle/Workflow through the existing ERP UI. This QA does not create or edit Lifecycle rows or PBAC policies.

### 1. Install after M6.2B.2.1

```powershell
cd D:\XAMPP\htdocs\erp
git apply --check .\M6.2B.3-dev-setstate-qa.patch
git apply .\M6.2B.3-dev-setstate-qa.patch
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

### 2. Inspect the existing DEV fixture (only SELECT)

```powershell
php spark m62b3:plan M62B2_1B6B52993C72 1466
```

Expected: `PASS_DEV_PRODUCT_FIXTURE`; observe `current` and `next_setstate`. If `next_setstate=UNAVAILABLE`, **stop**.

### 3. Test actor preflight through HTTP (no Product mutation)

```powershell
$env:M62B_DEV_USER = 'quyetdv'
$secure = Read-Host 'DEV password' -AsSecureString
$env:M62B_DEV_PASSWORD = [System.Net.NetworkCredential]::new('', $secure).Password

php tools\m62b3_dev_setstate.php `
  --marker=M62B2_1B6B52993C72 `
  --product-id=1466 `
  --preflight
```

Expected `PASS_DEV_PRODUCT_STATE_PREFLIGHT`. If it fails or the fixture was edited since M6.2B.2, stop without trying write.

### 4. Apply **only** if the read-only plan returned a concrete next state

```powershell
# Replace <NEXT_STATE> with exactly the uppercase next_setstate reported in Step 2.
php tools\m62b3_dev_setstate.php `
  --marker=M62B2_1B6B52993C72 `
  --product-id=1466 `
  --target-state=<NEXT_STATE> `
  --apply `
  --confirm=DEV_PRODUCT_SETSTATE

Remove-Item Env:\M62B_DEV_PASSWORD -ErrorAction SilentlyContinue
Remove-Item Env:\M62B_DEV_USER -ErrorAction SilentlyContinue
```

Expected `PASS_DEV_PRODUCT_SETSTATE_HTTP` and `POST=200 GET_AFTER=200`, then:

```powershell
php spark m62b3:plan M62B2_1B6B52993C72 1466
```

The final command reports the **current** persisted state from MariaDB. If the HTTP probe fails **after** posting, never retry blindly: its report might indicate a state change that was committed before a subsequent GET failure. Audit the fixture with the read-only command first.

## Acceptance boundaries

- Running `CHECK`/`RUN` proves only static and pure/fixture checks. It does not query DEV SQL or call HTTP.
- Plan PASS proves fixture identity and the Lifecycle transition as currently configured, but not actor PBAC.
- HTTP preflight PASS proves actor GET/PBAC *hint* only, not permission to perform the mutation.
- HTTP SETSTATE PASS plus post-write SQL plan confirms the state transition for this **one labelled DEV record**, not every Policy/Lifecycle path.
- Product Numbering counter **rollback**, two-writer issuance and **baseline mutation parity** remain **NOT_TESTED** and require separate evidence; do not mark them PASS.
- No production write acceptance is claimed.
