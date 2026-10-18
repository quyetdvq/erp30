# BE-REORG-009 Windows Acceptance Evidence (2026-10-17)

## Basis

- Source release: `erp_v1.1.7_BE-REORG-009_Workflow_Lifecycle_Core_CANDIDATE_2026-10-17.patch` plus `erp_v1.1.7_BE-REORG-009_FIX01_province_fixture_code_2026-10-17.patch`.
- Runtime: Windows/XAMPP, PHP 8.3.30, PHPUnit 10.5.65; disposable `127.0.0.1:3306/erp_test`, prefix `dinktech_`.
- BE008 reference: earlier user-submitted 916-test acceptance log, 121 PHPStan diagnostics.
- BE009 evidence: latest user-submitted full runner log.

## Results

| Gate | Evidence | Status |
|---|---|---|
| BE009 FIX01 focused checks | 2 tests, 37 assertions | PASS |
| Full PHPUnit | 948 tests, 9,223 assertions, no failures/errors | PASS |
| BE009 exact coverage | 32 tests, none skipped/errored/failed | PASS |
| BE008 inherited coverage | 26 cases | PASS |
| BE007 inherited coverage | 24 cases | PASS |
| PBAC authority | 91 cases | PASS |
| Foundation guard | 17 checks | PASS |
| Inherited BE001-BE006 coverage gates | Preserved in runner output | PASS |
| PHPStan raw execution | Exit code 1, 121 diagnostics | Expected inherited nonzero exit |
| PHPStan parity | Compared complete PHPStan report blocks; 473 lines identical, diff 0 | PASS |
| New PHPStan diagnostics | 0 relative to user-submitted BE008 log | PASS |

PHPStan report SHA-256 (UTF-8 report body between heading and final error count):
`a4e7c8abee5e6813bbd3f74510c738c111f13df6cc03cb4b9fdec59f6b0920de`.

The BE009 runner returns code 1 because the inherited PHPStan analysis retains the original 121 diagnostics; the full PHPUnit exit code is 0. Never report raw PHPStan as clean/exit 0.

## Acceptance decision

Automated backend runtime and PHPStan diagnostic-identity gates are met. The supplied logs do not establish browser/Apache UAT, actual Windows Git commit creation, or the independent code review required by the approved specification. Therefore mark **BACKEND GATES VERIFIED; FINAL RELEASE REVIEW PENDING**, not unconditional full acceptance. After independent review, record the final decision and create a clean Windows Git checkpoint.
