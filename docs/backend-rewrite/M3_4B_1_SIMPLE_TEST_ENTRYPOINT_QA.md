# M3.4B.1: Small baseline-style PowerShell test entrypoint

This patch adds **only a daily test launcher, a source inventory gate, and one pure test**. It changes no runtime module, HTTP route, `.env`, migration, frontend, or baseline source.

Compared to `backend_basline/tools/test-unit.ps1` (111 lines), the new Backend v2 wrapper deliberately separates fast source/offline tests, live **read-only** verification, and opt-in mutation integration. It does NOT claim the original 91-class NO-DROP data attestation exists in Backend v2.

From `D:\XAMPP\htdocs\erp\backend` in PowerShell:

```powershell
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN UNIT-STANDARD
.\tools\test-unit.ps1 RUN DB-READ
.\tools\test-unit.ps1 RUN ALL
.\tools\test-unit.ps1 RUN INTEGRATION
```

Alternatively from the ERP root:

```powershell
.\backend\tools\test-unit.ps1 CHECK
.\backend\tools\test-unit.ps1 RUN
```

| Command | Performs | Database / mutation |
| --- | --- | --- |
| `CHECK` | Pinned OpenAPI inventory (29 operations), suite/source availability, audit command presence | None |
| `RUN` | All 38+ pure/fixture suites, uses existing `m34b_run.php --offline` | None (creates a JSON local report) |
| `RUN UNIT-STANDARD` | Same as RUN | None |
| `RUN DB-READ` | Seven CI4 `spark` audit/readiness commands on currently configured DB | **Read only**, but does connect to existing `.env` DB |
| `RUN ALL` | Offline suite plus read-only DB commands | Read only |
| `RUN INTEGRATION` | Existing M3.4B synthetic MariaDB sandbox + HTTP mutation integration | **Creates a new sandbox**; explicitly opt-in |

For routine work use `CHECK`, then `RUN`. Only run `DB-READ` after verifying the target of `backend/.env`; these commands examine real persisted metadata and actor decisions, never test rollback. `INTEGRATION` requires MySQL permissions and local schema template per M3.4B documentation. No production mutation is authorized. `DINKERP_M33B_WRITE_MODE=off` remains required on production.

Testing: `php backend/tools/test-unit-check.php`, `php backend/tests/m34b1_launcher_pure.php`, `php backend/tools/m34b_run.php --offline` and lint. The PowerShell 5.1 entrypoint must still be exercised on Windows XAMPP, since the packaging environment has no PowerShell interpreter.
