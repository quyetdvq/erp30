# P1-FE-050 Auto Numbering configuration UI Assessment

## Scope and readiness

`P1-FE-050 — Auto Numbering configuration UI` was executed as a bounded
frontend task after `AUTHORITY_CACHE: VALID`, `P1-API-040 DONE`,
`CHANGE-USER-IDENTITY-MUTATION-001` closure commits `5c1dcbb` and `6623936`,
and `P1-FE-050 NOT_STARTED` were confirmed. `P1-FE-060` was not started.

The implementation uses the generated API-040 client and the exact
`resource.configure_numbering` Base Permission key for navigation and action
UX. Backend 403/404/409/422 responses remain authoritative. No PBAC inference,
System Admin/group-name inference, frontend number generation, migration, or
API/OpenAPI change was added.

## Implemented screens and behavior

- `/configuration/auto-numbering` is listed in the System sidebar mode and is
  hidden when the canonical Base Permission is absent.
- Table First layout is `Header -> Resource query control -> top pagination ->
  table -> bottom pagination`; `Status` is immediately before final,
  right-aligned `Actions`.
- Resource selector loads the authorized Resource catalog. Series list supports
  view, edit, create, activate/deactivate, and canonical detail GET.
- Create/edit uses the shared modal structure. Scope, reset policy, generation
  mode, default flag, status, BIGINT-as-string start number, and increment are
  canonical form values.
- Pattern builder inserts `SEQ`, `YYYY`, `YY`, `MM`, `DD`, `COMPANY_CODE`,
  `ORG_CODE`, and `RESOURCE`; `SEQ` width is configurable.
- Validation preview calls only `POST .../actions/validate-config`, renders
  normalized pattern and an illustrative sample, and states that it does not
  reserve or consume a counter. Browser coverage asserts zero counter requests.
- A canonical `NUM_ISSUED_FIELD_IMMUTABLE` response conservatively locks
  `series_code`, `scope_type`, and `reset_policy` for the open edit form. No
  hard-delete action is exposed.
- vi/en strings cover route, table, controls, modal, enums, validation, errors,
  and accessibility labels.

## Validation

| Check | Result |
|---|---|
| `php .../task-context.php P1-FE-050` | PASS; `AUTHORITY_CACHE: VALID` |
| Focused frontend unit tests | PASS; 3 files, 8 tests |
| `pnpm run type-check` | PASS |
| Touched-file ESLint | PASS |
| Touched-file Oxlint | PASS |
| Repository `pnpm run lint` | NOT PASS; pre-existing `SearchableSelect.spec.ts:10:11` unused `wrapper` |
| `pnpm run format:check` | PASS |
| `pnpm run build` | PASS |
| `pnpm run openapi:lint` | PASS |
| `pnpm run openapi:generate:check` | PASS; generated artifacts remain tool-owned |
| API-040 regression PHPUnit | PASS; 29 tests, 184 assertions |
| `pnpm run test:e2e -- fe050-auto-numbering.spec.ts --project=chromium` | PASS; 3 tests, axe included |
| Same Playwright file Firefox + WebKit | PASS; 6 tests |
| `validate-context-cache.php` | PASS; 12 authorities, 107 tasks, acyclic |
| `validate-ledger.php` | PASS before closure |
| `architecture-guard.php` | PASS |
| scoped `git diff --check` | PASS |

## Contract impact

- DB: none; no migration or schema change.
- Authorization: Base Permission UX only; backend remains final authority.
- API/OpenAPI: all six API-040 operations reused; no public contract drift.
- UI/i18n: one System-tab route, vi/en parity, modal/table/accessibility coverage.

Completion commit is recorded by the task ledger/Git closure. `P1-FE-060`
started: NO.
