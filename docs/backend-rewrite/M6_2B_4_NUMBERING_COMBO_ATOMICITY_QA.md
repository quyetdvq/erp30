# M6.2B.4 — Product AUTO Numbering + two-writer / COMBO rollback DEV QA

## Why this exists

M6.2B.2 on the real XAMPP environment has already established Product CREATE HTTP **201**, PATCH **200**, duplicate-SKU rejection, rejected COMBO, and persisted SQL fixture. M6.2B.3 separately established a **DRAFT → ACTIVE** mutation through HTTP **200**, followed by a SQL read confirming `ACTIVE`. Neither test, by itself, proves that *two independently authenticated writers* receive distinct **auto-issued Product codes** or that a failing COMBO *rolls back a changed numbering counter*.

M6.2B.4 introduces **DEV QA only**, no API routes, no schema migrations, no changes to Product mutations, PBAC, locale catalogs or baseline. Source route inventory stays **125/150**.

## Strict prerequisites and no-write policy

- Run **only** on XAMPP DEV (`backend`), with an existing ACTIVE `product` **default** Numbering Series using `generation_mode=AUTO_REQUIRED|AUTO_IF_EMPTY` and `scope_type=GROUP`. The SQL probe fails closed if the default is absent/ambiguous, inactive, non-AUTO, non-GROUP, incompatible or has no single ACTIVE GROUP root.
- This QA tool **never creates or changes** a Numbering Series or a database configuration. Do not add a default series just to get a green result on an operational DEV database without first reviewing impacts on ordinary Product creation.
- Use an ACTIVE Product Group and UOM. The known DEV examples from M6.2B.2 are `group-id=3`, `uom-id=1`, but use actual ACTIVE IDs.
- Default CLI mode: **PLAN ONLY** (no HTTP and no SQL). `--preflight` invokes read-only SQL and HTTP authentication/GET, but **no Product mutation**. `--apply` needs `--confirm=DEV_PRODUCT_ATOMICITY`.
- `--apply` launches two separate PHP processes, each with an **independent Shield login** against local `https://127.0.0.1` and fixed `Host: api.monmin.net`. A synchronization file releases both HTTP POST requests in close succession; it does not establish low-level InnoDB overlap or prove a lock wait occurred.
- The two successful Product fixtures have unique SKUs `DEV_M62B4_XXXXXXXXXXXX_A` and `_B`. **They remain in DEV**; there is no Product DELETE API and the tool does not delete or update any existing Product. The failed COMBO attempts SKU `_ROLLBACK`, which must **not exist**.
- The runner's SQL snapshots use `php spark m62b4:snapshot`, which performs **SELECT only**, and always query the currently configured local DEV database. Ensure no other Product writers are active during the COMBO counter comparison; background issuance could change the counter and invalidate the comparison.
- The runner writes only sanitized QA reports under `backend/writable/m62b4/reports`; passwords, session cookies, CSRF tokens, SQL statements and raw HTTP bodies are never logged.

## Install after M6.2B.3

