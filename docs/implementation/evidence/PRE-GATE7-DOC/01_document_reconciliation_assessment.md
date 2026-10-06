# Pre-Gate-7 Documentation Reconciliation

## Scope and outcome

This evidence records a documentation-only reconciliation against the seven
canonical P1 documents in authority order:

| Document | Revision inspected | Result |
|---|---:|---|
| User Requirements | v1.0.13 | upstream unchanged; no semantic conflict found |
| Functional Requirements Specification | v1.8 | upstream unchanged; no semantic conflict found |
| Database Design | v1.11 | canonical `import_job_rows` shape confirmed |
| Implementation Contract | v1.6 | transport, authorization, import, and pending boundaries confirmed |
| Implementation Plan | v1.5 | reconciled and versioned |
| API Design | v1.3 | reconciled, versioned, and given Gate-7 matrix |
| UI Design | v1.4 | reconciled, versioned, and aligned to API/persistence |

No code, schema, migration, public runtime API, or P1 progress state was
changed. The implementation state remains 69/100, Gate 6 CLOSED, Gate 7
0/10, and Gate 8 0/12.

## Reconciliation issues

| Issue | Authority source | Conflicting text / observed state | Resolution | Affected documents | Remaining blocker / pending status |
|---|---|---|---|---|---|
| CHILD display separator was described as unresolved in the Plan | Implementation Contract v1.6 open-items boundary; accepted Product role evidence `P1-PRD-004`; UI design decision | Plan v1.4 Gate 8 rules said the exact separator was still open, while the UI decision and current `ProductRoleService` resolve display as Parent Name + `" - "` + Child Name | Plan v1.5 records `" - "` as resolved and display-only; no persistence or Product identity change | Plan, UI | None; remains a UI presentation rule |
| API contract status vocabulary was not normalized | API Design governance; updated implementation skill `API.CONTRACT_STATUS` | Province routes used `READY`; prose used `Service READY`; readiness was not summarized per API task | API v1.3 uses only `PLANNED`, `CONTRACT_PENDING`, `CONTRACT_READY`, `IMPLEMENTED`, `VERIFIED`, `BLOCKED`; `READY`/`PENDING`/`DESIGN_READY` are explicitly invalid API statuses | API | Runtime API tasks remain NOT_STARTED; no implementation claim |
| Gate-7 readiness was not explicit for API-001..060 | Implementation Plan Gate 7 backlog; task context for P1-API-001..060 | API v1.2 had route sections but no bounded task readiness matrix | API v1.3 adds a matrix for P1-API-001, 002, 003, 010, 020, 030, 040, 050, and 060 with dependencies, status, and excluded pending sub-capabilities | API, Plan | P1-API-090 remains the separate regression/closure task; Gate 7 remains 0/10 |
| Pending API capabilities were not grouped as a Gate-7 boundary | Contract v1.6 sections 7, 11, 18; API v1.2 sections 9, 12, 18 | Pending behavior was distributed across endpoint sections and did not clearly exclude implementation | API v1.3 explicitly excludes Shield User writes, exact export media type, public Price Resolution, and COMBO preview APIs; no new Resource/Action or payload is invented | API, UI, Plan | Those four capabilities remain `CONTRACT_PENDING`; `employees.gender` enum remains source-open and pending |
| Import row DTO could drift from persistence | DBD v1.11 §17.2; Contract v1.6 §§11.4-11.9; accepted `P1-IMP-008` evidence | API v1.2 did not define a canonical row DTO and used broad job filters; UI v1.3 rendered Record Type/Source Key and filtered by `record_type` | API v1.3 defines exactly `row_number`, `row_status`, `entity_key`, `target_ref_id`, `error_code`, `error_message`, `created_at`; row filters are `row_status` and `q` over `entity_key`, with common pagination/sort. UI v1.4 removes persisted Record Type/Source Key and the `record_type` filter while preserving original row number, entity key/generated Product Code, result, errors, and timestamp | API, UI | `source_key` and `record_type` remain input/template/dependency concepts only; no raw payload persistence |
| UI/API route dependency wording was stale for Product Import and pending screens | API v1.3 §13; UI v1.4 §§13-14, 22-23; Plan Gate 8 table | UI dependency row combined route families and did not distinguish pending Price Resolution readiness | UI v1.4 maps Product Import to POST `/products/import-jobs`, GET `/import-jobs*`, rows, and export; Price Resolution is explicitly `DESIGN_PENDING` until its API is `CONTRACT_READY` | API, UI | No UI screen may bypass its API dependency; pending screen remains blocked |
| Version/source pointers were stale after downstream revisions | Authority manifest and compiled task/rule provenance | Compiled cache pointed at Plan v1.4/API v1.2/UI v1.3 | Added Plan v1.5/API v1.3/UI v1.4, updated authority hashes, validator Plan path, and compiled task/rule source pointers | Plan, API, UI, compiled context | Upstream UR/FRS/DBD/Contract were not modified |

