# DinkERP Backend v2 — M3.2.1 Baseline Test Hotfix

**Scope:** Test-only hotfix for `m32_baseline_lanes_pure.php`. No changes to routes, API behavior, application authorization, frontend, database, `.env`, XAMPP/Apache settings, or uploads.

## Background

The previous test unconditionally required
`backend_basline/app/Modules/Authorization/Decision/Application/Ports/PbacDecisionPort.php`.
If the locally deployed baseline was incomplete, PHP failed with `Failed to open stream` before running any parity tests. This is an **absent test dependency**, not evidence of a PBAC decision mismatch.

## Fix

The parity test now resolves two source files *as one pair*:

1. If both installed `backend_basline` files exist and match the pinned SHA-256 values from **erp_v1.2.4.zip**, load the locally installed sources.
2. If one/both are missing, load the two **byte-for-byte v1.2.4 test snapshots** bundled under `backend/tests/fixtures/baseline_v1_2_4/`. The output explicitly says **snapshot, NOT locally installed backend_basline**.
3. If any present installed file differs from the pinned original, **fail with an explicit error** (no silent substitution).
4. If fixture files are absent, unreadable or modified, **fail** (never simulate a successful parity test).

Original SHA-256 checksums:

| Baseline file | SHA-256 |
|---|---|
| `PbacDecisionPort.php` | `47bb0e46f096f083bf13362976b1616650abfc2682a825c7aad2042aeeafb1c7` |
| `PbacDecisionEngine.php` | `178a6bc4cf22ef2d9a36c3c9eeb9957df3177b4061c27f3414af20f226fc03d5` |

These were checked against the source bytes in `erp_v1.2.4.zip`. The snapshots are **only loaded by this CLI test**; they are never registered as production services or HTTP endpoints.

## Install on Windows

Backup locally modified test files, then extract `DinkERP_Backend_v2_M3.2.1_TestHotfix_Overlay.zip` into `D:\XAMPP\htdocs\erp\` keeping the directory structure. **Do not copy/overwrite `backend_basline/`**.

PowerShell:

```powershell
cd D:\XAMPP\htdocs\erp
php backend\tests\m32_baseline_lanes_pure.php
php backend\tests\m32_runtime_pure.php

cd backend
php spark m32:verify 50 100
php spark routes
```

### Optional source controls

Use the local installed baseline only (fails explicitly if incomplete):

```powershell
php backend\tests\m32_baseline_lanes_pure.php --require-installed
```

Use the pinned test snapshot explicitly:

```powershell
php backend\tests\m32_baseline_lanes_pure.php --snapshot-only
```

Use a custom baseline root (must have both v1.2.4 files matching SHA-256):

```powershell
php backend\tests\m32_baseline_lanes_pure.php "--baseline-root=D:\path\to\erp\backend_basline"
```

## QA and limits

- The fixture policy-lane comparison covers **1,024 deterministic scenarios**, including matched/decisive IDs.
- The test does **not** compare live HTTP responses, organization filters, or database permissions with an independently running baseline. A `PASS` against a snapshot must never be described as a live-installed-baseline comparison.
- `m32:verify` (already `mismatch=0, errors=0` in the user's latest run) compares **M2.1.1 ↔ M3.2** on persisted data, *not* standalone baseline HTTP.
- `DINKERP_M32_READ_MODE` stays unchanged. Do not switch to `enforce` on this test alone; use `shadow` for further real-account differential testing.

## Implementation notes

The hotfix contains only the test, two test fixture source files and this documentation. No migration or database writes.
