# P1-UAT-001 - Live Browser UAT Recheck

## Run identity

- Date: 2026-10-09
- Environment: current UAT `https://erp.monmin.net`
- Identity: `quyetdv` (user-supplied credential; password not recorded)
- Browser: Codex in-app Chromium tab, authenticated session
- Scope: read-only business journeys, validation-only negative path, locale/session checks
- Data safety: no create/update/delete/import submitted; existing data was only viewed or filtered

## Transport and authentication

| Check | Observed result | Status boundary |
|---|---|---|
| SPA load | `/products` and `/` loaded over HTTPS | PASS observation |
| Login | Supplied `quyetdv` credentials reached authenticated workspace | PASS observation |
| Workspace identity | Header showed `quyetdv`; Overview showed one permission group and active session | PASS observation |
| Logout | User menu `Sign out` returned to sign-in screen | PASS observation |
| Re-login | Same identity returned to Overview with active session | PASS observation |
| TLS/cache recheck | Browser reached authenticated SPA after the earlier `ERR_CERT_AUTHORITY_INVALID` and cache bootstrap failures | PASS observation; deployment logs were not independently inspected |

## Business observations

| Area | Observed outcome | Evidence |
|---|---|---|
| ORG-01/ORG-03 | Organization | Tree showed 6 visible nodes: GROUP, COMPANY, two BRANCH and two WAREHOUSE; selecting `KH-TL` displayed WAREHOUSE detail and parent branch | Live DOM snapshot and interactive screenshot |
| PRD-01/PRD-02/PRD-04 | Products | Product table loaded 1,452 rows with status/type/group/UOM/price/action columns; SIMPLE detail showed canonical fields | Live DOM snapshot |
| CMB-01 | COMBO | Filtering code `345` returned one COMBO; detail showed Components tab and one SIMPLE component with quantity `10.000000` and UOM `CHIEC` | Live DOM snapshot |
| PRD-02 | Product Group | Product Group table loaded 17 rows and displayed root hierarchy/actions | Live DOM snapshot |
| PRD-03 | UOM | UOM table loaded 13 rows | Prior live UAT observation plus current workspace navigation |
| CMB-03/CMB-04 | Price List | Price Policies loaded one active GROUP-scoped list; detail showed Product/UOM item and exact decimal price `150000.00` | Live DOM snapshot |
| IMP-01/IMP-02/IMP-03 | Product Import | Form exposed CSV/XLSX, `CREATE_ONLY`, `UPDATE_ONLY`, `UPSERT`, source-key/generated-code guidance; clicking Start import without file showed `Choose an import file.` | Live DOM snapshot; validation-only negative path |
| IMP-02/IMP-03 | Import result | Existing job detail showed `Completed`, 8 total, 8 success, 0 errors, with persisted row results `DEMO-P001`..`DEMO-P008` | Live DOM snapshot; pre-existing job, not attributed to this run |
| AUTH-01/AUTH-03 | Shield User | User list showed 2 active identities; `quyetdv` detail showed Shield ownership, one `SYSTEM_ADMIN` group, and direct permission catalog | Live DOM snapshot |
| ADM-01 | Authorization Group | SYSTEM_ADMIN detail exposed canonical Resource Actions; policy note states backend PBAC/ABSOLUTE_DENY still applies | Live DOM snapshot |
| AUTH-04/ADM-02 | PBAC | Policy list showed `ABSOLUTE_DENY` and `ALLOW` effects at organization/workflow scopes | Live DOM snapshot |
| NUM-01 | Auto Numbering | Product series `PRD`, GROUP scope, yearly reset, `AUTO_IF_EMPTY`, pattern `PRD-{YYYY}-{SEQ:4}` displayed | Live DOM snapshot |
| ADM-03 | Workflow/Lifecycle | Workflow State catalog loaded 10 active states; Lifecycles loaded `ADVANCE` and `BASIC` | Live DOM snapshot |
| AUTH-07/DSP-03 | Localization | Switching `vi` -> `English` translated shell, menus, forms, tables and detail labels; switching back restored Vietnamese; codes/enums/business values stayed unchanged | Live DOM snapshots |

## Boundary

These are genuine live browser observations, but they are not final business
PASS results. No second business role, reviewer, Product Owner sign-off,
isolated fixture mutation, generated-code import, failed-row rollback, or
approved screenshot/trace artifact was supplied. The matrix therefore remains
`BLOCKED` pending acceptance review and the remaining end-to-end cases.
