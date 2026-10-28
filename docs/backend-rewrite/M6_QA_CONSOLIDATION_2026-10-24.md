# ERP v1.4.0 - M5/M6 QA consolidation and next dependency (2026-10-24)

**Evidence grades:** `SOURCE_PASS` means reviewed source gates and offline tests succeeded. `OWNER_REPORTED_DEV_PASS` is a past XAMPP result stated in historical milestone docs, not rerun by this patch. `OPEN` / `NOT_TESTED` require new, attributable DEV HTTP+SQL reports. `PRODUCTION_ACCEPTED` is **false** throughout. Do not promote historical source PASS into full HTTP/PBAC/SQL acceptance.

## Snapshot and immutable boundaries

- Frozen OpenAPI: 150 non-OPTIONS operations from `openapi-operations-v1.2.4.json`. Current explicit v1.4.0 routes: **129/150**, 21 missing, zero extras (static gate).
- Original Frontend and Backend baseline bytes are anchored by new per-file SHA-256 inventories generated from the **original** `erp_v1.2.4.zip` (not from the workspace being audited): Frontend 277/277, Backend baseline 806/806; OpenAPI 2/2 unchanged. Snapshot manifests are checksum-pinned in `backend/tools/locked_snapshot.py`.
- No added HTTP routes, no edited Frontend, original baseline, OpenAPI, migrations or media mutation business services in this QA consolidation. Physical `/uploads/YYYY/...` URL remains mapped internally to `backend/public/upload/YYYY/...`; `/writable` is never a public asset route.
- The source-only M3.4D acceptance intentionally reports `production_cutover=BLOCKED` and independent baseline parity `NOT_TESTED`.

## Audit / acceptance ledger

| Slice | Source evidence | DEV/SQL evidence | Required next gate |
|---|---|---|---|
| M3.4D / M4 | Source gates PASS; M4 84/150 | M4.2A owner-reported 14-route XAMPP and M4.2B two-writer cases in historical QA | Independent old-vs-v2 HTTP/DB parity for all relevant writes |
| M5.1 Locations | SOURCE_PASS, 90/150 | OPEN | Authorized/limited/denied actor, state/Lifecycle, rollback, en/vi, SQL assertions |
| M5.2 Organization | SOURCE_PASS, 93/150 | OPEN | Persisted organization scope/owner, hierarchy, record-PBAC, concurrent changes |
| M5.3 People | SOURCE_PASS, 99/150 | OPEN | Employee/assignment scoped authorization and transaction reversal |
| M5.4 Departments / Positions | SOURCE_PASS, 105/150 | OPEN | Hierarchy cycles, unique code conflicts, SetState and rollback |
| M5.5 Contacts | SOURCE_PASS, 110/150 | OPEN | Organization owner, hidden-row total, en/vi validation, authorized mutation + SQL rollback |
| M6.1 / M6.2A Product reads | SOURCE_PASS, 122/150 | OPEN for complete record-PBAC/Pagination | Hidden IDs absent from results, `total`, labels and relation lookups |
| M6.2B Product mutations | SOURCE_PASS, 125/150 | Owner reported CREATE/PATCH, negative COMBO, SetState and SQL; separate atomicity suite documented | Two-writer AUTO numbering, rollback/forced faults, independent parity |
| M6.3A Product media | SOURCE_PASS, 127/150 | DEV probes documented, not independently rerun here | File vs SQL CAS, denial, replacement, MIME/errors, Apache revalidation |
| M6.3B.1 self Avatar | SOURCE_PASS, 129/150 | Historical owner-reported upload/replace/delete and final SQL for User 3; **current EMPTY mismatch** | Confirm current metadata and stop until a new disposable fixture is selected |
| M6.3B.2 delegated Avatar | SOURCE_PASS, no new routes | **OPEN** (no actual admin/owner result in this archive) | Distinct admin + disposable owner, database-pin PASS, scoped PBAC, SQL + Apache + `en`/`vi` |
| M7-M8 | 21 frozen operations absent | NOT_TESTED | Implement in dependency order only after high-priority QA risk closure |
| M9 final acceptance | NOT_STARTED | NOT_TESTED | 150/150 baseline replay, E2E, PBAC, perf, release/rollback rehearsal |

## Preflight/QA execution order

1. From root `erp/`, run `python backend/tools/verify_baseline.py --v1-root .`, `python backend/tools/verify_layout.py`, `python backend/tools/verify_routes.py` (no SQL/HTTP).
2. From `erp/backend`, run `php tools/test-unit-check.php`, `php tools/m34b_run.php --offline`, `php tools/m34d_accept.php`, `php tools/m63b_accept.php`. Record counts, SHA and timestamps; M3.4D report is written to `writable/m34d/reports`.
3. Compare the current owner database **without writes** for User 3 and inspect historical reports. Do **not** reset existing metadata. Select a new active disposable owner + distinct admin only if appropriate and authorized.
4. Execute M6.3B.2 fresh fixture audit and `--preflight` following `M6_3B_2_ADMIN_DELEGATION_AVATAR_QA.md`. Capture report `m63b2_dev_*.json`. If metadata, PBAC, local Apache, GD or DB pin fails: stop; do not run `--apply`.
5. Schedule a separate, explicitly authorized `--apply` only on a genuine non-production database, with dual authenticated sessions and safe fixture. After completion independently check SQL NULL and Apache 404. Any partial write requires evidence review before a rerun.
6. Close the M5 prioritized PBAC matrix next: one representative resource each for Organization, Employee Assignment, Department hierarchy and Contact, with authorized/limited/denied actor, `en`/`vi` errors, transaction rollback and scope-safe list `total`. Broaden to all resources and old-vs-v2 parity before production acceptance.
7. After these risk gates, begin **M6.4A** (3 Product selector/label routes), then M7.1 UOM (2), M7.2 Combo (2), M7.3 Pricing (7), M7.4 dependent lookups (3), M8 Import (4), M9 acceptance (no new routes).

## Evidence bundle expected from owner XAMPP

Save sanitized outputs without credentials, cookies, CSRF, raw HTTP bodies or business avatars. Minimum: terminal transcript of the three Python source gates, offline regression counts, `php spark m63b1:db-audit <SAFE_DEV_ID> EMPTY`, `m63b2` preflight report, and (only if approved) apply report plus independently verified SQL before/after and Apache image statuses. For each M5 scenario, capture request verb/path, actor type, expected/actual status and `error.code`, Content-Language, scoped SQL fact, `BEGIN/ROLLBACK` outcome, baseline comparison and an evidence timestamp.

**Not yet demonstrable from this ZIP:** live XAMPP database name, content of `writable/m63b1|m63b2` reports from the owner's PC, HTTP status from `api.monmin.net`, and any successful delegated `--apply`. No claims of completing those are made.