```powershell
cd D:\XAMPP\htdocs\erp
git apply --check .\M6.2B.4-dev-numbering-combo-atomicity-qa.patch
git apply .\M6.2B.4-dev-numbering-combo-atomicity-qa.patch
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

Source-only expected: **96/96 offline**, M3.4D **6/6**, inventory **125/150**. These are not HTTP/MariaDB runtime verdicts.

## 1. Dry plan (no HTTP/SQL)

```powershell
php tools\m62b4_dev_atomicity.php --group-id=3 --uom-id=1
```

## 2. Authenticated SQL+HTTP preflight (no Product writes)

```powershell
$env:M62B_DEV_USER = 'quyetdv'
$secure = Read-Host 'DEV password' -AsSecureString
$env:M62B_DEV_PASSWORD = [System.Net.NetworkCredential]::new('', $secure).Password
try {
    php tools\m62b4_dev_atomicity.php `
      --group-id=3 --uom-id=1 --preflight
} finally {
    Remove-Item Env:\M62B_DEV_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:\M62B_DEV_USER -ErrorAction SilentlyContinue
}
```

Expected when a compatible Product AUTO default already exists:

```text
M6.2B.4 DEV ATOMICITY: PASS_DEV_PRODUCT_ATOMICITY_PREFLIGHT; phase=complete
M6.2B.4 checks: SQL_READY=1 HTTP_PREFLIGHT=1 TWO_WRITERS=0 UNIQUE_AUTO=0 COMBO_REJECTED=0 COUNTER_ROLLBACK=0
```

The zero values after `HTTP_PREFLIGHT` are **NOT TESTED** in this mode. If the output says `FAIL_DEV_PRODUCT_ATOMICITY` or `readiness=AUTO_DEFAULT_MISSING_OR_AMBIGUOUS`, stop. This is a **config readiness** result, not a Numbering implementation failure. No Product was created.

## 3. Explicit two-writer and counter rollback test

Only after preflight PASS, and after verifying the DEV database target and absence of unrelated writers:

```powershell
$env:M62B_DEV_USER = 'quyetdv'
$secure = Read-Host 'DEV password' -AsSecureString
$env:M62B_DEV_PASSWORD = [System.Net.NetworkCredential]::new('', $secure).Password
try {
    php tools\m62b4_dev_atomicity.php `
      --group-id=3 --uom-id=1 `
      --apply --confirm=DEV_PRODUCT_ATOMICITY
} finally {
    Remove-Item Env:\M62B_DEV_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:\M62B_DEV_USER -ErrorAction SilentlyContinue
}
```

The runner checks in order:

1. Before snapshot: one compatible default AUTO series and zero Product rows with the new M62B4 nonce.
2. Two separately logged-in PHP workers simultaneously released for `POST /api/v1/products`, both with **no** `product_code`; HTTP **201** for both, `GET` **200** and matching distinct SKU/ID/code.
3. SQL snapshot: exactly two persistent fixture Products, unique generated codes and a consistent counter advance in the same series, organization scope and reset period.
4. COMBO `POST` using **automatic code** and nonexistent component `999999999999999999`. Expected HTTP **404** with `PRODUCT_NOT_FOUND`, after Product insert has been attempted inside its transaction.
5. Second SQL snapshot: counter ID and last value **unchanged** since just before the rejected COMBO; `_ROLLBACK` Product row absent; successful Product fixtures unchanged.

Expected **only after live DEV execution**:

```text
M6.2B.4 DEV ATOMICITY: PASS_DEV_PRODUCT_NUMBERING_COMBO_ATOMICITY; phase=complete
M6.2B.4 checks: SQL_READY=1 HTTP_PREFLIGHT=1 TWO_WRITERS=1 UNIQUE_AUTO=1 COMBO_REJECTED=1 COUNTER_ROLLBACK=1
M6.2B.4 SQL a: id=<NEW_ID>
M6.2B.4 SQL b: id=<NEW_ID>
M6.2B.4 SQL rollback: id=NONE
```

Keep the marker and two IDs from output/report. **Do not immediately rerun `--apply` if a phase fails.** It may have persisted one or both Product rows. The runner attempts a bounded SELECT-only recovery snapshot; inspect the report and rows first.

## Evidence limits

- A successful race probe shows two independent HTTP requesters produced distinct Product identifiers and that the DB counter advanced. The synchronization barrier does **not** prove simultaneous InnoDB lock waits, linearizability across distributed deployments, or stability under high contention.
- The rollback probe verifies **Product row absence plus an unchanged Numbering counter** for one invalid-component COMBO. It does not cover all SQL failure branches, every combo item, full successful COMBO assembly, price-list history, or production datasets.
- This intentionally does **not** modify or reset Numbering counters to known values and does not claim independent baseline parity, full transaction-history locks or production readiness.
- An external concurrent writer or a reset-period boundary during the test can make the SQL audit fail or become indeterminate. Treat this as inconclusive, not a permission to retry blindly.
