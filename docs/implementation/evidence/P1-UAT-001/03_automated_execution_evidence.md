# P1-UAT-001 - Automated Execution Evidence

## Run identity

- Date: 2026-10-09
- HEAD: `3889e7c8aa8307e3154536057957211fd337ba5e`
- Worktree before package: one unrelated pre-existing modification under `docs/thamkhao (Khong Duoc su dung)/Tracuuu-Tonghop.md`; preserved.
- Database safety: backend PHPUnit uses the repository test profile (`erp_test`); no UAT/staging mutation was attempted.
- Live UAT API probe: `08_live_api_uat_evidence.md`; 20 checks executed, all expected HTTP/status assertions passed.

## Authority read and closure verification

The package was traced against UR v1.0.15 business scope, FRS v1.12 section 26
and change-control synchronization, DBD v1.15 invariants, Implementation
Contract v1.10 runtime/security/import/display rules, Implementation Plan v1.10
section 17 Gate 9 dependencies, API Design v1.8, UI Design v1.9, approved
change controls 08/09/10/11, and the current `P1_DECISIONS.md` decision source.

SYS-005 has an explicit approved greenfield-only scope replacement in
`P1-SYS-005/03_greenfield_scope_change_approval.md`; the historical legacy path
is not claimed as verified. SYS-007 cannot be treated as independently closed:
`P1-SYS-007/03_approved_parameter_recheck.md` records a later DONE/recheck
statement, but `P1-SYS-007/02_production_readiness_decision_closure.md` still
contains blocked/open CSRF, deployment, SYSTEM_ADMIN review, and release
decision rows. This unresolved evidence conflict is carried as a release
blocker rather than resolved by the ledger label.

## Results

| Gate | Exact command | Result |
|---|---|---|
| Authority cache | `php .codex/skills/dinkerp-implementation/scripts/validate-context-cache.php` | PASS; 12 hashes, 107 tasks, 47 rules, 15 decisions |
| Ledger | `php .codex/skills/dinkerp-implementation/scripts/validate-ledger.php` | PASS; 107 tasks, 105 DONE |
| Architecture | `php .codex/skills/dinkerp-implementation/scripts/architecture-guard.php` | PASS; 47 application files, 0 waivers |
| Diff hygiene | `git diff --check` | PASS before package edits |
| Backend tests | `php vendor/bin/phpunit --no-coverage` from `backend` | PASS; 445 tests, 3,921 assertions |
| Backend static analysis | `php vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress` from `backend` | PASS; level 8, no errors |
| Frontend type check | `pnpm run type-check` | PASS |
| Frontend Oxlint | `pnpm run lint:oxlint` | PASS |
| Frontend ESLint | `pnpm run lint:eslint` | PASS |
| Frontend format | `pnpm run format:check` | PASS |
| Frontend unit | `pnpm run test:unit -- --run` | PASS; 16 files, 44 tests |
| OpenAPI lint | `pnpm run openapi:lint` | PASS |
| OpenAPI generated drift | `pnpm run openapi:generate:check` | PASS; 17 generated files verified |
| Production build | `pnpm run build` | PASS; existing Naive UI chunk-size warning only |
| Full browser support profile | `CI=1 pnpm run test:e2e` | NOT RUN to completion in this recheck; Chromium is the release-critical profile |
| Targeted browser support profile before fixture repair | `CI=1 pnpm exec playwright test --project=chromium --retries=0` | FAIL; 32 passed, 10 failed, 42 total; all failures stopped before mutation because CSRF bootstrap was not mocked |
| Targeted browser support profile after fixture repair | `CI=1 pnpm exec playwright test --project=chromium --retries=0` | PASS; 42 passed, 0 failed, 42 total |

## Targeted browser failures and repair

These were deterministic repository tests using mocked API routes, not live UAT
evidence. Each mutation test lacked the CSRF bootstrap route required by the
shared Axios interceptor, so the request never reached its mocked business
endpoint. The test fixtures were repaired to model the existing CSRF contract;
no application authorization or business behavior was bypassed.

| Defect key | Test | Failure |
|---|---|---|
| AUTO-UAT-001..010 | `dsp006`, `fe010`, `fe050`, `fe060`, `fe070`, `fe080`, `vue` | Mutation/login request was blocked by missing CSRF mock before the endpoint route; fixture repair verified by 42/42 pass |

The existing `playwright-report` and `test-results` artifacts are generated
outputs. They are not business sign-off and are not copied into this evidence
package because they may contain browser/runtime data.

## Interpretation boundary

The passing backend/static/unit results support implementation confidence only.
They do not replace a live authorized journey for permissions, scoped records,
forms, tables, mutation feedback, reports, import rollback, or language
switching. No screenshots, traces, or tester actions are claimed for live UAT.

## Local HTTP recheck

On 2026-10-09 the local backend was temporarily run with an ignored `.env`
override (`http://127.0.0.1:8080`, secure redirects disabled, localhost CORS)
and the frontend was served over HTTP with `VITE_API_BASE_URL` pointing to that
backend. `GET /api/v1/health` returned 200 and the supplied authorized UAT
identity completed login to `/`. The local `.env` was restored to its original
HTTPS values and no local runtime configuration was committed. This is local
transport evidence only and does not replace UAT host review.

After the live UAT environment update, the repository browser support command
was rerun unchanged:

`CI=1 pnpm exec playwright test --project=chromium --retries=0`

Result after the fixture repair: **42 passed, 0 failed, 42 total**. The live
browser recheck is separate and is recorded in `09_live_browser_uat_recheck.md`.

