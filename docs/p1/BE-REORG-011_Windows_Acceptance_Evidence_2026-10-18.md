# BE-REORG-011 — Windows Backend Acceptance Evidence

**Date:** 2026-10-18  
**Module:** Business Workflow Actions & Execution Core  
**Delivery:** BE-REORG-011 CANDIDATE + BE-REORG-011_FIX01  
**Evidence:** Owner-supplied Windows PowerShell/PHPUnit/PHPStan log, plus prior owner-supplied BE010 acceptance log (2026-10-17). This is a review of the supplied logs, not an independent re-run on Windows.

## 1. Runtime and PHPUnit

- PHP 8.3.30, PHPUnit 10.5.65, CodeIgniter 4 backend, Windows/XAMPP.
- Verified test database: `127.0.0.1:3306/erp_test`, prefix `dinktech_`.
- FIX01 InnoDB focused: **2/2 PASS, 34 assertions**, including the real lock timeout evidence case.
- Full backend suite: **1012 tests, 9960 assertions, 0 failures, 0 errors**, PHPUnit exit **0**; elapsed **11:06.660**.
- Exactly **34/34 BE011** test cases executed, no skips/failures/errors. Existing gates: BE010 30; BE009 32; BE008 26; BE007 24; PBAC 91; Foundation 17 guard checks PASS. Inherited HTTP/DB-lock coverage gates also PASS.
- Scope is in-process HTTP/real DB locking, **not** end-to-end browser/Apache UAT.

## 2. PHPStan — exact diagnostic-identity diff versus BE010

The report sections of the owner-supplied BE010 and BE011 logs were parsed by file path, line, PHPStan identifier, and normalized message content. Each report contains **120 diagnostics**.

| Category | Count | Finding |
|---|---:|---|
| Byte-equivalent identities (file, line, ID, message) | **118** | Unchanged |
| Existing diagnostics relocated by refactor | **2** | Same PHPStan ID and message |
| New diagnostics after accounting for relocation | **0** | No regression |
| Existing diagnostics eliminated | **0** | No reduction |

**Relocations:**

1. **`arrayValues.list`** — from `Services/BusinessWorkflowActions.php:68` to `Modules/Workflow/BusinessActions/Application/BusinessWorkflowActionService.php:53`. Exact message retained: "Parameter #1 $array (list<string>) of array_values is already a list, call has no effect."
2. **`method.nonObject`** — from `Services/BusinessWorkflowActions.php:152` to `Modules/Workflow/BusinessActions/Infrastructure/Persistence/SqlBusinessWorkflowRecordReader.php:44`. Exact message retained: "Cannot call method getRowArray() on CodeIgniter\\Database\\ResultInterface|false."

Raw PHPStan exit code remains **1**, because its **120 inherited errors still exist**. Consequently the BE011 guarded runner also exits **1**. This is not a PHPUnit failure, and it is not a clean PHPStan result. **PHPStan no-new-diagnostics acceptance PASS.**

A legacy BE010 runner message still refers to "121 inherited"; this is stale descriptive text relative to the now-accepted BE010 baseline of 120. The actual output reports 120 and was compared by identity.

## 3. Gate disposition

| Gate | Disposition |
|---|---|
| BE011_FIX01 actual MySQL InnoDB tests | **PASS** |
| Full backend PHPUnit 1012/1012 | **PASS** |
| BE011 exact test inventory 34/34 | **PASS** |
| Inherited backward-compatibility gates | **PASS** |
| PHPStan 0-new-diagnostic parity | **PASS** |
| PHPStan clean / raw exit 0 | **NO** — 120 inherited diagnostics; exit 1 |
| Independent full source code review | **PENDING** |
| Git checkpoint on owner's Windows repository (real SHA) | **PENDING** |
| Browser/Apache UAT | **NOT TESTED** |

**Conclusion:** `BE-REORG-011 + FIX01 — BACKEND VERIFIED / PENDING CODE REVIEW & GIT CHECKPOINT`. Do not mark the overall release fully ACCEPTED until the outstanding gates are confirmed.

**Repository hygiene:** The owner's Git status before patching had modified and untracked files, including unrelated changes. Do not stage with `git add .` or use destructive cleanup; review and commit only explicitly selected relevant paths. No Windows Git commit has been performed by this review.