## Cross-document audit

The following checks found no unresolved upstream semantic conflict:

- API routes and UI dependency rows use the same `/api/v1` route families and
  canonical Resource/Action names from Contract v1.6 §7. `authorization_group`
  remains distinct from Organization type `GROUP`.
- Authoritative Evaluation Organization remains server-derived: persisted
  target/owner for existing targets, canonical parent/owner/scope for creates,
  `GROUP` for shared master/import operations, and assignment organization for
  Employee Assignment mutations. Client context cannot override it.
- BIGINT values remain JSON strings and DECIMAL/money/quantity/conversion
  values remain decimal strings. The common 403/404 distinction is unchanged:
  visible target without action is 403; invisible/nonexistent target is 404.
- Pagination/filter/sort follows the common allowlisted query contract. Import
  row filtering is limited to persisted `row_status` and indexed `entity_key`
  search.
- `vi`/`en`, fallback `vi`, `Accept-Language`, `Content-Language`, stable
  machine error codes, loading/error/accessibility behavior, and the approved
  five-mode workspace shell remain unchanged.
- Product Group remains a nullable `parent_id` adjacency list with arbitrary
  depth, global code uniqueness, cycle rejection, and no helper hierarchy
  table. Product roles, direct Product/UOM rules, nested acyclic COMBO, Pricing
  fallback, and decimal allocation boundaries remain unchanged.
- Gate 7/8 task IDs and dependency edges are unchanged. The Plan only clarifies
  that API-090 closes Gate 7 and each FE feature task depends on its matching
  API task.

## Remaining status

- CONTRACT_PENDING: Shield User writes; exact export media type; public Price
  Resolution API; COMBO preview APIs; and the existing `employees.gender` enum
  source-open item.
- UI DESIGN_PENDING: Shield User create/edit form; Price Resolution Tester;
  COMBO Allocation Preview; and any screen that would require a pending API.
- API runtime implementation: P1-API-001..P1-API-090 NOT_STARTED.
- Progress: 69/100. Gate 6 CLOSED. Gate 7 0/10. Gate 8 0/12.

## Decision and scope result

Upstream decision required: **NO**. UR, FRS, DBD, and Implementation Contract
remain unchanged because the observed conflicts were downstream documentation
drift, not upstream semantic conflicts. The accepted current implementation
evidence is used only to reconcile documentation and does not promote any API
or UI task to runtime `IMPLEMENTED` or `VERIFIED`.

## Validation

| Command / check | Result |
|---|---|
| `php .codex/skills/dinkerp-implementation/scripts/task-context.php --batch P1-API-001 P1-API-002 P1-API-003 P1-API-010 P1-API-020 P1-API-030 P1-API-040 P1-API-050 P1-API-060 P1-API-090` | PASS; `AUTHORITY_CACHE: VALID` |
| `php .codex/skills/dinkerp-implementation/scripts/validate-context-cache.php` | PASS; 7 authorities, 100 tasks, acyclic dependency graph, approved decisions/rules resolved |
| `php .codex/skills/dinkerp-implementation/scripts/validate-ledger.php` | PASS; 100 tasks, 69 DONE |
| Module-map coverage | PASS through the context-cache/module-map guard; no standalone module-map validator exists in the repository |
| `git diff --cached --check` | PASS |
| Staged path review | PASS; only downstream architecture revisions, compiled context pointers, validation pointer, and requested evidence are staged |
| `architecture-guard.php` | NOT PASS; two pre-existing backend service-layer findings, outside this documentation-only scope and left unchanged |

No PHPUnit, PHPStan, frontend, migration, schema, or runtime API command was
run because this change contains no production code or runtime contract
implementation.
