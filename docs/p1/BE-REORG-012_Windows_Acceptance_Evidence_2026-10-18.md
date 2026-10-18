# BE-REORG-012 — Windows Backend Acceptance Evidence

Date: 2026-10-18 (Windows log time 2026-10-18T12:24:17+00:00)

## Scope and source

- Workstream: BE-REORG-012 — Workflow Write Boundary & Initial State Core.
- Applied changes: BE012 CANDIDATE + BE012 FIX01 (factory/HTTP tests) + BE012 FIX02 (PHPDoc annotations).
- User-provided Windows transcript: `Văn bản đã dán (1).txt` (SHA-256 `8c7468a23655b7bf28d3d004dd65e2c6efa7bdcc19f6c4ea15f70359f5abd385`).
- Target: PHP 8.3.30, PHPUnit 10.5.65, `127.0.0.1:3306/erp_test`, table prefix `dinktech_`. DB can be refreshed by DatabaseTestTrait.
- Coverage scope: backend PHPUnit, in-process HTTP, real database locking; *not* Apache/browser UAT.

## Windows acceptance results

| Gate | Evidence | Assessment |
|---|---|---|
| BE012 guarded runner preflight | Foundation guard 17 checks PASS | PASS |
| Full PHPUnit | `OK (1048 tests, 10261 assertions)`; exit code 0 | PASS |
| BE012 exact inventory | 36/36 BE012 tests; none skipped/errored/failed | PASS |
| Inherited gates | BE011 34, BE010 30, BE009 32, BE008 26, BE007 24, PBAC 91 retained | PASS |
| HTTP and real DB lock coverage | Foundation and module coverage gates explicitly reported no skips | PASS |
| PHPStan raw process | `[ERROR] Found 115 errors`; exit code 1 | NOT CLEAN (legacy diagnostics) |
| PHPStan parity against BE011 | 115/115 current diagnostics match baseline exactly; 5 baseline diagnostics removed; 0 added | PASS |

### PHPStan identity comparison

Comparison algorithm: normalized `file path + line number + diagnostic identifier + diagnostic message` between BE011 full Windows log (120 diagnostics) and this BE012 full Windows log (115 diagnostics). Results:

- 115 diagnostics identical in both versions, including file, line, code, and message.
- 5 diagnostics present in BE011 but absent after BE012_FIX02:
  - `Services/BusinessWorkflowWriteBoundary.php:17` — `missingType.generics` (`create::$db`).
  - `Services/BusinessWorkflowWriteBoundary.php:17` — `missingType.iterableValue` (`create` return).
  - `Services/BusinessWorkflowWriteBoundary.php:27` — `missingType.iterableValue` (`assertNoDirectStateMutation::$changes`).
  - `Services/BusinessWorkflowWriteBoundary.php:27` — `missingType.generics` (`assertNoDirectStateMutation::$db`).
  - `Services/BusinessWorkflowWriteBoundary.php:47` — `missingType.generics` (`isLifecycleBound::$db`).
- 0 new diagnostics.

A raw exit code of 1 from PHPStan propagates through the nested guarded runners and is expected under the unchanged inherited warnings. It does **not** indicate a PHPUnit failure; the full PHPUnit process exited 0.

### Notes and limits

- The prior BE012_FIX01 run reported 10,263 assertions, while this BE012_FIX02 run reports 10,261; both ran 1,048 tests with no failures. This two-assertion variation is documented, not attributed to a specific cause without a further focused investigation.
- The user has not yet supplied a BE012 Windows Git checkpoint SHA.
- Independent second-party code review was not evidenced by these test logs.
- Secure-runtime CSRF / browser Apache UAT is outside the scope reported by the runner and should not be represented as completed.

## Decision

`BE-REORG-012 + FIX01 + FIX02 — BACKEND VERIFIED / PHPSTAN PARITY PASS`.

Not yet `RELEASE ACCEPTED`: independent code review and explicit Windows Git checkpoint remain pending. No further PHPDoc patch is indicated by the uploaded evidence.

## Recommended continuation

Perform independent code review of BE012 candidate + FIX01 + FIX02 against Specification and Implementation Plan, then create a selective Windows Git commit on a named BE012 checkpoint branch. Avoid `git add .` because unrelated modified/untracked project files have existed on the host.
